<?php
/**
 * Tool: manage_agent_shortcode
 *
 * Create, list, update, and delete [agentic_chat] shortcode deployments —
 * the same admin-created rows the classic Deployment → Shortcodes tab
 * manages, via the shared Deployments data layer.
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
 * Manage shortcode-embedded agent chats.
 */
class Manage_Agent_Shortcode extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_agent_shortcode';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Create, list, update, or delete a [agentic_chat] shortcode deployment — a chat widget the user can paste into any page, post, or widget. Use this when the user wants to put an agent chat somewhere specific on their site via shortcode (as opposed to a site-wide floating widget, which is manage_frontend_modal_agent, or a Gutenberg block, which is manage_gutenberg_block_agent).';
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
				'action'      => array(
					'type'        => 'string',
					'enum'        => array( 'list', 'create', 'update', 'delete' ),
					'description' => 'list: show existing shortcode deployments. create: make a new one. update: change an existing admin-created one. delete: remove an admin-created one.',
				),
				'id'          => array(
					'type'        => 'integer',
					'description' => 'Deployment id. Required for update and delete.',
				),
				'agent_slug'  => array(
					'type'        => 'string',
					'description' => 'The agent to embed. Required for create.',
				),
				'label'       => array(
					'type'        => 'string',
					'description' => 'A human-readable name for this deployment, e.g. "Homepage support chat". Required for create.',
				),
				'style'       => array(
					'type'        => 'string',
					'enum'        => array( 'inline', 'popup', 'sidebar' ),
					'description' => 'How the chat renders: inline (embedded in the page flow), popup (floating launcher button), or sidebar. Defaults to inline.',
				),
				'height'      => array(
					'type'        => 'string',
					'description' => 'CSS height for inline/sidebar styles, e.g. "500px". Defaults to 500px.',
				),
				'show_header' => array(
					'type'        => 'boolean',
					'description' => 'Whether to show the chat header bar with agent name/icon. Defaults to true.',
				),
				'placeholder' => array(
					'type'        => 'string',
					'description' => 'Custom placeholder text for the message input, e.g. "Ask about our shipping policy…". Optional.',
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
			return $this->do_list();
		}

		if ( 'create' === $action ) {
			return $this->do_create( $arguments );
		}

		if ( 'update' === $action ) {
			return $this->do_update( $arguments );
		}

		if ( 'delete' === $action ) {
			return $this->do_delete( $arguments );
		}

		return array( 'error' => 'Unknown action. Use list, create, update, or delete.' );
	}

	/**
	 * List existing shortcode deployments.
	 *
	 * @return array
	 */
	private function do_list(): array {
		$rows = array();
		foreach ( Deployments::all( Deployments::TYPE_SHORTCODE ) as $row ) {
			$rows[] = $this->summarize( $row );
		}
		return array( 'shortcodes' => $rows );
	}

	/**
	 * Create a new shortcode deployment.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function do_create( array $args ): array {
		$agent_slug = sanitize_key( (string) ( $args['agent_slug'] ?? '' ) );
		$label      = sanitize_text_field( (string) ( $args['label'] ?? '' ) );

		if ( empty( $agent_slug ) || empty( $label ) ) {
			return array( 'error' => 'agent_slug and label are required to create a shortcode deployment.' );
		}

		if ( ! $this->agent_exists( $agent_slug ) ) {
			return array( 'error' => "Agent \"{$agent_slug}\" was not found or is not active." );
		}

		$id = Deployments::save(
			array(
				'type'       => Deployments::TYPE_SHORTCODE,
				'agent_slug' => $agent_slug,
				'label'      => $label,
				'enabled'    => 1,
				'source'     => Deployments::SOURCE_ADMIN,
				'config'     => $this->config_from_args( $args ),
			)
		);

		$row = Deployments::get( $id );
		if ( ! $row ) {
			return array( 'error' => 'Could not create the shortcode deployment.' );
		}

		return array(
			'ok'         => true,
			'deployment' => $this->summarize( $row ),
			'shortcode'  => Deployments::build_shortcode_string( $row ),
			'message'    => 'Created. Paste this into any page, post, or text widget: ' . Deployments::build_shortcode_string( $row ),
		);
	}

	/**
	 * Update an existing admin-created shortcode deployment.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function do_update( array $args ): array {
		$id = absint( $args['id'] ?? 0 );
		if ( $id < 1 ) {
			return array( 'error' => 'id is required to update a shortcode deployment.' );
		}

		$row = Deployments::get( $id );
		if ( ! $row ) {
			return array( 'error' => "No shortcode deployment found with id {$id}." );
		}
		if ( Deployments::SOURCE_ADMIN !== $row['source'] ) {
			return array( 'error' => 'This shortcode was auto-detected from theme/plugin code and cannot be edited here — only deployments created via chat or the admin form can be updated.' );
		}

		$agent_slug = isset( $args['agent_slug'] ) ? sanitize_key( (string) $args['agent_slug'] ) : $row['agent_slug'];
		if ( ! $this->agent_exists( $agent_slug ) ) {
			return array( 'error' => "Agent \"{$agent_slug}\" was not found or is not active." );
		}

		$config = array_merge( $row['config'], $this->config_from_args( $args, $row['config'] ) );

		Deployments::save(
			array(
				'id'         => $id,
				'agent_slug' => $agent_slug,
				'label'      => isset( $args['label'] ) ? sanitize_text_field( (string) $args['label'] ) : $row['label'],
				'config'     => $config,
			)
		);

		$updated = Deployments::get( $id );

		return array(
			'ok'         => true,
			'deployment' => $this->summarize( $updated ),
			'shortcode'  => Deployments::build_shortcode_string( $updated ),
		);
	}

	/**
	 * Delete an admin-created shortcode deployment.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function do_delete( array $args ): array {
		$id = absint( $args['id'] ?? 0 );
		if ( $id < 1 ) {
			return array( 'error' => 'id is required to delete a shortcode deployment.' );
		}

		$row = Deployments::get( $id );
		if ( ! $row ) {
			return array( 'error' => "No shortcode deployment found with id {$id}." );
		}
		if ( Deployments::SOURCE_ADMIN !== $row['source'] ) {
			return array( 'error' => 'This shortcode was auto-detected from theme/plugin code and cannot be deleted here.' );
		}

		Deployments::delete( $id );

		return array( 'ok' => true );
	}

	/**
	 * Build a config array from tool arguments, falling back to existing
	 * config values (for update) or sensible defaults (for create).
	 *
	 * @param array $args     Tool arguments.
	 * @param array $existing Existing config, when updating.
	 * @return array
	 */
	private function config_from_args( array $args, array $existing = array() ): array {
		$valid_styles = array( 'inline', 'popup', 'sidebar' );
		$style        = sanitize_key( (string) ( $args['style'] ?? ( $existing['style'] ?? 'inline' ) ) );
		if ( ! in_array( $style, $valid_styles, true ) ) {
			$style = 'inline';
		}

		return array(
			'style'       => $style,
			'height'      => isset( $args['height'] ) ? sanitize_text_field( (string) $args['height'] ) : ( $existing['height'] ?? '500px' ),
			'show_header' => array_key_exists( 'show_header', $args ) ? (bool) $args['show_header'] : ( $existing['show_header'] ?? true ),
			'placeholder' => isset( $args['placeholder'] ) ? sanitize_text_field( (string) $args['placeholder'] ) : ( $existing['placeholder'] ?? '' ),
		);
	}

	/**
	 * Flatten a Deployments row for the LLM, including the built shortcode string.
	 *
	 * @param array $row Deployment row.
	 * @return array
	 */
	private function summarize( array $row ): array {
		return array(
			'id'         => $row['id'],
			'label'      => $row['label'],
			'agent_slug' => $row['agent_slug'],
			'enabled'    => $row['enabled'],
			'source'     => $row['source'],
			'shortcode'  => Deployments::build_shortcode_string( $row ),
			'config'     => $row['config'],
		);
	}

	/**
	 * Whether an agent slug refers to a currently active agent.
	 *
	 * @param string $slug Agent slug.
	 * @return bool
	 */
	private function agent_exists( string $slug ): bool {
		if ( '' === $slug ) {
			return false;
		}
		$registry = \Agentic_Agent_Registry::get_instance();
		return null !== $registry->get_agent_instance( $slug );
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => true,
		);
	}
}

return new Manage_Agent_Shortcode();
