<?php
/**
 * Tool: get_post_performance
 *
 * Get performance metrics for a post.
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
 * Get performance metrics for a post: word count, comment count, category/tag counts,
 * age, and analytics data from Site Kit or Jetpack if available.
 */
class Get_Post_Performance extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_post_performance';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get performance metrics for a post: word count, comment count, category/tag counts, age, and analytics data from Site Kit or Jetpack if available.';
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
					'description' => 'The post ID to get performance data for.',
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
		if ( empty( $arguments['post_id'] ) ) {
			return array( 'error' => 'post_id is required.' );
		}
		$post_id = (int) $arguments['post_id'];
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );
		$categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
		$comments   = (int) $post->comment_count;

		$data = array(
			'post_id'       => $post_id,
			'title'         => $post->post_title,
			'status'        => $post->post_status,
			'type'          => $post->post_type,
			'published'     => $post->post_date,
			'modified'      => $post->post_modified,
			'word_count'    => $word_count,
			'comment_count' => $comments,
			'categories'    => $categories,
			'tags'          => $tags,
			'has_thumbnail' => has_post_thumbnail( $post_id ),
		);

		$published_ts = strtotime( $post->post_date_gmt );
		if ( $published_ts ) {
			$data['age_days'] = (int) floor( ( time() - $published_ts ) / DAY_IN_SECONDS );
		}

		if ( class_exists( 'Automattic\Jetpack\Stats\WPCOM_Stats' ) ) {
			$stats      = new \Automattic\Jetpack\Stats\WPCOM_Stats();
			$post_views = $stats->get_post_views( $post_id );
			if ( ! is_wp_error( $post_views ) && ! empty( $post_views ) ) {
				$data['jetpack_views'] = $post_views;
			}
		} elseif ( function_exists( 'stats_get_csv' ) ) {
			$csv = stats_get_csv(
				'postviews',
				array(
					'post_id' => $post_id,
					'days'    => 30,
					'limit'   => 1,
				)
			);
			if ( ! empty( $csv[0]['views'] ) ) {
				$data['jetpack_views_30d'] = (int) $csv[0]['views'];
			}
		}

		if ( function_exists( 'google_site_kit' ) ) {
			$analytics        = get_option( 'googlesitekit_analytics_settings' );
			$data['site_kit'] = array(
				'connected' => ! empty( $analytics ),
				'note'      => 'Site Kit analytics data is available in the WordPress admin dashboard.',
			);
		}

		$data['analytics_available'] = isset( $data['jetpack_views'] ) || isset( $data['jetpack_views_30d'] ) || isset( $data['site_kit'] );
		return $data;
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

return new Get_Post_Performance();
