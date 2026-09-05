<?php
/**
 * Tool: check_agent_readiness
 *
 * Self-exposes the Agent-Ready Score so an MCP client can query a site's
 * score without visiting wp-admin.
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
 * Reads (or forces a fresh computation of) the Agent-Ready Score.
 */
class Check_Agent_Readiness extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'check_agent_readiness';
	}

	public function get_description(): string {
		return 'Check this site\'s Agent-Ready Score — a 7-check readiness score covering MCP reachability, WebMCP tool registration, approval-gate safety, and llms.txt/robots.txt/schema.org discoverability. Returns the overall score, letter grade, and a per-check breakdown.';
	}

	public function get_category(): string {
		return 'diagnostics';
	}

	public function get_risk_level(): string {
		return 'none';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'force_rescan' => array(
					'type'        => 'boolean',
					'description' => 'Recompute now instead of using the cached score. Checks run in well under a second.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$result = ! empty( $arguments['force_rescan'] )
			? \Agentic\Agent_Ready_Score::rescan()
			: \Agentic\Agent_Ready_Score::get_latest();

		return $this->success( $result );
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Check_Agent_Readiness();
