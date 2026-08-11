<?php
/**
 * Tool: manage_event_listener
 *
 * Create, list, update, and delete WordPress event triggers for an agent —
 * the same admin-created triggers the classic Deployment → Event Listeners
 * tab manages, via the shared Agent_Lifecycle CRUD.
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

use Agentic\Agent_Lifecycle;

/**
 * Manage WordPress-event-triggered agent runs.
 */
class Manage_Event_Listener extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_event_listener';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Create, list, update, or delete a trigger that runs an agent automatically when something happens on the site — e.g. "reply to new comments", "welcome new users on registration", "review a post when it is published". Fires on standard WordPress action hooks such as publish_post, comment_post, user_register, wp_login, woocommerce_order_status_completed. The agent runs unattended when the hook fires, so always confirm the hook and prompt with the user before creating a listener.';
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
					'enum'        => array( 'list', 'create', 'update', 'delete' ),
					'description' => 'list: show existing event listeners. create: make a new one. update: change an existing one. delete: remove one.',
				),
				'id'         => array(
					'type'        => 'string',
					'description' => 'Trigger id (e.g. "ut_abc123"). Required for update and delete.',
				),
				'agent_slug' => array(
					'type'        => 'string',
					'description' => 'The agent to run when the hook fires. Required for create.',
				),
				'hook'       => array(
					'type'        => 'string',
					'description' => 'The WordPress action hook to listen for, e.g. publish_post, comment_post, user_register, wp_login, woocommerce_order_status_completed. Required for create.',
				),
				'prompt'     => array(
					'type'        => 'string',
					'description' => 'Instructions the agent follows when the hook fires, written as if speaking to the agent, e.g. "A new comment was posted — read it and, if it looks like spam, mark it as spam; otherwise leave it for review."',
				),
				'name'       => array(
					'type'        => 'string',
					'description' => 'A short name for the listener. Defaults to "{hook} → {agent}" if omitted.',
				),
				'priority'   => array(
					'type'        => 'integer',
					'description' => 'WordPress add_action() priority — lower runs earlier. Defaults to 10; only worth changing if the user needs specific ordering against other hooked behaviour.',
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
			$listeners = array();
			foreach ( Agent_Lifecycle::get_user_event_triggers() as $trigger ) {
				$listeners[] = array(
					'id'         => $trigger['id'] ?? '',
					'agent_slug' => $trigger['agent_slug'] ?? '',
					'hook'       => $trigger['hook'] ?? '',
					'name'       => $trigger['name'] ?? '',
					'prompt'     => $trigger['prompt'] ?? '',
					'priority'   => $trigger['priority'] ?? 10,
					'created_at' => $trigger['created_at'] ?? '',
				);
			}
			return array( 'event_listeners' => $listeners );
		}

		if ( 'delete' === $action ) {
			$id = sanitize_text_field( (string) ( $arguments['id'] ?? '' ) );
			if ( '' === $id ) {
				return array( 'error' => 'id is required to delete an event listener.' );
			}
			$result = Agent_Lifecycle::delete_user_trigger( $id );
			if ( empty( $result['ok'] ) ) {
				return array( 'error' => $result['error'] ?? 'Could not delete the event listener.' );
			}
			return array( 'ok' => true );
		}

		if ( 'create' === $action || 'update' === $action ) {
			$id = 'update' === $action ? sanitize_text_field( (string) ( $arguments['id'] ?? '' ) ) : '';
			if ( 'update' === $action && '' === $id ) {
				return array( 'error' => 'id is required to update an event listener.' );
			}

			$existing = null;
			if ( '' !== $id ) {
				foreach ( Agent_Lifecycle::get_user_event_triggers() as $trigger ) {
					if ( ( $trigger['id'] ?? '' ) === $id ) {
						$existing = $trigger;
						break;
					}
				}
			}
			if ( 'update' === $action && ! $existing ) {
				return array( 'error' => "No event listener found with id {$id}." );
			}

			$result = Agent_Lifecycle::save_user_trigger(
				array(
					'id'         => $id,
					'agent_slug' => $arguments['agent_slug'] ?? ( $existing['agent_slug'] ?? '' ),
					'hook'       => $arguments['hook'] ?? ( $existing['hook'] ?? '' ),
					'name'       => $arguments['name'] ?? ( $existing['name'] ?? '' ),
					'prompt'     => $arguments['prompt'] ?? ( $existing['prompt'] ?? '' ),
					'priority'   => $arguments['priority'] ?? ( $existing['priority'] ?? 10 ),
				)
			);

			if ( empty( $result['ok'] ) ) {
				return array( 'error' => $result['error'] ?? 'Could not save the event listener.' );
			}

			return array(
				'ok'   => true,
				'id'   => $result['id'],
				'name' => $result['name'],
			);
		}

		return array( 'error' => 'Unknown action. Use list, create, update, or delete.' );
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

return new Manage_Event_Listener();
