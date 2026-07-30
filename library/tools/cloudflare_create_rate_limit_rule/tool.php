<?php
/**
 * Cloudflare Create Rate Limit Rule Tool
 *
 * Creates or updates a rate limiting rule on a Cloudflare zone.
 * Targets common WordPress attack surfaces (login, xmlrpc, admin, REST, author archives).
 *
 * This is a mutating action — risk level should be respected by the agent system.
 *
 * @package Agentic\Tools
 */

declare(strict_types = 1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create Cloudflare Rate Limit Rule.
 */
class Cloudflare_Create_Rate_Limit_Rule extends \Agentic\Tool_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'cloudflare_create_rate_limit_rule';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create a rate limiting rule in Cloudflare for a specific path or set of paths (e.g. wp-login.php, xmlrpc.php, /wp-admin/). Highly effective against brute force and enumeration attacks. Requires a valid zone_id and API token with Rules edit permissions.';
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
			'required'   => array( 'zone_id', 'path' ),
			'properties' => array(
				'zone_id' => array(
					'type'        => 'string',
					'description' => 'The Cloudflare zone ID (get via cloudflare_list_zones)',
				),
				'path'    => array(
					'type'        => 'string',
					'description' => 'Path pattern to rate limit, e.g. "/wp-login.php" or "/xmlrpc.php" or "*/wp-admin/*"',
				),
				'threshold' => array(
					'type'        => 'integer',
					'description' => 'Requests per period before action triggers. Default 5.',
				),
				'period' => array(
					'type'        => 'integer',
					'description' => 'Time period in seconds. Default 60.',
				),
				'action' => array(
					'type'        => 'string',
					'enum'        => array( 'challenge', 'block', 'log', 'js_challenge' ),
					'description' => 'Action to take when limit is exceeded. Default "challenge".',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Human readable description for the rule.',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $arguments ): array {
		$zone_id = sanitize_text_field( $arguments['zone_id'] ?? '' );
		$path    = sanitize_text_field( $arguments['path'] ?? '' );

		if ( empty( $zone_id ) || empty( $path ) ) {
			return $this->tool_error( 'missing_params', 'zone_id and path are required.' );
		}

		$threshold   = max( 1, (int) ( $arguments['threshold'] ?? 5 ) );
		$period      = max( 10, (int) ( $arguments['period'] ?? 60 ) );
		$action      = in_array( $arguments['action'] ?? 'challenge', array( 'challenge', 'block', 'log', 'js_challenge' ), true )
			? $arguments['action'] : 'challenge';
		$description = sanitize_text_field( $arguments['description'] ?? "Agentic WP rate limit: {$path}" );

		// Modern approach: Create a Rate Limiting Rule via Rulesets API (simplified for common use)
		// Note: Full ruleset creation is complex. This creates a phase entry for http_ratelimit.
		$rule = array(
			'action'      => $action,
			'expression'  => "(http.request.uri.path eq \"{$path}\")",
			'description' => $description,
			'ratelimit'   => array(
				'characteristics' => array( 'ip.src' ),
				'period'          => $period,
				'requests_per_period' => $threshold,
				'mode'            => 'block', // or 'challenge' etc. — Cloudflare handles mapping
			),
		);

		// For many zones the simpler legacy rate limit endpoint still works well
		$body = array(
			'description' => $description,
			'path'        => $path,
			'threshold'   => $threshold,
			'period'      => $period,
			'action'      => array( 'mode' => $action ),
		);

		// Try the modern ruleset approach first, fall back gracefully
		$result = \Agentic\Cloudflare_Client::api_request(
			'POST',
			"/zones/{$zone_id}/rulesets/phases/http_ratelimit/entrypoint",
			array( 'rules' => array( $rule ) )
		);

		// If the modern endpoint fails or is not available, try legacy rate limits
		if ( ! $result || ( isset( $result['success'] ) && false === $result['success'] ) ) {
			$result = \Agentic\Cloudflare_Client::api_request(
				'POST',
				"/zones/{$zone_id}/rate_limits",
				$body
			);
		}

		if ( ! $result ) {
			return $this->tool_error( 'api_error', 'Failed to create rate limit rule. Check token permissions and zone_id.' );
		}

		return $this->success( array(
			'created'     => true,
			'zone_id'     => $zone_id,
			'path'        => $path,
			'threshold'   => $threshold,
			'period'      => $period,
			'action'      => $action,
			'cloudflare_response' => $result,
		) );
	}
}

return new Cloudflare_Create_Rate_Limit_Rule();