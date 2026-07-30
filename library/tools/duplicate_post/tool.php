<?php
/**
 * Tool: duplicate_post
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

class Duplicate_Post extends Tool_Base {
	public function get_name(): string {
		return 'duplicate_post';
	}

	public function get_description(): string {
		return 'Clone any post, page, or custom post type. Copies all post fields, postmeta (except _edit_lock/_edit_last), and all taxonomy terms. New post title defaults to "Copy of {original title}" and status defaults to draft.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'     => array(
					'type'        => 'integer',
					'description' => 'ID of the post to duplicate.',
				),
				'new_title'   => array(
					'type'        => 'string',
					'description' => 'Title for the new post. Defaults to "Copy of {original title}".',
				),
				'post_status' => array(
					'type'        => 'string',
					'description' => 'Status for the new post. Defaults to draft.',
					'enum'        => array( 'draft', 'publish', 'pending', 'private' ),
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$post_id     = (int) ( $args['post_id'] ?? 0 );
		$post_status = sanitize_key( $args['post_status'] ?? 'draft' );

		if ( ! $post_id ) {
			return array( 'error' => 'post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'revision' === $post->post_type ) {
			return array( 'error' => 'Post not found or is a revision.' );
		}

		$new_title = isset( $args['new_title'] ) ? sanitize_text_field( $args['new_title'] ) : 'Copy of ' . $post->post_title;

		$new_post_data = array(
			'post_title'     => $new_title,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => $post_status,
			'post_type'      => $post->post_type,
			'post_author'    => $post->post_author,
			'post_parent'    => $post->post_parent,
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
		);

		$new_post_id = wp_insert_post( $new_post_data, true );
		if ( is_wp_error( $new_post_id ) ) {
			return array( 'error' => $new_post_id->get_error_message() );
		}

		// Copy postmeta.
		$skip_meta = array( '_edit_lock', '_edit_last' );
		$all_meta  = get_post_meta( $post_id );
		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( in_array( $meta_key, $skip_meta, true ) ) {
				continue;
			}
			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $new_post_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}

		// Copy taxonomy terms.
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_post_id, $terms, $taxonomy );
			}
		}

		return array(
			'new_post_id' => $new_post_id,
			'new_title'   => $new_title,
			'post_status' => $post_status,
			'post_type'   => $post->post_type,
			'original_id' => $post_id,
			'edit_url'    => get_edit_post_link( $new_post_id, 'raw' ),
		);
	}
}

return new Duplicate_Post();
