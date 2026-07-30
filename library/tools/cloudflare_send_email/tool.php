<?php
/**
 * Cloudflare Send Email Tool (via user-deployed Worker relay)
 *
 * Sends transactional emails using Cloudflare Email Service through a Worker
 * the user has deployed in their own Cloudflare account.
 *
 * This is the recommended path for reliable, low-cost, agent-friendly
 * transactional email (drip campaigns, welcome emails, notifications, etc.).
 *
 * The actual email sending happens inside the user's Worker using the
 * native `send_email` binding — no secrets are stored in WordPress.
 *
 * @package Agentic\Tools
 */

declare(strict_types = 1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send transactional email via Cloudflare Worker.
 */
class Cloudflare_Send_Email extends \Agentic\Tool_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'cloudflare_send_email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Send a transactional email using the site\'s configured Cloudflare Email Worker relay (Cloudflare Email Service). Preferred method for drips, welcome messages, and notifications. Uses the user\'s own Cloudflare account for excellent deliverability.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_category(): string {
		return 'email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'required'   => array( 'to', 'subject' ),
			'properties' => array(
				'to'         => array(
					'type'        => 'string',
					'description' => 'Recipient email address',
				),
				'subject'    => array(
					'type'        => 'string',
					'description' => 'Email subject line',
				),
				'text'       => array(
					'type'        => 'string',
					'description' => 'Plain text body (preferred for transactional). Use this or html.',
				),
				'html'       => array(
					'type'        => 'string',
					'description' => 'HTML body. Use this or text.',
				),
				'from'       => array(
					'type'        => 'string',
					'description' => 'Optional From address (must be authorized in the Worker or Cloudflare).',
				),
				'from_name'  => array(
					'type'        => 'string',
					'description' => 'Optional From name to display.',
				),
				'reply_to'   => array(
					'type'        => 'string',
					'description' => 'Optional Reply-To address.',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $arguments ): array {
		$to      = sanitize_email( $arguments['to'] ?? '' );
		$subject = sanitize_text_field( $arguments['subject'] ?? '' );

		if ( ! $to || ! is_email( $to ) ) {
			return $this->tool_error( 'invalid_to', 'A valid "to" email address is required.' );
		}
		if ( empty( $subject ) ) {
			return $this->tool_error( 'invalid_subject', 'A subject is required.' );
		}

		$text = isset( $arguments['text'] ) ? wp_kses_post( $arguments['text'] ) : '';
		$html = isset( $arguments['html'] ) ? wp_kses_post( $arguments['html'] ) : '';

		if ( empty( $text ) && empty( $html ) ) {
			return $this->tool_error( 'missing_body', 'Either "text" or "html" body content is required.' );
		}

		$payload = array(
			'to'      => $to,
			'subject' => $subject,
		);

		if ( $text ) {
			$payload['text'] = $text;
		}
		if ( $html ) {
			$payload['html'] = $html;
		}
		if ( ! empty( $arguments['from'] ) ) {
			$payload['from'] = sanitize_email( $arguments['from'] );
		}
		if ( ! empty( $arguments['from_name'] ) ) {
			$payload['from_name'] = sanitize_text_field( $arguments['from_name'] );
		}
		if ( ! empty( $arguments['reply_to'] ) ) {
			$payload['reply_to'] = sanitize_email( $arguments['reply_to'] );
		}

		$result = \Agentic\Cloudflare_Client::send_via_worker( $payload );

		if ( ! empty( $result['success'] ) ) {
			return $this->success( array(
				'sent'       => true,
				'to'         => $to,
				'subject'    => $subject,
				'message_id' => $result['message_id'] ?? null,
				'provider'   => 'cloudflare_worker',
			) );
		}

		return $this->tool_error(
			'send_failed',
			'Failed to send email via Cloudflare Worker: ' . ( $result['error'] ?? 'Unknown error' )
		);
	}
}

// Return instance for Tool_Loader
return new Cloudflare_Send_Email();