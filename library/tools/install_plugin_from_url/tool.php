<?php
/**
 * Tool: install_plugin_from_url
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

class Install_Plugin_From_Url extends Tool_Base {
	public function get_name(): string {
		return 'install_plugin_from_url';
	}

	public function get_description(): string {
		return 'Install a WordPress plugin from an official WordPress.org ZIP URL (downloads.wordpress.org). Does not activate the plugin — activate it from Plugins → Installed Plugins.';
	}

	public function get_category(): string {
		return 'plugins';
	}

	public function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Hosts permitted as plugin ZIP sources. Restricting to the official
	 * WordPress.org download host prevents installing arbitrary remote code,
	 * which the WordPress.org plugin guidelines prohibit.
	 */
	private const ALLOWED_HOSTS = array( 'downloads.wordpress.org' );

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'zip_url' => array(
					'type'        => 'string',
					'description' => 'HTTPS URL of the plugin ZIP file to install.',
				),
			),
			'required'   => array( 'zip_url' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$zip_url = trim( $args['zip_url'] ?? '' );

		if ( ! $zip_url ) {
			return array( 'error' => 'zip_url is required.' );
		}
		if ( strpos( $zip_url, 'https://' ) !== 0 ) {
			return array( 'error' => 'zip_url must use HTTPS.' );
		}

		$host = wp_parse_url( $zip_url, PHP_URL_HOST );
		if ( ! is_string( $host ) || ! in_array( strtolower( $host ), self::ALLOWED_HOSTS, true ) ) {
			return array(
				'error' => 'zip_url must be an official WordPress.org download (host: ' . implode( ', ', self::ALLOWED_HOSTS ) . ').',
			);
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Initialise WP filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$skin     = new \Quiet_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $zip_url );

		if ( is_wp_error( $result ) ) {
			return array(
				'installed' => false,
				'error'     => $result->get_error_message(),
			);
		}

		if ( false === $result ) {
			$error_messages = $skin->get_upgrade_messages();
			return array(
				'installed' => false,
				'error'     => ! empty( $error_messages ) ? implode( ' ', $error_messages ) : 'Installation failed for an unknown reason.',
			);
		}

		$installed_plugin = $upgrader->plugin_info();
		$plugin_data      = array();

		if ( $installed_plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $installed_plugin, false, false );
		}

		return array(
			'installed'   => true,
			'plugin_name' => $plugin_data['Name'] ?? basename( $zip_url, '.zip' ),
			'version'     => $plugin_data['Version'] ?? 'unknown',
			'plugin_file' => $installed_plugin ?? '',
			'message'     => 'Installed but not activated. Activate it from Plugins → Installed Plugins.',
		);
	}
}

return new Install_Plugin_From_Url();
