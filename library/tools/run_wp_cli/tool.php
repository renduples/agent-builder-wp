<?php
/**
 * Tool: run_wp_cli
 *
 * Runs a whitelisted WP-CLI command on behalf of an AI agent.
 *
 * Security model:
 *  1. WP-CLI must be loaded (function_exists('WP_CLI')).
 *  2. The agent must be explicitly allowed to run CLI commands
 *     (checked via Agent_Settings meta_key 'cli_enabled').
 *  3. The requested command must exactly match one of the enabled
 *     entries in the tool's own allowed_commands list stored in
 *     wp_agentic_tools.parameters.
 *
 * Risk level: extreme — requires proposal/approval workflow unless
 * the agent is running in autonomous mode with CLI explicitly enabled.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.3.0
 */

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;
use Agentic\Tools_Registry;
use Agentic\Agent_Settings;
use Agentic\Risk_Level;

/**
 * Run a whitelisted WP-CLI command.
 */
class Run_Wp_Cli extends Tool_Base {

	/**
	 * Meta key used in wp_agentic_agent_settings to grant CLI access.
	 */
	const AGENT_META_KEY = 'cli_enabled';

	/**
	 * Meta key used in wp_agentic_agent_settings for whitelisted wp agent subcommands.
	 */
	const AGENT_INVOCATION_KEY = 'cli_invocation';

	public function get_name(): string {
		return 'run_wp_cli';
	}

	public function get_description(): string {
		return 'Run a whitelisted WP-CLI command on this WordPress site. Only commands explicitly enabled by the administrator in the CLI whitelist are permitted.';
	}

	public function get_category(): string {
		return 'cli';
	}

