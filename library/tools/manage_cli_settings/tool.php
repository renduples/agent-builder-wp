<?php
/**
 * Tool: manage_cli_settings
 *
 * Manage the WP-CLI command whitelist and per-agent CLI privileges — the
 * same settings the classic Deployment → CLI tab manages. The whitelist
 * controls what the (separate, existing) run_wp_cli tool is allowed to
 * run; per-agent privileges control which agents may call it at all.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      3.3.67
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Deployments;
use Agentic\Agent_Settings;
use Agentic\Tools_Registry;
use Agentic\Tools\Run_Wp_Cli;

/**
 * Manage run_wp_cli's whitelist and per-agent privileges.
 */
class Manage_Cli_Settings extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_cli_settings';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Manage which WP-CLI commands agents are allowed to run, and which agents may use the run_wp_cli tool at all. Expanding this whitelist expands what a future run_wp_cli call could execute, so always explain the specific command(s) to the user and get explicit confirmation before adding to the whitelist or granting a new agent CLI access.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'agent-orchestrator';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action'         => array(
					'type'        => 'string',
					'enum'        => array( 'get_whitelist', 'add_command', 'remove_command', 'get_agent_privileges', 'set_agent_privileges' ),
					'description' => 'get_whitelist: list allowed WP-CLI command patterns. add_command / remove_command: change the whitelist. get_agent_privileges: list per-agent CLI access. set_agent_privileges: change one agent\'s access.',
				),
				'pattern'        => array(
					'type'        => 'string',
					'description' => 'A WP-CLI command pattern, e.g. "wp cache flush" or "wp plugin list". Required for add_command and remove_command.',
				),
				'label'          => array(
					'type'        => 'string',
					'description' => 'Human-readable label for the command. Optional for add_command — defaults to the pattern.',
				),
				'group'          => array(
					'type'        => 'string',
					'enum'        => array( 'read', 'write', 'admin' ),
					'description' => 'Risk grouping shown in the classic UI. Defaults to write for add_command.',
				),
				'agent_slug'     => array(
					'type'        => 'string',
					'description' => 'Which agent. Required for set_agent_privileges.',
				),
				'cli_enabled'    => array(
					'type'        => 'boolean',
					'description' => 'Whether this agent may call the run_wp_cli tool in conversation. Required for set_agent_privileges.',
				),
			),
			'required'   => array( 'action' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$action = sanitize_key( (string) ( $arguments['action'] ?? '' ) );

		if ( in_array( $action, array( 'get_whitelist', 'add_command', 'remove_command' ), true ) ) {
			$this->ensure_tool_seeded();
		}

		if ( 'get_whitelist' === $action ) {
			return array( 'commands' => $this->current_whitelist() );
		}

		if ( 'add_command' === $action ) {
			return $this->add_command( $arguments );
		}

		if ( 'remove_command' === $action ) {
			return $this->remove_command( $arguments );
		}

		if ( 'get_agent_privileges' === $action ) {
			return $this->get_agent_privileges();
		}

		if ( 'set_agent_privileges' === $action ) {
			return $this->set_agent_privileges( $arguments );
		}

		return array( 'error' => 'Unknown action.' );
	}

	/**
	 * Seed the run_wp_cli tool into the registry if this is the first time
	 * anything has touched its settings (mirrors the classic CLI tab's own
	 * seed-on-first-visit step) so register()'s upsert has real description/
	 * category/risk values to preserve instead of falling back to generic
	 * ones.
	 *
	 * @return void
	 */
	private function ensure_tool_seeded(): void {
		if ( Tools_Registry::get( 'run_wp_cli' ) ) {
			return;
		}
		if ( class_exists( '\Agentic\Tool_Loader' ) ) {
			\Agentic\Tool_Loader::get_instance()->sync_to_registry();
		}
		if ( ! Tools_Registry::get( 'run_wp_cli' ) ) {
			Tools_Registry::seed_core_tools( array( 'run_wp_cli' => 'cli' ) );
		}
	}

	/**
	 * Read the current whitelist from the run_wp_cli tool's stored parameters.
	 *
	 * @return array
	 */
	private function current_whitelist(): array {
		$row = Tools_Registry::get( 'run_wp_cli' );
		if ( $row ) {
			$params = is_string( $row['parameters'] ?? '' ) ? json_decode( (string) $row['parameters'], true ) : ( $row['parameters'] ?? array() );
			if ( is_array( $params ) && isset( $params['_allowed_commands'] ) && is_array( $params['_allowed_commands'] ) ) {
				return $params['_allowed_commands'];
			}
		}
		return class_exists( Run_Wp_Cli::class ) ? Run_Wp_Cli::default_allowed_commands() : array();
	}

	/**
	 * Persist a whitelist array, mirroring the classic CLI tab's save path
	 * (Tools_Registry row + Deployments dual-write).
	 *
	 * @param array $commands Full whitelist.
	 * @return void
	 */
	private function save_whitelist( array $commands ): void {
		$row    = Tools_Registry::get( 'run_wp_cli' );
		$params = $row ? ( is_string( $row['parameters'] ?? '' ) ? json_decode( (string) $row['parameters'], true ) : ( $row['parameters'] ?? array() ) ) : array();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$params['_allowed_commands'] = $commands;

		Tools_Registry::register(
			array_merge(
				$row ?? array( 'name' => 'run_wp_cli' ),
				array( 'parameters' => $params )
			)
		);

		if ( class_exists( Deployments::class ) ) {
			$existing = array();
			foreach ( Deployments::all( Deployments::TYPE_CLI ) as $cli_row ) {
				$existing[ $cli_row['config']['pattern'] ?? '' ] = $cli_row['id'];
			}

			$seen = array();
			foreach ( $commands as $cmd ) {
				$pattern  = $cmd['pattern'] ?? '';
				$seen[]   = $pattern;
				$cli_save = array(
					'type'       => Deployments::TYPE_CLI,
					'agent_slug' => '',
					'label'      => $cmd['label'] ?? $pattern,
					'enabled'    => ! empty( $cmd['enabled'] ) ? 1 : 0,
					'source'     => Deployments::SOURCE_ADMIN,
					'config'     => array(
						'pattern' => $pattern,
						'group'   => $cmd['group'] ?? 'write',
					),
				);
				if ( isset( $existing[ $pattern ] ) ) {
					$cli_save['id'] = $existing[ $pattern ];
				}
				Deployments::save( $cli_save );
			}

			foreach ( $existing as $pattern => $id ) {
				if ( ! in_array( $pattern, $seen, true ) ) {
					Deployments::delete( $id );
				}
			}
		}
	}

	/**
	 * Add (or enable, if it already exists) a command in the whitelist.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function add_command( array $args ): array {
		$pattern = sanitize_text_field( (string) ( $args['pattern'] ?? '' ) );
		if ( '' === $pattern ) {
			return array( 'error' => 'pattern is required to add a command.' );
		}
		$group = sanitize_key( (string) ( $args['group'] ?? 'write' ) );
		if ( ! in_array( $group, array( 'read', 'write', 'admin' ), true ) ) {
			$group = 'write';
		}
		$label = sanitize_text_field( (string) ( $args['label'] ?? $pattern ) );

		$commands = $this->current_whitelist();
		$found    = false;
		foreach ( $commands as &$cmd ) {
			if ( ( $cmd['pattern'] ?? '' ) === $pattern ) {
				$cmd['enabled'] = true;
				$cmd['label']   = $label;
				$cmd['group']   = $group;
				$found          = true;
				break;
			}
		}
		unset( $cmd );

		if ( ! $found ) {
			$commands[] = array(
				'pattern' => $pattern,
				'label'   => $label,
				'group'   => $group,
				'enabled' => true,
			);
		}

		$this->save_whitelist( $commands );

		return array(
			'ok'      => true,
			'pattern' => $pattern,
			'count'   => count( $commands ),
		);
	}

	/**
	 * Remove a command from the whitelist entirely.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function remove_command( array $args ): array {
		$pattern = sanitize_text_field( (string) ( $args['pattern'] ?? '' ) );
		if ( '' === $pattern ) {
			return array( 'error' => 'pattern is required to remove a command.' );
		}

		$commands = array_values(
			array_filter(
				$this->current_whitelist(),
				fn( $cmd ) => ( $cmd['pattern'] ?? '' ) !== $pattern
			)
		);

		$this->save_whitelist( $commands );

		return array(
			'ok'      => true,
			'pattern' => $pattern,
			'count'   => count( $commands ),
		);
	}

	/**
	 * List every agent's CLI privileges.
	 *
	 * @return array
	 */
	private function get_agent_privileges(): array {
		$registry = \Agentic_Agent_Registry::get_instance();
		$rows     = array();
		foreach ( $registry->get_all_instances() as $slug => $agent ) {
			$rows[] = array(
				'agent_slug'  => $slug,
				'name'        => $agent->get_name(),
				'cli_enabled' => 'yes' === Agent_Settings::get( $slug, 'cli_enabled', 'no' ),
			);
		}
		return array( 'agent_privileges' => $rows );
	}

	/**
	 * Set one agent's run_wp_cli access.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function set_agent_privileges( array $args ): array {
		$slug = sanitize_key( (string) ( $args['agent_slug'] ?? '' ) );
		if ( '' === $slug ) {
			return array( 'error' => 'agent_slug is required.' );
		}
		if ( ! array_key_exists( 'cli_enabled', $args ) ) {
			return array( 'error' => 'cli_enabled is required.' );
		}

		$registry = \Agentic_Agent_Registry::get_instance();
		if ( ! $registry->get_agent_instance( $slug ) ) {
			return array( 'error' => "Agent \"{$slug}\" was not found or is not active." );
		}

		Agent_Settings::update( $slug, 'cli_enabled', ! empty( $args['cli_enabled'] ) ? 'yes' : 'no' );

		return array(
			'ok'          => true,
			'agent_slug'  => $slug,
			'cli_enabled' => ! empty( $args['cli_enabled'] ),
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
		);
	}
}

return new Manage_Cli_Settings();
