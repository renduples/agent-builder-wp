<?php
/**
 * Tool: form_set_notifications
 *
 * Configure email notification settings for a native agentic_form.
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
 * Configure email notifications for a native form.
 *
 * Allows enabling/disabling admin notifications, adding custom recipients,
 * and configuring a submitter confirmation email with custom subject and body.
 */
class Form_Set_Notifications extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_set_notifications';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Configure email notification settings for a native form. ' .
			'Set admin notifications, custom recipients, and submitter confirmation emails.';
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
				'form_id'              => array(
					'type'        => 'integer',
					'description' => 'The ID of the native form to configure notifications for.',
				),
				'admin_enabled'        => array(
					'type'        => 'boolean',
					'description' => 'Whether to send a notification to the site admin on each submission. Defaults to true.',
					'default'     => true,
				),
				'recipients'           => array(
					'type'        => 'array',
					'description' => 'Array of additional email addresses to receive submission notifications.',
					'items'       => array( 'type' => 'string' ),
				),
				'confirmation_enabled' => array(
					'type'        => 'boolean',
					'description' => 'Whether to send a confirmation email to the submitter.',
				),
				'confirmation_subject' => array(
					'type'        => 'string',
					'description' => 'Subject line for the submitter confirmation email.',
				),
				'confirmation_body'    => array(
					'type'        => 'string',
					'description' => 'Body text for the submitter confirmation email.',
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
			return array( 'error' => 'You do not have permission to configure form notifications.' );
		}

		$form_id = absint( $arguments['form_id'] ?? 0 );

		if ( 0 === $form_id ) {
			return array( 'error' => 'A form_id is required.' );
		}

		$post = get_post( $form_id );
		if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
			return array( 'error' => 'Native form with ID ' . $form_id . ' was not found.' );
		}

		$engine  = \Agentic_Native_Forms::get_instance();
		$current = $engine->get_notification_config( $form_id );

		// Merge provided values over current config.
		$config = array(
			'admin_enabled'        => (bool) ( $arguments['admin_enabled'] ?? $current['admin_enabled'] ),
			'recipients'           => array(),
			'confirmation_enabled' => (bool) ( $arguments['confirmation_enabled'] ?? $current['confirmation_enabled'] ),
			'confirmation_subject' => sanitize_text_field( $arguments['confirmation_subject'] ?? $current['confirmation_subject'] ),
			'confirmation_body'    => sanitize_textarea_field( $arguments['confirmation_body'] ?? $current['confirmation_body'] ),
		);

		// Sanitise recipient emails.
		$raw_recipients = $arguments['recipients'] ?? $current['recipients'];
		if ( is_array( $raw_recipients ) ) {
			foreach ( $raw_recipients as $email ) {
				$clean = sanitize_email( (string) $email );
				if ( is_email( $clean ) ) {
					$config['recipients'][] = $clean;
				}
			}
		}

		update_post_meta( $form_id, \Agentic_Native_Forms::META_NOTIFICATIONS, wp_json_encode( $config ) );

		return array(
			'form_id'       => $form_id,
			'form_title'    => $post->post_title,
			'notifications' => $config,
			'success'       => true,
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
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Form_Set_Notifications();
