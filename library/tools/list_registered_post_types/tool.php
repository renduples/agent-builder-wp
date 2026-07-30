<?php
/**
 * Tool: list_registered_post_types
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

class List_Registered_Post_Types extends Tool_Base {
	public function get_name(): string {
		return 'list_registered_post_types';
	}

	public function get_description(): string {
		return 'List all public post types registered on the site, with publish and draft counts for each.';
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
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$result     = array();

		foreach ( $post_types as $type ) {
			$counts          = wp_count_posts( $type->name );
			$published_count = isset( $counts->publish ) ? (int) $counts->publish : 0;
			$draft_count     = isset( $counts->draft ) ? (int) $counts->draft : 0;

			$result[] = array(
				'name'            => $type->name,
				'label'           => $type->label,
				'singular_label'  => $type->labels->singular_name ?? $type->label,
				'hierarchical'    => $type->hierarchical,
				'has_archive'     => $type->has_archive,
				'public'          => $type->public,
				'published_count' => $published_count,
				'draft_count'     => $draft_count,
			);
		}

		return array(
			'post_types' => $result,
			'total'      => count( $result ),
		);
	}
}

return new List_Registered_Post_Types();
