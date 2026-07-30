<?php
/**
 * Tool: find_duplicate_titles
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;

class Find_Duplicate_Titles extends Tool_Base {
	public function get_name(): string {
		return 'find_duplicate_titles';
	}

	public function get_description(): string {
		return 'Find published posts and pages that share the same title. Duplicate titles can hurt SEO and confuse visitors.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_type'   => array(
					'type'        => 'string',
					'description' => 'Post type to check. Use "any" to check both posts and pages. Defaults to "any".',
				),
				'post_status' => array(
					'type'        => 'string',
					'description' => 'Post status to filter by. Defaults to "publish".',
				),
			),
			'required'   => array(),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		global $wpdb;

		$post_status = sanitize_key( $args['post_status'] ?? 'publish' );
		$post_type   = sanitize_text_field( $args['post_type'] ?? 'any' );

		if ( 'any' === $post_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$duplicates = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_title, COUNT(*) AS cnt
					FROM {$wpdb->posts}
					WHERE post_status = %s
					AND post_type IN ('post', 'page')
					GROUP BY post_title
					HAVING cnt > 1
					ORDER BY cnt DESC",
					$post_status
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$duplicates = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_title, COUNT(*) AS cnt
					FROM {$wpdb->posts}
					WHERE post_status = %s
					AND post_type = %s
					GROUP BY post_title
					HAVING cnt > 1
					ORDER BY cnt DESC",
					$post_status,
					sanitize_key( $post_type )
				),
				ARRAY_A
			);
		}

		$result = array();
		foreach ( $duplicates as $dup ) {
			$query = new \WP_Query(
				array(
					'post_title'     => $dup['post_title'],
					'post_type'      => 'any' === $post_type ? array( 'post', 'page' ) : $post_type,
					'post_status'    => $post_status,
					'posts_per_page' => 20,
					'fields'         => 'ids',
				)
			);

			$posts = array();
			foreach ( $query->posts as $pid ) {
				$posts[] = array(
					'ID'        => $pid,
					'permalink' => get_permalink( $pid ),
					'post_type' => get_post_type( $pid ),
				);
			}

			$result[] = array(
				'title' => $dup['post_title'],
				'count' => (int) $dup['cnt'],
				'posts' => $posts,
			);
		}

		return array(
			'duplicates'   => $result,
			'total_groups' => count( $result ),
		);
	}
}

return new Find_Duplicate_Titles();
