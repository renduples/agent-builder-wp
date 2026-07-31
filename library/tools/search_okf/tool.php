<?php
/**
 * Tool: search_okf
 *
 * @package Agent_Builder
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keyword search the local OKF wiki.
 */
class Search_Okf extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'search_okf';
	}

	public function get_description(): string {
		return 'Keyword search the local Open Knowledge Format wiki (titles, tags, and bodies). Demo/example concepts are never returned. Prefer this for curated business knowledge; use search_content for WordPress posts/pages. Does not use cloud embeddings or credits.';
	}

	public function get_category(): string {
		return 'knowledge';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'query' => array(
					'type'        => 'string',
					'description' => 'Search terms.',
				),
				'scope' => array(
					'type'        => 'string',
					'description' => 'Wiki scope: "site" (default) or an agent slug.',
				),
				'limit' => array(
					'type'        => 'integer',
					'description' => 'Max hits (1–20). Default 8.',
				),
			),
			'required'   => array( 'query' ),
		);
	}

	public function execute( array $arguments ): array {
		$query = sanitize_text_field( $arguments['query'] ?? '' );
		$scope = sanitize_key( $arguments['scope'] ?? 'site' );
		$limit = min( max( (int) ( $arguments['limit'] ?? 8 ), 1 ), 20 );
		if ( '' === $query ) {
			return array( 'error' => 'query is required.' );
		}
		return array(
			'scope' => $scope,
			// Never search demo/example concepts for agents.
			'hits'  => \Agentic\Okf_Store::search( $query, $scope, $limit, false ),
		);
	}
}

return new Search_Okf();
