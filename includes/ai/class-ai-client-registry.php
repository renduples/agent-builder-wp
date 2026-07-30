<?php
/**
 * AI Client Registry
 *
 * Central place to register and retrieve AI generation backends.
 * Mirrors the exact successful pattern of Email_Provider_Registry +
 * Cloudflare_Email_Provider that let us make Cloudflare a first-class
 * transactional backend without breaking anything.
 *
 * On WP 7.0+ with the native AI Client present, the WP adapter is
 * automatically preferred when it reports itself available.
 * On every other situation the legacy adapter (our full bridge) wins.
 *
 * Early registration happens in the main plugin file so that
 * marketplace, drips, or any other code can ask for the preferred
 * adapter at any point in the request.
 *
 * @package    Agent_Builder
 * @subpackage AI
 * @since      2.11.0
 */

declare( strict_types = 1 );

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Client_Registry {

	/** @var AI_Client_Adapter[] */
	private static array $adapters = array();

	/** @var string|null */
	private static ?string $active_slug = null;

	/**
	 * Register an adapter implementation.
	 * Call this ultra-early (before plugins_loaded priority 5 is safe).
	 */
	public static function register( AI_Client_Adapter $adapter ): void {
		self::$adapters[ $adapter->get_slug() ] = $adapter;
	}

	public static function get( string $slug ): ?AI_Client_Adapter {
		return self::$adapters[ $slug ] ?? null;
	}

	public static function get_all(): array {
		return self::$adapters;
	}

	public static function get_available(): array {
		return array_filter( self::$adapters, fn( $a ) => $a->is_available() );
	}

	/**
	 * The adapter we should actually use for generation right now.
	 *
	 * Priority:
	 * 1. Explicitly chosen active slug (if still available)
	 * 2. Native WP AI Client (if present and available)
	 * 3. First available adapter (usually legacy bridge)
	 */
	public static function get_preferred(): ?AI_Client_Adapter {
		if ( self::$active_slug && isset( self::$adapters[ self::$active_slug ] ) ) {
			$adapter = self::$adapters[ self::$active_slug ];
			if ( $adapter->is_available() ) {
				return $adapter;
			}
		}

		// Prefer the modern native client when it is actually usable.
		$wp = self::get( 'wp-ai-client' );
		if ( $wp && $wp->is_available() ) {
			return $wp;
		}

		$available = self::get_available();
		return ! empty( $available ) ? reset( $available ) : null;
	}

	/**
	 * Allow code (or a future admin setting) to force a specific adapter.
	 */
	public static function set_active( string $slug ): void {
		self::$active_slug = $slug;
	}

	/**
	 * Reset (tests, or when Connectors change at runtime).
	 */
	public static function reset(): void {
		self::$active_slug = null;
		// Keep registered adapters; just forget preference.
	}
}
