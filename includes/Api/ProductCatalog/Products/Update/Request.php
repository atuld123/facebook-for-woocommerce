<?php
/**
 *
 */

namespace WooCommerce\Facebook\Api\ProductCatalog\Products\Update;

use WooCommerce\Facebook\Api\Request as ApiRequest;

defined( 'ABSPATH' ) or exit;

/**
 * Request object for Product Catalog > Product Groups > Products > Update Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-group/products/
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_id Facebook Product ID.
	 * @param array  $data Facebook Product Data.
	 */
	public function __construct( string $product_id, array $data ) {
		parent::__construct( "/{$product_id}", 'POST' );
		parent::set_data( $data );
	}
}
