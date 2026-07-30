<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Product Categories
 *
 * Returns the product category tree with product counts.
 *
 * @package Agentic\Tools
 */
class Wc_Get_Categories extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_get_categories';
	}

	public function get_description(): string {
		return 'Get WooCommerce product categories as a tree with product counts, slugs, and descriptions.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'hide_empty' => array(
					'type'        => 'boolean',
					'description' => 'Hide categories with no products. Defaults to false.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$hide_empty = (bool) ( $arguments['hide_empty'] ?? false );
		$terms      = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => $hide_empty,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array( 'error' => $terms->get_error_message() );
		}

		$flat = array();
		foreach ( $terms as $term ) {
			$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
			$flat[]   = array(
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent_id'   => $term->parent,
				'count'       => $term->count,
				'image'       => $thumb_id ? wp_get_attachment_url( $thumb_id ) : null,
			);
		}

		return array(
			'categories' => $flat,
			'total'      => count( $flat ),
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Wc_Get_Categories();
