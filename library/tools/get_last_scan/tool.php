<?php
/**
 * Tool: Get Last Scan
 *
 * Retrieve the most recent AI Radar scan results from cache.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns cached AI Radar scan results if available.
 */
class Get_Last_Scan extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_last_scan';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Retrieve the most recent AI Radar scan results from cache. Returns null if no scan has been run yet.';
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
	 * Execute the last scan retrieval.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Cached scan results or not-found message.
	 */
	public function execute( array $arguments ): array {
		$scan = get_transient( 'agentic_ai_radar_last_scan' );
		if ( false === $scan ) {
			return array(
				'has_scan' => false,
				'message'  => 'No previous scan found. Run run_ai_radar_scan to generate your first AI visibility score.',
			);
		}
		return array(
			'has_scan'   => true,
			'scan'       => $scan,
			'scanned_at' => $scan['scanned_at'] ?? null,
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

return new Get_Last_Scan();
