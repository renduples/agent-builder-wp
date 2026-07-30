<?php
/**
 * Tool: suggest_intent_alignment
 *
 * Recommend content changes to better align a page with search intent.
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
 * Recommend specific content changes to align a page with detected search intent.
 */
class Suggest_Intent_Alignment extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'suggest_intent_alignment';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Recommend specific content changes to better align a page with detected search intent. Analyses the gap between detected intent signals and ideal content patterns. Returns actionable title, heading, content, and CTA suggestions.';
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
				'post_id'       => array(
					'type'        => 'integer',
					'description' => 'The WordPress post ID.',
				),
				'target_intent' => array(
					'type'        => 'string',
					'description' => 'Override: "informational", "transactional", "navigational", or "commercial". Defaults to the detected primary intent.',
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
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		// Run intent analysis first.
		$analysis = \Agentic\Tool_Loader::get_instance()->execute( 'analyze_search_intent', array( 'post_id' => $post_id ) );

		if ( isset( $analysis['error'] ) ) {
			return $analysis;
		}

		$target_intent = strtolower( $arguments['target_intent'] ?? $analysis['primary_intent'] );
		$valid_intents = array( 'informational', 'transactional', 'navigational', 'commercial' );

		if ( ! in_array( $target_intent, $valid_intents, true ) ) {
			return array( 'error' => 'Invalid target_intent. Must be one of: ' . implode( ', ', $valid_intents ) );
		}

		$title   = $post->post_title;
		$content = wp_strip_all_tags( $post->post_content );

		$suggestions = array(
			'target_intent'  => $target_intent,
			'current_intent' => $analysis['primary_intent'],
			'confidence'     => $analysis['confidence'],
			'title'          => array(),
			'headings'       => array(),
			'content'        => array(),
			'cta'            => array(),
			'structure'      => array(),
		);

		switch ( $target_intent ) {
			case 'informational':
				if ( ! preg_match( '/how to|what is|guide|tutorial|learn/i', $title ) ) {
					$suggestions['title'][] = 'Consider adding intent-signalling words: "How to", "Guide", "What is", "Learn" to the title.';
				}
				$word_count = str_word_count( $content );
				if ( $word_count < 800 ) {
					$suggestions['content'][] = "Content is {$word_count} words. Informational pages perform best at 1,000–2,500 words. Add depth with examples, steps, or explanations.";
				}
				if ( ! preg_match( '/<h[23]/i', $post->post_content ) ) {
					$suggestions['headings'][] = 'Add H2/H3 subheadings to break content into scannable sections.';
				}
				if ( ! preg_match( '/<ol|<ul/i', $post->post_content ) ) {
					$suggestions['structure'][] = 'Add numbered lists or bullet points for step-by-step or itemised information.';
				}
				$suggestions['cta'][] = 'Informational pages benefit from a soft CTA (e.g., "Learn more", "Read our guide") rather than aggressive sales CTAs.';
				break;

			case 'transactional':
				if ( ! preg_match( '/buy|shop|order|get|start|try/i', $title ) ) {
					$suggestions['title'][] = 'Consider adding action words: "Buy", "Shop", "Get Started", "Order Now" to the title.';
				}
				if ( ! preg_match( '/wp-block-button|<button|class="[^"]*btn/i', $post->post_content ) ) {
					$suggestions['cta'][] = 'Add a clear call-to-action button (Buy Now, Add to Cart, Get Started).';
				}
				if ( ! preg_match( '/\$[\d,.]+|price|pricing|cost/i', $content ) ) {
					$suggestions['content'][] = 'Include pricing information — transactional pages with visible prices have higher conversion rates.';
				}
				$suggestions['structure'][] = 'Ensure product/service benefits are listed above the fold. Use trust signals (reviews, guarantees, security badges).';
				break;

			case 'navigational':
				if ( ! preg_match( '/contact|about|support|help|login|account/i', $title ) ) {
					$suggestions['title'][] = 'Use clear brand + destination naming: "[Brand] Contact", "[Brand] Login", "About [Brand]".';
				}
				$suggestions['content'][]   = 'Navigational pages should load fast and present key information immediately. Reduce unnecessary content.';
				$suggestions['structure'][] = 'Add breadcrumbs and clear internal links to help users find what they need.';
				break;

			case 'commercial':
				if ( ! preg_match( '/best|review|comparison|top|vs/i', $title ) ) {
					$suggestions['title'][] = 'Consider adding comparison signals: "Best", "Review", "Comparison", "Top [N]", "[X] vs [Y]".';
				}
				if ( ! preg_match( '/<table|comparison|versus|pros|cons/i', $post->post_content ) ) {
					$suggestions['structure'][] = 'Add comparison tables, pros/cons lists, or side-by-side feature breakdowns.';
				}
				$suggestions['content'][] = 'Include specific criteria for evaluation, expert opinions, or data-backed recommendations.';
				$suggestions['cta'][]     = 'Use CTAs that match investigation intent: "See Pricing", "Compare Plans", "Read Full Review".';
				break;
		}

		if ( $analysis['primary_intent'] !== $target_intent ) {
			$suggestions['content'][] = "Page currently signals \"{$analysis['primary_intent']}\" intent (confidence: {$analysis['confidence']}%). Significant rewording may be needed to shift to \"{$target_intent}\".";
		}

		if ( ! empty( $analysis['search_console']['top_queries'] ) ) {
			$top_query = $analysis['search_console']['top_queries'][0]['query'];

			if ( ! str_contains( strtolower( $title ), strtolower( $top_query ) ) ) {
				$suggestions['title'][] = "Your top search query is \"{$top_query}\" — consider including it in the title for better CTR.";
			}
		}

		foreach ( $suggestions as $key => $val ) {
			if ( is_array( $val ) && empty( $val ) ) {
				unset( $suggestions[ $key ] );
			}
		}

		return array(
			'post_id'     => $post_id,
			'title'       => $title,
			'url'         => get_permalink( $post_id ),
			'analysis'    => $analysis,
			'suggestions' => $suggestions,
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

return new Suggest_Intent_Alignment();
