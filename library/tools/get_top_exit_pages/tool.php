<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Top Exit Pages Tool
 *
 * Identifies pages most likely to cause visitors to leave the site
 * based on dead-end analysis (no internal links, thin content,
 * no images, wall of text). Scores each page by exit likelihood.
 *
 * @package Agentic\Tools
 */
class Get_Top_Exit_Pages extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_top_exit_pages';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Identify pages most likely to cause visitors to leave your site based on dead-end analysis (no internal links, thin content, no images, wall of text). Scores each page by exit likelihood.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'analytics';
	}

	/**
	 * Get the tool parameters.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Max pages to return (1–30). Defaults to 10.',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => '"post" (default), "page", or "any".',
				),
			),
		);
	}

	/**
	 * Execute the exit pages analysis.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		$limit      = min( max( (int) ( $arguments['limit'] ?? 10 ), 1 ), 30 );
		$post_type  = $arguments['post_type'] ?? 'post';
		$post_types = 'any' === $post_type ? array( 'post', 'page' ) : array( $post_type );

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$site_url        = untrailingslashit( get_bloginfo( 'url' ) );
		$exit_candidates = array();

		foreach ( $posts as $post ) {
			$content    = $post->post_content;
			$plain_text = wp_strip_all_tags( $content );
			$word_count = str_word_count( $plain_text );

			$exit_score = 0;
			$exit_flags = array();

			// Count internal links.
			preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links );
			$internal_links = 0;
			foreach ( $links[1] as $href ) {
				if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
					++$internal_links;
				}
			}

			if ( 0 === $internal_links ) {
				$exit_score  += 40;
				$exit_flags[] = 'No internal links (dead-end page)';
			} elseif ( $internal_links < 2 ) {
				$exit_score  += 15;
				$exit_flags[] = 'Only 1 internal link';
			}

			if ( $word_count < 200 ) {
				$exit_score  += 25;
				$exit_flags[] = "Thin content ({$word_count} words)";
			} elseif ( $word_count < 400 ) {
				$exit_score  += 10;
				$exit_flags[] = "Short content ({$word_count} words)";
			}

			if ( ! preg_match( '/<img[^>]+>/i', $content ) ) {
				$exit_score  += 10;
				$exit_flags[] = 'No images';
			}

			if ( ! preg_match( '/<h[2-6]/i', $content ) && $word_count > 300 ) {
				$exit_score  += 10;
				$exit_flags[] = 'No subheadings (wall of text)';
			}

			if ( empty( $post->post_excerpt ) ) {
				$exit_score  += 5;
				$exit_flags[] = 'No excerpt set';
			}

			if ( $exit_score > 0 ) {
				$exit_candidates[] = array(
					'post_id'        => $post->ID,
					'title'          => $post->post_title,
					'url'            => get_permalink( $post->ID ),
					'post_type'      => $post->post_type,
					'word_count'     => $word_count,
					'internal_links' => $internal_links,
					'exit_score'     => $exit_score,
					'exit_flags'     => $exit_flags,
				);
			}
		}

		usort( $exit_candidates, fn( $a, $b ) => $b['exit_score'] - $a['exit_score'] );
		$exit_candidates = array_slice( $exit_candidates, 0, $limit );

		return array(
			'total_analysed' => count( $posts ),
			'exit_pages'     => $exit_candidates,
			'count'          => count( $exit_candidates ),
			'recommendation' => count( $exit_candidates ) > 0
				? 'Add internal links and CTAs to the top exit pages to keep users engaged. Dead-end pages signal poor engagement to search engines.'
				: 'No major exit page issues detected.',
		);
	}

	/**
	 * Get the tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly' => true,
		);
	}
}

return new Get_Top_Exit_Pages();
