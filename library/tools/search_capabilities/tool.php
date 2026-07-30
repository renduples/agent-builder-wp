<?php
/**
 * Tool: search_capabilities
 *
 * Search for AI agents by what they can do — tool name, skill, or purpose.
 * Covers bundled agents and all marketplace agents. Use this to answer
 * "which agent can do X?" or "what tools handle Y?".
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.9.85
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search agents and tools by capability.
 */
class Search_Capabilities extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 */
	public function get_name(): string {
		return 'search_capabilities';
	}

	/**
	 * Get the tool description.
	 */
	public function get_description(): string {
		return 'Search for AI agents and tools by capability. Returns bundled agents and marketplace agents that match a query — by tool name, purpose, or keyword. Use this when a user asks "which agent can do X?", "is there an agent for Y?", or when you need to recommend a specialist agent.';
	}

	/**
	 * Get the tool category.
	 */
	public function get_category(): string {
		return 'agents';
	}

	/**
	 * Get the parameter schema.
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'q'      => array(
					'type'        => 'string',
					'description' => 'Search term — tool name (e.g. "generate_image"), keyword (e.g. "SEO"), or agent purpose (e.g. "WooCommerce orders").',
				),
				'type'   => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'agent', 'tool' ),
					'description' => 'Limit results to agents, tools, or all. Default: all.',
				),
				'source' => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'bundled', 'marketplace' ),
					'description' => 'Limit to bundled agents, marketplace agents, or all. Default: all.',
				),
			),
			'required'   => array( 'q' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$api_base   = \Agentic\Service_Registry::url( 'agentic-api' );
		$query_args = array_filter(
			array(
				'q'      => sanitize_text_field( $arguments['q'] ?? '' ),
				'type'   => sanitize_key( $arguments['type'] ?? 'all' ),
				'source' => sanitize_key( $arguments['source'] ?? 'all' ),
			)
		);

		$cache_key = 'agentic_search_caps_' . md5( wp_json_encode( $query_args ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$url      = add_query_arg( $query_args, $api_base . '/wp-json/agentic/v1/search-capabilities' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Could not reach the capabilities index: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body ) || ! isset( $body['results'] ) ) {
			return array(
				'results' => array(),
				'total'   => 0,
				'note'    => 'No results found.',
			);
		}

		set_transient( $cache_key, $body, 10 * MINUTE_IN_SECONDS );

		return $body;
	}

	/**
	 * Get tool annotations.
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
		);
	}
}

return new Search_Capabilities();
