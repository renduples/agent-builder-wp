<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Update Order Status
 *
 * Changes the status of an order and optionally adds a note.
 *
 * @package Agentic\Tools
 */
class Wc_Update_Order_Status extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_update_order_status';
	}

	public function get_description(): string {
		return 'Change the status of a WooCommerce order (e.g. processing → completed) and optionally add an order note.';
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
					'description' => 'The order ID.',
				),
				'status'        => array(
					'type'        => 'string',
					'description' => 'New order status: pending, processing, on-hold, completed, cancelled, refunded.',
					'enum'        => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded' ),
				),
				'note'          => array(
					'type'        => 'string',
					'description' => 'Optional order note to add (visible to admin only by default).',
				),
				'customer_note' => array(
					'type'        => 'boolean',
					'description' => 'If true, the note is also emailed to the customer. Defaults to false.',
				),
			),
			'required'   => array( 'order_id', 'status' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$order = wc_get_order( (int) $arguments['order_id'] );
		if ( ! $order ) {
			return array( 'error' => 'Order not found.' );
		}

		$old_status = $order->get_status();
		$new_status = sanitize_key( $arguments['status'] );

		$order->update_status( $new_status, '', true );

		if ( ! empty( $arguments['note'] ) ) {
			$is_customer_note = ! empty( $arguments['customer_note'] );
			$order->add_order_note(
				sanitize_textarea_field( $arguments['note'] ),
				$is_customer_note ? 1 : 0,
				$is_customer_note
			);
		}

		return array(
			'order_id'   => $order->get_id(),
			'old_status' => $old_status,
			'new_status' => $order->get_status(),
			'note_added' => ! empty( $arguments['note'] ),
		);
	}

	public function get_annotations(): array {
		return array( 'destructive' => false );
	}
}

return new Wc_Update_Order_Status();
