<?php
/**
 * Tool: enable_webmcp_defaults
 *
 * Free fix for the Agent-Ready Score's webmcp_tools_registered check.
 *
 * Deliberately NOT "expose every readonly, NONE/LOW-risk tool" — that risk
 * taxonomy was designed for the trusted wp-admin chat context (Risk_Level::LOW
 * is explicitly documented as "read operations that MAY expose personal
 * information," which is a completely different threat model than "safe to
 * hand to any anonymous visitor on the public internet"). An earlier version
 * of this tool did exactly that and, on a real test site, exposed things like
 * get_security_overview (failed logins, admin count), list_privileged_users
 * (admin usernames), get_recent_registrations, check_plugin_updates (version
 * fingerprinting), and get_form_entries (can contain PII) — to anyone, and
 * exposed tools belonging to purely admin-facing agents (user-assistant,
 * wordpress-assistant, assistant-trainer) that have no business being
 * visitor-facing at all.
 *
 * Instead this exposes only from SAFE_FOR_ANONYMOUS, a small, deliberately
 * curated allowlist of tool names known to be genuinely safe for an
 * anonymous public visitor — today just search_content, which only ever
 * returns published content (see its own is_user_logged_in() guard). Site
 * owners can always expose more via the Advanced tab's per-tool matrix —
 * that is a deliberate, informed, one-at-a-time choice, unlike this
 * automatic sweep.
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
 * Sets webmcp_expose:true on a small, curated allowlist of tool names only.
 */
class Enable_Webmcp_Defaults extends \Agentic\Tool_Base {

	/**
	 * Tool names safe to auto-expose to an anonymous public visitor.
	 *
	 * Keep this list short and reviewed by hand — see the class docblock for
	 * why risk tier alone is not a safe substitute for this.
	 */
	private const SAFE_FOR_ANONYMOUS = array( 'search_content' );

	public function get_name(): string {
		return 'enable_webmcp_defaults';
	}

	public function get_description(): string {
		return 'Expose a small, curated set of genuinely public-safe tools (currently just search_content) to the WebMCP frontend surface. Never overwrites a tool the site owner already explicitly opted out (webmcp_expose:false), and never expands this list to arbitrary readonly/low-risk tools.';
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

		$updated_agents = array();
		$exposed_tools  = array();
		$skipped        = array();

		foreach ( $slugs as $slug ) {
			$manifest = \Agentic\Abilities_Manifest::load( $slug );
			if ( ! $manifest || empty( $manifest['abilities'] ) || ! is_array( $manifest['abilities'] ) ) {
				continue;
			}

			$path = \Agentic\Abilities_Manifest::resolve_path( $slug );
			if ( ! $path || ! wp_is_writable( $path ) ) {
				$skipped[] = $slug;
				continue;
			}

			$changed = false;
			foreach ( $manifest['abilities'] as $tool_name => &$entry ) {
				if ( ! in_array( $tool_name, self::SAFE_FOR_ANONYMOUS, true ) ) {
					continue;
				}
				if ( array_key_exists( 'webmcp_expose', $entry ) ) {
					continue; // Site owner already made an explicit choice either way.
				}

				$tool = \Agentic\Tool_Loader::get_instance()->get( (string) $tool_name );
				if ( ! $tool ) {
					continue;
				}

				$readonly = (bool) ( $tool->get_annotations()['readonly'] ?? false );
				$risk     = \Agentic\Abilities_Manifest::get_effective_risk( $slug, (string) $tool_name, $tool );

				if ( ! $readonly || ! in_array( $risk, array( \Agentic\Risk_Level::NONE, \Agentic\Risk_Level::LOW ), true ) ) {
					continue;
				}

				$entry['webmcp_expose']  = true;
				$entry['webmcp_context'] = 'frontend';
				$changed                 = true;
				$exposed_tools[]         = "{$slug}:{$tool_name}";
			}
			unset( $entry );

			if ( ! $changed ) {
				continue;
			}

			$written = file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Editing this agent's own bundled manifest file; WP_Filesystem is unavailable in this runtime tool-execution context.
				$path,
				wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
			);
			if ( false === $written ) {
				$skipped[] = $slug;
				continue;
			}

			\Agentic\Abilities_Manifest::clear_cache( $slug );
			\Agentic\Abilities_Manifest::save_integrity_hash( $slug );
			$updated_agents[] = $slug;
		}

		return $this->success(
			array(
				'updated_agents' => $updated_agents,
				'exposed_tools'  => $exposed_tools,
				'skipped_agents' => $skipped,
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

return new Enable_Webmcp_Defaults();
