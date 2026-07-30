<?php
/**
 * Tool: form_set_webhook
 *
 * Configure a webhook URL for a native form so that submissions
 * are forwarded to an external endpoint via POST.
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
 * Configure webhook settings for a native agentic_form.
 */
class Form_Set_Webhook extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_set_webhook';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Configure a webhook for a native form. When a submission is received, ' .
			'the entry payload will be POSTed to the specified URL. ' .
			'Optionally include an HMAC signing secret for payload verification. ' .
			'Set enabled=false or pass an empty URL to remove the webhook.';
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
					'description' => 'The ID of the native form to configure.',
				),
				'url'     => array(
					'type'        => 'string',
					'description' => 'The webhook URL that will receive POST requests on submission.',
				),
				'secret'  => array(
					'type'        => 'string',
					'description' => 'Optional HMAC signing secret used to sign the payload for verification.',
				),
				'enabled' => array(
					'type'        => 'boolean',
					'description' => 'Whether the webhook is enabled. Defaults to true. Set to false to remove the webhook.',
					'default'     => true,
				),
			),
			'required'   => array( 'form_id', 'url' ),
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
			return array( 'error' => 'You do not have permission to configure form webhooks.' );
		}

		$form_id = absint( $arguments['form_id'] ?? 0 );
		$url     = trim( (string) ( $arguments['url'] ?? '' ) );
		$secret  = sanitize_text_field( $arguments['secret'] ?? '' );
		$enabled = (bool) ( $arguments['enabled'] ?? true );

		if ( 0 === $form_id ) {
			return array( 'error' => 'A form_id is required.' );
		}

		$post = get_post( $form_id );
		if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
			return array( 'error' => 'Native form with ID ' . $form_id . ' was not found.' );
		}

		// If disabled or URL is empty, remove the webhook.
		if ( ! $enabled || '' === $url ) {
			delete_post_meta( $form_id, \Agentic_Native_Forms::META_WEBHOOK );

			return array(
				'form_id' => $form_id,
				'webhook' => null,
				'message' => 'Webhook removed from form ' . $form_id . '.',
			);
		}

		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array( 'error' => 'The provided URL is not valid.' );
		}

		$config = array(
			'url'    => esc_url_raw( $url ),
			'secret' => $secret,
		);

		update_post_meta( $form_id, \Agentic_Native_Forms::META_WEBHOOK, wp_json_encode( $config ) );

		return array(
			'form_id' => $form_id,
			'webhook' => $config,
			'message' => 'Webhook configured for form ' . $form_id . '.',
		);
	}
}

return new Form_Set_Webhook();
