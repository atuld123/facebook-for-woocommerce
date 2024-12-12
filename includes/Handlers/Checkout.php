<?php
// phpcs:ignoreFile
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

defined( 'ABSPATH' ) or exit;

/**
 * The WebHook handler.
 *
 * @since 2.3.0
 */
class Checkout {

	/** @var string auth page ID */
	const WEBHOOK_PAGE_ID = 'wc-facebook-checkout';

	/**
	 * Constructs a new WebHook.
	 *
	 * @param \WC_Facebookcommerce $plugin Plugin instance.
	 *
	 * @since 2.3.0
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {
		add_action( 'rest_api_init', array( $this, 'init_checkout_endpoint' ) );
	}


	/**
	 * Register Checkout REST API endpoint
	 *
	 * @since 2.3.0
	 */
	public function init_checkout_endpoint() {
		register_rest_route(
            'wc-facebook/v1', // Namespace for your custom API
            '/checkout', // The endpoint URL (e.g., /wp-json/myplugin/v1/experience)
            array(
                'methods' => array( 'GET', 'POST' ), // You can also use POST, PUT, etc.
                'callback' => array( $this, 'redirect_to_checkout' ), // Callback function
                'permission_callback' => '__return_true', // You can add permission checks here
            )
        );
	}

	// The callback function to handle the redirect logic
    public function redirect_to_checkout() {
        // Perform the redirect
		$this->add_multiple_items_and_apply_coupon();
        wp_redirect( wc_get_cart_url() );
        exit; // Ensure no further output after the redirect
    }

    public function add_multiple_items_and_apply_coupon() {
        $product_ids = isset($_REQUEST['products']) ? array_map('trim', explode(',', urldecode(wp_unslash($_REQUEST['products'])))) : array();
        $quantities = isset($_REQUEST['quantity']) ? array_map('trim', explode(',', urldecode(wp_unslash($_REQUEST['quantity'])))) : array();
        $coupon_code = isset($_REQUEST['coupon']) ? wp_unslash($_REQUEST['coupon']) : '';
        $clear_cart = isset($_REQUEST['clear']) ? wp_unslash($_REQUEST['clear']) : false;

        // Clear the existing WooCommerce cart
        if($clear_cart !== false)
        {
            WC()->cart->empty_cart();
        }


        $was_added_to_cart = false;
        $url = false; // Ensure $url is defined

        // Ensure we have the same number of quantities as product IDs
        $quantities = array_pad($quantities, count($product_ids), 1);

        foreach ($product_ids as $index => $product_param) {
            $product_data = explode( ':', $product_param );

            if ( count( $product_data ) != 2 ) {
                continue;
            }
            $product_sku = sanitize_text_field( $product_data[0] );
            $quantity = intval( $product_data[1] );
            if ( $quantity <= 0 ) {
                continue;
            }
            // Get the product ID by SKU
            // Extract only the last numeric part after the last underscore
            $product_id = $this->get_product_id($product_sku);
            $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($product_id));
            $adding_to_cart = wc_get_product($product_id);

            if (!$adding_to_cart) {
                continue;
            }

            $add_to_cart_handler = apply_filters('woocommerce_add_to_cart_handler', $adding_to_cart->get_type(), $adding_to_cart);
            $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
                                wc_load_cart();
                                $cart = WC()->cart;

            if ($passed_validation && false !== $cart->add_to_cart($product_id, $quantity)) {
                wc_add_to_cart_message(array($product_id => $quantity), true);
                $was_added_to_cart = true;
            } else {
                $was_added_to_cart = false;
            }
        }

        if ($coupon_code && !$cart->has_discount($coupon_code)) {
            $cart->add_discount(trim($coupon_code));
        }

        if ($was_added_to_cart && 0 === wc_notice_count('error')) {
            $url = apply_filters('woocommerce_add_to_cart_redirect', $url, $adding_to_cart);

            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }

    private function get_product_id($product_sku) {
        $product_sku = (string) $product_sku;

        // Find the position of the last underscore
        $underscore_pos = strrpos($product_sku, '_');

        if ($underscore_pos !== false) {
            // Extract the part after the last underscore
            return intval(substr($product_sku, $underscore_pos + 1));
        } else {
            // If no underscore, convert the whole string to an integer
            return intval($product_sku);
        }
    }

}
