<?php
/**
 * Tool: get_form_stats
 *
 * Return submission statistics for one or all forms.
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
 * Get submission statistics for a specific form or all forms.
 *
 * Returns total entries, entries in the last 7 and 30 days, and the most
 * active form when no form_id is provided.
 */
class Get_Form_Stats extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_form_stats';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get submission statistics for a form or all forms: total entries, last 7/30 days, and most active form.';
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
					'description' => 'Optional. Form ID to get stats for. Omit to get aggregate stats across all forms.',
				),
				'plugin'  => array(
					'type'        => 'string',
					'description' => 'Plugin slug: contact-form-7, wpforms, gravityforms, fluentform. Omit to auto-detect.',
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
		$form_id = isset( $arguments['form_id'] ) ? (int) $arguments['form_id'] : null;
		$plugin  = sanitize_key( $arguments['plugin'] ?? '' );

		if ( '' === $plugin ) {
			$plugin = $this->detect_plugin();
		}

		switch ( $plugin ) {
			case 'contact-form-7':
				return $this->stats_cf7( $form_id );
			case 'wpforms':
				return $this->stats_wpforms( $form_id );
			case 'gravityforms':
				return $this->stats_gravity( $form_id );
			case 'fluentform':
				return $this->stats_fluent( $form_id );
			default:
				return array( 'error' => 'No supported form plugin detected. Specify the "plugin" parameter explicitly.' );
		}
	}

	// -------------------------------------------------------------------------
	// Plugin-specific stats implementations
	// -------------------------------------------------------------------------

	/**
	 * Get stats from Flamingo (CF7 entries).
	 *
	 * @param int|null $form_id Optional form ID (unused — Flamingo doesn't link to form ID).
	 * @return array
	 */
	private function stats_cf7( ?int $form_id ): array {
		if ( ! post_type_exists( 'flamingo_inbound' ) ) {
			return array(
				'plugin' => 'contact-form-7',
				'note'   => 'Flamingo plugin required for entry tracking.',
				'total'  => 0,
			);
		}

		$date_7  = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$date_30 = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		$total = wp_count_posts( 'flamingo_inbound' );
		$total = ( $total->publish ?? 0 ) + ( $total->private ?? 0 );

		$last_7 = (int) ( new \WP_Query(
			array(
				'post_type'      => 'flamingo_inbound',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'date_query'     => array( array( 'after' => $date_7 ) ),
				'fields'         => 'ids',
			)
		) )->found_posts;

		$last_30 = (int) ( new \WP_Query(
			array(
				'post_type'      => 'flamingo_inbound',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'date_query'     => array( array( 'after' => $date_30 ) ),
				'fields'         => 'ids',
			)
		) )->found_posts;

		return array(
			'plugin'               => 'contact-form-7',
			'form_id'              => $form_id,
			'total_entries'        => (int) $total,
			'entries_last_7_days'  => $last_7,
			'entries_last_30_days' => $last_30,
		);
	}

	/**
	 * Get WPForms entry stats.
	 *
	 * @param int|null $form_id Optional form ID.
	 * @return array
	 */
	private function stats_wpforms( ?int $form_id ): array {
		global $wpdb;

		$entries_table = $wpdb->prefix . 'wpforms_entries';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $entries_table ) ) !== $entries_table ) {
			return array( 'error' => 'WPForms is not active or entries table is missing.' );
		}

		$date_7  = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$date_30 = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		if ( null !== $form_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries_table} WHERE form_id = %d", $form_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_7 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries_table} WHERE form_id = %d AND date > %s", $form_id, $date_7 ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_30 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries_table} WHERE form_id = %d AND date > %s", $form_id, $date_30 ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$entries_table}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_7 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries_table} WHERE date > %s", $date_7 ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_30 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries_table} WHERE date > %s", $date_30 ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$top = $wpdb->get_row( "SELECT form_id, COUNT(*) as cnt FROM {$entries_table} GROUP BY form_id ORDER BY cnt DESC LIMIT 1", ARRAY_A );
		}

		$result = array(
			'plugin'               => 'wpforms',
			'form_id'              => $form_id,
			'total_entries'        => $total,
			'entries_last_7_days'  => $last_7,
			'entries_last_30_days' => $last_30,
		);

		if ( null === $form_id && ! empty( $top ) ) {
			$result['most_active_form'] = array(
				'form_id' => (int) $top['form_id'],
				'total'   => (int) $top['cnt'],
			);
		}

		return $result;
	}

	/**
	 * Get Gravity Forms entry stats.
	 *
	 * @param int|null $form_id Optional form ID.
	 * @return array
	 */
	private function stats_gravity( ?int $form_id ): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array( 'error' => 'Gravity Forms is not active.' );
		}

		$date_7  = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$date_30 = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		if ( null !== $form_id ) {
			$total   = \GFAPI::count_entries( $form_id );
			$last_7  = \GFAPI::count_entries( $form_id, array( 'start_date' => $date_7 ) );
			$last_30 = \GFAPI::count_entries( $form_id, array( 'start_date' => $date_30 ) );
			$total   = is_wp_error( $total ) ? 0 : (int) $total;
			$last_7  = is_wp_error( $last_7 ) ? 0 : (int) $last_7;
			$last_30 = is_wp_error( $last_30 ) ? 0 : (int) $last_30;
		} else {
			$forms   = \GFAPI::get_forms( true, false );
			$total   = 0;
			$last_7  = 0;
			$last_30 = 0;
			$top_id  = null;
			$top_cnt = 0;

			foreach ( $forms as $form ) {
				$cnt    = \GFAPI::count_entries( $form['id'] );
				$cnt    = is_wp_error( $cnt ) ? 0 : (int) $cnt;
				$total += $cnt;
				if ( $cnt > $top_cnt ) {
					$top_cnt = $cnt;
					$top_id  = $form['id'];
				}
				$c7       = \GFAPI::count_entries( $form['id'], array( 'start_date' => $date_7 ) );
				$c30      = \GFAPI::count_entries( $form['id'], array( 'start_date' => $date_30 ) );
				$last_7  += is_wp_error( $c7 ) ? 0 : (int) $c7;
				$last_30 += is_wp_error( $c30 ) ? 0 : (int) $c30;
			}
		}

		$result = array(
			'plugin'               => 'gravityforms',
			'form_id'              => $form_id,
			'total_entries'        => $total,
			'entries_last_7_days'  => $last_7,
			'entries_last_30_days' => $last_30,
		);

		if ( null === $form_id && isset( $top_id ) ) {
			$result['most_active_form'] = array(
				'form_id' => (int) $top_id,
				'total'   => $top_cnt,
			);
		}

		return $result;
	}

	/**
	 * Get Fluent Forms submission stats.
	 *
	 * @param int|null $form_id Optional form ID.
	 * @return array
	 */
	private function stats_fluent( ?int $form_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array( 'error' => 'Fluent Forms is not active or its submissions table is missing.' );
		}

		$date_7  = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$date_30 = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		if ( null !== $form_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %d", $form_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_7 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %d AND created_at > %s", $form_id, $date_7 ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_30 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %d AND created_at > %s", $form_id, $date_30 ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_7 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at > %s", $date_7 ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_30 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at > %s", $date_30 ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$top = $wpdb->get_row( "SELECT form_id, COUNT(*) as cnt FROM {$table} GROUP BY form_id ORDER BY cnt DESC LIMIT 1", ARRAY_A );
		}

		$result = array(
			'plugin'               => 'fluentform',
			'form_id'              => $form_id,
			'total_entries'        => $total,
			'entries_last_7_days'  => $last_7,
			'entries_last_30_days' => $last_30,
		);

		if ( null === $form_id && ! empty( $top ) ) {
			$result['most_active_form'] = array(
				'form_id' => (int) $top['form_id'],
				'total'   => (int) $top['cnt'],
			);
		}

		return $result;
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

return new Get_Form_Stats();
