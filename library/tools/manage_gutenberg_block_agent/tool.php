<?php
/**
 * Tool: manage_gutenberg_block_agent
 *
 * Turn an agent's Gutenberg block on or off in the block inserter — the
 * same rows the classic Deployment → Gutenberg Blocks tab manages, via the
 * shared Deployments data layer.
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

/**
 * Manage agent availability as Gutenberg blocks.
 */
class Manage_Gutenberg_Block_Agent extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_gutenberg_block_agent';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Make an agent available as a block in the WordPress block editor (Gutenberg) — once enabled, the user (or anyone editing content) can search for the agent by name in the block inserter and drop its chat interface directly into a post, page, or template, including the Site Editor.';
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
				'action'     => array(
					'type'        => 'string',
					'enum'        => array( 'list', 'enable', 'disable' ),
					'description' => 'list: show which agents are currently available as Gutenberg blocks. enable: turn one on. disable: turn one off.',
				),
				'agent_slug' => array(
					'type'        => 'string',
					'description' => 'Which agent. Required for enable and disable.',
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
		$denied = \Agentic\Tool_Helpers::deny_unless_admin_user();
		if ( null !== $denied ) {
			return $denied;
		}

		$action = sanitize_key( (string) ( $arguments['action'] ?? '' ) );

		if ( 'list' === $action ) {
			$registry = \Agentic_Agent_Registry::get_instance();
			$enabled  = array();
			foreach ( Deployments::all( Deployments::TYPE_GUTENBERG, '', true ) as $row ) {
				$enabled[ $row['agent_slug'] ] = true;
			}

			$rows = array();
			foreach ( $registry->get_all_instances() as $slug => $agent ) {
				$rows[] = array(
					'agent_slug' => $slug,
					'name'       => $agent->get_name(),
					'enabled'    => ! empty( $enabled[ $slug ] ),
				);
			}
			return array( 'gutenberg_block_agents' => $rows );
		}

		if ( 'enable' !== $action && 'disable' !== $action ) {
			return array( 'error' => 'Unknown action. Use list, enable, or disable.' );
		}

		$slug = sanitize_key( (string) ( $arguments['agent_slug'] ?? '' ) );
		if ( '' === $slug ) {
			return array( 'error' => 'agent_slug is required.' );
		}

		$registry = \Agentic_Agent_Registry::get_instance();
		if ( ! $registry->get_agent_instance( $slug ) ) {
			return array( 'error' => "Agent \"{$slug}\" was not found or is not active." );
		}

		$existing_id = 0;
		foreach ( Deployments::all( Deployments::TYPE_GUTENBERG, $slug ) as $row ) {
			$existing_id = (int) $row['id'];
			break;
		}

		$save = array(
			'type'       => Deployments::TYPE_GUTENBERG,
			'agent_slug' => $slug,
			'label'      => ucwords( str_replace( '-', ' ', $slug ) ),
			'enabled'    => 'enable' === $action,
			'source'     => Deployments::SOURCE_ADMIN,
			'config'     => array(),
		);
		if ( $existing_id ) {
			$save['id'] = $existing_id;
		}
		Deployments::save( $save );

		return array(
			'ok'         => true,
			'agent_slug' => $slug,
			'enabled'    => 'enable' === $action,
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

return new Manage_Gutenberg_Block_Agent();
