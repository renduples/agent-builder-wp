<?php
/**
 * Tool: Check Direct Answers
 *
 * Check whether pages front-load their answer in the first paragraph.
 * Pages that answer queries directly are more likely to appear in featured
 * snippets and AI citations.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Check_Direct_Answers extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'check_direct_answers';
	}

	public function get_description(): string {
		return 'Check whether pages front-load their answer in the first paragraph. Pages that answer queries directly are more likely to appear in featured snippets and AI citations.';
	}

	public function get_category(): string {
		return 'site-audit';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Specific post ID to check. Omit for site-wide check of 20 recent posts.',
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
		$post_id = $args['post_id'] ?? null;

		if ( $post_id ) {
			$posts = array( get_post( (int) $post_id ) );
			if ( ! $posts[0] ) {
				return array( 'error' => 'Post not found.' );
			}
		} else {
			$posts = get_posts(
				array(
					'post_type'      => array( 'post', 'page' ),
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
		}

		$results     = array();
		$with_answer = 0;

		foreach ( $posts as $post ) {
			$content = $post->post_content;

			// Extract text after first H1/title, before second heading.
			$parts         = preg_split( '/<h[1-6][^>]*>/i', $content, 3 );
			$first_section = isset( $parts[1] ) ? $parts[1] : ( $parts[0] ?? '' );
			$first_section = wp_strip_all_tags( $first_section );
			$first_section = trim( $first_section );

			// First 170 characters.
			$snippet     = mb_substr( $first_section, 0, 170 );
			$snippet_len = mb_strlen( $snippet );

			// Check for direct answer signals.
			$has_definition  = (bool) preg_match( '/\bis\b|\bare\b|\bmeans\b|\brefers to\b/i', $snippet );
			$has_list_signal = (bool) preg_match( '/\bsteps?\b|\bways?\b|\btips?\b|\bhere\s+(are|is)\b/i', $snippet );
			$has_number      = (bool) preg_match( '/\b\d+\b/', $snippet );
			$ends_sentence   = (bool) preg_match( '/[.!?]\s*$/', $snippet );

			$is_direct = ( $has_definition || $has_list_signal || $has_number ) && $ends_sentence && $snippet_len >= 50;

			if ( $is_direct ) {
				++$with_answer;
			}

			$results[] = array(
				'post_id'           => $post->ID,
				'title'             => $post->post_title,
				'first_170_chars'   => $snippet,
				'has_direct_answer' => $is_direct,
				'signals'           => array(
					'definition_pattern' => $has_definition,
					'list_signal'        => $has_list_signal,
					'contains_number'    => $has_number,
					'ends_sentence'      => $ends_sentence,
				),
				'suggestion'        => $is_direct ? null : 'Rewrite the opening paragraph to directly answer the page\'s main question within the first 2 sentences.',
			);
		}

		$total = count( $results );
		$pct   = $total > 0 ? round( ( $with_answer / $total ) * 100 ) : 0;

		return array(
			'posts_checked'          => $total,
			'with_direct_answer'     => $with_answer,
			'pct_with_direct_answer' => $pct,
			'results'                => $results,
		);
	}
}
return new Check_Direct_Answers();
