<?php
/**
 * Tool: schedule_post
 *
 * Set or update the scheduled publish date on a WordPress post.
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
 * Schedule a post for future publishing or reschedule an existing scheduled post.
 */
class Schedule_Post extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'schedule_post';
	}

	public function get_description(): string {
		return 'Schedule a draft post for future publication, or reschedule an already-scheduled post to a new date and time. The post must be a draft or already scheduled — use db_update_post to publish immediately instead. Confirm the post ID and date with the user before scheduling.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'      => array(
					'type'        => 'integer',
					'description' => 'ID of the post to schedule. Must be a draft or currently scheduled post.',
				),
				'publish_date' => array(
					'type'        => 'string',
					'description' => 'Date and time to publish (site timezone). Formats: "2026-06-15 09:00:00" or "2026-06-15" (defaults to 09:00:00).',
				),
			),
			'required'   => array( 'post_id', 'publish_date' ),
		);
	}

	public function execute( array $arguments ): array {
		$post_id    = (int) ( $arguments['post_id'] ?? 0 );
		$date_input = sanitize_text_field( $arguments['publish_date'] ?? '' );

		if ( $post_id <= 0 ) {
			return array( 'error' => 'post_id is required.' );
		}
		if ( '' === $date_input ) {
			return array( 'error' => 'publish_date is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => "Post {$post_id} not found." );
		}
		if ( ! in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) ) {
			return array( 'error' => "Post {$post_id} has status '{$post->post_status}'. Only drafts and scheduled posts can be rescheduled." );
		}

		// Parse and validate the date.
		if ( ! str_contains( $date_input, ':' ) ) {
			$date_input .= ' 09:00:00';
		}

		$tz       = wp_timezone();
		$dt_local = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_input, $tz );
		if ( ! $dt_local ) {
			return array( 'error' => "Could not parse publish_date '{$date_input}'. Use format: YYYY-MM-DD HH:MM:SS" );
		}

		// Must be in the future.
		$now_local = new \DateTime( 'now', $tz );
		if ( $dt_local <= $now_local ) {
			return array( 'error' => 'publish_date must be in the future. Use db_update_post to publish immediately.' );
		}

		// WordPress stores dates in local time; post_date_gmt in UTC.
		$dt_gmt = clone $dt_local;
		$dt_gmt->setTimezone( new \DateTimeZone( 'UTC' ) );

		$result = wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => $dt_local->format( 'Y-m-d H:i:s' ),
				'post_date_gmt' => $dt_gmt->format( 'Y-m-d H:i:s' ),
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array( 'error' => 'Scheduling failed: ' . $result->get_error_message() );
		}

		return array(
			'post_id'       => $post_id,
			'title'         => $post->post_title,
			'status'        => 'future',
			'scheduled_for' => $dt_local->format( 'Y-m-d H:i:s' ),
			'timezone'      => $tz->getName(),
			'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
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

return new Schedule_Post();
