<?php
/**
 * Email Provider Registry
 *
 * Central place to register and retrieve email sending backends.
 * This allows the system (drips, notifications, agents) to choose
 * the best available provider, with Cloudflare as a top-tier option.
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

class Email_Provider_Registry {

	/** @var Email_Provider[] */
	private static array $providers = array();

	/** @var string|null */
	private static ?string $active_slug = null;

	public static function register( Email_Provider $provider ): void {
		self::$providers[ $provider->get_slug() ] = $provider;
	}

	public static function get( string $slug ): ?Email_Provider {
		return self::$providers[ $slug ] ?? null;
	}

	public static function get_all(): array {
		return self::$providers;
	}

	public static function get_configured(): array {
		return array_filter( self::$providers, fn( $p ) => $p->is_configured() );
	}

	/**
	 * Get the preferred provider.
	 * Priority: explicitly set active > first configured > null
	 */
	public static function get_preferred(): ?Email_Provider {
		if ( self::$active_slug && isset( self::$providers[ self::$active_slug ] ) ) {
			$provider = self::$providers[ self::$active_slug ];
			if ( $provider->is_configured() ) {
				return $provider;
			}
		}

		$configured = self::get_configured();
		return ! empty( $configured ) ? reset( $configured ) : null;
	}

	public static function set_active( string $slug ): void {
		self::$active_slug = $slug;
	}

	/**
	 * Send using the preferred provider (fallback aware).
	 */
	public static function send( array $args ): array {
		$provider = self::get_preferred();

		if ( ! $provider ) {
			// Ultimate fallback to wp_mail
			$sent = wp_mail(
				$args['to'] ?? '',
				$args['subject'] ?? '',
				$args['text'] ?? ( $args['html'] ?? '' ),
				isset( $args['headers'] ) ? $args['headers'] : ''
			);

			return array(
				'success'  => $sent,
				'provider' => 'wp_mail_fallback',
			);
		}

		return $provider->send( $args );
	}
}
