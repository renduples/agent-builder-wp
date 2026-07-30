<?php
/**
 * Tool: get_abandoned_plugins
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

class Get_Abandoned_Plugins extends Tool_Base {
	public function get_name(): string {
		return 'get_abandoned_plugins';
	}

	public function get_description(): string {
		return 'Check all installed plugins against the WordPress.org API to identify those that have not been updated in a long time. Results are cached per-plugin for 12 hours to avoid rate-limiting.';
	}

	public function get_category(): string {
		return 'plugins';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'months_threshold' => array(
					'type'        => 'integer',
					'description' => 'Number of months without an update before a plugin is considered abandoned. Defaults to 12.',
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
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$months_threshold = (int) ( $args['months_threshold'] ?? 12 );
		$months_threshold = max( 1, $months_threshold );

		$all_plugins = get_plugins();
		$abandoned   = array();
		$checked     = 0;

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			// Extract slug from first path segment.
			$slug = explode( '/', $plugin_file )[0];
			if ( $slug === $plugin_file ) {
				// Single-file plugin — derive slug from filename.
				$slug = basename( $plugin_file, '.php' );
			}

			$transient_key = 'agentic_wporg_info_' . md5( $slug );
			$info          = get_transient( $transient_key );

			if ( false === $info ) {
				$response = wp_remote_get(
					'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . rawurlencode( $slug ) . '&request[fields][last_updated]=1',
					array( 'timeout' => 10 )
				);

				if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
					continue;
				}

				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( empty( $body ) || isset( $body['error'] ) ) {
					continue;
				}

				$info = $body;
				set_transient( $transient_key, $info, 12 * HOUR_IN_SECONDS );
			}

			++$checked;

			if ( empty( $info['last_updated'] ) ) {
				continue;
			}

			$last_updated        = strtotime( $info['last_updated'] );
			$months_since_update = (int) floor( ( time() - $last_updated ) / ( 30 * DAY_IN_SECONDS ) );

			if ( $months_since_update >= $months_threshold ) {
				$abandoned[] = array(
					'name'                => $plugin_data['Name'],
					'slug'                => $slug,
					'installed_version'   => $plugin_data['Version'],
					'last_updated'        => gmdate( 'Y-m-d', $last_updated ),
					'months_since_update' => $months_since_update,
				);
			}
		}

		usort( $abandoned, fn( $a, $b ) => $b['months_since_update'] <=> $a['months_since_update'] );

		return array(
			'abandoned'        => $abandoned,
			'total_abandoned'  => count( $abandoned ),
			'total_checked'    => $checked,
			'months_threshold' => $months_threshold,
		);
	}
}

return new Get_Abandoned_Plugins();
