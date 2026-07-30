<?php
/**
 * Tool: form_get_analytics
 *
 * Retrieve submission analytics for one or all native forms,
 * including entries over time, most active day, and field summaries.
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
 * Get analytics and statistics for native form submissions.
 */
class Form_Get_Analytics extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_get_analytics';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get submission analytics for native forms. Returns total entries, entries by day, ' .
			'most active day, and field value summaries for select/radio fields. ' .
			'Omit form_id to get stats across all forms.';
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
					'description' => 'The ID of a specific native form. If omitted, returns stats for all forms.',
				),
				'period'  => array(
					'type'        => 'string',
					'description' => 'Time period to analyse: "7days", "30days", "90days", or "all". Defaults to "30days".',
					'enum'        => array( '7days', '30days', '90days', 'all' ),
					'default'     => '30days',
				),
			),
			'required'   => array(),
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
			return array( 'error' => 'You do not have permission to view form analytics.' );
		}

		$form_id = absint( $arguments['form_id'] ?? 0 );
		$period  = sanitize_key( $arguments['period'] ?? '30days' );

		$valid_periods = array( '7days', '30days', '90days', 'all' );
		if ( ! in_array( $period, $valid_periods, true ) ) {
			$period = '30days';
		}

		// Calculate the date threshold.
		$days_map   = array(
			'7days'  => 7,
			'30days' => 30,
			'90days' => 90,
			'all'    => 0,
		);
		$days       = $days_map[ $period ];
		$date_after = $days > 0 ? gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) ) : '';

		// Build query args for entries.
		$query_args = array(
			'post_type'      => \Agentic_Native_Forms::ENTRY_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		if ( $form_id > 0 ) {
			// Verify the form exists.
			$post = get_post( $form_id );
			if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
				return array( 'error' => 'Native form with ID ' . $form_id . ' was not found.' );
			}
			$query_args['post_parent'] = $form_id;
		}

		// Get total entries (all time).
		$all_entries   = new \WP_Query( $query_args );
		$total_entries = $all_entries->found_posts;

		// Get entries within the period.
		if ( '' !== $date_after ) {
			$query_args['date_query'] = array(
				array(
					'after'     => $date_after,
					'inclusive' => true,
				),
			);
		}

		$period_query      = new \WP_Query( $query_args );
		$period_entry_ids  = $period_query->posts;
		$entries_in_period = count( $period_entry_ids );

		// Build entries_by_day.
		$entries_by_day = array();
		foreach ( $period_entry_ids as $entry_id ) {
			$entry_date = get_the_date( 'Y-m-d', $entry_id );
			if ( ! isset( $entries_by_day[ $entry_date ] ) ) {
				$entries_by_day[ $entry_date ] = 0;
			}
			++$entries_by_day[ $entry_date ];
		}

		// Most active day.
		$most_active_day = '';
		if ( ! empty( $entries_by_day ) ) {
			$most_active_day = array_search( max( $entries_by_day ), $entries_by_day, true );
		}

		$result = array(
			'form_id'           => $form_id > 0 ? $form_id : 'all',
			'period'            => $period,
			'total_entries'     => $total_entries,
			'entries_in_period' => $entries_in_period,
			'entries_by_day'    => $entries_by_day,
			'most_active_day'   => $most_active_day,
		);

		// Per-form field summary for select/radio fields.
		if ( $form_id > 0 && $entries_in_period > 0 ) {
			$engine     = \Agentic_Native_Forms::get_instance();
			$definition = $engine->get_definition( $form_id );
			$fields     = $definition['fields'] ?? array();

			// Identify select/radio/checkbox fields for summarization.
			$summary_fields = array();
			foreach ( $fields as $field ) {
				$type = $field['type'] ?? '';
				if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
					$summary_fields[] = $field['name'] ?? '';
				}
			}

			if ( ! empty( $summary_fields ) ) {
				$field_summary = array();
				foreach ( $summary_fields as $field_name ) {
					$field_summary[ $field_name ] = array();
				}

				foreach ( $period_entry_ids as $entry_id ) {
					$payload = get_post_meta( $entry_id, \Agentic_Native_Forms::META_PAYLOAD, true );
					$data    = is_string( $payload ) ? json_decode( $payload, true ) : $payload;
					if ( ! is_array( $data ) ) {
						continue;
					}

					foreach ( $summary_fields as $field_name ) {
						$val = $data[ $field_name ] ?? '';
						// Payload stores { label, value } — extract the value.
						if ( is_array( $val ) && isset( $val['value'] ) ) {
							$val = $val['value'];
						} elseif ( is_array( $val ) ) {
							$val = implode( ', ', $val );
						}
						$val = (string) $val;
						if ( '' === $val ) {
							continue;
						}
						if ( ! isset( $field_summary[ $field_name ][ $val ] ) ) {
							$field_summary[ $field_name ][ $val ] = 0;
						}
						++$field_summary[ $field_name ][ $val ];
					}
				}

				// Sort each field's values by count descending.
				foreach ( $field_summary as &$counts ) {
					arsort( $counts );
				}
				unset( $counts );

				$result['field_summary'] = $field_summary;
			}
		}

		return $result;
	}
}

return new Form_Get_Analytics();
