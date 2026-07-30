<?php
/**
 * Tool: wc_add_order_note
 *
 * Add an internal or customer-facing note to a WooCommerce order.
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
 * Add a note to a WooCommerce order.
 */
class Wc_Add_Order_Note extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_add_order_note';
	}

	public function get_description(): string {
		return 'Add an internal admin note or a customer-facing note to a WooCommerce order. Customer notes trigger an email notification to the customer. Use for logging actions, communicating with customers, or recording investigation findings.';
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
					'description' => 'The order ID to add the note to.',
				),
				'note'          => array(
					'type'        => 'string',
					'description' => 'The note content.',
				),
				'customer_note' => array(
					'type'        => 'boolean',
					'description' => 'If true, the note is visible to the customer and triggers an email. Defaults to false (admin-only).',
				),
			),
			'required'   => array( 'order_id', 'note' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$order = wc_get_order( (int) ( $arguments['order_id'] ?? 0 ) );
		if ( ! $order ) {
			return array( 'error' => 'Order not found.' );
		}

		$note          = sanitize_textarea_field( $arguments['note'] ?? '' );
		$customer_note = ! empty( $arguments['customer_note'] );

		if ( '' === $note ) {
			return array( 'error' => 'note is required.' );
		}

		$note_id = $order->add_order_note( $note, $customer_note ? 1 : 0, $customer_note );

		return array(
			'note_id'       => $note_id,
			'order_id'      => $order->get_id(),
			'note'          => $note,
			'customer_note' => $customer_note,
			'date'          => current_time( 'Y-m-d H:i:s' ),
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

return new Wc_Add_Order_Note();
