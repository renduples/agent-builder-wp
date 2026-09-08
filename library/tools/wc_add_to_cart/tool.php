<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Add To Cart
 *
 * Adds a product to the calling visitor's own session cart. This mutates
 * state, but only the caller's own ephemeral, per-session cart — it never
 * discloses or affects any other visitor's data, moves no money, and is
 * fully reversible (wc_update_cart_item can remove it again). That safety
 * class — not "readonly" — is why this is WebMCP-anonymous-safe; see
 * Webmcp_Bridge::ANONYMOUS_SAFE_TOOLS's docblock.
 *
 * Deliberately stops here: checkout/payment is not wrapped by this or any
 * other tool in this plugin. That is a real, separate design problem (order
 * creation semantics, payment-failure states, PCI scope) — routing it
 * through a chat/agent intermediary needs its own dedicated design pass, not
 * a same-shape extension of "add to cart." A shopper still completes their
 * purchase on the store's own checkout page, which wc_view_cart's
 * checkout_url points at.
 *
 * @package Agentic\Tools
 */
class Wc_Add_To_Cart extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_add_to_cart';
	}

	public function get_description(): string {
		return "Add a product to the current visitor's own shopping cart. Does not place an order or take payment — the shopper still completes checkout themselves.";
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'product_id'           => array(
					'type'        => 'integer',
					'description' => 'The product ID to add.',
				),
				'quantity'             => array(
					'type'        => 'integer',
					'description' => 'How many to add. Defaults to 1.',
				),
				'variation_id'         => array(
					'type'        => 'integer',
					'description' => 'For variable products, the specific variation ID to add.',
				),
				'variation_attributes' => array(
					'type'        => 'object',
					'description' => 'For variable products, the chosen attribute values (e.g. {"attribute_size": "Large"}).',
				),
			),
			'required'   => array( 'product_id' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$product_id = (int) ( $arguments['product_id'] ?? 0 );
		$quantity   = max( 1, (int) ( $arguments['quantity'] ?? 1 ) );

		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return array( 'error' => 'Product not found.' );
		}
		if ( ! $product->is_purchasable() ) {
			return array( 'error' => 'This product cannot currently be purchased.' );
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return array( 'error' => 'Cart is not available.' );
		}

		wc_clear_notices();

		$variation_id = (int) ( $arguments['variation_id'] ?? 0 );
		$variation    = is_array( $arguments['variation_attributes'] ?? null ) ? $arguments['variation_attributes'] : array();

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

		if ( ! $cart_item_key ) {
			$errors = array();
			foreach ( wc_get_notices( 'error' ) as $notice ) {
				$errors[] = wp_strip_all_tags( is_array( $notice ) ? ( $notice['notice'] ?? '' ) : $notice );
			}
			wc_clear_notices();
			return array( 'error' => ! empty( $errors ) ? implode( ' ', $errors ) : 'Could not add this product to the cart.' );
		}

		wc_clear_notices();

		return array_merge(
			array(
				'added'         => true,
				'cart_item_key' => $cart_item_key,
			),
			Wc_View_Cart::cart_summary()
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}
}

return new Wc_Add_To_Cart();
