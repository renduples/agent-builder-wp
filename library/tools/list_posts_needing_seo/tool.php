<?php
/**
 * Tool: list_posts_needing_seo
 *
 * List posts filtered by a specific SEO issue.
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
 * List posts filtered by a specific SEO issue for batching fixes.
 */
class List_Posts_Needing_Seo extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'list_posts_needing_seo';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'List posts filtered by a specific SEO issue. Useful for batching fixes.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'seo';
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
				'issue'     => array(
					'type'        => 'string',
					'description' => '"missing_meta_description", "thin_content", "title_too_long", "title_too_short", "no_internal_links", "no_images", or "no_excerpt".',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => '"post" (default) or "page".',
				),
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Max results (1–50). Defaults to 20.',
				),
			),
			'required'   => array( 'issue' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$issue     = $arguments['issue'] ?? '';
		$post_type = $arguments['post_type'] ?? 'post';
		$limit     = min( max( (int) ( $arguments['limit'] ?? 20 ), 1 ), 50 );
		$site_url  = untrailingslashit( get_bloginfo( 'url' ) );

		$all_posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
			)
		);

		$results = array();
		foreach ( $all_posts as $post ) {
			$content   = $post->post_content;
			$plain     = wp_strip_all_tags( $content );
			$title     = $post->post_title;
			$title_len = mb_strlen( $title );
			$meta_desc = \Agentic\Tool_Helpers::get_meta_description( $post->ID, $post->post_excerpt );
			$match     = false;

			switch ( $issue ) {
				case 'missing_meta_description':
					$match = empty( $meta_desc );
					break;
				case 'thin_content':
					$match = str_word_count( $plain ) < 300;
					break;
				case 'title_too_long':
					$match = $title_len > 60;
					break;
				case 'title_too_short':
					$match = $title_len < 30;
					break;
				case 'no_internal_links':
					preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links );
					$has_internal = false;
					foreach ( $links[1] as $href ) {
						if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
							$has_internal = true;
							break;
						}
					}
					$match = ! $has_internal;
					break;
				case 'no_images':
					$match = ! preg_match( '/<img[^>]+>/i', $content );
					break;
				case 'no_excerpt':
					$match = empty( $post->post_excerpt );
					break;
			}

			if ( $match ) {
				$results[] = array(
					'id'          => $post->ID,
					'title'       => $title,
					'title_chars' => $title_len,
					'word_count'  => str_word_count( $plain ),
					'meta_desc'   => $meta_desc,
					'url'         => get_permalink( $post->ID ),
					'date'        => $post->post_date,
				);
				if ( count( $results ) >= $limit ) {
					break;
				}
			}
		}

		return array(
			'issue' => $issue,
			'count' => count( $results ),
			'posts' => $results,
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly' => true,
		);
	}
}

return new List_Posts_Needing_Seo();
