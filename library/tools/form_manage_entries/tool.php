<?php
/**
 * Tool: form_manage_entries
 *
 * Manage individual native form entries (mark read/unread, star, archive,
 * delete, add notes).
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.9.0
 *
 * php version 8.1
 */

declare( strict_types=1 );

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage individual form entries.
 *
 * Supports actions: mark_read, mark_unread, star, unstar, archive, delete,
 * and add_note on native agentic_form_entry posts.
 */
class Form_Manage_Entries extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_manage_entries';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Manage individual native form entries. ' .
			'Supports marking as read/unread, starring, archiving, deleting, and adding notes.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'forms';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'entry_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the form entry to manage.',
				),
				'action'   => array(
					'type'        => 'string',
					'description' => 'The action to perform: mark_read, mark_unread, star, unstar, archive, delete, or add_note.',
					'enum'        => array( 'mark_read', 'mark_unread', 'star', 'unstar', 'archive', 'delete', 'add_note' ),
				),
				'note'     => array(
					'type'        => 'string',
					'description' => 'Note text to add (only used with the add_note action).',
				),
			),
			'required'   => array( 'entry_id', 'action' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'error' => 'You do not have permission to manage form entries.' );
		}

		$entry_id = absint( $arguments['entry_id'] ?? 0 );
		$action   = sanitize_key( $arguments['action'] ?? '' );

		if ( 0 === $entry_id ) {
			return array( 'error' => 'An entry_id is required.' );
		}

		if ( '' === $action ) {
			return array( 'error' => 'An action is required.' );
		}

		$allowed_actions = array( 'mark_read', 'mark_unread', 'star', 'unstar', 'archive', 'delete', 'add_note' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return array( 'error' => 'Invalid action. Allowed: ' . implode( ', ', $allowed_actions ) . '.' );
		}

		$post = get_post( $entry_id );
		if ( ! $post || \Agentic_Native_Forms::ENTRY_CPT !== $post->post_type ) {
			return array( 'error' => 'Form entry with ID ' . $entry_id . ' was not found.' );
		}

		// Handle delete action separately.
		if ( 'delete' === $action ) {
			$deleted = wp_delete_post( $entry_id, true );
			if ( ! $deleted ) {
				return array( 'error' => 'Failed to delete entry ' . $entry_id . '.' );
			}
			return array(
				'entry_id' => $entry_id,
				'action'   => 'delete',
				'success'  => true,
			);
		}

		// Get current status.
		$raw    = get_post_meta( $entry_id, \Agentic_Native_Forms::META_ENTRY_STATUS, true );
		$status = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		$status = array_merge(
			array(
				'read'     => false,
				'starred'  => false,
				'archived' => false,
			),
			$status
		);

		// Apply action.
		switch ( $action ) {
			case 'mark_read':
				$status['read'] = true;
				break;
			case 'mark_unread':
				$status['read'] = false;
				break;
			case 'star':
				$status['starred'] = true;
				break;
			case 'unstar':
				$status['starred'] = false;
				break;
			case 'archive':
				$status['archived'] = true;
				break;
			case 'add_note':
				$note = sanitize_textarea_field( $arguments['note'] ?? '' );
				if ( '' === $note ) {
					return array( 'error' => 'A note is required for the add_note action.' );
				}

				$notes_raw = get_post_meta( $entry_id, \Agentic_Native_Forms::META_ENTRY_NOTES, true );
				$notes     = is_string( $notes_raw ) && '' !== $notes_raw ? json_decode( $notes_raw, true ) : array();
				if ( ! is_array( $notes ) ) {
					$notes = array();
				}

				$notes[] = array(
					'text'   => $note,
					'author' => get_current_user_id(),
					'date'   => current_time( 'mysql' ),
				);

				update_post_meta( $entry_id, \Agentic_Native_Forms::META_ENTRY_NOTES, wp_json_encode( $notes ) );

				// Also mark as read when adding a note.
				$status['read'] = true;
				break;
		}

		update_post_meta( $entry_id, \Agentic_Native_Forms::META_ENTRY_STATUS, wp_json_encode( $status ) );

		return array(
			'entry_id' => $entry_id,
			'action'   => $action,
			'status'   => $status,
			'success'  => true,
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => true,
		);
	}
}

return new Form_Manage_Entries();
