<?php
/**
 * Tool: get_post_revisions
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

class Get_Post_Revisions extends Tool_Base {
	public function get_name(): string {
		return 'get_post_revisions';
	}

	public function get_description(): string {
		return 'List saved revisions for a post, including the date, author, and which fields changed. Useful before deciding to restore an earlier version.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the post whose revisions to list.',
				),
				'limit'   => array(
					'type'        => 'integer',
					'description' => 'Maximum number of revisions to return. Defaults to 10.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$limit   = (int) ( $args['limit'] ?? 10 );
		$limit   = min( max( 1, $limit ), 100 );

		if ( ! $post_id ) {
			return array( 'error' => 'post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$revisions = wp_get_post_revisions( $post_id, array( 'numberposts' => $limit ) );

		if ( empty( $revisions ) ) {
			return array(
				'post_id'   => $post_id,
				'revisions' => array(),
				'total'     => 0,
				'note'      => 'No revisions found. Revisions may be disabled (WP_POST_REVISIONS = false or 0).',
			);
		}

		$result = array();
		foreach ( $revisions as $rev ) {
			$author = get_the_author_meta( 'display_name', $rev->post_author );

			// Determine which fields changed compared to previous revision.
			$modified_fields = array();
			if ( $rev->post_content !== $post->post_content ) {
				$modified_fields[] = 'content';
			}
			if ( $rev->post_title !== $post->post_title ) {
				$modified_fields[] = 'title';
			}
			if ( $rev->post_excerpt !== $post->post_excerpt ) {
				$modified_fields[] = 'excerpt';
			}

			$result[] = array(
				'revision_id'     => $rev->ID,
				'date'            => $rev->post_modified,
				'author'          => $author,
				'modified_fields' => empty( $modified_fields ) ? 'no changes detected' : implode( ', ', $modified_fields ),
			);
		}

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'revisions'  => $result,
			'total'      => count( $result ),
		);
	}
}

return new Get_Post_Revisions();
