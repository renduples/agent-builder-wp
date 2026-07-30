<?php
/**
 * Tool: get_user_activity_summary
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

class Get_User_Activity_Summary extends Tool_Base {
	public function get_name(): string {
		return 'get_user_activity_summary';
	}

	public function get_description(): string {
		return 'Get a comprehensive activity summary for a WordPress user: post count, comment count, last post date, last comment date, and active session count.';
	}

	public function get_category(): string {
		return 'users';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'user_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the user to summarise.',
				),
			),
			'required'   => array( 'user_id' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		global $wpdb;

		$user_id = (int) ( $args['user_id'] ?? 0 );
		if ( ! $user_id ) {
			return array( 'error' => 'user_id is required.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array( 'error' => 'User not found.' );
		}

		// Post count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_author = %d AND post_type = 'post' AND post_status = 'publish'",
				$user_id
			)
		);

		// Last post date.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_post_date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_date) FROM {$wpdb->posts}
				WHERE post_author = %d AND post_type = 'post' AND post_status = 'publish'",
				$user_id
			)
		);

		// Comment count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$comment_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments}
				WHERE user_id = %d AND comment_approved = '1'",
				$user_id
			)
		);

		// Last comment date.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_comment_date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(comment_date) FROM {$wpdb->comments}
				WHERE user_id = %d AND comment_approved = '1'",
				$user_id
			)
		);

		// Active sessions.
		$sessions        = get_user_meta( $user_id, 'session_tokens', true );
		$active_sessions = 0;
		$now             = time();
		if ( is_array( $sessions ) ) {
			foreach ( $sessions as $session ) {
				if ( isset( $session['expiration'] ) && $session['expiration'] > $now ) {
					++$active_sessions;
				}
			}
		}

		$roles = (array) $user->roles;

		return array(
			'user_id'           => $user_id,
			'display_name'      => $user->display_name,
			'email'             => $user->user_email,
			'role'              => ! empty( $roles ) ? $roles[0] : 'none',
			'registered'        => $user->user_registered,
			'post_count'        => $post_count,
			'comment_count'     => $comment_count,
			'last_post_date'    => $last_post_date,
			'last_comment_date' => $last_comment_date,
			'active_sessions'   => $active_sessions,
		);
	}
}

return new Get_User_Activity_Summary();
