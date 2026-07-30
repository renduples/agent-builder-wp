<?php
/**
 * Optional WordPress APIs introduced after our minimum supported version.
 *
 * Direct calls to e.g. wp_get_abilities() are flagged by Plugin Check when
 * "Requires at least" is below the version those symbols appeared. We still
 * support WP 6.4+: invoke them only when present, via dynamic names so static
 * analysis does not treat them as hard dependencies.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      3.2.0
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime wrappers for optional core symbols (Abilities API, AI Client, Connectors).
 */
final class WP_Optional_API {

	/**
	 * Call a global function by name if it exists.
	 *
	 * @param string       $name Function name.
	 * @param array<mixed> $args Arguments.
	 * @return mixed|null Null when the function is unavailable.
	 */
	private static function call( string $name, array $args = array() ) {
		if ( ! function_exists( $name ) ) {
			return null;
		}
		return call_user_func_array( $name, $args );
	}

	/**
	 * Whether a named global function exists.
	 *
	 * @param string $name Function name.
	 */
	public static function has( string $name ): bool {
		return function_exists( $name );
	}

	/**
	 * @param string               $slug Category slug.
	 * @param array<string, mixed> $args Category args.
	 */
	public static function register_ability_category( string $slug, array $args ): void {
		self::call( 'wp_register_ability_category', array( $slug, $args ) );
	}

	/**
	 * @param string               $name Ability name.
	 * @param array<string, mixed> $args Ability args.
	 */
	public static function register_ability( string $name, array $args ): void {
		self::call( 'wp_register_ability', array( $name, $args ) );
	}

	/**
	 * @return array<int, mixed>
	 */
	public static function get_abilities(): array {
		$result = self::call( 'wp_get_abilities' );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * @param string $name Ability name.
	 * @return mixed|null
	 */
	public static function get_ability( string $name ) {
		return self::call( 'wp_get_ability', array( $name ) );
	}

	/**
	 * @return mixed|null Prompt builder or null.
	 */
	public static function ai_client_prompt() {
		return self::call( 'wp_ai_client_prompt' );
	}

	/**
	 * @return array<int, mixed>
	 */
	public static function get_connectors(): array {
		$result = self::call( 'wp_get_connectors' );
		return is_array( $result ) ? $result : array();
	}
}