	public function get_risk_level(): string {
		return Risk_Level::EXTREME;
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => true,
			'idempotent'  => false,
		);
	}

	public function get_parameters(): array {
		return array(
			'command' => array(
				'type'        => 'string',
				'description' => 'The exact WP-CLI command to run (e.g. "wp cache flush"). Must match an enabled entry in the CLI whitelist.',
				'required'    => true,
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $args Tool arguments from the LLM.
	 * @return array
	 */
	public function execute( array $args ): array {
		// 1. WP-CLI must be available.
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return array(
				'success' => false,
				'error'   => 'WP-CLI is not available in this environment.',
			);
		}

		$command = trim( sanitize_text_field( $args['command'] ?? '' ) );
		if ( '' === $command ) {
			return array(
				'success' => false,
				'error'   => 'No command provided.',
			);
		}

		// 2. Check that the calling agent has CLI access granted.
		$agent_slug = $this->get_current_agent_slug();
		if ( ! $this->agent_has_cli_access( $agent_slug ) ) {
			return array(
				'success' => false,
				'error'   => "Agent '{$agent_slug}' does not have CLI access. Enable it on the Deployment → CLI tab.",
			);
		}

		// 3. Validate the command against the whitelist.
		$allowed = $this->get_allowed_commands();
		$match   = $this->match_command( $command, $allowed );

		if ( null === $match ) {
			return array(
				'success'          => false,
				'error'            => "Command not in whitelist: '{$command}'. Add it on the Deployment → CLI tab.",
				'allowed_commands' => array_column(
					array_filter( $allowed, fn( $c ) => ! empty( $c['enabled'] ) ),
					'pattern'
				),
			);
		}

		// 4. Run the command via WP_CLI::runcommand().
		// Strip the leading "wp " if present — runcommand does not want it.
		$cli_command = preg_replace( '/^wp\s+/', '', $command );

		$output = '';
		$error  = '';

		try {
			ob_start();
			\WP_CLI::runcommand(
				$cli_command,
				array(
					'return'     => 'all',   // Return WP_CLI\Process object.
					'launch'     => false,   // Run in-process (same PHP context).
					'exit_error' => false,   // Don't exit on error; let us handle it.
				)
			);
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			$error = $e->getMessage();
		}

		return array(
			'success' => '' === $error,
			'command' => $command,
			'label'   => $match['label'] ?? $command,
			'group'   => $match['group'] ?? 'unknown',
			'output'  => $output,
			'error'   => $error,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the allowed_commands list from this tool's parameters column.
	 *
	 * The parameters column stores the OpenAI schema, but we extend it with
	 * a private key `_allowed_commands` that the admin can edit via the CLI tab.
	 *
	 * @return array<int, array{pattern:string, label:string, group:string, enabled:bool}>
	 */
	private function get_allowed_commands(): array {
		$row = Tools_Registry::get( 'run_wp_cli' );
		if ( ! $row ) {
			return self::default_allowed_commands();
		}

		$params = $row['parameters'] ?? array();
		if ( is_string( $params ) ) {
			$params = json_decode( $params, true ) ?? array();
		}

		return isset( $params['_allowed_commands'] ) && is_array( $params['_allowed_commands'] )
			? $params['_allowed_commands']
			: self::default_allowed_commands();
	}

	/**
	 * Find the first enabled whitelist entry whose pattern matches $command.
	 *
	 * Matching is case-insensitive and normalises multiple spaces.
	 *
	 * @param string $command    The command string from the LLM.
	 * @param array  $whitelist  The allowed_commands list.
	 * @return array|null Matching entry, or null if not found / disabled.
	 */
	private function match_command( string $command, array $whitelist ): ?array {
		$normalised = strtolower( preg_replace( '/\s+/', ' ', trim( $command ) ) );

		foreach ( $whitelist as $entry ) {
			if ( empty( $entry['enabled'] ) ) {
				continue;
			}
			$pattern = strtolower( preg_replace( '/\s+/', ' ', trim( $entry['pattern'] ?? '' ) ) );
			if ( $pattern === $normalised ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Determine the slug of the agent currently calling this tool.
	 *
	 * Reads the static property set by Agent_Controller before each tool call.
	 *
	 * @return string
	 */
	private function get_current_agent_slug(): string {
		return parent::get_calling_agent_slug();
	}

	/**
	 * Check whether the given agent is allowed to use CLI tools.
	 *
	 * Empty slug = unknown context = deny.
	 *
	 * @param string $agent_slug Agent ID / slug.
	 * @return bool
	 */
	private function agent_has_cli_access( string $agent_slug ): bool {
		if ( '' === $agent_slug ) {
			return false;
		}
		$value = Agent_Settings::get( $agent_slug, self::AGENT_META_KEY, 'no' );
		return 'yes' === $value;
	}

	/**
	 * Factory default allowed commands list, used on fresh installs before
	 * the admin has customised the whitelist.
	 *
	 * @return array
	 */
	public static function default_allowed_commands(): array {
		return array(
			// --- Read ---
			array(
				'pattern' => 'wp option get siteurl',
				'label'   => 'Get site URL',
				'group'   => 'read',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp plugin list',
				'label'   => 'List plugins',
				'group'   => 'read',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp theme list',
				'label'   => 'List themes',
				'group'   => 'read',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp user list',
				'label'   => 'List users',
				'group'   => 'read',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp post list',
				'label'   => 'List posts',
				'group'   => 'read',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp cron event list',
				'label'   => 'List cron events',
				'group'   => 'read',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp db check',
				'label'   => 'Check database',
				'group'   => 'read',
				'enabled' => true,
			),
			// --- Write ---
			array(
				'pattern' => 'wp cache flush',
				'label'   => 'Flush object cache',
				'group'   => 'write',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp rewrite flush',
				'label'   => 'Flush rewrite rules',
				'group'   => 'write',
				'enabled' => true,
			),
			array(
				'pattern' => 'wp transient delete --all',
				'label'   => 'Delete all transients',
				'group'   => 'write',
				'enabled' => false,
			),
			array(
				'pattern' => 'wp cron event run --due-now',
				'label'   => 'Run due cron events',
				'group'   => 'write',
				'enabled' => false,
			),
			// --- Admin ---
			array(
				'pattern' => 'wp db optimize',
				'label'   => 'Optimize database tables',
				'group'   => 'admin',
				'enabled' => false,
			),
			array(
				'pattern' => 'wp db repair',
				'label'   => 'Repair database tables',
				'group'   => 'admin',
				'enabled' => false,
			),
			array(
				'pattern' => 'wp plugin update --all',
				'label'   => 'Update all plugins',
				'group'   => 'admin',
				'enabled' => false,
			),
			array(
				'pattern' => 'wp core update',
				'label'   => 'Update WordPress core',
				'group'   => 'admin',
				'enabled' => false,
			),
		);
	}
}

return new Run_Wp_Cli();
