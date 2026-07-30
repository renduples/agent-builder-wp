<?php
/**
 * Tool: get_form_entries
 *
 * Retrieve paginated form submissions from the active form plugin.
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
 * Retrieve form entries / submissions.
 *
 * Supports Contact Form 7 (via Flamingo), WPForms, Gravity Forms, and Fluent Forms.
 * Returns paginated results with field key/value pairs per entry.
 */
class Get_Form_Entries extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_form_entries';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Retrieve form submissions/entries. Supports WPForms, Gravity Forms, Fluent Forms, and Contact Form 7 with Flamingo.';
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
				'form_id' => array(
					'type'        => 'integer',
					'description' => 'The form ID to get entries for.',
				),
				'plugin'  => array(
					'type'        => 'string',
					'description' => 'Plugin slug: contact-form-7, wpforms, gravityforms, fluentform. Omit to auto-detect.',
				),
				'limit'   => array(
					'type'        => 'integer',
					'description' => 'Max entries to return. Default 20, max 100.',
				),
				'offset'  => array(
					'type'        => 'integer',
					'description' => 'Entries to skip for pagination. Default 0.',
				),
				'status'  => array(
					'type'        => 'string',
					'description' => 'Filter by status: all, read, unread. Default all. (Gravity Forms / WPForms only.)',
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
		$form_id = (int) ( $arguments['form_id'] ?? 0 );
		$plugin  = sanitize_key( $arguments['plugin'] ?? '' );
		$limit   = max( 1, min( 100, (int) ( $arguments['limit'] ?? 20 ) ) );
		$offset  = max( 0, (int) ( $arguments['offset'] ?? 0 ) );
		$status  = sanitize_key( $arguments['status'] ?? 'all' );

		if ( $form_id <= 0 ) {
			return array( 'error' => 'form_id is required and must be a positive integer.' );
		}

		if ( '' === $plugin ) {
			$plugin = $this->detect_plugin();
		}

		switch ( $plugin ) {
			case 'contact-form-7':
				return $this->entries_cf7( $form_id, $limit, $offset );
			case 'wpforms':
				return $this->entries_wpforms( $form_id, $limit, $offset, $status );
			case 'gravityforms':
				return $this->entries_gravity( $form_id, $limit, $offset, $status );
			case 'fluentform':
				return $this->entries_fluent( $form_id, $limit, $offset );
			default:
				return array( 'error' => 'No supported form plugin detected. Specify the "plugin" parameter explicitly.' );
		}
	}

	// -------------------------------------------------------------------------
	// Plugin-specific implementations
	// -------------------------------------------------------------------------

	/**
	 * Get CF7 entries via Flamingo.
	 *
	 * @param int $form_id CF7 post ID.
	 * @param int $limit   Max results.
	 * @param int $offset  Pagination offset.
	 * @return array
	 */
	private function entries_cf7( int $form_id, int $limit, int $offset ): array {
		if ( ! post_type_exists( 'flamingo_inbound' ) ) {
			return array(
				'error' => 'Contact Form 7 does not store entries natively. Install the Flamingo plugin to enable entry storage.',
			);
		}

		$args    = array(
			'post_type'      => 'flamingo_inbound',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for meta query.
			'meta_key'       => '_source_url',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$posts   = get_posts( $args );
		$entries = array();

		foreach ( $posts as $post ) {
			$fields    = get_post_meta( $post->ID, '_fields', true );
			$entries[] = array(
				'id'         => $post->ID,
				'date'       => $post->post_date,
				'source_url' => get_post_meta( $post->ID, '_source_url', true ),
				'fields'     => is_array( $fields ) ? $fields : array(),
			);
		}

		return array(
			'plugin'  => 'contact-form-7',
			'form_id' => $form_id,
			'entries' => $entries,
			'count'   => count( $entries ),
			'offset'  => $offset,
		);
	}

	/**
	 * Get WPForms entries.
	 *
	 * @param int    $form_id WPForms form post ID.
	 * @param int    $limit   Max results.
	 * @param int    $offset  Pagination offset.
	 * @param string $status  Entry status filter.
	 * @return array
	 */
	private function entries_wpforms( int $form_id, int $limit, int $offset, string $status ): array {
		if ( ! function_exists( 'wpforms' ) ) {
			return array( 'error' => 'WPForms is not active.' );
		}

		if ( ! method_exists( wpforms()->entry, 'get_entries' ) ) {
			return array( 'error' => 'WPForms entry API is not available. WPForms Pro is required for entry access.' );
		}

		$query = array(
			'form_id' => $form_id,
			'number'  => $limit,
			'offset'  => $offset,
			'order'   => 'DESC',
			'orderby' => 'entry_id',
		);

		if ( 'all' !== $status && in_array( $status, array( 'read', 'unread' ), true ) ) {
			$query['viewed'] = 'read' === $status ? '1' : '0';
		}

		$raw     = wpforms()->entry->get_entries( $query );
		$entries = array();

		foreach ( $raw as $entry ) {
			$fields = wpforms_decode( $entry->fields );
			$parsed = array();
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					$parsed[ sanitize_text_field( $field['name'] ?? $field['id'] ?? '' ) ] = $field['value'] ?? '';
				}
			}
			$entries[] = array(
				'id'     => $entry->entry_id,
				'date'   => $entry->date,
				'status' => $entry->viewed ? 'read' : 'unread',
				'fields' => $parsed,
			);
		}

		return array(
			'plugin'  => 'wpforms',
			'form_id' => $form_id,
			'entries' => $entries,
			'count'   => count( $entries ),
			'offset'  => $offset,
		);
	}

	/**
	 * Get Gravity Forms entries.
	 *
	 * @param int    $form_id GF form ID.
	 * @param int    $limit   Max results.
	 * @param int    $offset  Pagination offset.
	 * @param string $status  Entry status filter.
	 * @return array
	 */
	private function entries_gravity( int $form_id, int $limit, int $offset, string $status ): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array( 'error' => 'Gravity Forms is not active.' );
		}

		$search = array();
		if ( 'all' !== $status && in_array( $status, array( 'read', 'unread' ), true ) ) {
			$search['field_filters'] = array(
				array(
					'key'   => 'is_read',
					'value' => 'read' === $status ? '1' : '0',
				),
			);
		}

		$paging = array(
			'page_size' => $limit,
			'offset'    => $offset,
		);
		$raw    = \GFAPI::get_entries(
			$form_id,
			$search,
			array(
				'key'       => 'date_created',
				'direction' => 'DESC',
			),
			$paging
		);

		if ( is_wp_error( $raw ) ) {
			return array( 'error' => $raw->get_error_message() );
		}

		$form    = \GFAPI::get_form( $form_id );
		$entries = array();

		foreach ( $raw as $entry ) {
			$parsed = array();
			if ( ! empty( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					$val = $entry[ $field->id ] ?? '';
					if ( '' !== $val ) {
						$parsed[ sanitize_text_field( $field->label ) ] = $val;
					}
				}
			}
			$entries[] = array(
				'id'     => $entry['id'],
				'date'   => $entry['date_created'],
				'status' => $entry['is_read'] ? 'read' : 'unread',
				'fields' => $parsed,
			);
		}

		return array(
			'plugin'  => 'gravityforms',
			'form_id' => $form_id,
			'entries' => $entries,
			'count'   => count( $entries ),
			'offset'  => $offset,
		);
	}

	/**
	 * Get Fluent Forms entries.
	 *
	 * @param int $form_id FF form ID.
	 * @param int $limit   Max results.
	 * @param int $offset  Pagination offset.
	 * @return array
	 */
	private function entries_fluent( int $form_id, int $limit, int $offset ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array( 'error' => 'Fluent Forms submission table not found.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$raw = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				"SELECT id, response, status, created_at FROM {$table} WHERE form_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$form_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		$entries = array();
		foreach ( $raw as $row ) {
			$response  = json_decode( $row['response'] ?? '{}', true );
			$fields    = is_array( $response ) ? $response : array();
			$entries[] = array(
				'id'     => (int) $row['id'],
				'date'   => $row['created_at'],
				'status' => $row['status'],
				'fields' => $fields,
			);
		}

		return array(
			'plugin'  => 'fluentform',
			'form_id' => $form_id,
			'entries' => $entries,
			'count'   => count( $entries ),
			'offset'  => $offset,
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
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Get_Form_Entries();
