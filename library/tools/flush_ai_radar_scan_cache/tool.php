<?php
/**
 * Tool: Flush AI Radar Scan Cache
 *
 * Invalidates the cached AI Radar scan when a schema/SEO plugin is activated or
 * deactivated, so the next scan reflects the new plugin state. Extracted from the
 * AI Radar agent's on_plugin_state_changed event callback so a declarative
 * manifest can bind it to the activated_plugin / deactivated_plugin hooks.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flushes the cached AI Radar scan when a relevant schema plugin changes state.
 */
class Flush_AI_Radar_Scan_Cache extends \Agentic\Tool_Base {

	/**
	 * Transient key for the cached AI Radar scan.
	 */
	private const SCAN_TRANSIENT = 'agentic_ai_radar_last_scan';

	/**
	 * Schema/SEO plugin main files whose state affects the scan.
	 *
	 * @var string[]
	 */
	private const SCHEMA_PLUGINS = array(
		'wordpress-seo/wp-seo.php',
		'seo-by-rank-math/rank-math.php',
		'schema-and-structured-data-for-wp/index.php',
		'wp-schema-pro/wp-schema-pro.php',
		'all-in-one-seo-pack/all_in_one_seo_pack.php',
	);

	/**
	 * Tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'flush_ai_radar_scan_cache';
	}

	/**
	 * Tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Invalidate the cached AI Radar scan when a schema or SEO plugin (Yoast, Rank Math, Schema Pro, AIOSEO) is activated or deactivated, so the next scan reflects the change.';
	}

	/**
	 * Tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'ai-visibility';
	}

	/**
	 * Only deletes a transient — very low impact.
	 *
	 * @return string
	 */
	public function get_risk_level(): string {
		return 'none';
	}

	/**
	 * Parameter schema.
	 *
	 * The `plugin` parameter mirrors the activated_plugin / deactivated_plugin
	 * hook argument so a manifest event listener can map it positionally.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'plugin' => array(
					'type'        => 'string',
					'description' => 'Plugin main-file path, e.g. "wordpress-seo/wp-seo.php".',
				),
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		$plugin = (string) ( $arguments['plugin'] ?? '' );

		foreach ( self::SCHEMA_PLUGINS as $schema_plugin ) {
			if ( '' !== $plugin && str_contains( $plugin, dirname( $schema_plugin ) ) ) {
				delete_transient( self::SCAN_TRANSIENT );
				return array(
					'relevant'      => true,
					'cache_flushed' => true,
					'plugin'        => $plugin,
				);
			}
		}

		return array(
			'relevant' => false,
			'plugin'   => $plugin,
		);
	}
}

return new Flush_AI_Radar_Scan_Cache();
