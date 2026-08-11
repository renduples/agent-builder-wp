<?php
/**
 * Tool: agents_available
 *
 * Browse available community agents.
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
 * Query for available agents.
 */
class Agents_Available extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'agents_available';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Browse AI agents available for download. Returns name, description, version, author, pricing, rating, and download count. Use this when a user asks about available agents, wants recommendations, or when you need to suggest a more specialised agents for a task outside your expertise.';
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
			'properties' => array(
				'search'   => array(
					'type'        => 'string',
					'description' => 'Search term to filter agents by name or description.',
				),
				'category' => array(
					'type'        => 'string',
					'description' => 'Filter agents by category.',
				),
			),
			'required'   => array(),
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
		$query_args = array( 'per_page' => 30 );

		if ( ! empty( $arguments['search'] ) ) {
			$query_args['search'] = sanitize_text_field( $arguments['search'] );
		}

		if ( ! empty( $arguments['category'] ) ) {
			$query_args['category'] = sanitize_text_field( $arguments['category'] );
		}

		$cache_key = 'agentic_community_agents_' . md5( wp_json_encode( $query_args ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url      = add_query_arg( $query_args, $api_base . '/wp-json/agentic/v1/agents' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Could not fetch community agents: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['agents'] ) || ! is_array( $body['agents'] ) ) {
			return array(
				'agents' => array(),
				'total'  => 0,
				'note'   => 'No agents found matching your criteria.',
			);
		}

		$agents = array();

		foreach ( $body['agents'] as $agent ) {
			$agents[] = array(
				'name'        => $agent['name'] ?? '',
				'slug'        => $agent['slug'] ?? '',
				'description' => $agent['excerpt'] ?? '',
				'icon'        => $agent['icon'] ?? '',
				'version'     => $agent['version'] ?? '',
				'author'      => $agent['author'] ?? '',
				'is_pro'      => ! empty( $agent['is_pro'] ),
				'price'       => $agent['price'] ?? 0,
				'downloads'   => $agent['downloads'] ?? 0,
				'rating'      => $agent['rating'] ?? 0,
				'url'         => $agent['url'] ?? '',
			);
		}

		$result = array(
			'agents'        => $agents,
			'total'         => (int) ( $body['total'] ?? count( $agents ) ),
			'community_url' => $api_base . '/community-agents/',
		);

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
		);
	}
}

return new Agents_Available();
