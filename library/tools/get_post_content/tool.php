<?php
/**
 * Tool: get_post_content
 *
 * Retrieve the full content, metadata, categories, and tags of a single post or page.
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
 * Retrieve the full content, metadata, categories, and tags of a single post or page by ID.
 */
class Get_Post_Content extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_post_content';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Retrieve the full content, metadata, categories, and tags of a single post or page by ID. Always call this before editing.';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post ID.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$post_id = (int) ( $arguments['post_id'] ?? 0 );

		$ability_result = $this->call_ability( 'wp-extended/get-post', array( 'post_id' => $post_id ) );

		if ( $ability_result && ! isset( $ability_result['error'] ) ) {
			// Enrich with categories, tags, slug, URL (not in ability).
			$post       = get_post( $post_id );
			$categories = $post ? get_the_category( $post->ID ) : array();
			$tags       = $post ? get_the_tags( $post->ID ) : false;

			return array(
				'id'         => $ability_result['ID'] ?? $post_id,
				'post_type'  => $ability_result['post_type'] ?? '',
				'title'      => $ability_result['post_title'] ?? '',
				'content'    => $ability_result['post_content'] ?? '',
				'excerpt'    => $ability_result['post_excerpt'] ?? '',
				'status'     => $ability_result['post_status'] ?? '',
				'slug'       => $post ? $post->post_name : '',
				'author_id'  => $post ? (int) $post->post_author : 0,
				'date'       => $ability_result['post_date'] ?? '',
				'modified'   => $post ? $post->post_modified : '',
				'url'        => $ability_result['permalink'] ?? ( $post ? get_permalink( $post->ID ) : '' ),
				'categories' => array_map(
					fn( $c ) => array(
						'id'   => $c->term_id,
						'name' => $c->name,
					),
					$categories
				),
				'tags'       => $tags ? array_map(
					fn( $t ) => array(
						'id'   => $t->term_id,
						'name' => $t->name,
					),
					$tags
				) : array(),
			);
		}

		if ( isset( $ability_result['error'] ) ) {
			return $ability_result;
		}

		// Fallback: ability unavailable, query directly.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$categories = get_the_category( $post->ID );
		$tags       = get_the_tags( $post->ID );

		return array(
			'id'         => $post->ID,
			'post_type'  => $post->post_type,
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'excerpt'    => $post->post_excerpt,
			'status'     => $post->post_status,
			'slug'       => $post->post_name,
			'author_id'  => (int) $post->post_author,
			'date'       => $post->post_date,
			'modified'   => $post->post_modified,
			'url'        => get_permalink( $post->ID ),
			'categories' => array_map(
				fn( $c ) => array(
					'id'   => $c->term_id,
					'name' => $c->name,
				),
				$categories
			),
			'tags'       => $tags ? array_map(
				fn( $t ) => array(
					'id'   => $t->term_id,
					'name' => $t->name,
				),
				$tags
			) : array(),
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Get_Post_Content();
