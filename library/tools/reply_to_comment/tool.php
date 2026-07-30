<?php
/**
 * Tool: reply_to_comment
 *
 * Post a reply to an existing WordPress comment as the site admin.
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
 * Post an admin reply to a WordPress comment.
 */
class Reply_To_Comment extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'reply_to_comment';
	}

	public function get_description(): string {
		return 'Post a reply to an existing WordPress comment as the site admin user. The reply is published immediately as an approved comment. Always show the draft reply to the user for approval before calling this tool.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'comment_id'  => array(
					'type'        => 'integer',
					'description' => 'The comment ID to reply to.',
				),
				'content'     => array(
					'type'        => 'string',
					'description' => 'The reply text.',
				),
				'author_name' => array(
					'type'        => 'string',
					'description' => 'Display name for the reply author. Defaults to the site name.',
				),
			),
			'required'   => array( 'comment_id', 'content' ),
		);
	}

	public function execute( array $arguments ): array {
		$comment_id  = (int) ( $arguments['comment_id'] ?? 0 );
		$content     = sanitize_textarea_field( $arguments['content'] ?? '' );
		$author_name = sanitize_text_field( $arguments['author_name'] ?? '' );

		if ( $comment_id <= 0 ) {
			return array( 'error' => 'comment_id is required.' );
		}
		if ( '' === $content ) {
			return array( 'error' => 'content is required.' );
		}

		$parent = get_comment( $comment_id );
		if ( ! $parent ) {
			return array( 'error' => "Comment {$comment_id} not found." );
		}

		if ( '' === $author_name ) {
			$author_name = get_bloginfo( 'name' );
		}

		$admin_email = get_bloginfo( 'admin_email' );

		$new_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => (int) $parent->comment_post_ID,
				'comment_parent'       => $comment_id,
				'comment_content'      => $content,
				'comment_author'       => $author_name,
				'comment_author_email' => $admin_email,
				'comment_approved'     => 1,
				'user_id'              => get_current_user_id(),
			)
		);

		if ( ! $new_comment_id || is_wp_error( $new_comment_id ) ) {
			return array( 'error' => 'Failed to post reply.' );
		}

		return array(
			'reply_id'    => $new_comment_id,
			'parent_id'   => $comment_id,
			'post_id'     => (int) $parent->comment_post_ID,
			'post_title'  => get_the_title( (int) $parent->comment_post_ID ),
			'author_name' => $author_name,
			'content'     => $content,
			'status'      => 'approved',
			'date'        => current_time( 'Y-m-d H:i:s' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}
}

return new Reply_To_Comment();
