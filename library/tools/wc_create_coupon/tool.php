<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Create Coupon
 *
 * Creates a new coupon with discount rules.
 *
 * @package Agentic\Tools
 */
class Wc_Create_Coupon extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_create_coupon';
	}

	public function get_description(): string {
		return 'Create a new WooCommerce coupon with discount type, amount, usage limits, and expiry date.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'code'                 => array(
					'type'        => 'string',
					'description' => 'Coupon code (e.g. "SUMMER20"). Will be uppercased.',
				),
				'discount_type'        => array(
					'type'        => 'string',
					'description' => 'Discount type: percent, fixed_cart, or fixed_product.',
					'enum'        => array( 'percent', 'fixed_cart', 'fixed_product' ),
				),
				'amount'               => array(
					'type'        => 'string',
					'description' => 'Discount amount (e.g. "20" for 20% or $20).',
				),
				'description'          => array(
					'type'        => 'string',
					'description' => 'Internal description / note for this coupon.',
				),
				'expiry_date'          => array(
					'type'        => 'string',
					'description' => 'Expiry date in YYYY-MM-DD format.',
				),
				'usage_limit'          => array(
					'type'        => 'integer',
					'description' => 'Total number of times this coupon can be used (0 = unlimited).',
				),
				'usage_limit_per_user' => array(
					'type'        => 'integer',
					'description' => 'Max uses per customer (0 = unlimited).',
				),
				'minimum_amount'       => array(
					'type'        => 'string',
					'description' => 'Minimum cart total required.',
				),
				'maximum_amount'       => array(
					'type'        => 'string',
					'description' => 'Maximum cart total allowed.',
				),
				'free_shipping'        => array(
					'type'        => 'boolean',
					'description' => 'Whether this coupon grants free shipping.',
				),
				'individual_use'       => array(
					'type'        => 'boolean',
					'description' => 'If true, cannot be combined with other coupons.',
				),
				'product_ids'          => array(
					'type'        => 'array',
					'description' => 'Restrict to specific product IDs.',
					'items'       => array( 'type' => 'integer' ),
				),
				'category_ids'         => array(
					'type'        => 'array',
					'description' => 'Restrict to specific product category IDs.',
					'items'       => array( 'type' => 'integer' ),
				),
			),
			'required'   => array( 'code', 'discount_type', 'amount' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$code = strtoupper( sanitize_text_field( $arguments['code'] ) );

		// Check for duplicate.
		$existing = wc_get_coupon_id_by_code( $code );
		if ( $existing ) {
			return array( 'error' => "Coupon code '{$code}' already exists (ID: {$existing})." );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( sanitize_key( $arguments['discount_type'] ) );
		$coupon->set_amount( sanitize_text_field( $arguments['amount'] ) );

		if ( ! empty( $arguments['description'] ) ) {
			$coupon->set_description( sanitize_textarea_field( $arguments['description'] ) );
		}
		if ( ! empty( $arguments['expiry_date'] ) ) {
			$coupon->set_date_expires( sanitize_text_field( $arguments['expiry_date'] ) );
		}
		if ( isset( $arguments['usage_limit'] ) ) {
			$coupon->set_usage_limit( (int) $arguments['usage_limit'] );
		}
		if ( isset( $arguments['usage_limit_per_user'] ) ) {
			$coupon->set_usage_limit_per_user( (int) $arguments['usage_limit_per_user'] );
		}
		if ( ! empty( $arguments['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( sanitize_text_field( $arguments['minimum_amount'] ) );
		}
		if ( ! empty( $arguments['maximum_amount'] ) ) {
			$coupon->set_maximum_amount( sanitize_text_field( $arguments['maximum_amount'] ) );
		}
		if ( ! empty( $arguments['free_shipping'] ) ) {
			$coupon->set_free_shipping( true );
		}
		if ( ! empty( $arguments['individual_use'] ) ) {
			$coupon->set_individual_use( true );
		}
		if ( ! empty( $arguments['product_ids'] ) ) {
			$coupon->set_product_ids( array_map( 'intval', $arguments['product_ids'] ) );
		}
		if ( ! empty( $arguments['category_ids'] ) ) {
			$coupon->set_product_categories( array_map( 'intval', $arguments['category_ids'] ) );
		}

		$coupon_id = $coupon->save();

		if ( ! $coupon_id ) {
			return array( 'error' => 'Failed to create coupon.' );
		}

		return array(
			'coupon_id'     => $coupon_id,
			'code'          => $coupon->get_code(),
			'discount_type' => $coupon->get_discount_type(),
			'amount'        => $coupon->get_amount(),
			'expiry_date'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
		);
	}

	public function get_annotations(): array {
		return array( 'destructive' => false );
	}
}

return new Wc_Create_Coupon();
