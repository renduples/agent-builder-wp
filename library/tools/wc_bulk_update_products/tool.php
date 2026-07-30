<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Bulk Update Products
 *
 * Batch-updates product fields (descriptions, titles, prices, status)
 * for multiple products at once. Designed for AI-generated content workflows.
 *
 * @package Agentic\Tools
 */
class Wc_Bulk_Update_Products extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_bulk_update_products';
	}

	public function get_description(): string {
		return 'Bulk-update multiple WooCommerce products at once. Each item specifies a product_id and the fields to change (name, description, short_description, regular_price, sale_price, status). Ideal for AI-generated product descriptions.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'updates' => array(
					'type'        => 'array',
					'description' => 'Array of product updates. Each item must have product_id and at least one field to update.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'product_id'        => array(
								'type'        => 'integer',
								'description' => 'Product ID.',
							),
							'name'              => array(
								'type'        => 'string',
								'description' => 'New product name.',
							),
							'description'       => array(
								'type'        => 'string',
								'description' => 'New full description.',
							),
							'short_description' => array(
								'type'        => 'string',
								'description' => 'New short description.',
							),
							'regular_price'     => array(
								'type'        => 'string',
								'description' => 'New regular price.',
							),
							'sale_price'        => array(
								'type'        => 'string',
								'description' => 'New sale price.',
							),
							'status'            => array(
								'type'        => 'string',
								'description' => 'New status.',
							),
						),
						'required'   => array( 'product_id' ),
					),
				),
			),
			'required'   => array( 'updates' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$updates = $arguments['updates'] ?? array();
		$max     = 50;
		$updates = array_slice( $updates, 0, $max );
		$results = array();
		$success = 0;
		$failed  = 0;

		foreach ( $updates as $item ) {
			$pid = (int) ( $item['product_id'] ?? 0 );
			if ( ! $pid ) {
				++$failed;
				$results[] = array(
					'product_id' => $pid,
					'error'      => 'Missing product_id.',
				);
				continue;
			}

			$product = wc_get_product( $pid );
			if ( ! $product ) {
				++$failed;
				$results[] = array(
					'product_id' => $pid,
					'error'      => 'Product not found.',
				);
				continue;
			}

			$changes = array();

			if ( isset( $item['name'] ) ) {
				$product->set_name( sanitize_text_field( $item['name'] ) );
				$changes[] = 'name';
			}
			if ( isset( $item['description'] ) ) {
				$product->set_description( wp_kses_post( $item['description'] ) );
				$changes[] = 'description';
			}
			if ( isset( $item['short_description'] ) ) {
				$product->set_short_description( wp_kses_post( $item['short_description'] ) );
				$changes[] = 'short_description';
			}
			if ( isset( $item['regular_price'] ) && ! $product->is_type( 'variable' ) ) {
				$product->set_regular_price( sanitize_text_field( $item['regular_price'] ) );
				$changes[] = 'regular_price';
			}
			if ( isset( $item['sale_price'] ) && ! $product->is_type( 'variable' ) ) {
				$product->set_sale_price( sanitize_text_field( $item['sale_price'] ) );
				$changes[] = 'sale_price';
			}
			if ( isset( $item['status'] ) ) {
				$product->set_status( sanitize_key( $item['status'] ) );
				$changes[] = 'status';
			}

			if ( empty( $changes ) ) {
				$results[] = array(
					'product_id' => $pid,
					'skipped'    => true,
					'reason'     => 'No fields to update.',
				);
				continue;
			}

			$product->save();
			++$success;
			$results[] = array(
				'product_id' => $pid,
				'name'       => $product->get_name(),
				'changes'    => $changes,
			);
		}

		return array(
			'total_processed' => count( $updates ),
			'success'         => $success,
			'failed'          => $failed,
			'results'         => $results,
		);
	}

	public function get_annotations(): array {
		return array( 'destructive' => false );
	}
}

return new Wc_Bulk_Update_Products();
