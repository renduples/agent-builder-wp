<?php
/**
 * Tool: cleanup_spam_comments
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

class Cleanup_Spam_Comments extends Tool_Base {
	public function get_name(): string {
		return 'cleanup_spam_comments';
	}

	public function get_description(): string {
		return 'Permanently delete all comments marked as spam. Supports dry_run mode to count without deleting.';
	}

	public function get_category(): string {
		return 'maintenance';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'dry_run' => array(
					'type'        => 'boolean',
					'description' => 'If true, count spam comments without deleting them. Defaults to false.',
				),
			),
			'required'   => array(),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => true,
		);
	}

	public function execute( array $args ): array {
		global $wpdb;

		$dry_run = (bool) ( $args['dry_run'] ?? false );

		if ( $dry_run ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
			);

			return array(
				'deleted_count' => $count,
				'dry_run'       => true,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
		);

		// Clean up orphaned comment meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE cm FROM {$wpdb->commentmeta} cm
			LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
			WHERE c.comment_ID IS NULL"
		);

		return array(
			'deleted_count' => $count,
			'dry_run'       => false,
		);
	}
}

return new Cleanup_Spam_Comments();
