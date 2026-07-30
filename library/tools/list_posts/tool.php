<?php
/**
 * Tool: list_posts
 *
 * List posts or pages, filtered by status, type, or search term.
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
 * List posts or pages, filtered by status, type, or search term.
 */
class List_Posts extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'list_posts';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'List posts or pages, filtered by status, type, or search term. Use this to find content to work with before fetching or editing it.';
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
				'post_type' => array(
					'type'        => 'string',
					'description' => '"post" (default) or "page".',
				),
				'status'    => array(
					'type'        => 'string',
					'description' => '"publish", "draft", "pending", "private", or "any". Defaults to "any".',
				),
				'search'    => array(
					'type'        => 'string',
					'description' => 'Optional keyword to search titles and content.',
				),
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Maximum results (1–50). Defaults to 10.',
				),
				'offset'    => array(
					'type'        => 'integer',
					'description' => 'Pagination offset. Defaults to 0.',
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
		$post_type = in_array( $arguments['post_type'] ?? 'post', array( 'post', 'page' ), true ) ? ( $arguments['post_type'] ?? 'post' ) : 'post';
		$status    = $arguments['status'] ?? 'any';
		$limit     = min( max( (int) ( $arguments['limit'] ?? 10 ), 1 ), 50 );
		$offset    = max( (int) ( $arguments['offset'] ?? 0 ), 0 );
		$search    = sanitize_text_field( $arguments['search'] ?? '' );

		$ability_input = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $search ) {
			$ability_input['s'] = $search;
		}

		$ability_result = $this->call_ability( 'wp-extended/get-posts', $ability_input );

		if ( $ability_result && ! isset( $ability_result['error'] ) ) {
			// Enrich with word count and URL (not in ability).
			$posts = array();
			foreach ( $ability_result as $p ) {
				$post_obj = get_post( $p['ID'] ?? 0 );
				$posts[]  = array(
					'id'         => $p['ID'] ?? 0,
					'title'      => $p['post_title'] ?? '',
					'status'     => $p['post_status'] ?? '',
					'date'       => $p['post_date'] ?? '',
					'modified'   => $post_obj ? $post_obj->post_modified : '',
					'word_count' => $post_obj ? str_word_count( wp_strip_all_tags( $post_obj->post_content ) ) : 0,
					'url'        => $post_obj ? get_permalink( $post_obj->ID ) : '',
				);
			}

			return array(
				'total_returned' => count( $posts ),
				'offset'         => $offset,
				'posts'          => $posts,
			);
		}

		if ( isset( $ability_result['error'] ) ) {
			return $ability_result;
		}

		// Fallback: ability unavailable, query directly.
		$query_args           = $ability_input;
		$query_args['offset'] = $offset;

		$posts = get_posts( $query_args );
		return array(
			'total_returned' => count( $posts ),
			'offset'         => $offset,
			'posts'          => array_map(
				fn( $p ) => array(
					'id'         => $p->ID,
					'title'      => $p->post_title,
					'status'     => $p->post_status,
					'date'       => $p->post_date,
					'modified'   => $p->post_modified,
					'word_count' => str_word_count( wp_strip_all_tags( $p->post_content ) ),
					'url'        => get_permalink( $p->ID ),
				),
				$posts
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
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new List_Posts();
