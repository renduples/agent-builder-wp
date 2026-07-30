<?php
/**
 * Tool: Run AI Radar Scan
 *
 * Run a full AI visibility scan across all four categories.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs a comprehensive AI Radar scan and returns a scored report.
 */
class Run_AI_Radar_Scan extends \Agentic\Tool_Base {

	/**
	 * Transient key for caching the last scan result.
	 */
	private const SCAN_TRANSIENT = 'agentic_ai_radar_last_scan';

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'run_ai_radar_scan';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Run a full AI visibility scan across all four categories: AI crawler access (30 pts), schema markup (25 pts), content structure (25 pts), and technical readiness (20 pts). Returns a score 0–100 with a letter grade and prioritised action items. The result is cached.';
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
			'properties' => new \stdClass(),
		);
	}

	/**
	 * Execute the AI Radar scan.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Scan results with score, grade, categories, and actions.
	 */
	public function execute( array $arguments ): array {
		$loader  = \Agentic\Tool_Loader::get_instance();
		$robots  = $loader->execute( 'check_robots_txt', array() );
		$schema  = $loader->execute( 'check_schema_markup', array() );
		$content = $loader->execute( 'check_content_structure', array( 'post_limit' => 20 ) );
		$tech    = $loader->execute( 'check_technical_readiness', array() );

		$total_score = ( $robots['score'] ?? 0 ) + ( $schema['score'] ?? 0 ) + ( $content['score'] ?? 0 ) + ( $tech['score'] ?? 0 );

		$critical  = array();
		$important = array();
		$passing   = array();

		$ai_bots = \Agentic\Tool_Helpers::get_ai_bots();
		foreach ( $robots['bots'] ?? array() as $bot => $info ) {
			if ( 'allowed' !== $info['access'] ) {
				$label = $ai_bots[ $bot ]['label'] ?? $bot;
				if ( 'critical' === ( $ai_bots[ $bot ]['severity'] ?? 'high' ) ) {
					$critical[] = "{$label} ({$bot}) is BLOCKED — cannot read your site.";
				} else {
					$important[] = "{$label} ({$bot}) is BLOCKED.";
				}
			} else {
				$passing[] = "{$bot}: allowed";
			}
		}
		if ( ! empty( $robots['blanket_block'] ) ) {
			$critical[] = 'Blanket block (User-agent: * Disallow: /) detected — ALL crawlers including AI bots are blocked.';
		}

		foreach ( $schema['issues'] ?? array() as $issue ) {
			if ( 'critical' === ( $issue['severity'] ?? 'important' ) ) {
				$critical[] = $issue['message'];
			} else {
				$important[] = $issue['message'];
			}
		}
		foreach ( $schema['passing'] ?? array() as $p ) {
			$passing[] = $p;
		}
		foreach ( $content['issues'] ?? array() as $issue ) {
			if ( 'critical' === ( $issue['severity'] ?? 'important' ) ) {
				$critical[] = $issue['message'];
			} else {
				$important[] = $issue['message'];
			}
		}
		foreach ( $content['passing'] ?? array() as $p ) {
			$passing[] = $p;
		}
		foreach ( $tech['issues'] ?? array() as $issue ) {
			if ( 'critical' === ( $issue['severity'] ?? 'medium' ) ) {
				$critical[] = $issue['message'];
			} else {
				$important[] = $issue['message'];
			}
		}
		foreach ( $tech['passing'] ?? array() as $p ) {
			$passing[] = $p;
		}

		$result = array(
			'score'      => $total_score,
			'grade'      => \Agentic\Tool_Helpers::score_to_grade( $total_score ),
			'scanned_at' => wp_date( 'Y-m-d H:i:s' ),
			'categories' => array(
				'ai_crawler_access'   => array(
					'score' => $robots['score'] ?? 0,
					'max'   => 30,
					'label' => 'AI Crawler Access',
				),
				'schema_markup'       => array(
					'score' => $schema['score'] ?? 0,
					'max'   => 25,
					'label' => 'Schema Markup',
				),
				'content_structure'   => array(
					'score' => $content['score'] ?? 0,
					'max'   => 25,
					'label' => 'Content Structure',
				),
				'technical_readiness' => array(
					'score' => $tech['score'] ?? 0,
					'max'   => 20,
					'label' => 'Technical Readiness',
				),
			),
			'actions'    => array(
				'critical'  => $critical,
				'important' => $important,
				'passing'   => $passing,
			),
			'site_url'   => home_url(),
		);

		set_transient( self::SCAN_TRANSIENT, $result, WEEK_IN_SECONDS * 4 );

		return $result;
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

return new Run_AI_Radar_Scan();
