<?php
/**
 * Tool: restore_revision
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

class Restore_Revision extends Tool_Base {
	public function get_name(): string {
		return 'restore_revision';
	}

	public function get_description(): string {
		return 'Restore a post to a previous revision. Returns the current state of the post after restoration.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'revision_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the revision to restore. Use get_post_revisions to find the correct revision ID.',
				),
			),
			'required'   => array( 'revision_id' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$revision_id = (int) ( $args['revision_id'] ?? 0 );

		if ( ! $revision_id ) {
			return array( 'error' => 'revision_id is required.' );
		}

		$revision = get_post( $revision_id );
		if ( ! $revision || 'revision' !== $revision->post_type ) {
			return array( 'error' => 'Revision not found.' );
		}

		$parent_id = $revision->post_parent;
		if ( ! $parent_id ) {
			return array( 'error' => 'Revision has no parent post.' );
		}

		$result = wp_restore_post_revision( $revision_id );
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		$post = get_post( $parent_id );

		return array(
			'post_id'            => $parent_id,
			'post_title'         => $post->post_title,
			'restored_from_date' => $revision->post_modified,
			'current_status'     => $post->post_status,
			'edit_url'           => get_edit_post_link( $parent_id, 'raw' ),
		);
	}
}

return new Restore_Revision();
