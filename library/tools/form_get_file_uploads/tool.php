<?php
/**
 * Tool: form_get_file_uploads
 *
 * List file uploads from native form entries by scanning entry
 * payloads for field values that contain WordPress upload URLs.
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
 * List file uploads from native form entries.
 */
class Form_Get_File_Uploads extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_get_file_uploads';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'List file uploads from native form entries. Scans entry payloads for field values ' .
			'that look like WordPress upload URLs (containing /wp-content/uploads/). ' .
			'Optionally filter to a specific entry.';
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
				'form_id'  => array(
					'type'        => 'integer',
					'description' => 'The ID of the native form to scan for file uploads.',
				),
				'entry_id' => array(
					'type'        => 'integer',
					'description' => 'Optional entry ID to filter results to a single submission.',
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
			return array( 'error' => 'You do not have permission to view form uploads.' );
		}

		$form_id  = absint( $arguments['form_id'] ?? 0 );
		$entry_id = absint( $arguments['entry_id'] ?? 0 );

		if ( 0 === $form_id ) {
			return array( 'error' => 'A form_id is required.' );
		}

		$post = get_post( $form_id );
		if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
			return array( 'error' => 'Native form with ID ' . $form_id . ' was not found.' );
		}

		// Query entries.
		$query_args = array(
			'post_type'      => \Agentic_Native_Forms::ENTRY_CPT,
			'post_status'    => 'publish',
			'post_parent'    => $form_id,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $entry_id > 0 ) {
			$query_args['p'] = $entry_id;
		}

		$entries = get_posts( $query_args );
		$uploads = array();

		foreach ( $entries as $entry ) {
			$payload = get_post_meta( $entry->ID, \Agentic_Native_Forms::META_PAYLOAD, true );
			$data    = is_string( $payload ) ? json_decode( $payload, true ) : $payload;

			if ( ! is_array( $data ) ) {
				continue;
			}

			foreach ( $data as $field_name => $value ) {
				$values = is_array( $value ) ? $value : array( $value );

				foreach ( $values as $val ) {
					$val = (string) $val;
					if ( str_contains( $val, '/wp-content/uploads/' ) ) {
						$uploads[] = array(
							'entry_id'       => $entry->ID,
							'field_name'     => $field_name,
							'file_url'       => $val,
							'submitted_date' => $entry->post_date,
						);
					}
				}
			}
		}

		return array(
			'form_id'      => $form_id,
			'entry_filter' => $entry_id > 0 ? $entry_id : 'all',
			'total_files'  => count( $uploads ),
			'uploads'      => $uploads,
		);
	}
}

return new Form_Get_File_Uploads();
