<?php
/**
 * License Client
 *
 * Local-only license status and feature gating.
 * Reads stored license data — no remote calls here.
 *
 * All phone-home, periodic revalidation, admin notices, and AJAX handlers
 * live in includes/pro/class-license-remote.php (add-on build only), which is
 * stripped from the WordPress.org free build by the build script.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      1.7.0
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License Client
 *
 * Responsibilities (WPorg-safe):
 * 1. Read stored license status — never makes remote calls
 * 2. Feature gating — can_agent_run() uses local file checks only
 * 3. Fail-open — if no data is cached, treats install as free tier
 *
 * @since 1.7.0
 */
class License_Client {

	/**
	 * Option key for the plugin license key.
	 */
	const OPTION_LICENSE_KEY = 'agent_builder_license_key';

	/**
	 * Option key for cached license data.
	 */
	const OPTION_LICENSE_DATA = 'agent_builder_license_data';

	/**
	 * Cron hook name (kept for back-compat cleanup in Deactivator).
	 */
	const CRON_HOOK = 'agentic_license_revalidate';

	/**
	 * Cache duration in seconds (72 hours).
	 * If the server is unreachable, we trust the cached result for this long.
	 */
	const CACHE_TTL = 72 * HOUR_IN_SECONDS;

	/**
	 * Grace period in days after license expiration.
	 */
	const GRACE_PERIOD_DAYS = 7;

	/**
	 * Cached license status for the current request.
	 *
	 * @var array|null
	 */
	private ?array $cached_status = null;

