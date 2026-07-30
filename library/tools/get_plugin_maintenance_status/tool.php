<?php
/**
 * Tool: get_plugin_maintenance_status
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;

class Get_Plugin_Maintenance_Status extends Tool_Base {
	public function get_name(): string {
		return 'get_plugin_maintenance_status';
	}

	public function get_description(): string {
		return 'Get the maintenance and support status for a WordPress.org plugin. Returns last updated date, tested-up-to version, active installs, support stats, and whether it appears abandoned.';
	}

	public function get_category(): string {
		return 'plugins';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'plugin_slug' => array(
					'type'        => 'string',
					'description' => 'The WordPress.org plugin slug, e.g. "woocommerce".',
				),
			),
			'required'   => array( 'plugin_slug' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$slug = sanitize_key( $args['plugin_slug'] ?? '' );
		if ( ! $slug ) {
			return array( 'error' => 'plugin_slug is required.' );
		}

		$url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information'
			. '&request[slug]=' . rawurlencode( $slug )
			. '&request[fields][last_updated]=1'
			. '&request[fields][support_threads]=1'
			. '&request[fields][support_threads_resolved]=1'
			. '&request[fields][active_installs]=1'
			. '&request[fields][rating]=1'
			. '&request[fields][tested]=1'
			. '&request[fields][requires]=1';

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'API request failed: ' . $response->get_error_message() );
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array( 'error' => 'WP.org API returned non-200 response.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data ) || isset( $data['error'] ) ) {
			return array( 'error' => "Plugin '{$slug}' not found on WordPress.org." );
		}

		$last_updated_ts = isset( $data['last_updated'] ) ? strtotime( $data['last_updated'] ) : 0;
		$months_ago      = $last_updated_ts ? (int) floor( ( time() - $last_updated_ts ) / ( 30 * DAY_IN_SECONDS ) ) : 999;

		return array(
			'name'                     => $data['name'] ?? $slug,
			'slug'                     => $slug,
			'version'                  => $data['version'] ?? 'unknown',
			'last_updated'             => $data['last_updated'] ?? 'unknown',
			'tested_up_to'             => $data['tested'] ?? 'unknown',
			'requires'                 => $data['requires'] ?? 'unknown',
			'active_installs'          => $data['active_installs'] ?? 0,
			'support_threads'          => $data['support_threads'] ?? 0,
			'support_threads_resolved' => $data['support_threads_resolved'] ?? 0,
			'rating'                   => $data['rating'] ?? 0,
			'is_abandoned'             => $months_ago >= 12,
			'months_since_update'      => $months_ago,
		);
	}
}

return new Get_Plugin_Maintenance_Status();
