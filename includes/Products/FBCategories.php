<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace SkyVerge\WooCommerce\Facebook\Products;

defined( 'ABSPATH' ) || exit;

/**
 * The main product feed handler.
 *
 * This will eventually replace \WC_Facebook_Product_Feed as we refactor and move its functionality here.
 *
 * @since 1.11.0
 */
class FBCategories {

	const ATTRIBUTES_FILE       = '/data/google_category_to_attribute_mapping.json';
	const ATTRIBUTES_FIELD_FILE = '/data/google_category_to_attribute_mapping_fields.json';

	/**
	 * @var array of attributes data
	 */
	protected $attributes_data;

	/**
	 * @var array of attributes fields data
	 */
	protected $attributes_fields_data;

	/**
	 * This function ensures that everything is loaded before the we start using the data.
	 *
	 * @param bool $with_attributes Do we need attributes data or just categories.
	 */
	private function ensure_data_is_loaded( $with_attributes = false ) {
		// This makes the GoogleProductTaxonomy available.
		require_once __DIR__ . '/GoogleProductTaxonomy.php';
		if ( $with_attributes && ! $this->attributes_data ) {
			$file_contents         = @file_get_contents( facebook_for_woocommerce()->get_plugin_path() . self::ATTRIBUTES_FILE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->attributes_data = json_decode( $file_contents, true );

			$file_contents                = @file_get_contents( facebook_for_woocommerce()->get_plugin_path() . self::ATTRIBUTES_FIELD_FILE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->attributes_fields_data = json_decode( $file_contents, true );
		}
	}

	/**
	 * Fetches the attribute from a category using attribute key.
	 *
	 * @param string $category_id   Id of the category for which attribute we want to fetch.
	 * @param string $attribute_key The key of the attribute.
	 *
	 * @return null|array Attribute.
	 */
	private function get_attribute( $category_id, $attribute_key ) {
		$category = $this->get_category_with_attrs( $category_id );
		if ( ! is_array( $category ) ) {
			return null;
		}

		$attributes = array_filter(
			$category['attributes'],
			function ( $attr ) use ( $attribute_key ) {
				return ( $attribute_key === $attr['key'] );
			}
		);
		if ( empty( $attributes ) ) {
			return null;
		}
		return array_shift( $attributes );
	}

	/**
	 * Checks if $value is correct for a given category attribute.
	 *
	 * @param string $category_id   Id of the category for which attribute we want to check the value.
	 * @param string $attribute_key The key of the attribute.
	 * @param string $value         Value of the attribute.
	 *
	 * @return boolean Is this a valid value for the attribute.
	 */
	public function is_valid_value_for_attribute( $category_id, $attribute_key, $value ) {
		$attribute = $this->get_attribute( $category_id, $attribute_key );

		if ( is_null( $attribute ) ) {
			return false;
		}

		// TODO: can perform more validations here.
		switch ( $attribute['type'] ) {
			case 'enum':
				return in_array( $value, $attribute['enum_values'] );
			case 'boolean':
				return in_array( $value, array( 'yes', 'no' ) );
			default:
				return true;
		}
	}

	/**
	 * Fetches given category.
	 *
	 * @param string $category_id Id of the category we want to fetch.
	 *
	 * @return null|array Null if category was not found or the category array.
	 */
	public function get_category( $category_id ) {
		$this->ensure_data_is_loaded();
		if ( $this->is_category( $category_id ) ) {
			return GoogleProductTaxonomy::TAXONOMY[ $category_id ];
		} else {
			return null;
		}
	}

	/**
	 * Checks if category is root category - it has no parents.
	 *
	 * @param string $category_id   Id of the category for which attribute we want to check the value.
	 *
	 * @return null|boolean Null if category was not found or boolean that determines if this is a root category or not.
	 */
	public function is_root_category( $category_id ) {
		if ( ! $this->is_category( $category_id ) ) {
			return null;
		}

		$category = $this->get_category( $category_id );
		return empty( $category['parent'] );
	}

	/**
	 * Get attributes for the category.
	 *
	 * @param string $category_id   Id of the category for which we want to fetch attributes.
	 *
	 * @return null|boolean|array Null if category was not found or boolean that determines if this is a root category or not.
	 */
	public function get_category_with_attrs( $category_id ) {
		$this->ensure_data_is_loaded( true );
		if ( ! $this->is_category( $category_id ) ) {
			return null;
		}

		if ( isset( $this->attributes_data[ $category_id ] ) ) {
			$category = $this->attributes_data[ $category_id ];
			if ( isset( $category['attributes'] ) ) {
				foreach ( $category['attributes'] as &$attribute ) {
					// replace attribute hash with field array
					$attribute = $this->get_attribute_field_by_hash( $attribute );
				}
			}

			return $category;
		}

		facebook_for_woocommerce()->log( sprintf( 'Google Product Category to Facebook attributes mapping for category with id: %s not found', $category_id ) );
		// Category has no attributes entry - it should be add but for now check parent category.
		if ( $this->is_root_category( $category_id ) ) {
			return null;
		}

		$parent_category_id = GoogleProductTaxonomy::TAXONOMY[ $category_id ]['parent'];

		if ( isset( $this->attributes_data[ $parent_category_id ] ) ) {
			// TODO clean up
			$category = $this->attributes_data[ $parent_category_id ];
			if ( isset( $category['attributes'] ) ) {
				foreach ( $category['attributes'] as &$attribute ) {
					// replace attribute hash with field array
					$attribute = $this->get_attribute_field_by_hash( $attribute );
				}
			}

			return $category;
		}

		/*
		* We could check further as we have 3 levels of product categories.
		* This would meant that we have a big problem with mapping - let this fail and log the problem.
		*/
		facebook_for_woocommerce()->log( sprintf( 'Google Product Category to Facebook attributes mapping for parent category with id: %s not found', $parent_category_id ) );

		return null;
	}

	/**
	 * Checks if given category id is valid.
	 *
	 * @param string $category_id   Id of the category which we check.
	 *
	 * @return boolean Is the id a valid category id.
	 */
	public function is_category( $category_id ) {
		$this->ensure_data_is_loaded();
		return isset( GoogleProductTaxonomy::TAXONOMY[ $category_id ] );
	}

	/**
	 * Get all categories.
	 *
	 * @return array All categories data.
	 */
	public function get_categories() {
		$this->ensure_data_is_loaded();
		return GoogleProductTaxonomy::TAXONOMY;
	}

	/**
	 * Get category attribute field by it's hash.
	 *
	 * @param string $hash
	 *
	 * @return array|null
	 */
	protected function get_attribute_field_by_hash( $hash ) {
		if ( isset( $this->attributes_fields_data[ $hash ] ) ) {
			return $this->attributes_fields_data[ $hash ];
		} else {
			return null;
		}
	}

}
