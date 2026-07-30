<?php
/**
 * Cloudflare List Zones Tool
 *
 * Returns the zones (domains) the configured Cloudflare API token can access.
 * Essential for UI zone selection and for agents that need to know which
 * Cloudflare zone to apply security rules against.
 *
 * @package Agentic\Tools
 */

declare(strict_types = 1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List Cloudflare zones visible to the token.
 */
class Cloudflare_List_Zones extends \Agentic\Tool_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'cloudflare_list_zones';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'List the Cloudflare zones (domains) that the saved API token has access to. Use this to discover the correct zone_id before creating WAF rules, rate limits, or other security actions.';
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
			'properties' => array(
				'limit' => array(
					'type'        => 'integer',
					'description' => 'Maximum number of zones to return (1-50). Default 20.',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $arguments ): array {
		$limit = max( 1, min( 50, (int) ( $arguments['limit'] ?? 20 ) ) );

		$result = \Agentic\Cloudflare_Client::api_request(
			'GET',
			'/zones?per_page=' . $limit . '&status=active'
		);

		if ( null === $result ) {
			return $this->tool_error(
				'api_error',
				'Could not retrieve zones from Cloudflare. Verify the API token in Integrations → Cloudflare.'
			);
		}

		if ( isset( $result['success'] ) && false === $result['success'] ) {
			return $this->tool_error(
				'api_error',
				'Cloudflare API error: ' . wp_json_encode( $result['errors'] ?? array() )
			);
		}

		$zones = array();
		foreach ( (array) $result as $zone ) {
			$zones[] = array(
				'id'            => $zone['id'] ?? '',
				'name'          => $zone['name'] ?? '',
				'status'        => $zone['status'] ?? '',
				'account_name'  => $zone['account']['name'] ?? '',
			);
		}

		return $this->success( array(
			'count' => count( $zones ),
			'zones' => $zones,
		) );
	}
}

return new Cloudflare_List_Zones();