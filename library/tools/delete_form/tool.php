<?php
/**
 * Tool: delete_form
 *
 * Delete a form and optionally all its associated entries.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.3.0
 *
 * php version 8.1
 */

declare( strict_types=1 );

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delete a WordPress form permanently.
 *
 * Requires manage_options capability. Optionally deletes all associated
 * submissions / entries from the database.
 */
class Delete_Form extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'delete_form';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Permanently delete a WordPress form. Set delete_entries to true to also remove all associated submissions.';
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
				'form_id'        => array(
					'type'        => 'integer',
					'description' => 'The ID of the form to delete.',
				),
				'plugin'         => array(
					'type'        => 'string',
					'description' => 'Plugin slug: contact-form-7, wpforms, gravityforms, fluentform. Omit to auto-detect.',
				),
				'delete_entries' => array(
					'type'        => 'boolean',
					'description' => 'Whether to also delete all form entries/submissions. Defaults to false.',
				),
			),
			'required'   => array( 'form_id' ),
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
			return array( 'error' => 'You do not have permission to delete forms.' );
		}

		$form_id        = (int) ( $arguments['form_id'] ?? 0 );
		$plugin         = sanitize_key( $arguments['plugin'] ?? '' );
		$delete_entries = (bool) ( $arguments['delete_entries'] ?? false );

		if ( $form_id <= 0 ) {
			return array( 'error' => 'form_id is required and must be a positive integer.' );
		}

		if ( '' === $plugin ) {
			$plugin = $this->detect_plugin();
		}

		switch ( $plugin ) {
			case 'contact-form-7':
				return $this->delete_cf7( $form_id, $delete_entries );
			case 'wpforms':
				return $this->delete_wpforms( $form_id, $delete_entries );
			case 'gravityforms':
				return $this->delete_gravity( $form_id, $delete_entries );
			case 'fluentform':
				return $this->delete_fluent( $form_id, $delete_entries );
			default:
				return array( 'error' => 'No supported form plugin detected. Specify the "plugin" parameter explicitly.' );
		}
	}

	// -------------------------------------------------------------------------
	// Plugin-specific delete implementations
	// -------------------------------------------------------------------------

	/**
	 * Delete a Contact Form 7 form.
	 *
	 * @param int  $form_id        Post ID.
	 * @param bool $delete_entries Whether to delete entries (requires Flamingo).
	 * @return array
	 */
	private function delete_cf7( int $form_id, bool $delete_entries ): array {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return array( 'error' => 'Contact Form 7 is not active.' );
		}

		$post = get_post( $form_id );
		if ( ! $post || 'wpcf7_contact_form' !== $post->post_type ) {
			return array( 'error' => "Contact Form 7 form ID {$form_id} not found." );
		}

		$entries_deleted = 0;

		if ( $delete_entries && post_type_exists( 'flamingo_inbound' ) ) {
			$flamingo_posts = get_posts(
				array(
					'post_type'      => 'flamingo_inbound',
					'posts_per_page' => -1,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for meta query.
					'meta_key'       => '_channel',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for meta query.
					'meta_value'     => 'contact-form-7',
					'fields'         => 'ids',
				)
			);
			foreach ( $flamingo_posts as $fp_id ) {
				if ( wp_delete_post( (int) $fp_id, true ) ) {
					++$entries_deleted;
				}
			}
		}

		$result = wp_delete_post( $form_id, true );

		return array(
			'plugin'          => 'contact-form-7',
			'id'              => $form_id,
			'deleted'         => (bool) $result,
			'entries_deleted' => $entries_deleted,
		);
	}

	/**
	 * Delete a WPForms form.
	 *
	 * @param int  $form_id        Post ID.
	 * @param bool $delete_entries Whether to delete entries.
	 * @return array
	 */
	private function delete_wpforms( int $form_id, bool $delete_entries ): array {
		if ( ! function_exists( 'wpforms' ) ) {
			return array( 'error' => 'WPForms is not active.' );
		}

		$form = wpforms()->form->get( $form_id );
		if ( ! $form ) {
			return array( 'error' => "WPForms form ID {$form_id} not found." );
		}

		$entries_deleted = 0;

		if ( $delete_entries && method_exists( wpforms()->entry, 'delete_by' ) ) {
			$entries = wpforms()->entry->get_entries( array( 'form_id' => $form_id ) );
			foreach ( $entries as $entry ) {
				wpforms()->entry->delete( $entry->entry_id );
				++$entries_deleted;
			}
		}

		$result = wpforms()->form->delete( $form_id );

		return array(
			'plugin'          => 'wpforms',
			'id'              => $form_id,
			'deleted'         => (bool) $result,
			'entries_deleted' => $entries_deleted,
		);
	}

	/**
	 * Delete a Gravity Forms form.
	 *
	 * @param int  $form_id        GF form ID.
	 * @param bool $delete_entries Whether to delete entries.
	 * @return array
	 */
	private function delete_gravity( int $form_id, bool $delete_entries ): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array( 'error' => 'Gravity Forms is not active.' );
		}

		$form = \GFAPI::get_form( $form_id );
		if ( ! $form || is_wp_error( $form ) ) {
			return array( 'error' => "Gravity Forms form ID {$form_id} not found." );
		}

		$entries_deleted = 0;

		if ( $delete_entries ) {
			$entries = \GFAPI::get_entries( $form_id );
			if ( is_array( $entries ) ) {
				foreach ( $entries as $entry ) {
					\GFAPI::delete_entry( $entry['id'] );
					++$entries_deleted;
				}
			}
		}

		$result = \GFAPI::delete_form( $form_id );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array(
			'plugin'          => 'gravityforms',
			'id'              => $form_id,
			'deleted'         => true,
			'entries_deleted' => $entries_deleted,
		);
	}

	/**
	 * Delete a Fluent Forms form.
	 *
	 * @param int  $form_id        FF form ID.
	 * @param bool $delete_entries Whether to delete entries.
	 * @return array
	 */
	private function delete_fluent( int $form_id, bool $delete_entries ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array( 'error' => 'Fluent Forms is not active or its tables are missing.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$form = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $form_id ) );
		if ( ! $form ) {
			return array( 'error' => "Fluent Forms form ID {$form_id} not found." );
		}

		$entries_deleted = 0;

		if ( $delete_entries ) {
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$subs_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $submissions_table ) );
			if ( $subs_exist ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$entries_deleted = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->prepare( "SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d", $form_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->delete( $submissions_table, array( 'form_id' => $form_id ), array( '%d' ) );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->delete( $table, array( 'id' => $form_id ), array( '%d' ) );

		return array(
			'plugin'          => 'fluentform',
			'id'              => $form_id,
			'deleted'         => false !== $result,
			'entries_deleted' => $entries_deleted,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Auto-detect the primary active form plugin.
	 *
	 * @return string Plugin slug or empty string if none found.
	 */
	private function detect_plugin(): string {
		if ( class_exists( 'WPCF7' ) ) {
			return 'contact-form-7';
		}
		if ( function_exists( 'wpforms' ) ) {
			return 'wpforms';
		}
		if ( class_exists( 'GFAPI' ) ) {
			return 'gravityforms';
		}
		if ( function_exists( 'wpFluentForm' ) ) {
			return 'fluentform';
		}
		return '';
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
			'idempotent'  => false,
		);
	}
}

return new Delete_Form();
