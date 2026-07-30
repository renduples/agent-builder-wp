<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Manage Category
 *
 * Create, update, or delete a product category.
 *
 * @package Agentic\Tools
 */
class Wc_Manage_Category extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_manage_category';
	}

	public function get_description(): string {
		return 'Create, update, or delete a WooCommerce product category. Can set name, slug, description, parent, and display type.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action'      => array(
					'type'        => 'string',
					'description' => 'Action to perform: create, update, or delete.',
					'enum'        => array( 'create', 'update', 'delete' ),
				),
				'category_id' => array(
					'type'        => 'integer',
					'description' => 'Category ID (required for update and delete).',
				),
				'name'        => array(
					'type'        => 'string',
					'description' => 'Category name (required for create).',
				),
				'slug'        => array(
					'type'        => 'string',
					'description' => 'Category slug.',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Category description.',
				),
				'parent_id'   => array(
					'type'        => 'integer',
					'description' => 'Parent category ID (0 for top-level).',
				),
				'display'     => array(
					'type'        => 'string',
					'description' => 'Display type on category archive: default, products, subcategories, both.',
					'enum'        => array( 'default', 'products', 'subcategories', 'both' ),
				),
			),
			'required'   => array( 'action' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$action = $arguments['action'];

		if ( 'create' === $action ) {
			if ( empty( $arguments['name'] ) ) {
				return array( 'error' => 'Name is required to create a category.' );
			}

			$term_args = array();
			if ( ! empty( $arguments['slug'] ) ) {
				$term_args['slug'] = sanitize_title( $arguments['slug'] );
			}
			if ( ! empty( $arguments['description'] ) ) {
				$term_args['description'] = sanitize_textarea_field( $arguments['description'] );
			}
			if ( isset( $arguments['parent_id'] ) ) {
				$term_args['parent'] = (int) $arguments['parent_id'];
			}

			$result = wp_insert_term( sanitize_text_field( $arguments['name'] ), 'product_cat', $term_args );

			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}

			if ( ! empty( $arguments['display'] ) ) {
				update_term_meta( $result['term_id'], 'display_type', sanitize_key( $arguments['display'] ) );
			}

			$term = get_term( $result['term_id'], 'product_cat' );
			return array(
				'action'      => 'created',
				'category_id' => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
			);
		}

		if ( 'update' === $action ) {
			$cat_id = (int) ( $arguments['category_id'] ?? 0 );
			if ( ! $cat_id ) {
				return array( 'error' => 'category_id is required for update.' );
			}

			$term_args = array();
			if ( ! empty( $arguments['name'] ) ) {
				$term_args['name'] = sanitize_text_field( $arguments['name'] );
			}
			if ( ! empty( $arguments['slug'] ) ) {
				$term_args['slug'] = sanitize_title( $arguments['slug'] );
			}
			if ( isset( $arguments['description'] ) ) {
				$term_args['description'] = sanitize_textarea_field( $arguments['description'] );
			}
			if ( isset( $arguments['parent_id'] ) ) {
				$term_args['parent'] = (int) $arguments['parent_id'];
			}

			$result = wp_update_term( $cat_id, 'product_cat', $term_args );

			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}

			if ( ! empty( $arguments['display'] ) ) {
				update_term_meta( $cat_id, 'display_type', sanitize_key( $arguments['display'] ) );
			}

			$term = get_term( $cat_id, 'product_cat' );
			return array(
				'action'      => 'updated',
				'category_id' => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
			);
		}

		if ( 'delete' === $action ) {
			$cat_id = (int) ( $arguments['category_id'] ?? 0 );
			if ( ! $cat_id ) {
				return array( 'error' => 'category_id is required for delete.' );
			}

			$term = get_term( $cat_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				return array( 'error' => 'Category not found.' );
			}

			$deleted = wp_delete_term( $cat_id, 'product_cat' );
			if ( is_wp_error( $deleted ) ) {
				return array( 'error' => $deleted->get_error_message() );
			}

			return array(
				'action'      => 'deleted',
				'category_id' => $cat_id,
				'name'        => $term->name,
			);
		}

		return array( 'error' => 'Invalid action. Use create, update, or delete.' );
	}

	public function get_annotations(): array {
		return array( 'destructive' => false );
	}
}

return new Wc_Manage_Category();
