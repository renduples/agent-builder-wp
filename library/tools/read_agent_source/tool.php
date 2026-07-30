<?php
/**
 * Tool: read_agent_source
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

class Read_Agent_Source extends Tool_Base {

	public function get_name(): string {
		return 'read_agent_source';
	}

	public function get_description(): string {
		return 'Read the source code of an existing agent for reference.';
	}

	public function get_category(): string {
		return 'assistant-trainer';
	}

	public function get_risk_level(): string {
		return 'none';
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function get_parameters(): array {
		return array(
			'agent_slug' => array(
				'type'        => 'string',
				'description' => 'The slug of the agent to read.',
				'required'    => true,
			),
		);
	}

	public function execute( array $args ): array {
		$agent_slug = $args['agent_slug'] ?? '';

		if ( empty( $agent_slug ) ) {
			return array( 'error' => 'Agent slug is required' );
		}

		$agent_dir  = \Agentic\Tool_Helpers::get_library_path() . $agent_slug;
		$agent_file = $agent_dir . '/agent.php';
		if ( ! file_exists( $agent_file ) ) {
			// Declarative agents keep their source in agent.json.
			$agent_file = $agent_dir . '/agent.json';
		}

		if ( ! file_exists( $agent_file ) ) {
			return array( 'error' => "Agent '{$agent_slug}' not found in library" );
		}

		$content = (string) file_get_contents( $agent_file );

		return array(
			'slug'       => $agent_slug,
			'file'       => $agent_file,
			'source'     => $content,
			'line_count' => substr_count( $content, "\n" ),
			'size'       => strlen( $content ),
		);
	}
}

return new Read_Agent_Source();
