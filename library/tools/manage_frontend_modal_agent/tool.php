<?php
/**
 * Tool: manage_frontend_modal_agent
 *
 * Configure the site-wide floating chat widget shown to visitors on the
 * public-facing frontend — the same rows the classic Deployment → Frontend
 * Modal tab manages, via the shared Deployments data layer.
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
 * Manage the floating frontend chat widget.
 */
class Manage_Frontend_Modal_Agent extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_frontend_modal_agent';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Turn an agent into a floating chat widget that appears in the corner of every (or some) frontend page for site visitors — the classic "chat bubble" widget. Use this when the user wants an agent available site-wide rather than embedded in one specific page (that would be manage_agent_shortcode instead).';
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
					'enum'        => array( 'list', 'enable', 'disable' ),
					'description' => 'list: show which agents are currently deployed as a frontend widget. enable: turn one on (or update its settings). disable: turn one off.',
				),
				'agent_slug'     => array(
					'type'        => 'string',
					'description' => 'Which agent. Required for enable and disable.',
				),
				'position'       => array(
					'type'        => 'string',
					'enum'        => array( 'bottom-right', 'bottom-left' ),
					'description' => 'Corner of the screen the widget opens from. Defaults to bottom-right.',
				),
				'pages'          => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'front', 'singular', 'homepage' ),
					'description' => 'Which frontend pages show this widget. Defaults to all.',
				),
				'require_login'  => array(
					'type'        => 'boolean',
					'description' => 'Only show the widget to logged-in visitors. Defaults to false.',
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

		if ( 'list' === $action ) {
			$rows = array();
			foreach ( Deployments::all( Deployments::TYPE_MODAL ) as $row ) {
				$rows[] = array(
					'agent_slug'    => $row['agent_slug'],
					'enabled'       => $row['enabled'],
					'position'      => $row['config']['position'] ?? 'bottom-right',
					'pages'         => $row['config']['pages'] ?? 'all',
					'require_login' => ! empty( $row['config']['require_login'] ),
				);
			}
			return array( 'frontend_modal_agents' => $rows );
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
		foreach ( Deployments::all( Deployments::TYPE_MODAL, $slug ) as $row ) {
			$existing_id = (int) $row['id'];
			break;
		}

		if ( 'disable' === $action ) {
			if ( $existing_id ) {
				Deployments::disable( $existing_id );
			}
			return array( 'ok' => true, 'agent_slug' => $slug, 'enabled' => false );
		}

		$position = sanitize_key( (string) ( $arguments['position'] ?? 'bottom-right' ) );
		if ( ! in_array( $position, array( 'bottom-right', 'bottom-left' ), true ) ) {
			$position = 'bottom-right';
		}
		$pages = sanitize_key( (string) ( $arguments['pages'] ?? 'all' ) );
		if ( ! in_array( $pages, array( 'all', 'front', 'singular', 'homepage' ), true ) ) {
			$pages = 'all';
		}

		$save = array(
			'type'       => Deployments::TYPE_MODAL,
			'agent_slug' => $slug,
			'label'      => ucwords( str_replace( '-', ' ', $slug ) ),
			'enabled'    => 1,
			'source'     => Deployments::SOURCE_ADMIN,
			'config'     => array(
				'position'      => $position,
				'pages'         => $pages,
				'require_login' => ! empty( $arguments['require_login'] ) ? '1' : '0',
			),
		);
		if ( $existing_id ) {
			$save['id'] = $existing_id;
		}
		Deployments::save( $save );

		return array(
			'ok'            => true,
			'agent_slug'    => $slug,
			'enabled'       => true,
			'position'      => $position,
			'pages'         => $pages,
			'require_login' => ! empty( $arguments['require_login'] ),
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

return new Manage_Frontend_Modal_Agent();
