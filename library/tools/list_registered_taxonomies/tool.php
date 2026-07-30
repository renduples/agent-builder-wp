<?php
/**
 * Tool: list_registered_taxonomies
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;

class List_Registered_Taxonomies extends Tool_Base {
	public function get_name(): string {
		return 'list_registered_taxonomies';
	}

	public function get_description(): string {
		return 'List all public taxonomies registered on the site, with term counts and the post types each taxonomy is associated with.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
			'required'   => array(),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$result     = array();

		foreach ( $taxonomies as $tax ) {
			$term_count = wp_count_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => false,
				)
			);

			$result[] = array(
				'name'         => $tax->name,
				'label'        => $tax->label,
				'hierarchical' => $tax->hierarchical,
				'post_types'   => $tax->object_type,
				'term_count'   => is_wp_error( $term_count ) ? 0 : (int) $term_count,
				'public'       => $tax->public,
			);
		}

		return array(
			'taxonomies' => $result,
			'total'      => count( $result ),
		);
	}
}

return new List_Registered_Taxonomies();
