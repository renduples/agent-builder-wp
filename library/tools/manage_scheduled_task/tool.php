<?php
/**
 * Tool: manage_scheduled_task
 *
 * Create, list, update, and delete recurring agent tasks (WP-Cron backed) —
 * the same admin-created tasks the classic Deployment → Scheduled Tasks tab
 * manages, via the shared Agent_Lifecycle CRUD.
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
 * Manage recurring agent tasks.
 */
class Manage_Scheduled_Task extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_scheduled_task';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Create, list, update, or delete a recurring scheduled task for an agent — e.g. "check my site health every morning" or "summarise new comments every week". The task runs the given agent with the given prompt on the chosen recurrence, with no human present, so always confirm the prompt and recurrence with the user before creating one.';
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
					'description' => 'list: show existing scheduled tasks. create: make a new one. update: change an existing one. delete: remove one.',
				),
				'id'          => array(
					'type'        => 'string',
					'description' => 'Task id (e.g. "us_abc123"). Required for update and delete.',
				),
				'agent_slug'  => array(
					'type'        => 'string',
					'description' => 'The agent that runs the task. Required for create.',
				),
				'prompt'      => array(
					'type'        => 'string',
					'description' => 'Instructions the agent follows each time the task runs, written as if speaking to the agent, e.g. "Check for PHP errors and failed cron jobs, and email me a summary if anything needs attention." Required for create.',
				),
				'name'        => array(
					'type'        => 'string',
					'description' => 'A short name for the task. Defaults to "{schedule} — {agent name}" if omitted.',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Optional longer description of what this task does, shown in the classic Scheduled Tasks list.',
				),
				'schedule'    => array(
					'type'        => 'string',
					'enum'        => array( 'hourly', 'twicedaily', 'daily', 'weekly' ),
					'description' => 'How often the task runs. Defaults to daily.',
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
			$tasks = array();
			foreach ( Agent_Lifecycle::get_user_scheduled_tasks() as $task ) {
				$tasks[] = array(
					'id'          => $task['id'] ?? '',
					'agent_slug'  => $task['agent_slug'] ?? '',
					'name'        => $task['name'] ?? '',
					'prompt'      => $task['prompt'] ?? '',
					'description' => $task['description'] ?? '',
					'schedule'    => $task['schedule'] ?? 'daily',
					'created_at'  => $task['created_at'] ?? '',
				);
			}
			return array( 'scheduled_tasks' => $tasks );
		}

		if ( 'delete' === $action ) {
			$id = sanitize_key( (string) ( $arguments['id'] ?? '' ) );
			if ( '' === $id ) {
				return array( 'error' => 'id is required to delete a scheduled task.' );
			}
			$result = Agent_Lifecycle::delete_user_scheduled_task( $id );
			if ( empty( $result['ok'] ) ) {
				return array( 'error' => $result['error'] ?? 'Could not delete the scheduled task.' );
			}
			return array( 'ok' => true );
		}

		if ( 'create' === $action || 'update' === $action ) {
			$id = 'update' === $action ? sanitize_key( (string) ( $arguments['id'] ?? '' ) ) : '';
			if ( 'update' === $action && '' === $id ) {
				return array( 'error' => 'id is required to update a scheduled task.' );
			}

			$existing = '' !== $id ? Agent_Lifecycle::find_user_scheduled_task( $id ) : null;
			if ( 'update' === $action && ! $existing ) {
				return array( 'error' => "No scheduled task found with id {$id}." );
			}

			$result = Agent_Lifecycle::save_user_scheduled_task(
				array(
					'id'          => $id,
					'agent_slug'  => $arguments['agent_slug'] ?? ( $existing['agent_slug'] ?? '' ),
					'name'        => $arguments['name'] ?? ( $existing['name'] ?? '' ),
					'prompt'      => $arguments['prompt'] ?? ( $existing['prompt'] ?? '' ),
					'description' => $arguments['description'] ?? ( $existing['description'] ?? '' ),
					'schedule'    => $arguments['schedule'] ?? ( $existing['schedule'] ?? 'daily' ),
				)
			);

			if ( empty( $result['ok'] ) ) {
				return array( 'error' => $result['error'] ?? 'Could not save the scheduled task.' );
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

return new Manage_Scheduled_Task();
