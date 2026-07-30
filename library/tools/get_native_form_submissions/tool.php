<?php
/**
 * Tool: get_native_form_submissions
 *
 * Lists entries submitted through a native agentic_form shortcode form.
 *
 * Agent-agnostic: any agent that deals with form data can use this tool
 * to read submissions without knowing the underlying storage details.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.5.0
 *
 * php version 8.1
 */

declare( strict_types=1 );

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieve entries submitted through a native agentic_form.
 */
class Get_Native_Form_Submissions extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_native_form_submissions';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Retrieve submissions (entries) for a native agentic_form. ' .
			'Returns paginated entry data including all submitted field values and submission timestamps.';
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
					'description' => 'The ID of the native form (as returned by save_native_form).',
				),
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Number of entries to return per page. Defaults to 20, max 100.',
					'default'     => 20,
				),
				'page'     => array(
					'type'        => 'integer',
					'description' => 'Page number (1-based). Defaults to 1.',
					'default'     => 1,
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
			return array( 'error' => 'You do not have permission to view form submissions.' );
		}

		$form_id  = absint( $arguments['form_id'] ?? 0 );
		$per_page = min( absint( $arguments['per_page'] ?? 20 ), 100 );
		$page     = max( 1, absint( $arguments['page'] ?? 1 ) );

		if ( 0 === $form_id ) {
			return array( 'error' => 'A form_id is required.' );
		}

		$engine = \Agentic_Native_Forms::get_instance();

		// Verify the form exists.
		$post = get_post( $form_id );
		if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
			return array( 'error' => 'Native form with ID ' . $form_id . ' was not found. Make sure you are using the ID returned by save_native_form.' );
		}

		$result = $engine->get_entries( $form_id, $per_page, $page );

		return array(
			'form_id'    => $form_id,
			'form_title' => $post->post_title,
			'total'      => $result['total'],
			'page'       => $page,
			'per_page'   => $per_page,
			'entries'    => $result['entries'],
		);
	}
}

return new Get_Native_Form_Submissions();
