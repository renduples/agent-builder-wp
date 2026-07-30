<?php
/**
 * Tool: moderate_comment
 *
 * Approve, hold, spam, or trash a WordPress comment.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.7.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change the moderation status of a WordPress comment.
 */
class Moderate_Comment extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'moderate_comment';
	}

	public function get_description(): string {
		return 'Approve, hold (send back to pending), mark as spam, or trash a WordPress comment. Use list_comments to find the comment ID first.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'comment_id' => array(
					'type'        => 'integer',
					'description' => 'The comment ID to moderate.',
				),
				'action'     => array(
					'type'        => 'string',
					'description' => '"approve" — publish the comment. "hold" — move back to pending. "spam" — mark as spam. "trash" — move to trash.',
					'enum'        => array( 'approve', 'hold', 'spam', 'trash' ),
				),
			),
			'required'   => array( 'comment_id', 'action' ),
		);
	}

	public function execute( array $arguments ): array {
		$comment_id = (int) ( $arguments['comment_id'] ?? 0 );
		$action     = sanitize_key( $arguments['action'] ?? '' );

		if ( $comment_id <= 0 ) {
			return array( 'error' => 'comment_id is required.' );
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return array( 'error' => "Comment {$comment_id} not found." );
		}

		$valid_actions = array( 'approve', 'hold', 'spam', 'trash' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return array( 'error' => 'action must be one of: approve, hold, spam, trash.' );
		}

		$old_status = wp_get_comment_status( $comment_id );

		$result = wp_set_comment_status( $comment_id, $action );
		if ( ! $result ) {
			// wp_set_comment_status returns false if the status didn't change.
			if ( $old_status === $action || ( 'approve' === $action && '1' === $comment->comment_approved ) ) {
				return array(
					'comment_id' => $comment_id,
					'action'     => $action,
					'status'     => $old_status,
					'note'       => 'Comment was already in this state.',
				);
			}
			return array( 'error' => 'Failed to update comment status.' );
		}

		return array(
			'comment_id' => $comment_id,
			'action'     => $action,
			'old_status' => $old_status,
			'new_status' => wp_get_comment_status( $comment_id ),
			'author'     => $comment->comment_author,
			'post_id'    => (int) $comment->comment_post_ID,
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Moderate_Comment();
