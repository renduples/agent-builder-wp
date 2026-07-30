<?php
/**
 * Tool: Check Robots Txt
 *
 * Read and parse the site's robots.txt for AI bot access.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and parses robots.txt to determine AI bot access status.
 */
class Check_Robots_Txt extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'check_robots_txt';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return "Read and parse the site's robots.txt. Returns the access status for each AI bot (GPTBot, ChatGPT-User, ClaudeBot, Google-Extended, PerplexityBot), whether a blanket block exists, the raw content, and the file source.";
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
	 * Execute the robots.txt check.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Bot access status, score, and raw content.
	 */
	public function execute( array $arguments ): array {
		$ai_bots = \Agentic\Tool_Helpers::get_ai_bots();
		$robots  = \Agentic\Tool_Helpers::read_robots_txt();
		$parsed  = \Agentic\Tool_Helpers::parse_robots_bots( $robots['content'] );
		$score   = 0;
		$bots    = array();

		foreach ( $ai_bots as $bot => $cfg ) {
			$access      = $parsed['bot_access'][ $bot ] ?? 'allowed';
			$is_explicit = isset( $parsed['explicit_bots'][ strtolower( $bot ) ] );
			if ( $parsed['blanket_block'] && ! $is_explicit ) {
				$access = 'blocked_by_wildcard';
			}
			// Distinguish explicit allow from wildcard default.
			if ( 'allowed' === $access && ! $is_explicit ) {
				$access = 'allowed_by_default';
			}
			$bots[ $bot ] = array(
				'label'    => $cfg['label'],
				'access'   => $access,
				'explicit' => $is_explicit,
				'points'   => $cfg['points'],
			);
			if ( str_starts_with( $access, 'allowed' ) && $cfg['points'] > 0 ) {
				$score += $cfg['points'];
			}
		}
		if ( $parsed['blanket_block'] ) {
			$score = 0;
		}

		// Build issues/passing lists.
		$issues  = array();
		$passing = array();
		if ( $parsed['blanket_block'] ) {
			$issues[] = array(
				'message'  => 'Blanket block detected (User-agent: * / Disallow: /) — all AI crawlers are blocked.',
				'severity' => 'critical',
			);
		}

		$has_no_explicit = false;
		foreach ( $bots as $bot => $info ) {
			if ( 'blocked' === $info['access'] || 'blocked_by_wildcard' === $info['access'] ) {
				$severity = $ai_bots[ $bot ]['severity'] ?? 'high';
				$issues[] = array(
					'message'  => "{$info['label']} ({$bot}) is BLOCKED.",
					'severity' => $severity,
				);
			} elseif ( 'allowed_by_default' === $info['access'] ) {
				$passing[]       = "{$bot} ({$info['label']}) — allowed by wildcard (no explicit User-agent entry)";
				$has_no_explicit = true;
			} else {
				$passing[] = "{$bot} ({$info['label']}) — explicitly allowed";
			}
		}

		if ( $has_no_explicit && ! $parsed['blanket_block'] ) {
			$issues[] = array(
				'message'  => 'No explicit User-agent entries for AI bots. They are allowed by the wildcard rule, but adding explicit Allow directives is recommended for clarity and future-proofing.',
				'severity' => 'low',
			);
		}

		return array(
			'score'         => $score,
			'max'           => 30,
			'bots'          => $bots,
			'blanket_block' => $parsed['blanket_block'],
			'raw_content'   => $robots['content'],
			'source'        => $robots['source'],
			'source_path'   => $robots['path'],
			'issues'        => $issues,
			'passing'       => $passing,
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

return new Check_Robots_Txt();
