<?php
/**
 * Email Provider Interface
 *
 * Allows pluggable transactional email backends (Cloudflare, Brevo, SMTP, etc.).
 * This is the foundation for making Cloudflare Email a first-class citizen
 * for drips and transactional sends.
 *
 * @package    Agent_Builder
 * @subpackage Email
 * @since      2.11.0
 */

declare(strict_types = 1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Email_Provider {

	/**
	 * Unique slug for the provider (e.g. 'cloudflare', 'brevo').
	 */
	public function get_slug(): string;

	/**
	 * Human name.
	 */
	public function get_name(): string;

	/**
	 * Whether this provider is currently configured and ready.
	 */
	public function is_configured(): bool;

	/**
	 * Send an email.
	 *
	 * @param array $args {
	 *     @type string $to
	 *     @type string $subject
	 *     @type string $text
	 *     @type string $html (optional)
	 *     @type string $from (optional)
	 *     etc.
	 * }
	 * @return array{success: bool, message_id?: string, error?: string}
	 */
	public function send( array $args ): array;

	/**
	 * Get configuration fields for admin UI (for future provider selection UI).
	 */
	public function get_config_fields(): array;
}