	/**
	 * Singleton instance.
	 *
	 * @var License_Client|null
	 */
	private static ?License_Client $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return License_Client
	 */
	public static function get_instance(): License_Client {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — no hooks; all side-effects live in License_Remote (add-on).
	 */
	private function __construct() {}

	// =========================================================================
	// 1. LICENSE STATUS (local reads only)
	// =========================================================================

	/**
	 * Get the current license status from stored options.
	 *
	 * Never makes a remote call. Returns cached data or the stored option.
	 *
	 * @return array {
	 *     @type string $status      'active', 'expired', 'grace_period', 'revoked', 'invalid', 'free'
	 *     @type string $license_key The license key (masked for display).
	 *     @type string $type        'personal', 'agency', or 'free'
	 *     @type string $expires_at  Expiration date.
	 *     @type int    $activations_used  Number of sites activated.
	 *     @type int    $activations_limit Maximum activations.
	 *     @type string $validated_at      Last successful validation timestamp.
	 *     @type bool   $is_valid    Whether the license grants pro access.
	 * }
	 */
	public function get_status(): array {
		if ( null !== $this->cached_status ) {
			return $this->cached_status;
		}

		$license_key = get_option( self::OPTION_LICENSE_KEY, '' );

		if ( empty( $license_key ) ) {
			$this->cached_status = $this->free_status();
			return $this->cached_status;
		}

		$stored = get_option( self::OPTION_LICENSE_DATA, array() );

		if ( empty( $stored ) || empty( $stored['status'] ) ) {
			$this->cached_status = array(
				'status'      => 'pending',
				'is_valid'    => false,
				'license_key' => $this->mask_key( $license_key ),
				'message'     => 'License saved — awaiting confirmation from the licensing server. Pro stays active in the meantime.',
			);
			return $this->cached_status;
		}

		// Check if cached data has exceeded the fail-open window.
		$validated_at  = strtotime( $stored['validated_at'] ?? '2000-01-01' );
		$cache_expired = ( time() - $validated_at ) > self::CACHE_TTL;

		if ( $cache_expired ) {
			$stored['cache_stale'] = true;
		}

		$this->cached_status = $this->evaluate_status( $stored );
		return $this->cached_status;
	}

	/**
	 * Check if advanced (add-on) features are available.
	 *
	 * Single method other code should call to gate add-on features (detected via add-on presence).
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		return defined( 'AGENT_BUILDER_PRO_FILE' );
	}

	/**
	 * Check whether the site was connected via an approved AI connector
	 * (Anthropic, xAI, OpenAI, etc.). Returns true when at least one
	 * connector is active — recorded by Agentic_Relay_Connect on approval.
	 *
	 * @return bool
	 */
	public function has_connector(): bool {
		$connectors = get_option( 'agentic_active_connectors', array() );
		return ! empty( $connectors );
	}

	/**
	 * Whether the MCP endpoint should be accessible.
	 * Granted when Pro is active OR a connector was installed via an AI provider.
	 *
	 * @return bool
	 */
	public function can_use_mcp(): bool {
		return $this->is_pro() || $this->has_connector();
	}

	/**
	 * Get the license type.
	 *
	 * @return string 'personal', 'agency', or 'free'
	 */
	public function get_type(): string {
		return $this->get_status()['type'] ?? 'free';
	}

	/**
	 * Reset the in-memory status cache.
	 *
	 * Called by License_Remote after a remote revalidation updates the stored option.
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		$this->cached_status = null;
	}

	// =========================================================================
	// 2. FEATURE GATING (local file checks only)
	// =========================================================================

	/**
	 * Check if a pro (user-installed) agent should be allowed to run.
	 *
	 * Bundled library agents always run. Only user-installed uploaded agents
	 * are gated by per-agent licensing. No remote call is made.
	 *
	 * @param string $slug Agent slug.
	 * @return bool True if the agent should load.
	 */
	public function can_agent_run( string $slug ): bool {
		// Bundled library agents always run — they were gated at activation.
		if ( in_array( $slug, $this->get_bundled_agent_slugs(), true ) ) {
			return true;
		}

		$agents_dir = AGENTIC_AGENTS_DIR . '/' . $slug;

		// User-created agents (no .uploaded marker) always run.
		if ( is_dir( $agents_dir ) && ! file_exists( $agents_dir . '/.uploaded' ) ) {
			return true;
		}

		// Uploaded agent — check for per-agent license file.
		$license_file    = $agents_dir . '/.license';
		$activation_file = $agents_dir . '/.activation';

		if ( ! file_exists( $license_file ) ) {
			// Legacy upload (before per-agent licensing) — fall back to plugin license.
			return $this->is_pro();
		}

		// Per-agent premium license — activation file must exist.
		if ( ! file_exists( $activation_file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$lic = json_decode( file_get_contents( $license_file ), true );
		if ( empty( $lic['token'] ) ) {
			return false;
		}

		// Check expiry with 7-day grace period.
		if ( ! empty( $lic['expires_at'] ) ) {
			$grace_end = strtotime( $lic['expires_at'] ) + ( 7 * DAY_IN_SECONDS );
			if ( time() > $grace_end ) {
				return false;
			}
		}

		// Verify domain-binding: re-derive activation hash from site URL + token.
		$parsed   = wp_parse_url( home_url() );
		$site_key = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
		$expected = hash_hmac( 'sha256', $site_key, $lic['token'] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$stored = trim( file_get_contents( $activation_file ) );

		return hash_equals( $expected, $stored );
	}

	/**
	 * Get list of bundled agent slugs (scanned from library/ directory).
	 *
	 * @return string[]
	 */
	private function get_bundled_agent_slugs(): array {
		$library_dirs = apply_filters( 'agentic_library_dirs', array( AGENT_BUILDER_DIR . 'library/agents/' ) );

		$slugs = array();
		foreach ( $library_dirs as $library_dir ) {
			if ( ! is_dir( $library_dir ) ) {
				continue;
			}
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$dirs = @scandir( $library_dir );
			if ( is_array( $dirs ) ) {
				foreach ( $dirs as $dir ) {
					if ( '.' !== $dir && '..' !== $dir && is_dir( $library_dir . $dir ) ) {
						$slugs[] = $dir;
					}
				}
			}
		}
		return array_unique( $slugs );
	}

	// =========================================================================
	// INTERNAL HELPERS
	// =========================================================================

	/**
	 * Evaluate license status from stored data, including grace period logic.
	 *
	 * @param array $data Stored license data.
	 * @return array Evaluated status with is_valid flag.
	 */
	private function evaluate_status( array $data ): array {
		$license_key         = get_option( self::OPTION_LICENSE_KEY, '' );
		$data['license_key'] = $this->mask_key( $license_key );

		$status = $data['status'] ?? 'invalid';

		if ( 'active' === $status ) {
			if ( ! empty( $data['expires_at'] ) && strtotime( $data['expires_at'] ) < time() ) {
				$status = 'expired';
			} else {
				$data['is_valid'] = true;
				return $data;
			}
		}

		if ( in_array( $status, array( 'expired', 'license_expired' ), true ) ) {
			if ( ! empty( $data['expires_at'] ) ) {
				$expires   = strtotime( $data['expires_at'] );
				$grace_end = $expires + ( self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS );
				$days_left = max( 0, (int) ceil( ( $grace_end - time() ) / DAY_IN_SECONDS ) );

				if ( time() <= $grace_end ) {
					$data['status']          = 'grace_period';
					$data['grace_days_left'] = $days_left;
					$data['is_valid']        = true;
					return $data;
				}
			}

			$data['status']   = 'expired';
			$data['is_valid'] = false;
			return $data;
		}

		if ( in_array( $status, array( 'revoked', 'license_revoked' ), true ) ) {
			$data['is_valid'] = false;
			return $data;
		}

		$data['is_valid'] = false;
		return $data;
	}

	/**
	 * Return the status array for an unlicensed (free) installation.
	 *
	 * @return array
	 */
	private function free_status(): array {
		return array(
			'status'      => 'free',
			'is_valid'    => false,
			'license_key' => '',
			'type'        => 'free',
			'message'     => 'No license key entered.',
		);
	}

	/**
	 * Mask a license key for display (show first and last segments only).
	 *
	 * @param string $key Full license key.
	 * @return string Masked key, e.g. "AGNT-****-****-****-JG2R"
	 */
	private function mask_key( string $key ): string {
		if ( strlen( $key ) < 10 ) {
			return '****';
		}

		$parts = explode( '-', $key );
		if ( count( $parts ) < 3 ) {
			return substr( $key, 0, 4 ) . '****' . substr( $key, -4 );
		}

		$first  = $parts[0];
		$last   = end( $parts );
		$middle = array_fill( 0, count( $parts ) - 2, '****' );

		return $first . '-' . implode( '-', $middle ) . '-' . $last;
	}

	/**
	 * Log a license-related message (debug only).
	 *
	 * @param string $message Log message.
	 */
	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Agentic License: ' . $message );
		}
	}
}
