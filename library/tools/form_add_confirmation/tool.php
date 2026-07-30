<?php
/**
 * Tool: form_add_confirmation
 *
 * Configure what happens after a native form submission — either
 * display a confirmation message or redirect to a URL.
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
 * Configure post-submission confirmation for a native agentic_form.
 */
class Form_Add_Confirmation extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_add_confirmation';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Configure what happens after a native form is submitted. ' .
			'Choose "message" to display a thank-you message, or "redirect" to send the user to another URL.';
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
				'form_id'      => array(
					'type'        => 'integer',
					'description' => 'The ID of the native form to configure.',
				),
				'type'         => array(
					'type'        => 'string',
					'description' => 'Confirmation type: "message" to show a text message, or "redirect" to send the user to a URL.',
					'enum'        => array( 'message', 'redirect' ),
				),
				'message'      => array(
					'type'        => 'string',
					'description' => 'The confirmation message to display (used when type is "message").',
				),
				'redirect_url' => array(
					'type'        => 'string',
					'description' => 'The URL to redirect to after submission (used when type is "redirect").',
				),
			),
			'required'   => array( 'form_id', 'type' ),
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
			return array( 'error' => 'You do not have permission to configure form confirmations.' );
		}

		$form_id      = absint( $arguments['form_id'] ?? 0 );
		$type         = sanitize_key( $arguments['type'] ?? '' );
		$message      = sanitize_text_field( $arguments['message'] ?? '' );
		$redirect_url = esc_url_raw( $arguments['redirect_url'] ?? '' );

		if ( 0 === $form_id ) {
			return array( 'error' => 'A form_id is required.' );
		}

		if ( ! in_array( $type, array( 'message', 'redirect' ), true ) ) {
			return array( 'error' => 'Type must be "message" or "redirect".' );
		}

		$post = get_post( $form_id );
		if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
			return array( 'error' => 'Native form with ID ' . $form_id . ' was not found.' );
		}

		if ( 'message' === $type && '' === $message ) {
			return array( 'error' => 'A message is required when type is "message".' );
		}

		if ( 'redirect' === $type && '' === $redirect_url ) {
			return array( 'error' => 'A redirect_url is required when type is "redirect".' );
		}

		// Load the existing definition and update confirmation keys.
		$engine     = \Agentic_Native_Forms::get_instance();
		$definition = $engine->get_definition( $form_id );

		if ( ! is_array( $definition ) ) {
			return array( 'error' => 'Could not load form definition for form ' . $form_id . '.' );
		}

		$definition['confirmation'] = $type;

		if ( 'message' === $type ) {
			$definition['confirmation_message'] = $message;
			// Clear redirect if switching types.
			unset( $definition['confirmation_redirect'] );
		} else {
			$definition['confirmation_redirect'] = $redirect_url;
			// Keep the message as fallback but set type to redirect.
		}

		update_post_meta( $form_id, \Agentic_Native_Forms::META_DEFINITION, wp_json_encode( $definition ) );

		$result = array(
			'form_id'      => $form_id,
			'confirmation' => $type,
		);

		if ( 'message' === $type ) {
			$result['confirmation_message'] = $message;
			$result['message']              = 'Confirmation message set for form ' . $form_id . '.';
		} else {
			$result['redirect_url'] = $redirect_url;
			$result['message']      = 'Redirect URL set for form ' . $form_id . '.';
		}

		return $result;
	}
}

return new Form_Add_Confirmation();
