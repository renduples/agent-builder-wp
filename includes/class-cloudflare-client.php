<?php
/**
 * Cloudflare Client
 *
 * Shared helper for talking to Cloudflare (both the REST API for security actions
 * and user-deployed Workers for transactional email relay).
 *
 * This class lives in the FREE plugin so the Cloudflare agent tools can use it
 * even without Pro installed (graceful degradation when not configured).
 *
 * @package    Agent_Builder
 * @subpackage Integrations
 * @since      2.11.0
 */

declare(strict_types = 1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloudflare API + Worker client.
 */
class Cloudflare_Client {

	/** Option key for all Cloudflare settings (free + pro). */
	const OPTION_KEY = 'agentic_cloudflare_settings';

	/**
	 * Get a setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public static function get_setting( string $key, $fallback = '' ) {
		$settings = (array) get_option( self::OPTION_KEY, array() );
		return $settings[ $key ] ?? $fallback;
	}

	/**
	 * Save Cloudflare settings (merges with existing).
	 *
	 * @param array $data Key => value pairs.
	 * @return bool
	 */
	public static function save_settings( array $data ): bool {
		$current = (array) get_option( self::OPTION_KEY, array() );
		$updated = array_merge( $current, $data );
		return update_option( self::OPTION_KEY, $updated );
	}

	/**
	 * Get decrypted API token (for Cloudflare REST API calls).
	 *
	 * @return string|null
	 */
	public static function get_api_token(): ?string {
		$encrypted = self::get_setting( 'api_token' );
		if ( empty( $encrypted ) ) {
			return null;
		}
		return self::decrypt( $encrypted );
	}

	/**
	 * Get the transactional email Worker URL (if configured).
	 *
	 * @return string|null
	 */
	public static function get_email_worker_url(): ?string {
		$url = self::get_setting( 'email_worker_url' );
		return $url ? rtrim( $url, '/' ) : null;
	}

	/**
	 * Get decrypted auth token for the email Worker.
	 *
	 * @return string|null
	 */
	public static function get_email_auth_token(): ?string {
		$encrypted = self::get_setting( 'email_auth_token' );
		return $encrypted ? self::decrypt( $encrypted ) : null;
	}

	/**
	 * Simple AES-256-CBC encryption (matches patterns used in channels).
	 *
	 * @param string $value Plain text.
	 * @return string Encrypted + base64.
	 */
	public static function encrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$key = self::get_encryption_key();
		$iv  = random_bytes( 16 );

		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt value.
	 *
	 * @param string $value Encrypted value.
	 * @return string|null
	 */
	public static function decrypt( string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}

		$decoded = base64_decode( $value, true );
		if ( false === $decoded || strlen( $decoded ) < 16 ) {
			return null;
		}

		$iv        = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );
		$key       = self::get_encryption_key();

		$plain = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
		return ( false === $plain ) ? null : $plain;
	}

	/**
	 * Get encryption key (falls back to SECURE_AUTH_KEY).
	 *
	 * @return string
	 */
	private static function get_encryption_key(): string {
		$key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'agentic-fallback-key-change-me';
		return hash( 'sha256', $key, true );
	}

	/**
	 * Make a request to the Cloudflare REST API.
	 *
	 * @param string $method  HTTP method.
	 * @param string $path    API path (e.g. '/zones').
	 * @param array  $body    Request body (for POST/PUT).
	 * @return array|null     Decoded JSON or null on failure.
	 */
	public static function api_request( string $method, string $path, array $body = array() ): ?array {
		$token = self::get_api_token();
		if ( ! $token ) {
			return null;
		}

		$url = 'https://api.cloudflare.com/client/v4' . $path;

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 20,
		);

		if ( ! empty( $body ) && in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 || empty( $data['success'] ) ) {
			// Store last error for debugging (optional)
			return array(
				'success' => false,
				'errors'  => $data['errors'] ?? array(),
				'code'    => $code,
			);
		}

		return $data['result'] ?? $data;
	}

	/**
	 * Send a transactional email via the user's Cloudflare Worker relay.
	 *
	 * @param array $payload Must contain 'to', 'subject', and either 'text' or 'html'.
	 * @return array{success: bool, message_id?: string, error?: string}
	 */
	public static function send_via_worker( array $payload ): array {
		$url   = self::get_email_worker_url();
		$token = self::get_email_auth_token();

		if ( ! $url || ! $token ) {
			return array(
				'success' => false,
				'error'   => 'Cloudflare Email Worker not configured.',
			);
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				// The email Worker authenticates via X-Send-Secret (see the
				// agentic-plugin-email Worker + the agentic-cf-email mu-plugin),
				// NOT a Bearer token. Sending Bearer returned HTTP 401 and this
				// path silently fell back to wp_mail — so it never actually ran
				// and never captured the Worker-issued message id.
				'X-Send-Secret' => $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 15,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::record_email_send( $payload, false, null, $response->get_error_message() );
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			// The Worker returns the id at result.messageId; older/other workers
			// may put it at a top-level message_id. Extract defensively.
			$message_id = $data['result']['messageId']
				?? $data['message_id']
				?? ( $data['result']['message_id'] ?? null );
			self::record_email_send( $payload, true, $message_id );
			return array(
				'success'         => true,
				'message_id'      => $message_id,
				'worker_response' => $data,
			);
		}

		$error = $data['error'] ?? ( 'Worker returned HTTP ' . $code );
		self::record_email_send( $payload, false, null, $error );
		return array(
			'success' => false,
			'error'   => $error,
			'body'    => $data,
		);
	}

	/**
	 * Record an outbound email send for auditing (to, subject, Worker message id,
	 * status). Prefers the shared marketplace log table `{prefix}agentic_email_log`
	 * so transactional sends sit alongside drips/newsletters/wp_mail; on standalone
	 * installs without that table (e.g. the free plugin without the marketplace) it
	 * falls back to a rolling last-100 non-autoloaded option `agentic_email_log`
	 * (read: `wp option get agentic_email_log --format=json`). Disable via the
	 * `agentic_email_log_enabled` filter (e.g. for high-volume bulk contexts).
	 *
	 * @param array       $payload    Send payload (uses 'to' + 'subject').
	 * @param bool        $ok         Whether the Worker accepted the send.
	 * @param string|null $message_id Worker-issued message id, if any.
	 * @param string      $error      Error message on failure.
	 */
	private static function record_email_send( array $payload, bool $ok, ?string $message_id, string $error = '' ): void {
		if ( ! apply_filters( 'agentic_email_log_enabled', true ) ) {
			return;
		}

		// Store the bare message id (drop RFC `<id@host>` angle brackets) so it is
		// consistent with the drip logger and survives sanitize_text_field() readers.
		$message_id = ( null !== $message_id ) ? trim( $message_id, '<>' ) : null;

		global $wpdb;
		$to      = $payload['to'] ?? '';
		$to_str  = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
		$subject = (string) ( $payload['subject'] ?? '' );
		$table   = $wpdb->prefix . 'agentic_email_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				array(
					'user_id'             => get_current_user_id(),
					'drip_post_id'        => 0,
					'pipeline'            => 'transactional',
					'step'                => 0,
					'subject'             => mb_substr( $subject, 0, 255 ),
					'sent_at'             => current_time( 'mysql' ),
					'status'              => $ok ? 'sent' : 'failed',
					'provider'            => 'cloudflare',
					'provider_message_id' => $message_id,
					'error_message'       => '' !== $error ? $error : null,
					'metadata'            => wp_json_encode( array( 'to' => $to_str ) ),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			return;
		}

		// Standalone fallback: rolling last-100 in a non-autoloaded option.
		$entry = array(
			'time'       => gmdate( 'c' ),
			'to'         => $to_str,
			'subject'    => $subject,
			'message_id' => $message_id,
			'status'     => $ok ? 'sent' : 'failed',
			'error'      => $error,
		);
		$log = get_option( 'agentic_email_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = $entry;
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, -100 );
		}
		update_option( 'agentic_email_log', $log, false );
	}
}
