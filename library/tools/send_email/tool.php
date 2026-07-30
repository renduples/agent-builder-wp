<?php
/**
 * Tool: send_email
 *
 * Send an email using the Agent Builder Email channel settings.
 * Uses the channel's configured from_name / from_email when available.
 * Requires the add-on with the Email channel.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.7.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send an email via the Email channel.
 */
class Send_Email extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'send_email';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Send an email to one or more recipients using the configured Email channel. Supports plain text and HTML bodies, CC, and custom subject lines. Use get_email_settings first to confirm the channel is configured. Requires Agent Builder Pro.';
	}

	public function get_category(): string {
		return 'communication';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'to'       => array(
					'type'        => 'string',
					'description' => 'Recipient email address. For multiple recipients, separate with commas.',
				),
				'subject'  => array(
					'type'        => 'string',
					'description' => 'Email subject line.',
				),
				'body'     => array(
					'type'        => 'string',
					'description' => 'Email body. Plain text by default. Set is_html to true to send HTML.',
				),
				'is_html'  => array(
					'type'        => 'boolean',
					'description' => 'Set to true to send body as HTML. Defaults to false (plain text).',
				),
				'cc'       => array(
					'type'        => 'string',
					'description' => 'CC address or comma-separated list of addresses.',
				),
				'reply_to' => array(
					'type'        => 'string',
					'description' => 'Reply-To address if different from the sender.',
				),
			),
			'required'   => array( 'to', 'subject', 'body' ),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( '\Agentic\Channels\Email_Channel' ) ) {
			return array(
				'error'        => 'The Email channel requires Agent Builder Pro. Upgrade at agentic-plugin.com to enable email sending.',
				'pro_required' => true,
			);
		}

		$to      = sanitize_text_field( $arguments['to'] ?? '' );
		$subject = sanitize_text_field( $arguments['subject'] ?? '' );
		$body    = $arguments['body'] ?? '';
		$is_html = ! empty( $arguments['is_html'] );

		if ( '' === $to || '' === $subject || '' === $body ) {
			return array( 'error' => 'to, subject, and body are all required.' );
		}

		// Validate each recipient.
		$recipients = array_filter( array_map( 'trim', explode( ',', $to ) ) );
		foreach ( $recipients as $addr ) {
			if ( ! is_email( $addr ) ) {
				return array( 'error' => "Invalid email address: {$addr}" );
			}
		}

		// Build headers from channel settings.
		$from_name  = \Agentic\Channels\Email_Channel::get_setting( 'from_name' ) ?: get_bloginfo( 'name' );
		$from_email = \Agentic\Channels\Email_Channel::get_setting( 'from_email' ) ?: get_bloginfo( 'admin_email' );

		$content_type = $is_html ? 'text/html' : 'text/plain';
		$headers      = array(
			"Content-Type: {$content_type}; charset=UTF-8",
			"From: {$from_name} <{$from_email}>",
		);

		if ( ! empty( $arguments['cc'] ) ) {
			$cc_list = array_filter( array_map( 'trim', explode( ',', $arguments['cc'] ) ) );
			foreach ( $cc_list as $cc_addr ) {
				if ( is_email( $cc_addr ) ) {
					$headers[] = "Cc: {$cc_addr}";
				}
			}
		}

		if ( ! empty( $arguments['reply_to'] ) && is_email( trim( $arguments['reply_to'] ) ) ) {
			$headers[] = 'Reply-To: ' . trim( $arguments['reply_to'] );
		}

		$sent = wp_mail( implode( ',', $recipients ), $subject, $body, $headers );

		if ( ! $sent ) {
			return array(
				'error' => 'wp_mail() returned false. Check your WordPress mail configuration (SMTP plugin or hosting mail settings).',
				'to'    => $to,
			);
		}

		return array(
			'sent'    => true,
			'to'      => implode( ', ', $recipients ),
			'subject' => $subject,
			'from'    => "{$from_name} <{$from_email}>",
			'is_html' => $is_html,
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}
}

return new Send_Email();
