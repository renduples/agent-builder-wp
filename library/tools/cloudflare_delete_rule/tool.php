<?php
/**
 * Cloudflare Delete Rule Tool
 *
 * Allows agents (and UI) to delete specific rate limiting or security rules.
 * High-risk tool — should respect Agent Mode and approvals.
 *
 * @package Agentic\Tools
 */

declare(strict_types = 1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cloudflare_Delete_Rule extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'cloudflare_delete_rule';
	}

	public function get_description(): string {
		return 'Delete a specific rate limiting or WAF/security rule from a Cloudflare zone by its ID. Use after listing rules with cloudflare_list_rules when cleaning up or undoing previous security changes. Requires the exact rule ID and zone ID. This is a destructive action.';
	}

	public function get_category(): string {
		return 'security';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'required'   => array( 'zone_id', 'rule_id' ),
			'properties' => array(
				'zone_id' => array(
					'type'        => 'string',
					'description' => 'The Cloudflare zone ID',
				),
				'rule_id' => array(
					'type'        => 'string',
					'description' => 'The ID of the rule to delete (from cloudflare_list_rules)',
				),
				'type' => array(
					'type'        => 'string',
					'enum'        => array( 'ratelimit', 'ratelimit_legacy', 'ruleset' ),
					'description' => 'Type of rule (helps choose correct API endpoint). Default "ratelimit_legacy".',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$zone_id = sanitize_text_field( $arguments['zone_id'] ?? '' );
		$rule_id = sanitize_text_field( $arguments['rule_id'] ?? '' );
		$type    = $arguments['type'] ?? 'ratelimit_legacy';

		if ( empty( $zone_id ) || empty( $rule_id ) ) {
			return $this->tool_error( 'missing_params', 'zone_id and rule_id are required.' );
		}

		$success = false;
		$message = '';

		if ( $type === 'ratelimit_legacy' || $type === 'ratelimit' ) {
			// Legacy rate limits endpoint
			$result = \Agentic\Cloudflare_Client::api_request( 'DELETE', "/zones/{$zone_id}/rate_limits/{$rule_id}" );
			$success = ( $result !== null ); // DELETE often returns empty on success
			$message = $success ? 'Legacy rate limit rule deleted.' : 'Failed to delete legacy rate limit rule.';
		} else {
			// For modern rulesets, deletion is more involved (patch the ruleset removing the rule).
			// For now, provide guidance + attempt common endpoint.
			$result = \Agentic\Cloudflare_Client::api_request( 'DELETE', "/zones/{$zone_id}/rulesets/{$rule_id}" );
			$success = ( $result !== null );
			$message = $success ? 'Rule/ruleset entry deleted.' : 'Modern ruleset deletion attempted. Some rules require patching the full ruleset.';
		}

		if ( $success ) {
			return $this->success( array(
				'deleted' => true,
				'zone_id' => $zone_id,
				'rule_id' => $rule_id,
				'message' => $message,
			) );
		}

		return $this->tool_error( 'delete_failed', $message ?: 'Unable to delete the rule. Check permissions and rule ID.' );
	}
}

return new Cloudflare_Delete_Rule();