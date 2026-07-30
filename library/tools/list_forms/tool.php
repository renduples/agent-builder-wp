<?php
/**
 * Tool: list_forms
 *
 * List all forms from the active WordPress form plugin.
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
 * List all forms from the active form plugin.
 *
 * Supports Contact Form 7, WPForms, Gravity Forms, Fluent Forms, and Ninja Forms.
 * Returns each form's ID, title, shortcode, and entry count where available.
 */
class List_Forms extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'list_forms';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'List all forms from the active WordPress form plugin. Returns form IDs, titles, shortcodes, and entry counts.';
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
				'plugin' => array(
					'type'        => 'string',
					'description' => 'Plugin slug: contact-form-7, wpforms, gravityforms, fluentform. Omit to auto-detect.',
				),
				'limit'  => array(
					'type'        => 'integer',
					'description' => 'Maximum forms to return. Default 50.',
				),
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		$plugin = sanitize_key( $arguments['plugin'] ?? '' );
		$limit  = max( 1, min( 200, (int) ( $arguments['limit'] ?? 50 ) ) );

		if ( '' === $plugin ) {
			$plugin = $this->detect_plugin();
		}

		switch ( $plugin ) {
			case 'contact-form-7':
				return $this->list_cf7( $limit );
			case 'wpforms':
				return $this->list_wpforms( $limit );
			case 'gravityforms':
				return $this->list_gravity( $limit );
			case 'fluentform':
				return $this->list_fluent( $limit );
			case 'ninja-forms':
				return $this->list_ninja( $limit );
			default:
				return array( 'error' => 'No supported form plugin detected. Run detect_form_plugins first.' );
		}
	}

	// -------------------------------------------------------------------------
	// Plugin-specific implementations
	// -------------------------------------------------------------------------

	/**
	 * List Contact Form 7 forms.
	 *
	 * @param int $limit Max forms to return.
	 * @return array
	 */
	private function list_cf7( int $limit ): array {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return array( 'error' => 'Contact Form 7 is not active.' );
		}

		$args  = array(
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		$posts = get_posts( $args );
		$forms = array();

		foreach ( $posts as $post ) {
			$forms[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'shortcode' => '[contact-form-7 id="' . $post->ID . '" title="' . esc_attr( $post->post_title ) . '"]',
				'modified'  => $post->post_modified,
			);
		}

		return array(
			'plugin' => 'contact-form-7',
			'forms'  => $forms,
			'count'  => count( $forms ),
		);
	}

	/**
	 * List WPForms forms.
	 *
	 * @param int $limit Max forms to return.
	 * @return array
	 */
	private function list_wpforms( int $limit ): array {
		if ( ! function_exists( 'wpforms' ) ) {
			return array( 'error' => 'WPForms is not active.' );
		}

		$raw_forms = wpforms()->form->get(
			'',
			array(
				'orderby' => 'title',
				'order'   => 'ASC',
				'number'  => $limit,
			)
		);

		if ( empty( $raw_forms ) ) {
			return array(
				'plugin' => 'wpforms',
				'forms'  => array(),
				'count'  => 0,
			);
		}

		$forms = array();
		foreach ( $raw_forms as $form ) {
			$entries    = 0;
			$entry_data = null;
			if ( function_exists( 'wpforms()->entry' ) ) {
				$entry_data = wpforms()->entry->get_entries( array( 'form_id' => $form->ID ) );
				$entries    = is_array( $entry_data ) ? count( $entry_data ) : 0;
			}

			$forms[] = array(
				'id'          => $form->ID,
				'title'       => $form->post_title,
				'shortcode'   => '[wpforms id="' . $form->ID . '"]',
				'entry_count' => $entries,
				'modified'    => $form->post_modified,
			);
		}

		return array(
			'plugin' => 'wpforms',
			'forms'  => $forms,
			'count'  => count( $forms ),
		);
	}

	/**
	 * List Gravity Forms forms.
	 *
	 * @param int $limit Max forms to return.
	 * @return array
	 */
	private function list_gravity( int $limit ): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array( 'error' => 'Gravity Forms is not active.' );
		}

		$raw_forms = \GFAPI::get_forms( true, false, 'title', 0, $limit );
		if ( is_wp_error( $raw_forms ) ) {
			return array( 'error' => $raw_forms->get_error_message() );
		}

		$forms = array();
		foreach ( $raw_forms as $form ) {
			$entry_count = class_exists( 'GFAPI' ) ? \GFAPI::count_entries( $form['id'] ) : 0;
			$forms[]     = array(
				'id'          => $form['id'],
				'title'       => $form['title'],
				'shortcode'   => '[gravityforms id="' . $form['id'] . '"]',
				'entry_count' => is_wp_error( $entry_count ) ? 0 : (int) $entry_count,
				'modified'    => $form['date_updated'] ?? '',
			);
		}

		return array(
			'plugin' => 'gravityforms',
			'forms'  => $forms,
			'count'  => count( $forms ),
		);
	}

	/**
	 * List Fluent Forms forms.
	 *
	 * @param int $limit Max forms to return.
	 * @return array
	 */
	private function list_fluent( int $limit ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array( 'error' => 'Fluent Forms is not active or its tables are missing.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$raw = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				"SELECT id, title, status, created_at FROM {$table} ORDER BY title ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$limit
			),
			ARRAY_A
		);

		$forms = array();
		foreach ( $raw as $form ) {
			$entry_table = $wpdb->prefix . 'fluentform_submissions';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$entry_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->prepare( "SELECT COUNT(*) FROM {$entry_table} WHERE form_id = %d", (int) $form['id'] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			);
			$forms[]     = array(
				'id'          => (int) $form['id'],
				'title'       => $form['title'],
				'shortcode'   => '[fluentform id="' . $form['id'] . '"]',
				'status'      => $form['status'],
				'entry_count' => $entry_count,
				'created'     => $form['created_at'],
			);
		}

		return array(
			'plugin' => 'fluentform',
			'forms'  => $forms,
			'count'  => count( $forms ),
		);
	}

	/**
	 * List Ninja Forms forms.
	 *
	 * @param int $limit Max forms to return.
	 * @return array
	 */
	private function list_ninja( int $limit ): array {
		if ( ! class_exists( 'Ninja_Forms' ) ) {
			return array( 'error' => 'Ninja Forms is not active.' );
		}

		$raw_forms = Ninja_Forms()->form()->get_forms();
		$forms     = array();
		$i         = 0;

		foreach ( $raw_forms as $form ) {
			if ( $i >= $limit ) {
				break;
			}
			$forms[] = array(
				'id'        => $form->get_id(),
				'title'     => $form->get_setting( 'title' ),
				'shortcode' => '[ninja_form id=' . $form->get_id() . ']',
			);
			++$i;
		}

		return array(
			'plugin' => 'ninja-forms',
			'forms'  => $forms,
			'count'  => count( $forms ),
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
		if ( class_exists( 'Ninja_Forms' ) ) {
			return 'ninja-forms';
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

return new List_Forms();
