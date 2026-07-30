<?php
/**
 * Tool: get_link_suggestions
 *
 * Suggest related internal pages to link to for a given post.
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
 * Suggest related internal pages to link to based on shared taxonomy and keywords.
 */
class Get_Link_Suggestions extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_link_suggestions';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'For a given post, suggest related internal pages to link to based on shared categories, tags, title keyword overlap, and content keyword matching. Automatically excludes utility pages (checkout, invoice, legal) and pages already linked from the post.';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post ID to get link suggestions for.',
				),
				'limit'   => array(
					'type'        => 'integer',
					'description' => 'Max suggestions to return (1–20). Defaults to 10.',
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
		$limit   = min( max( (int) ( $arguments['limit'] ?? 10 ), 1 ), 20 );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$scored = \Agentic\Tool_Helpers::score_link_candidates( $post );
		usort( $scored, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		$site_url       = untrailingslashit( get_bloginfo( 'url' ) );
		$already_linked = 0;
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $link_matches );
		foreach ( $link_matches[1] as $href ) {
			if ( str_starts_with( $href, '/' ) || str_starts_with( $href, $site_url ) ) {
				++$already_linked;
			}
		}

		return array(
			'post_id'        => $post_id,
			'title'          => $post->post_title,
			'suggestions'    => array_slice( $scored, 0, $limit ),
			'already_linked' => $already_linked,
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

return new Get_Link_Suggestions();
