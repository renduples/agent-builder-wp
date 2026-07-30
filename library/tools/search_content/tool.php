<?php
/**
 * Tool: search_content
 *
 * Full-text search across WordPress posts, pages, and custom post types
 * using WP_Query's 's' parameter (equivalent to the ?s=searchterm front-end search).
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
 * Full-text search across WordPress content using WP_Query.
 */
class Search_Content extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'search_content';
	}

	public function get_description(): string {
		return 'Full-text search across WordPress posts, pages, and custom post types. Searches titles and content (same as the site search bar). Returns matching posts with title, excerpt, type, status, date, and URL. Use this when the user asks to find posts by keyword, topic, or phrase.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'query'     => array(
					'type'        => 'string',
					'description' => 'The search term. Searches post titles and content.',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to search: "post", "page", "any" (all public types), or a custom post type slug. Defaults to "any".',
				),
				'status'    => array(
					'type'        => 'string',
					'description' => 'Post status filter: "publish" (default), "draft", "any", or another valid status.',
				),
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Max results (1–50). Defaults to 10.',
				),
				'offset'    => array(
					'type'        => 'integer',
					'description' => 'Pagination offset. Defaults to 0.',
				),
			),
			'required'   => array( 'query' ),
		);
	}

	public function execute( array $arguments ): array {
		$query_str = sanitize_text_field( $arguments['query'] ?? '' );
		if ( '' === $query_str ) {
			return array( 'error' => 'query is required.' );
		}

		$status = sanitize_key( $arguments['status'] ?? 'publish' );
		$limit  = min( max( (int) ( $arguments['limit'] ?? 10 ), 1 ), 50 );
		$offset = max( (int) ( $arguments['offset'] ?? 0 ), 0 );

		// Resolve post_type — default 'any', otherwise validate against registered types.
		$raw_type = sanitize_key( $arguments['post_type'] ?? 'any' );
		if ( 'any' === $raw_type ) {
			$post_type = 'any';
		} elseif ( post_type_exists( $raw_type ) ) {
			$post_type = $raw_type;
		} else {
			$post_type = 'any';
		}

		$query = new \WP_Query(
			array(
				's'              => $query_str,
				'post_type'      => $post_type,
				'post_status'    => $status,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'relevance',
				'no_found_rows'  => false,
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			$excerpt = $post->post_excerpt;
			if ( '' === $excerpt ) {
				$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
			}

			$item = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'date'      => $post->post_date,
				'modified'  => $post->post_modified,
				'url'       => get_permalink( $post->ID ),
				'excerpt'   => $excerpt,
			);

			// Categories and tags for standard posts.
			if ( 'post' === $post->post_type ) {
				$cats = get_the_terms( $post->ID, 'category' );
				$tags = get_the_terms( $post->ID, 'post_tag' );
				if ( $cats && ! is_wp_error( $cats ) ) {
					$item['categories'] = wp_list_pluck( $cats, 'name' );
				}
				if ( $tags && ! is_wp_error( $tags ) ) {
					$item['tags'] = wp_list_pluck( $tags, 'name' );
				}
			}

			$results[] = $item;
		}

		return array(
			'query'          => $query_str,
			'total_found'    => (int) $query->found_posts,
			'total_returned' => count( $results ),
			'offset'         => $offset,
			'results'        => $results,
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Search_Content();
