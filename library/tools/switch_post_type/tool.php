<?php
/**
 * Tool: switch_post_type
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

class Switch_Post_Type extends Tool_Base {
	public function get_name(): string {
		return 'switch_post_type';
	}

	public function get_description(): string {
		return 'Change a post\'s type to a different registered post type. Validates the target type exists before making the change.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'  => array(
					'type'        => 'integer',
					'description' => 'ID of the post whose type should be changed.',
				),
				'new_type' => array(
					'type'        => 'string',
					'description' => 'The target post type slug (e.g. "page", "post", "product").',
				),
			),
			'required'   => array( 'post_id', 'new_type' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$post_id  = (int) ( $args['post_id'] ?? 0 );
		$new_type = sanitize_key( $args['new_type'] ?? '' );

		if ( ! $post_id ) {
			return array( 'error' => 'post_id is required.' );
		}
		if ( ! $new_type ) {
			return array( 'error' => 'new_type is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$registered_types = get_post_types();
		if ( ! isset( $registered_types[ $new_type ] ) ) {
			return array(
				'error'           => "Post type '{$new_type}' is not registered on this site.",
				'available_types' => array_keys( $registered_types ),
			);
		}

		$old_type = $post->post_type;
		if ( $old_type === $new_type ) {
			return array( 'error' => "Post is already of type '{$new_type}'." );
		}

		$result = wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => $new_type,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'old_type'   => $old_type,
			'new_type'   => $new_type,
			'edit_url'   => get_edit_post_link( $post_id, 'raw' ),
		);
	}
}

return new Switch_Post_Type();
