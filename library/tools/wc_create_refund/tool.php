<?php
/**
 * Tool: wc_create_refund
 *
 * Issue a partial or full refund on a WooCommerce order.
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
 * Create a refund on a WooCommerce order.
 */
class Wc_Create_Refund extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_create_refund';
	}

	public function get_description(): string {
		return 'Issue a partial or full refund on a WooCommerce order. Records the refund in WooCommerce order history. Note: this creates a manual refund record — it does NOT automatically trigger a payment gateway refund. For gateway refunds, use the WooCommerce admin UI. Always confirm the amount and reason with the user before calling.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'order_id'      => array(
					'type'        => 'integer',
					'description' => 'The order ID to refund.',
				),
				'amount'        => array(
					'type'        => 'number',
					'description' => 'Refund amount in the order currency. Must not exceed the order total.',
				),
				'reason'        => array(
					'type'        => 'string',
					'description' => 'Reason for the refund. Stored in the order history.',
				),
				'restock_items' => array(
					'type'        => 'boolean',
					'description' => 'If true, restocks the refunded items. Defaults to false.',
				),
			),
			'required'   => array( 'order_id', 'amount', 'reason' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$order_id = (int) ( $arguments['order_id'] ?? 0 );
		$amount   = (float) ( $arguments['amount'] ?? 0 );
		$reason   = sanitize_textarea_field( $arguments['reason'] ?? '' );

		if ( $order_id <= 0 ) {
			return array( 'error' => 'order_id is required.' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'error' => "Order {$order_id} not found." );
		}

		$order_total      = (float) $order->get_total();
		$already_refunded = (float) $order->get_total_refunded();
		$max_refundable   = $order_total - $already_refunded;

		if ( $amount <= 0 ) {
			return array( 'error' => 'amount must be greater than 0.' );
		}

		if ( $amount > $max_refundable + 0.001 ) {
			return array(
				'error'            => "Refund amount ({$amount}) exceeds the refundable balance ({$max_refundable}).",
				'order_total'      => $order_total,
				'already_refunded' => $already_refunded,
				'max_refundable'   => $max_refundable,
			);
		}

		if ( '' === $reason ) {
			return array( 'error' => 'reason is required.' );
		}

		$refund = wc_create_refund(
			array(
				'order_id'       => $order_id,
				'amount'         => $amount,
				'reason'         => $reason,
				'restock_items'  => ! empty( $arguments['restock_items'] ),
				'refund_payment' => false, // Manual — never auto-trigger gateway.
			)
		);

		if ( is_wp_error( $refund ) ) {
			return array( 'error' => 'Refund failed: ' . $refund->get_error_message() );
		}

		return array(
			'refund_id'       => $refund->get_id(),
			'order_id'        => $order_id,
			'amount'          => $refund->get_amount(),
			'reason'          => $refund->get_reason(),
			'currency'        => $order->get_currency(),
			'order_total'     => $order_total,
			'total_refunded'  => (float) $order->get_total_refunded(),
			'remaining_total' => round( $order_total - (float) $order->get_total_refunded(), 2 ),
			'restock'         => ! empty( $arguments['restock_items'] ),
			'note'            => 'This is a manual refund record. No payment gateway refund was triggered.',
			'edit_link'       => $order->get_edit_order_url(),
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => false,
		);
	}
}

return new Wc_Create_Refund();
