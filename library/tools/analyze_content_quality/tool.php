<?php
/**
 * Tool: Analyze Content Quality
 *
 * Deep analysis of content quality for a specific post or site-wide. Scores
 * originality signals, information density, word count, sentence variety,
 * and unique phrase usage.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analyze_Content_Quality extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'analyze_content_quality';
	}

	public function get_description(): string {
		return 'Deep analysis of content quality for a specific post or site-wide. Scores originality signals, information density, word count, sentence variety, and unique phrase usage.';
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
					'description' => 'Analyse a specific post. If omitted, analyses up to 20 recent posts.',
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

		$results = array();

		foreach ( $posts as $post ) {
			$content    = wp_strip_all_tags( $post->post_content );
			$word_count = str_word_count( $content );
			$sentences  = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
			$sent_count = count( $sentences );

			// Information density: unique words / total words.
			$words        = str_word_count( strtolower( $content ), 1 );
			$unique_words = count( array_unique( $words ) );
			$total_words  = count( $words );
			$vocab_ratio  = $total_words > 0 ? round( $unique_words / $total_words, 2 ) : 0;

			// Sentence length variety.
			$sent_lengths = array_map( fn( $s ) => str_word_count( trim( $s ) ), $sentences );
			$avg_sent_len = $sent_count > 0 ? array_sum( $sent_lengths ) / $sent_count : 0;

			// Depth signals.
			$has_headings    = (bool) preg_match( '/<h[23]/', $post->post_content );
			$has_lists       = (bool) preg_match( '/<[ou]l/', $post->post_content );
			$has_images      = (bool) preg_match( '/<img/', $post->post_content ) || has_post_thumbnail( $post->ID );
			$has_data        = (bool) preg_match( '/\b\d+%|\$[\d,]+|\b\d{4,}\b/', $content );
			$has_blockquotes = (bool) preg_match( '/<blockquote/', $post->post_content );

			// Quality score 0-100.
			$score = 0;
			if ( $word_count >= 300 ) {
				$score += 15;
			}
			if ( $word_count >= 800 ) {
				$score += 10;
			}
			if ( $word_count >= 1500 ) {
				$score += 5;
			}
			if ( $vocab_ratio >= 0.4 ) {
				$score += 15;
			}
			if ( $has_headings ) {
				$score += 10;
			}
			if ( $has_lists ) {
				$score += 10;
			}
			if ( $has_images ) {
				$score += 10;
			}
			if ( $has_data ) {
				$score += 10;
			}
			if ( $has_blockquotes ) {
				$score += 5;
			}
			if ( $avg_sent_len >= 10 && $avg_sent_len <= 22 ) {
				$score += 10;
			}

			$issues = array();
			if ( $word_count < 300 ) {
				$issues[] = 'Thin content — under 300 words.';
			}
			if ( $vocab_ratio < 0.3 ) {
				$issues[] = 'Low vocabulary diversity — content may be repetitive.';
			}
			if ( ! $has_headings ) {
				$issues[] = 'No subheadings — add H2/H3 to break up content.';
			}
			if ( ! $has_images ) {
				$issues[] = 'No images — visual content improves engagement.';
			}
			if ( $avg_sent_len > 25 ) {
				$issues[] = 'Avg sentence length is ' . round( $avg_sent_len ) . ' words — aim for under 20.';
			}

			$results[] = array(
				'post_id'          => $post->ID,
				'title'            => $post->post_title,
				'word_count'       => $word_count,
				'sentence_count'   => $sent_count,
				'avg_sentence_len' => round( $avg_sent_len, 1 ),
				'vocabulary_ratio' => $vocab_ratio,
				'has_headings'     => $has_headings,
				'has_lists'        => $has_lists,
				'has_images'       => $has_images,
				'has_data'         => $has_data,
				'quality_score'    => min( 100, $score ),
				'issues'           => $issues,
			);
		}

		$avg_quality = count( $results ) > 0 ? round( array_sum( array_column( $results, 'quality_score' ) ) / count( $results ) ) : 0;

		return array(
			'posts_analysed' => count( $results ),
			'avg_quality'    => $avg_quality,
			'results'        => $results,
		);
	}
}

return new Analyze_Content_Quality();
