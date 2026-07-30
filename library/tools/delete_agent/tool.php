<?php
/**
 * Tool: delete_agent
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;

class Delete_Agent extends Tool_Base {

	public function get_name(): string {
		return 'delete_agent';
	}

	public function get_description(): string {
		return 'Delete an agent from the library. Use with caution.';
	}

	public function get_category(): string {
		return 'assistant-trainer';
	}

	public function get_risk_level(): string {
		return 'medium';
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => true,
		);
	}

	public function get_parameters(): array {
		return array(
			'slug'    => array(
				'type'        => 'string',
				'description' => 'Agent slug to delete.',
				'required'    => true,
			),
			'confirm' => array(
				'type'        => 'boolean',
				'description' => 'Must be true to confirm deletion.',
				'required'    => true,
			),
		);
	}

	public function execute( array $args ): array {
		$slug    = $args['slug'] ?? '';
		$confirm = $args['confirm'] ?? false;

		if ( empty( $slug ) ) {
			return array( 'error' => 'Agent slug is required' );
		}

		if ( ! $confirm ) {
			return array( 'error' => 'Deletion must be confirmed by setting confirm=true' );
		}

		$protected = array( 'content-writer', 'wordpress-assistant', 'assistant-trainer' );

		if ( in_array( $slug, $protected, true ) ) {
			return array( 'error' => "Cannot delete protected agent: {$slug}" );
		}

		$agent_dir = \Agentic\Tool_Helpers::get_library_path() . $slug;

		if ( ! is_dir( $agent_dir ) ) {
			return array( 'error' => "Agent '{$slug}' not found" );
		}

		\Agentic\File_Manager::rmdir( $agent_dir, true );

		return array(
			'success' => true,
			'message' => "Agent '{$slug}' deleted",
			'slug'    => $slug,
		);
	}
}

return new Delete_Agent();
