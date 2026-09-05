<?php
/**
 * Tool: resign_agent_manifest
 *
 * Free fix for the Agent-Ready Score's mcp_server_reachable check, for the
 * specific case of a stale abilities.json signature (a manifest edited after
 * the plugin's own version-bump re-sign already ran once).
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      3.3.90
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Re-signs any active agent whose abilities.json integrity check is failing.
 *
 * Deliberately does not touch agents that are inactive or have no manifest —
 * this tool cannot force-activate an agent or synthesize a manifest, so
 * those cases are left for the site owner to resolve on the Agents page.
 */
class Resign_Agent_Manifest extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'resign_agent_manifest';
	}

	public function get_description(): string {
		return 'Re-sign the abilities.json integrity signature for every active agent whose manifest signature is currently mismatched, restoring its MCP server. Does not affect agents that are inactive or have no manifest at all.';
	}

	public function get_category(): string {
		return 'diagnostics';
	}

	public function get_risk_level(): string {
		return 'low';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	public function execute( array $arguments ): array {
		$slugs = class_exists( '\\Agentic_Agent_Registry' )
			? array_keys( \Agentic_Agent_Registry::get_instance()->get_all_instances() )
			: array();

		$resigned = array();
		foreach ( $slugs as $slug ) {
			if ( ! \Agentic\Abilities_Manifest::load( $slug ) ) {
				continue; // No manifest at all — not this tool's job.
			}
			if ( \Agentic\Abilities_Manifest::verify_integrity( $slug ) ) {
				continue; // Already valid.
			}
			if ( \Agentic\Abilities_Manifest::save_integrity_hash( $slug ) ) {
				$resigned[] = $slug;
			}
		}

		return $this->success(
			array(
				'resigned_agents' => $resigned,
				'count'           => count( $resigned ),
			)
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Resign_Agent_Manifest();
