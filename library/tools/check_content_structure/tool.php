<?php
/**
 * Tool: Check Content Structure
 *
 * Analyse published content for AI extraction readiness.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyses posts and pages for AI-friendly content structure.
 */
class Check_Content_Structure extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'check_content_structure';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Analyse published posts and pages for AI extraction readiness: FAQ-formatted content, heading hierarchy, content freshness, entity clarity, and thin content pages. Returns a score out of 25.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'ai-visibility';
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
				'post_limit' => array(
					'type'        => 'integer',
					'description' => 'Max published posts to analyse (5–50). Defaults to 20.',
				),
			),
		);
	}

	/**
	 * Execute the content structure check.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Content analysis results with score and issues.
	 */
	public function execute( array $arguments ): array {
		$post_limit = min( max( (int) ( $arguments['post_limit'] ?? 20 ), 5 ), 50 );
		$posts      = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => $post_limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$score   = 0;
		$issues  = array();
		$passing = array();

		if ( empty( $posts ) ) {
			return array(
				'score'   => 0,
				'max'     => 25,
				'note'    => 'No published content found.',
				'issues'  => array(),
				'passing' => array(),
			);
		}

		$most_recent_date  = max( array_map( fn( $p ) => strtotime( $p->post_modified ), $posts ) );
		$days_since_update = (int) floor( ( time() - $most_recent_date ) / DAY_IN_SECONDS );

		if ( $days_since_update <= 30 ) {
			$score    += 6;
			$passing[] = "Content updated recently ({$days_since_update} days ago)";
		} elseif ( $days_since_update <= 90 ) {
			$score    += 4;
			$passing[] = "Content updated {$days_since_update} days ago";
		} else {
			$issues[] = array(
				'message'  => "Most recent update: {$days_since_update} days ago — AI platforms deprioritise stale sites.",
				'severity' => $days_since_update > 180 ? 'important' : 'low',
			);
		}

		$faq_pages   = 0;
		$thin_pages  = array();
		$no_headings = array();

		foreach ( $posts as $post ) {
			$content    = wp_strip_all_tags( $post->post_content );
			$word_count = str_word_count( $content );

			if ( $word_count < 200 && 'page' !== $post->post_type ) {
				$thin_pages[] = $post->post_title;
			}

			if ( preg_match( '/(<h[23][^>]*>.*?\?.*?<\/h[23]>|Q:\s*\w|\bfrequ.*?ask|\bfaq\b)/si', $post->post_content ) ) {
				++$faq_pages;
			}

			if ( 'post' === $post->post_type && $word_count > 400 && ! preg_match( '/<h2[^>]*>/i', $post->post_content ) ) {
				$no_headings[] = $post->post_title;
			}
		}

		if ( $faq_pages > 0 ) {
			$score    += 7;
			$passing[] = "{$faq_pages} page(s) with FAQ-formatted content";
		} else {
			$issues[] = array(
				'message'  => 'No FAQ-formatted content detected.',
				'severity' => 'important',
			);
		}

		if ( empty( $no_headings ) ) {
			$score    += 4;
			$passing[] = 'Posts use H2 headings';
		} else {
			$count = count( $no_headings );
			if ( $count <= 2 ) {
				$score += 2;
			}
			$issues[] = array(
				'message'  => "{$count} post(s) over 400 words with no H2 headings.",
				'severity' => 'important',
			);
		}

		$rendered = \Agentic\Page_Renderer::fetch( home_url( '/' ) );
		if ( $rendered['success'] ) {
			$homepage_text = wp_strip_all_tags( $rendered['html'] ?? '' );
			// Remove scripts/style leftovers and collapse whitespace.
			$homepage_text = preg_replace( '/\s+/', ' ', $homepage_text );
		} else {
			// Fallback to post_content if renderer fails.
			$homepage_id   = get_option( 'page_on_front' );
			$homepage      = $homepage_id ? get_post( (int) $homepage_id ) : null;
			$homepage_text = $homepage ? wp_strip_all_tags( $homepage->post_content ) : '';
		}

		$first_200 = substr( $homepage_text, 0, 800 );
		if ( preg_match( '/\b(we|our|help|offer|specialist|service|solution|platform|tool|software|agency|studio|shop|store|expert|provider|based in|located|manage|plugin|app|automate|build|create|design|develop|consulting|product|brand|company|team|mission)\b/i', $first_200 ) ) {
			$score    += 5;
			$passing[] = 'Homepage clearly states what the site does';
		} else {
			$issues[] = array(
				'message'  => 'Homepage doesn\'t clearly identify what the site is in the first 200 words.',
				'severity' => 'important',
			);
		}

		if ( ! empty( $thin_pages ) ) {
			$count    = count( $thin_pages );
			$score    = max( 0, $score - min( 6, $count * 2 ) );
			$issues[] = array(
				'message'  => "{$count} post(s) with under 200 words.",
				'severity' => 'low',
			);
		}

		return array(
			'score'             => min( 25, $score ),
			'max'               => 25,
			'posts_analysed'    => count( $posts ),
			'faq_pages'         => $faq_pages,
			'thin_pages'        => count( $thin_pages ),
			'thin_page_titles'  => array_slice( $thin_pages, 0, 5 ),
			'days_since_update' => $days_since_update,
			'issues'            => $issues,
			'passing'           => $passing,
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

return new Check_Content_Structure();
