<?php
/**
 * WP-CLI Commands for Agent Builder (Free + basic layer)
 *
 * Provides core CLI access: list, info.
 *
 * WP7 AI substrate commands live in dedicated classes under includes/cli/
 * (wp agent wp-ai status, wp agent wp-ai test-execute, wp agent abilities list, etc.)
 * This prevents a god class while giving powerful free CLI surface for the new WP 7 features.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      2.11.0
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Core WP-CLI commands for Agent Builder.
 *
 * This is the "free" / basic layer per the 2026 WP7 architecture decision.
 */
class CLI_Command extends \WP_CLI_Command {

	/**
	 * List all registered agents.
	 *
	 * @subcommand list
	 */
	public function list_( array $args, array $assoc_args ): void {
		$registry      = \Agentic_Agent_Registry::get_instance();
		$status        = $assoc_args['status'] ?? 'all';
		$format        = $assoc_args['format'] ?? 'table';
		$active_agents = $registry->get_active_agents();
		$installed     = $registry->get_installed_agents();

		if ( empty( $installed ) ) {
			\WP_CLI::warning( 'No agents found.' );
			return;
		}

		$llm             = new LLM_Client();
		$global_provider = $llm->get_provider();
		$global_model    = $llm->get_model();
		$rows            = array();

		foreach ( $installed as $slug => $agent_info ) {
			$is_active = in_array( $slug, $active_agents, true );
			if ( 'active' === $status && ! $is_active ) {
				continue;
			}

			$ov_prov  = Agent_Settings::get( $slug, 'override_provider' );
			$ov_model = Agent_Settings::get( $slug, 'override_model' );
			$provider = ! empty( $ov_prov ) ? $ov_prov : $global_provider;
			$model    = ! empty( $ov_model ) ? $ov_model : $global_model;

			$rows[] = array(
				'slug'     => $slug,
				'name'     => $agent_info['name'] ?? $slug,
				'version'  => $agent_info['version'] ?? '–',
				'category' => $agent_info['category'] ?? '–',
				'provider' => $provider,
				'model'    => $model,
				'status'   => $is_active ? 'active' : 'inactive',
				'source'   => ! empty( $agent_info['bundled'] ) ? 'bundled' : 'user',
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::warning( 'No agents match the given filters.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'slug', 'name', 'version', 'category', 'provider', 'model', 'status', 'source' ) );
	}

	/**
	 * Show info about an agent.
	 *
	 * @subcommand info
	 */
	public function info( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Please provide an agent slug.' );
		}

		$slug     = $args[0];
		$registry = \Agentic_Agent_Registry::get_instance();
		$agent    = $registry->get_agent_instance( $slug );

		if ( ! $agent ) {
			$all       = $registry->get_all_instances();
			$available = array_keys( $all );
			\WP_CLI::error(
				sprintf(
					"Agent '%s' not found or not active. Available agents: %s",
					$slug,
					! empty( $available ) ? implode( ', ', $available ) : 'none (activate agents first)'
				)
			);
		}

		// Basic info + WP7 substrate awareness
		$matrix = class_exists( '\\Agentic\\WP_AI_Detection' ) ? \Agentic\WP_AI_Detection::get_feature_matrix() : array();

		\WP_CLI::log( "Agent: {$slug}" );
		\WP_CLI::log( 'Name: ' . ( $agent->get_name() ?? $slug ) );
		\WP_CLI::log( 'WP7 Substrate: ' . ( $matrix ? \Agentic\WP_AI_Detection::get_mode_label() : 'N/A' ) );

		// TODO: Expand with tools, abilities, etc. in follow-up
	}

	// Note: WP7 AI substrate commands (wp-ai status/abilities/test-execute and abilities list/test)
	// have been moved to dedicated classes in includes/cli/ for maintainability.
	// See: WP_AI_Command and Abilities_Command. Registered via WP_CLI::add_command( 'agent wp-ai', ... )
	// This keeps the core CLI_Command small and focused on top-level agent management.
}
