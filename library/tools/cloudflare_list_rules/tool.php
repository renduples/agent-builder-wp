<?php
/**
 * Cloudflare List Rules Tool
 *
 * Lists existing rate limiting and WAF rules on a zone.
 * Extremely useful for agents to inspect current security posture before making changes.
 *
 * @package Agentic\Tools
 */

declare(strict_types = 1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cloudflare_List_Rules extends \Agentic\Tool_Base {

	public function get_name(): string { return 'cloudflare_list_rules'; }

	public function get_description(): string {
		return 'List all existing rate limiting and security rules currently active on a Cloudflare zone. Essential first step for the Security Assistant before recommending or applying new protections. Returns rule IDs, expressions, and descriptions so you can avoid duplicates.';
	}

	public function get_category(): string { return 'security'; }

	public function get_parameters(): array {
		return array(
			'type' => 'object',
			'required' => array('zone_id'),
			'properties' => array(
				'zone_id' => array('type' => 'string', 'description' => 'Cloudflare zone ID'),
				'type'    => array('type' => 'string', 'enum' => array('all', 'ratelimit', 'waf'), 'description' => 'Filter by rule type'),
			),
		);
	}

	public function execute( array $arguments ): array {
		$zone_id = sanitize_text_field( $arguments['zone_id'] ?? '' );
		$type    = $arguments['type'] ?? 'all';

		if ( empty( $zone_id ) ) {
			return $this->tool_error( 'missing_zone', 'zone_id is required.' );
		}

		$rules = array();

		// Try modern rulesets first
		$ratelimit = \Agentic\Cloudflare_Client::api_request( 'GET', "/zones/{$zone_id}/rulesets/phases/http_ratelimit/entrypoint" );
		if ( $ratelimit && ! isset( $ratelimit['success'] ) ) {
			foreach ( (array) $ratelimit as $rule ) {
				$rules[] = array(
					'id'          => $rule['id'] ?? '',
					'description' => $rule['description'] ?? '',
					'expression'  => $rule['expression'] ?? '',
					'type'        => 'ratelimit',
				);
			}
		}

		// Legacy rate limits
		$legacy = \Agentic\Cloudflare_Client::api_request( 'GET', "/zones/{$zone_id}/rate_limits" );
		if ( is_array( $legacy ) ) {
			foreach ( $legacy as $r ) {
				$rules[] = array(
					'id'          => $r['id'] ?? '',
					'description' => $r['description'] ?? '',
					'path'        => $r['path'] ?? '',
					'type'        => 'ratelimit_legacy',
				);
			}
		}

		return $this->success( array(
			'zone_id' => $zone_id,
			'count'   => count( $rules ),
			'rules'   => $rules,
		) );
	}
}

return new Cloudflare_List_Rules();