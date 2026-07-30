<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check Plugin Updates Tool
 *
 * Checks which active plugins have available updates.
 * Outdated plugins are a major security risk.
 *
 * @package Agentic\Tools
 */
class Check_Plugin_Updates extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'check_plugin_updates';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Check which active plugins have available updates. Outdated plugins are a major security risk.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'security';
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
				'outdated_only' => array(
					'type'        => 'boolean',
					'description' => 'If true, return only plugins with available updates. Defaults to false.',
				),
			),
		);
	}

	/**
	 * Execute the plugin update check.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$outdated_only  = (bool) ( $arguments['outdated_only'] ?? false );
		$update_plugins = get_site_transient( 'update_plugins' );
		$active_plugins = get_option( 'active_plugins', array() );
		$results        = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( ! file_exists( $plugin_path ) ) {
				continue;
			}

			$data        = get_plugin_data( $plugin_path, false, false );
			$has_update  = isset( $update_plugins->response[ $plugin_file ] );
			$new_version = $has_update ? ( $update_plugins->response[ $plugin_file ]->new_version ?? null ) : null;

			if ( $outdated_only && ! $has_update ) {
				continue;
			}

			$results[] = array(
				'file'        => $plugin_file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'new_version' => $new_version,
				'has_update'  => $has_update,
			);
		}

		return array(
			'total_active'   => count( $active_plugins ),
			'outdated_count' => count( array_filter( $results, fn( $p ) => $p['has_update'] ) ),
			'plugins'        => $results,
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

return new Check_Plugin_Updates();
