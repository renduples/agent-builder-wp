<?php
/**
 * Tool: enable_agent_readiness
 *
 * The "one switch" master toggle backing three Agent-Ready Score checks at
 * once (webmcp_tools_registered, approval_gate_configured's table check is
 * independent, well_known_manifest) — turning on the WebMCP Bridge is what
 * the Basic-mode "Make my site agent-ready" switch actually does.
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
 * Turns the WebMCP Bridge on or off.
 */
class Enable_Agent_Readiness extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'enable_agent_readiness';
	}

	public function get_description(): string {
		return 'Turn the WebMCP Bridge on or off — the master switch that lets this site register browser-side tools for visitors and serves the /.well-known/webmcp.json discovery manifest.';
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
			'properties' => array(
				'enabled' => array(
					'type'        => 'boolean',
					'description' => 'true to turn the WebMCP Bridge on, false to turn it off.',
				),
			),
			'required'   => array( 'enabled' ),
		);
	}

	public function execute( array $arguments ): array {
		$enabled = ! empty( $arguments['enabled'] );
		update_option( \Agentic\Webmcp_Bridge::OPTION_ENABLED, $enabled ? '1' : '', true );

		return $this->success( array( 'enabled' => $enabled ) );
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Enable_Agent_Readiness();
