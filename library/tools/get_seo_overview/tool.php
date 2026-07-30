<?php
/**
 * Tool: get_seo_overview
 *
 * Scan published posts for common SEO issues.
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
 * Scan all published posts for common SEO issues and return counts with examples.
 */
class Get_Seo_Overview extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_seo_overview';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Scan all published posts for common SEO issues: missing meta descriptions, titles that are too short (<30 chars) or too long (>60 chars), thin content (<300 words), no internal links, and no images. Returns counts and examples for each issue type.';
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
				'post_type' => array(
					'type'        => 'string',
					'description' => '"post" (default), "page", or "any".',
				),
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Max posts to scan (1–200). Defaults to 100.',
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
		$post_type = $arguments['post_type'] ?? 'post';
		$limit     = min( max( (int) ( $arguments['limit'] ?? 100 ), 1 ), 200 );
		$site_url  = untrailingslashit( get_bloginfo( 'url' ) );

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$issues = array(
			'missing_meta_description' => array(),
			'title_too_short'          => array(),
			'title_too_long'           => array(),
			'thin_content'             => array(),
			'no_internal_links'        => array(),
			'no_images'                => array(),
		);

		foreach ( $posts as $post ) {
			$title      = $post->post_title;
			$title_len  = mb_strlen( $title );
			$content    = $post->post_content;
			$word_count = str_word_count( wp_strip_all_tags( $content ) );
			$meta_desc  = \Agentic\Tool_Helpers::get_meta_description( $post->ID, $post->post_excerpt );
			$stub       = array(
				'id'    => $post->ID,
				'title' => $title,
			);

			if ( empty( $meta_desc ) ) {
				$issues['missing_meta_description'][] = $stub;
			}
			if ( $title_len < 30 ) {
				$issues['title_too_short'][] = $stub;
			}
			if ( $title_len > 60 ) {
				$issues['title_too_long'][] = $stub;
			}
			if ( $word_count < 300 ) {
				$issues['thin_content'][] = $stub;
			}

			preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links );
			$has_internal = false;
			foreach ( $links[1] as $href ) {
				if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
					$has_internal = true;
					break;
				}
			}
			if ( ! $has_internal ) {
				$issues['no_internal_links'][] = $stub;
			}
			if ( ! preg_match( '/<img[^>]+>/i', $content ) ) {
				$issues['no_images'][] = $stub;
			}
		}

		$summary = array();
		foreach ( $issues as $key => $affected ) {
			$summary[ $key ] = array(
				'count'    => count( $affected ),
				'examples' => array_slice( $affected, 0, 5 ),
			);
		}

		return array(
			'total_posts_scanned' => count( $posts ),
			'issues'              => $summary,
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

return new Get_Seo_Overview();
