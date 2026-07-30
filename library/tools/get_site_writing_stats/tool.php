<?php
/**
 * Tool: get_site_writing_stats
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

class Get_Site_Writing_Stats extends Tool_Base {
	public function get_name(): string {
		return 'get_site_writing_stats';
	}

	public function get_description(): string {
		return 'Get an aggregate overview of writing activity on the site: published post counts by type, top authors by post count, posts published per month (last 6 months), and average word count.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
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

		// Posts by type.
		$public_types  = get_post_types( array( 'public' => true ), 'names' );
		$posts_by_type = array();
		foreach ( $public_types as $type ) {
			$counts = wp_count_posts( $type );
			if ( $counts && isset( $counts->publish ) && (int) $counts->publish > 0 ) {
				$posts_by_type[ $type ] = (int) $counts->publish;
			}
		}

		// Top 5 authors by post count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$top_authors_rows = $wpdb->get_results(
			"SELECT post_author, COUNT(*) AS post_count
			FROM {$wpdb->posts}
			WHERE post_type = 'post' AND post_status = 'publish'
			GROUP BY post_author
			ORDER BY post_count DESC
			LIMIT 5",
			ARRAY_A
		);

		$top_authors = array();
		foreach ( $top_authors_rows as $row ) {
			$user          = get_userdata( (int) $row['post_author'] );
			$top_authors[] = array(
				'display_name' => $user ? $user->display_name : 'Unknown',
				'post_count'   => (int) $row['post_count'],
			);
		}

		// Posts per month, last 6 months.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$monthly_rows = $wpdb->get_results(
			"SELECT DATE_FORMAT(post_date, '%Y-%m') AS month, COUNT(*) AS cnt
			FROM {$wpdb->posts}
			WHERE post_type = 'post' AND post_status = 'publish'
			AND post_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
			GROUP BY month
			ORDER BY month DESC",
			ARRAY_A
		);

		$posts_per_month = array_map(
			fn( $r ) => array(
				'month' => $r['month'],
				'count' => (int) $r['cnt'],
			),
			$monthly_rows
		);

		// Average word count from sample of 50 posts.
		$sample_posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'rand',
			)
		);

		$total_words = 0;
		foreach ( $sample_posts as $p ) {
			$total_words += str_word_count( wp_strip_all_tags( $p->post_content ) );
		}
		$avg_word_count = count( $sample_posts ) > 0
			? (int) round( $total_words / count( $sample_posts ) )
			: 0;

		return array(
			'posts_by_type'   => $posts_by_type,
			'top_authors'     => $top_authors,
			'posts_per_month' => $posts_per_month,
			'avg_word_count'  => $avg_word_count,
			'avg_based_on'    => count( $sample_posts ) . ' sample posts',
		);
	}
}

return new Get_Site_Writing_Stats();
