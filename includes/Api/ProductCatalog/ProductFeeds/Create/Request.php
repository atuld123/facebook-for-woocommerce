<?php
/**
 *
 */

namespace WooCommerce\Facebook\Api\ProductCatalog\ProductFeeds\Create;

use WooCommerce\Facebook\Api\Request as ApiRequest;

defined( 'ABSPATH' ) or exit;

/**
 * Request object for Product Catalog > Product Feeds > Create Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/product_feeds/v13.0#Creating
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @param array  $data Facebook Product Feed Data.
	 */
	public function __construct( string $product_catalog_id, array $data ) {
		parent::__construct( "/{$product_catalog_id}/product_feeds", 'POST' );
		parent::set_data( $data );
	}
}
