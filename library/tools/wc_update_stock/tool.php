<?php
/**
 * Tool: wc_update_stock
 *
 * Adjust stock quantity on a WooCommerce product or variation.
 * Supports set, increase, and decrease operations.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.7.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update stock quantity on a WooCommerce product or variation.
 */
class Wc_Update_Stock extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_update_stock';
	}

	public function get_description(): string {
		return 'Set, increase, or decrease the stock quantity of a WooCommerce product or product variation. Automatically enables stock management if it is not already on. Returns the new stock level and stock status.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'product_id'          => array(
					'type'        => 'integer',
					'description' => 'Product ID (or variation ID for variable products).',
				),
				'quantity'            => array(
					'type'        => 'integer',
					'description' => 'The quantity value. For "set": new total. For "increase"/"decrease": amount to add/subtract.',
				),
				'operation'           => array(
					'type'        => 'string',
					'description' => '"set" (default) — set to exact quantity. "increase" — add quantity. "decrease" — subtract quantity.',
					'enum'        => array( 'set', 'increase', 'decrease' ),
				),
				'low_stock_threshold' => array(
					'type'        => 'integer',
					'description' => 'Optional: update the low stock notification threshold at the same time.',
				),
			),
			'required'   => array( 'product_id', 'quantity' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$product_id = (int) ( $arguments['product_id'] ?? 0 );
		$quantity   = (int) ( $arguments['quantity'] ?? 0 );
		$operation  = $arguments['operation'] ?? 'set';

		if ( $product_id <= 0 ) {
			return array( 'error' => 'product_id is required.' );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array( 'error' => "Product {$product_id} not found." );
		}

		if ( $product->is_type( 'variable' ) ) {
			return array( 'error' => 'Use a variation ID to update stock on a variable product, not the parent product ID.' );
		}

		// Enable stock management.
		if ( ! $product->managing_stock() ) {
			$product->set_manage_stock( true );
		}

		$current_qty = (int) $product->get_stock_quantity();

		switch ( $operation ) {
			case 'increase':
				$new_qty = $current_qty + abs( $quantity );
				break;
			case 'decrease':
				$new_qty = $current_qty - abs( $quantity );
				break;
			default:
				$new_qty = $quantity;
		}

		$product->set_stock_quantity( $new_qty );

		if ( isset( $arguments['low_stock_threshold'] ) ) {
			$product->set_low_stock_amount( (int) $arguments['low_stock_threshold'] );
		}

		$product->save();

		// WooCommerce stock status is auto-set on save, but let's refresh.
		$product = wc_get_product( $product_id );

		return array(
			'product_id'   => $product_id,
			'name'         => $product->get_name(),
			'operation'    => $operation,
			'previous_qty' => $current_qty,
			'new_qty'      => (int) $product->get_stock_quantity(),
			'stock_status' => $product->get_stock_status(),
			'edit_link'    => get_edit_post_link( $product_id, 'raw' ),
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

return new Wc_Update_Stock();
