<?php
/**
 * Tool: manage_tags
 *
 * List the most-used tags, create a new tag, or get all tags on a specific post.
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
 * List the most-used tags, create a new tag, or get all tags on a specific post.
 */
class Manage_Tags extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_tags';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'List the most-used tags, create a new tag, or get all tags on a specific post.';
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
				'action'  => array(
					'type'        => 'string',
					'description' => '"list" (default), "create", or "get_post_tags".',
				),
				'name'    => array(
					'type'        => 'string',
					'description' => 'Tag name. Required for "create".',
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID. Required for "get_post_tags".',
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
				return array( 'error' => 'You do not have permission to manage tags.' );
			}
			if ( empty( $arguments['name'] ) ) {
				return array( 'error' => 'A tag name is required.' );
			}
			$term = wp_insert_term( sanitize_text_field( $arguments['name'] ), 'post_tag' );
			if ( is_wp_error( $term ) ) {
				return array( 'error' => $term->get_error_message() );
			}
			return array(
				'success' => true,
				'tag_id'  => $term['term_id'],
				'name'    => $arguments['name'],
			);
		}

		if ( 'get_post_tags' === $action ) {
			if ( empty( $arguments['post_id'] ) ) {
				return array( 'error' => 'A post_id is required.' );
			}
			$tags = get_the_tags( (int) $arguments['post_id'] );
			return array(
				'tags' => $tags ? array_map(
					fn( $t ) => array(
						'id'   => $t->term_id,
						'name' => $t->name,
						'slug' => $t->slug,
					),
					$tags
				) : array(),
			);
		}

		// Delegate listing to wp-extended ability.
		$ability_result = $this->call_ability( 'wp-extended/get-tags', array() );

		if ( $ability_result && ! isset( $ability_result['error'] ) ) {
			return array(
				'tags' => array_slice(
					array_map(
						fn( $t ) => array(
							'id'    => $t['term_id'] ?? 0,
							'name'  => $t['name'] ?? '',
							'slug'  => $t['slug'] ?? '',
							'count' => $t['count'] ?? 0,
						),
						$ability_result
					),
					0,
					50
				),
			);
		}

		// Fallback: query directly.
		$tags = get_tags(
			array(
				'orderby' => 'count',
				'order'   => 'DESC',
				'number'  => 50,
			)
		);
		return array(
			'tags' => array_map(
				fn( $t ) => array(
					'id'    => $t->term_id,
					'name'  => $t->name,
					'slug'  => $t->slug,
					'count' => $t->count,
				),
				$tags
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

return new Manage_Tags();
