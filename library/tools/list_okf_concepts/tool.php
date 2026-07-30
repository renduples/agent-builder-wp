<?php
/**
 * Tool: list_okf_concepts
 *
 * @package Agent_Builder
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List concepts in the local Open Knowledge Format wiki.
 */
class List_Okf_Concepts extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'list_okf_concepts';
	}

	public function get_description(): string {
		return 'List curated knowledge concepts in the local Open Knowledge Format (OKF) wiki. Returns id, type, title, description, tags, and stale status. Demo/example concepts are never included. Use before read_okf_concept when you need to discover what business facts, policies, or playbooks are available. Scope "site" is the shared wiki; pass an agent slug for a per-agent wiki.';
	}

	public function get_category(): string {
		return 'knowledge';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'scope' => array(
					'type'        => 'string',
					'description' => 'Wiki scope: "site" (default) or an agent slug.',
				),
			),
			'required'   => array(),
		);
	}

	public function execute( array $arguments ): array {
		$scope = sanitize_key( $arguments['scope'] ?? 'site' );
		if ( '' === $scope ) {
			$scope = 'site';
		}
		\Agentic\Okf_Store::ensure_bundle( $scope );
		// Never expose demo/example concepts to agents.
		$concepts = \Agentic\Okf_Store::list_concepts( $scope, false );
		return array(
			'scope'    => $scope,
			'count'    => count( $concepts ),
			'concepts' => $concepts,
		);
	}
}
