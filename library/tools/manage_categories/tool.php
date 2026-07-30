<?php
/**
 * Tool: manage_categories
 *
 * List existing categories, create a new category, or get posts in a specific category.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List existing categories, create a new category, or get posts in a specific category.
 */
class Manage_Categories extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_categories';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'List existing categories, create a new category, or get posts in a specific category.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'content';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action'      => array(
					'type'        => 'string',
					'description' => '"list" (default), "create", or "get_posts".',
				),
				'name'        => array(
					'type'        => 'string',
					'description' => 'Category name. Required for "create".',
				),
				'parent_id'   => array(
					'type'        => 'integer',
					'description' => 'Parent category ID. Optional for "create".',
				),
				'category_id' => array(
					'type'        => 'integer',
					'description' => 'Category ID. Required for "get_posts".',
				),
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$action = $arguments['action'] ?? 'list';

		if ( 'create' === $action ) {
			if ( ! current_user_can( 'manage_categories' ) ) {
				return array( 'error' => 'You do not have permission to manage categories.' );
			}
			if ( empty( $arguments['name'] ) ) {
				return array( 'error' => 'A category name is required.' );
			}
			$term = wp_insert_term(
				sanitize_text_field( $arguments['name'] ),
				'category',
				array( 'parent' => (int) ( $arguments['parent_id'] ?? 0 ) )
			);
			if ( is_wp_error( $term ) ) {
				return array( 'error' => $term->get_error_message() );
			}
			return array(
				'success'     => true,
				'category_id' => $term['term_id'],
				'name'        => $arguments['name'],
			);
		}

		if ( 'get_posts' === $action ) {
			if ( empty( $arguments['category_id'] ) ) {
				return array( 'error' => 'A category_id is required.' );
			}
			$posts = get_posts(
				array(
					'category'       => (int) $arguments['category_id'],
					'posts_per_page' => 20,
					'post_status'    => 'any',
				)
			);
			return array(
				'posts' => array_map(
					fn( $p ) => array(
						'id'     => $p->ID,
						'title'  => $p->post_title,
						'status' => $p->post_status,
					),
					$posts
				),
			);
		}

		// Delegate listing to wp-extended ability.
		$ability_result = $this->call_ability( 'wp-extended/get-categories', array( 'hide_empty' => false ) );

		if ( $ability_result && ! isset( $ability_result['error'] ) ) {
			return array(
				'categories' => array_slice(
					array_map(
						fn( $c ) => array(
							'id'     => $c['term_id'] ?? 0,
							'name'   => $c['name'] ?? '',
							'slug'   => $c['slug'] ?? '',
							'parent' => $c['parent'] ?? 0,
							'count'  => $c['count'] ?? 0,
						),
						$ability_result
					),
					0,
					50
				),
			);
		}

		// Fallback: query directly.
		$categories = get_categories(
			array(
				'hide_empty' => false,
				'number'     => 50,
			)
		);
		return array(
			'categories' => array_map(
				fn( $c ) => array(
					'id'     => $c->term_id,
					'name'   => $c->name,
					'slug'   => $c->slug,
					'parent' => $c->parent,
					'count'  => $c->count,
				),
				$categories
			),
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}
}

return new Manage_Categories();
