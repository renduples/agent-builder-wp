<?php
/**
 * Tool: get_agent_list
 *
 * Get a list of all installed agents and their status.
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

/**
 * Get a list of all installed agents and their status.
 */
class Get_Agent_List extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_agent_list';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get a list of all installed agents and their status.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'assistant-trainer';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		// Use the agent registry as the single source of truth. It resolves both
		// legacy agent.php and declarative agent.json agents (and library-table
		// rows), so this tool never under-reports the installed roster.
		$agents = array();

		if ( class_exists( '\Agentic_Agent_Registry' ) ) {
			foreach ( \Agentic_Agent_Registry::get_instance()->get_installed_agents() as $slug => $data ) {
				$agents[] = array(
					'id'          => $data['slug'] ?? (string) $slug,
					'name'        => $data['name'] ?? (string) $slug,
					'description' => $data['description'] ?? '',
					'category'    => $data['category'] ?? 'unknown',
					'version'     => $data['version'] ?? '0.0.0',
					'active'      => ! empty( $data['active'] ),
				);
			}
		}

		return array(
			'agents'       => $agents,
			'total'        => count( $agents ),
			'active_count' => count( array_filter( $agents, fn( $a ) => $a['active'] ) ),
		);
	}

	/**
	 * Parse agent file header comments.
	 *
	 * @param string $file_path Path to the agent file.
	 * @return array Parsed header values.
	 */
	private function parse_agent_header( string $file_path ): array {
		$content = file_get_contents( $file_path );
		$headers = array();

		if ( preg_match_all( '/^\s*\*\s*(Agent Name|Version|Description|Category|Author|Icon):\s*(.+)$/m', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headers[ trim( $match[1] ) ] = trim( $match[2] );
			}
		}

		return $headers;
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly' => true,
		);
	}
}

return new Get_Agent_List();
