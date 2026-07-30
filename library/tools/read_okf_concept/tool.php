<?php
/**
 * Tool: read_okf_concept
 *
 * @package Agent_Builder
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a single OKF concept body.
 */
class Read_Okf_Concept extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'read_okf_concept';
	}

	public function get_description(): string {
		return 'Read the full body and frontmatter of one Open Knowledge Format concept by id (from list_okf_concepts). Use this to load FAQs, policies, product facts, or playbooks into context instead of guessing.';
	}

	public function get_category(): string {
		return 'knowledge';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'    => array(
					'type'        => 'string',
					'description' => 'Concept id / relative path without .md (e.g. "returns-policy").',
				),
				'scope' => array(
					'type'        => 'string',
					'description' => 'Wiki scope: "site" (default) or an agent slug.',
				),
			),
			'required'   => array( 'id' ),
		);
	}

	public function execute( array $arguments ): array {
		$id    = sanitize_text_field( $arguments['id'] ?? '' );
		$scope = sanitize_key( $arguments['scope'] ?? 'site' );
		if ( '' === $id ) {
			return array( 'error' => 'id is required.' );
		}
		// Deny demo/example concepts — agents must not use them.
		$doc = \Agentic\Okf_Store::get_concept( $id, $scope, false );
		if ( is_wp_error( $doc ) ) {
			return array( 'error' => $doc->get_error_message() );
		}
		return array(
			'id'          => $doc['id'],
			'scope'       => $scope,
			'frontmatter' => $doc['frontmatter'],
			'body'        => $doc['body'],
		);
	}
}
