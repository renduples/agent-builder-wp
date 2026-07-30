<?php
/**
 * Cloudflare Verify API Token Tool
 *
 * Validates that a Cloudflare API token is present and has basic permissions.
 * Used by the Security Assistant and the Pro UI "Test Connection" button.
 *
 * This is intentionally a read-only, low-risk tool.
 *
 * @package Agentic\Tools
 */

declare(strict_types = 1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verify Cloudflare API Token Tool.
 */
class Cloudflare_Verify_API_Token extends \Agentic\Tool_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'cloudflare_verify_api_token';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Verify that a Cloudflare API token is configured and has basic access (lists zones the token can see). Safe read-only check used before performing security actions.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_category(): string {
		return 'security';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => (object) array(), // No parameters needed — reads from saved settings
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $arguments ): array {
		$token = \Agentic\Cloudflare_Client::get_api_token();

		if ( ! $token ) {
			return $this->tool_error(
				'not_configured',
				'No Cloudflare API token has been configured yet. Go to Integrations → Cloudflare in the Pro settings to add one.'
			);
		}

		// Try to list zones — this is the most common minimal permission test
		$result = \Agentic\Cloudflare_Client::api_request( 'GET', '/zones?per_page=5' );

		if ( null === $result ) {
			return $this->tool_error(
				'invalid_token',
				'Could not connect to Cloudflare with the saved token. The token may be invalid, revoked, or lack the required permissions (Zone:Read recommended).'
			);
		}

		// If we got an error array back from the client
		if ( isset( $result['success'] ) && false === $result['success'] ) {
			return $this->tool_error(
				'api_error',
				'Cloudflare API returned an error: ' . wp_json_encode( $result['errors'] ?? array() )
			);
		}

		$zone_count = is_array( $result ) ? count( $result ) : 0;
		$zones      = array();

		if ( $zone_count > 0 ) {
			foreach ( array_slice( $result, 0, 3 ) as $zone ) {
				$zones[] = array(
					'id'   => $zone['id'] ?? '',
					'name' => $zone['name'] ?? '',
				);
			}
		}

		return $this->success( array(
			'valid'           => true,
			'zones_visible'   => $zone_count,
			'sample_zones'    => $zones,
			'message'         => $zone_count > 0
				? "Token is valid. Can see {$zone_count} zone(s)."
				: 'Token is valid but sees no zones (may still work for specific zone-scoped tokens).',
		) );
	}
}

// Return an instance so Tool_Loader can register it
return new Cloudflare_Verify_API_Token();