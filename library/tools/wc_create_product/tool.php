<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Create Product
 *
 * Creates a new WooCommerce product (simple or variable).
 *
 * @package Agentic\Tools
 */
class Wc_Create_Product extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_create_product';
	}

	public function get_description(): string {
		return 'Create a new WooCommerce product with title, description, price, SKU, categories, images, and attributes. Defaults to draft status.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'name'              => array(
					'type'        => 'string',
					'description' => 'Product name / title.',
				),
				'type'              => array(
					'type'        => 'string',
					'description' => 'Product type: simple or variable. Defaults to simple.',
					'enum'        => array( 'simple', 'variable' ),
				),
				'status'            => array(
					'type'        => 'string',
					'description' => 'Product status: draft or publish. Defaults to draft.',
					'enum'        => array( 'draft', 'publish' ),
				),
				'description'       => array(
					'type'        => 'string',
					'description' => 'Full product description (HTML supported).',
				),
				'short_description' => array(
					'type'        => 'string',
					'description' => 'Short product description / excerpt.',
				),
				'regular_price'     => array(
					'type'        => 'string',
					'description' => 'Regular price (e.g. "29.99").',
				),
				'sale_price'        => array(
					'type'        => 'string',
					'description' => 'Sale price (optional).',
				),
				'sku'               => array(
					'type'        => 'string',
					'description' => 'Unique product SKU.',
				),
				'manage_stock'      => array(
					'type'        => 'boolean',
					'description' => 'Whether to manage stock quantity. Defaults to false.',
				),
				'stock_quantity'    => array(
					'type'        => 'integer',
					'description' => 'Stock quantity (requires manage_stock=true).',
				),
				'weight'            => array(
					'type'        => 'string',
					'description' => 'Product weight.',
				),
				'categories'        => array(
					'type'        => 'array',
					'description' => 'Array of category names or IDs to assign.',
					'items'       => array( 'type' => 'string' ),
				),
				'tags'              => array(
					'type'        => 'array',
					'description' => 'Array of tag names to assign.',
					'items'       => array( 'type' => 'string' ),
				),
				'image_id'          => array(
					'type'        => 'integer',
					'description' => 'Media library attachment ID for the main product image.',
				),
			),
			'required'   => array( 'name' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$type = $arguments['type'] ?? 'simple';

		if ( 'variable' === $type ) {
			$product = new \WC_Product_Variable();
		} else {
			$product = new \WC_Product_Simple();
		}

		$product->set_name( sanitize_text_field( $arguments['name'] ) );
		$product->set_status( sanitize_key( $arguments['status'] ?? 'draft' ) );

		if ( ! empty( $arguments['description'] ) ) {
			$product->set_description( wp_kses_post( $arguments['description'] ) );
		}
		if ( ! empty( $arguments['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $arguments['short_description'] ) );
		}
		if ( isset( $arguments['regular_price'] ) && 'simple' === $type ) {
			$product->set_regular_price( sanitize_text_field( $arguments['regular_price'] ) );
		}
		if ( isset( $arguments['sale_price'] ) && 'simple' === $type ) {
			$product->set_sale_price( sanitize_text_field( $arguments['sale_price'] ) );
		}
		if ( ! empty( $arguments['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $arguments['sku'] ) );
		}
		if ( ! empty( $arguments['manage_stock'] ) ) {
			$product->set_manage_stock( true );
			if ( isset( $arguments['stock_quantity'] ) ) {
				$product->set_stock_quantity( (int) $arguments['stock_quantity'] );
			}
		}
		if ( ! empty( $arguments['weight'] ) ) {
			$product->set_weight( sanitize_text_field( $arguments['weight'] ) );
		}
		if ( ! empty( $arguments['image_id'] ) ) {
			$product->set_image_id( (int) $arguments['image_id'] );
		}

		// Categories.
		if ( ! empty( $arguments['categories'] ) ) {
			$cat_ids = array();
			foreach ( $arguments['categories'] as $cat ) {
				if ( is_numeric( $cat ) ) {
					$cat_ids[] = (int) $cat;
				} else {
					$term = get_term_by( 'name', $cat, 'product_cat' );
					if ( ! $term ) {
						$term = get_term_by( 'slug', sanitize_title( $cat ), 'product_cat' );
					}
					if ( ! $term ) {
						$result = wp_insert_term( sanitize_text_field( $cat ), 'product_cat' );
						if ( ! is_wp_error( $result ) ) {
							$cat_ids[] = $result['term_id'];
						}
					} else {
						$cat_ids[] = $term->term_id;
					}
				}
			}
			$product->set_category_ids( $cat_ids );
		}

		// Tags.
		if ( ! empty( $arguments['tags'] ) ) {
			$tag_ids = array();
			foreach ( $arguments['tags'] as $tag ) {
				$term = get_term_by( 'name', $tag, 'product_tag' );
				if ( ! $term ) {
					$result = wp_insert_term( sanitize_text_field( $tag ), 'product_tag' );
					if ( ! is_wp_error( $result ) ) {
						$tag_ids[] = $result['term_id'];
					}
				} else {
					$tag_ids[] = $term->term_id;
				}
			}
			$product->set_tag_ids( $tag_ids );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return array( 'error' => 'Failed to create product.' );
		}

		return array(
			'product_id' => $product_id,
			'name'       => $product->get_name(),
			'status'     => $product->get_status(),
			'type'       => $product->get_type(),
			'price'      => $product->get_price(),
			'sku'        => $product->get_sku(),
			'permalink'  => $product->get_permalink(),
			'edit_link'  => get_edit_post_link( $product_id, 'raw' ),
		);
	}

	public function get_annotations(): array {
		return array( 'destructive' => false );
	}
}

return new Wc_Create_Product();
