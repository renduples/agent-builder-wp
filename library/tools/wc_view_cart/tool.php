<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce View Cart
 *
 * Reads the calling visitor's own session cart — never any other visitor's.
 * WooCommerce carts are keyed to the current PHP/WC session (a cookie set on
 * this same browser), so this is exactly as safe to expose anonymously as
 * the storefront's own cart page already is: it discloses nothing the
 * visitor doesn't already see by clicking "View cart".
 *
 * @package Agentic\Tools
 */
class Wc_View_Cart extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_view_cart';
	}

	public function get_description(): string {
		return "Get the current visitor's own shopping cart — items, quantities, and totals. Never discloses another visitor's cart.";
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		return self::cart_summary();
	}

	/**
	 * Build the current session's cart summary. Shared with wc_add_to_cart
	 * and wc_update_cart_item, which both return the post-mutation cart in
	 * this exact shape rather than duplicating the formatting.
	 *
	 * @return array
	 */
	public static function cart_summary(): array {
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( ! WC()->cart ) {
			return array( 'error' => 'Cart is not available.' );
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product ) {
				continue;
			}

			$items[] = array(
				'cart_item_key' => $cart_item_key,
				'product_id'    => (int) $item['product_id'],
				'variation_id'  => (int) ( $item['variation_id'] ?? 0 ),
				'name'          => $product->get_name(),
				'quantity'      => (int) $item['quantity'],
				'price'         => $product->get_price(),
				'line_subtotal' => $item['line_subtotal'],
				'line_total'    => $item['line_total'],
				'permalink'     => $product->get_permalink(),
			);
		}

		return array(
			'items'         => $items,
			'item_count'    => WC()->cart->get_cart_contents_count(),
			'subtotal'      => WC()->cart->get_subtotal(),
			'total'         => WC()->cart->get_total( 'edit' ),
			'currency'      => get_woocommerce_currency(),
			'checkout_url'  => wc_get_checkout_url(),
			'cart_url'      => wc_get_cart_url(),
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Wc_View_Cart();
