<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Update Cart Item
 *
 * Changes the quantity of (or removes) one line in the calling visitor's own
 * session cart. Same safety class as wc_add_to_cart — see that tool's
 * docblock and Webmcp_Bridge::ANONYMOUS_SAFE_TOOLS.
 *
 * @package Agentic\Tools
 */
class Wc_Update_Cart_Item extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_update_cart_item';
	}

	public function get_description(): string {
		return "Change the quantity of an item in the current visitor's own cart, or remove it entirely by setting quantity to 0.";
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'cart_item_key' => array(
					'type'        => 'string',
					'description' => 'The cart item to change, from wc_view_cart or wc_add_to_cart.',
				),
				'quantity'      => array(
					'type'        => 'integer',
					'description' => 'New quantity. 0 removes the item from the cart.',
				),
			),
			'required'   => array( 'cart_item_key', 'quantity' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$cart_item_key = sanitize_text_field( (string) ( $arguments['cart_item_key'] ?? '' ) );
		$quantity      = (int) ( $arguments['quantity'] ?? -1 );

		if ( '' === $cart_item_key || $quantity < 0 ) {
			return array( 'error' => 'cart_item_key and a non-negative quantity are required.' );
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return array( 'error' => 'Cart is not available.' );
		}

		// get_cart_item() reads WC_Cart's in-memory cart_contents directly and
		// does NOT hydrate it from the session itself — only get_cart() does
		// that (see its did_action('woocommerce_load_cart_from_session')
		// check). Calling get_cart_item() first, without this, always misses
		// a genuinely-existing item on a fresh request. Confirmed live: this
		// exact bug made every update/remove call fail against a real cart.
		if ( ! WC()->cart->get_cart() || ! WC()->cart->get_cart_item( $cart_item_key ) ) {
			return array( 'error' => 'That item is not in the cart — it may have already been removed.' );
		}

		if ( 0 === $quantity ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		} else {
			$updated = WC()->cart->set_quantity( $cart_item_key, $quantity );
			if ( ! $updated ) {
				return array( 'error' => 'Could not update that quantity — it may exceed available stock.' );
			}
		}

		return array_merge(
			array( 'updated' => true ),
			Wc_View_Cart::cart_summary()
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Wc_Update_Cart_Item();
