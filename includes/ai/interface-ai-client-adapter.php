<?php
/**
 * AI Client Adapter Interface
 *
 * Pluggable backends for LLM communication.
 * When WordPress 7.0+ AI Client is present we prefer the native implementation
 * (host credentials via Connectors, unified error + token reporting).
 * On older WordPress (or when native is unavailable) we fall back to our
 * battle-tested legacy stack — delivering full modern capability as the bridge.
 *
 * This is the direct analogue of the Email_Provider interface and registry
 * pattern that enabled Cloudflare Email as a first-class backend.
 *
 * @package    Agent_Builder
 * @subpackage AI
 * @since      2.11.0
 */

declare( strict_types=1 );

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AI_Client_Adapter {

	/**
	 * Unique slug for the adapter (e.g. 'wp-ai-client', 'legacy').
	 */
	public function get_slug(): string;

	/**
	 * Human name shown in health / debug surfaces.
	 */
	public function get_name(): string;

	/**
	 * Whether this adapter is currently usable (native functions present + configured, or legacy always ready).
	 */
	public function is_available(): bool;

	/**
	 * Send a prompt / generation request.
	 *
	 * The implementation must:
	 * - Accept a flexible $args shape (text, system, history, model prefs, json_schema, etc.)
	 * - Return a normalized array on success or WP_Error on failure.
	 * - Never throw.
	 *
	 * Expected success shape (minimal):
	 * [
	 *   'success'          => true,
	 *   'text'             => string,
	 *   'tokens_used'      => int|null,
	 *   'model'            => string|null,
	 *   'provider_metadata'=> array,
	 * ]
	 *
	 * On error: WP_Error with code + message (consistent with core WP AI Client).
	 *
	 * @param array $args Prompt arguments (text, system_instruction, temperature, max_tokens, json_schema, history, files, etc.).
	 * @return array|WP_Error
	 */
	public function generate( array $args );

	/**
	 * Feature flags this adapter supports (used for capability negotiation in Agent_Controller etc.).
	 *
	 * @return array<string, bool> e.g. ['json_schema' => true, 'multimodal' => false, ...]
	 */
	public function get_capabilities(): array;
}
