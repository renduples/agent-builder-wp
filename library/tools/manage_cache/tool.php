<?php
/**
 * Tool: manage_cache
 *
 * Active control over the caches typical to a WordPress install: the object
 * cache, transients (DB- and object-cache-backed), the PHP opcode cache, the
 * rewrite-rules cache, and third-party page-cache plugins.
 *
 * Safety model: several actions (object-cache flush, opcode reset, full
 * transient flush, page-cache purge) can affect the entire server or other
 * sites sharing the same Redis/Memcached/PHP-FPM instance, so the whole tool
 * is HIGH risk and disabled by default (see Tools_Registry::seed_core_tools()).
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.18.0
 *
 * php version 8.1
 */

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;
use Agentic\Risk_Level;

class Manage_Cache extends Tool_Base {

	/**
	 * Supported cache actions.
	 *
	 * @var string[]
	 */
	private const ACTIONS = array(
		'flush_object_cache',
		'flush_transients',
		'delete_transient',
		'reset_opcode_cache',
		'flush_rewrite_rules',
		'purge_page_cache',
	);

	public function get_name(): string {
		return 'manage_cache';
	}

	public function get_description(): string {
		return 'Control WordPress caches. Actions: flush_object_cache (wp_cache_flush), flush_transients (delete all transients), delete_transient (one key), reset_opcode_cache (OPcache), flush_rewrite_rules, purge_page_cache (WP Rocket, W3TC, WP Super Cache, LiteSpeed, WP Fastest Cache, Cache Enabler, Comet Cache, Autoptimize). Use dry_run:true to preview without changing anything. Caution: object-cache, opcode and page-cache flushes may affect other sites sharing the same server/cache instance and can cause a temporary load spike.';
	}

	public function get_category(): string {
		return 'caching';
	}

	public function get_risk_level(): string {
		// Highest-impact action (whole-instance flushes) governs the tool.
		return Risk_Level::HIGH;
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => false,
		);
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action'  => array(
					'type'        => 'string',
					'enum'        => self::ACTIONS,
					'description' => 'Which cache operation to perform.',
				),
				'key'     => array(
					'type'        => 'string',
					'description' => 'Bare transient name (without the "_transient_" prefix). Required only for action=delete_transient.',
				),
				'dry_run' => array(
					'type'        => 'boolean',
					'description' => 'If true, report what would happen without modifying any cache. Defaults to false.',
				),
			),
			'required'   => array( 'action' ),
		);
	}

	public function execute( array $args ): array {
		$action  = is_string( $args['action'] ?? null ) ? $args['action'] : '';
		$dry_run = (bool) ( $args['dry_run'] ?? false );

		// validate_args() does not enforce enum, so guard the action here.
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return $this->tool_error(
				'invalid_action',
				sprintf( 'Unknown action "%s". Supported actions: %s.', $action, implode( ', ', self::ACTIONS ) )
			);
		}

		return match ( $action ) {
			'flush_object_cache'  => $this->flush_object_cache( $dry_run ),
			'flush_transients'    => $this->flush_transients( $dry_run ),
			'delete_transient'    => $this->delete_one_transient( $args['key'] ?? null, $dry_run ),
			'reset_opcode_cache'  => $this->reset_opcode_cache( $dry_run ),
			'flush_rewrite_rules' => $this->flush_rewrite( $dry_run ),
			'purge_page_cache'    => $this->purge_page_cache( $dry_run ),
		};
	}

	/**
	 * Flush the entire WordPress object cache.
	 *
	 * @param bool $dry_run Preview only.
	 * @return array
	 */
	private function flush_object_cache( bool $dry_run ): array {
		$persistent = wp_using_ext_object_cache();
		$warnings   = array();
		if ( $persistent ) {
			$warnings[] = 'A persistent object cache (e.g. Redis/Memcached) is active; wp_cache_flush() typically clears the whole cache instance, which may affect other sites or apps sharing it.';
		}
		$warnings[] = 'Flushing the object cache can cause a temporary performance dip while caches are rebuilt.';

		if ( $dry_run ) {
			return $this->success(
				array(
					'action'              => 'flush_object_cache',
					'dry_run'             => true,
					'would_run'           => 'wp_cache_flush()',
					'persistent_external' => $persistent,
					'warnings'            => $warnings,
				)
			);
		}

		$flushed = wp_cache_flush();

		return $this->success(
			array(
				'action'              => 'flush_object_cache',
				'dry_run'             => false,
				'flushed'             => (bool) $flushed,
				'persistent_external' => $persistent,
				'warnings'            => $warnings,
			)
		);
	}

	/**
	 * Delete all transients (normal and site/network), not just expired ones.
	 *
	 * Uses delete_transient()/delete_site_transient() per key so a persistent
	 * object cache is cleared correctly. Transients that live ONLY in an
	 * external object cache (never written to the DB) are not discoverable by
	 * this DB scan and may remain — flush_object_cache covers those.
	 *
	 * @param bool $dry_run Preview only.
	 * @return array
	 */
	private function flush_transients( bool $dry_run ): array {
		$normal = $this->collect_transient_keys( false );
		$site   = $this->collect_transient_keys( true );

		$warnings = array(
			'Deletes DB-backed transients only; transients stored exclusively in an external object cache may not be discoverable here.',
			'Some transients hold locks, rate limits, or in-progress state; clearing them all may force plugins to redo work.',
		);

		if ( ! $dry_run ) {
			foreach ( $normal as $key ) {
				delete_transient( $key );
			}
			foreach ( $site as $key ) {
				delete_site_transient( $key );
			}
		}

		return $this->success(
			array(
				'action'                 => 'flush_transients',
				'dry_run'                => $dry_run,
				'normal_transients'      => count( $normal ),
				'site_transients'        => count( $site ),
				'sample_normal_keys'     => array_slice( $normal, 0, 10 ),
				'multisite'              => is_multisite(),
				'warnings'               => $warnings,
			)
		);
	}

	/**
	 * Collect bare transient names from the database.
	 *
	 * @param bool $site Whether to collect site/network transients.
	 * @return string[] Bare transient names (prefix stripped, timeout rows excluded).
	 */
	private function collect_transient_keys( bool $site ): array {
		global $wpdb;

		$prefix         = $site ? '_site_transient_' : '_transient_';
		$timeout_prefix = $site ? '_site_transient_timeout_' : '_transient_timeout_';

		if ( $site && is_multisite() ) {
			// Network transients live in sitemeta on multisite.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient enumeration; no caching layer applies.
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
					$wpdb->esc_like( $prefix ) . '%'
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient enumeration; no caching layer applies.
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( $prefix ) . '%'
				)
			);
		}

		$keys = array();
		foreach ( (array) $names as $name ) {
			// Skip the companion timeout rows; derive the bare key from the value rows.
			if ( str_starts_with( $name, $timeout_prefix ) ) {
				continue;
			}
			$keys[] = substr( $name, strlen( $prefix ) );
		}

		return $keys;
	}

	/**
	 * Delete a single transient (both normal and site scope) by bare key.
	 *
	 * @param mixed $key     Transient name from the caller (validated, not mutated).
	 * @param bool  $dry_run Preview only.
	 * @return array
	 */
	private function delete_one_transient( mixed $key, bool $dry_run ): array {
		if ( ! is_string( $key ) || '' === trim( $key ) ) {
			return $this->tool_error( 'missing_key', 'A non-empty "key" (the bare transient name) is required for delete_transient.' );
		}
		// Validate without mutating: reject control chars and over-long names so
		// we never silently target the wrong key.
		if ( preg_match( '/[\x00-\x1f\x7f]/', $key ) ) {
			return $this->tool_error( 'invalid_key', 'Transient key contains control characters.' );
		}
		if ( strlen( $key ) > 172 ) {
			return $this->tool_error( 'invalid_key', 'Transient key is too long (max 172 characters for the bare name).' );
		}

		if ( $dry_run ) {
			return $this->success(
				array(
					'action'    => 'delete_transient',
					'dry_run'   => true,
					'key'       => $key,
					'would_run' => 'delete_transient() and delete_site_transient()',
				)
			);
		}

		$deleted_normal = delete_transient( $key );
		$deleted_site   = delete_site_transient( $key );

		return $this->success(
			array(
				'action'         => 'delete_transient',
				'dry_run'        => false,
				'key'            => $key,
				'deleted_normal' => (bool) $deleted_normal,
				'deleted_site'   => (bool) $deleted_site,
			)
		);
	}

	/**
	 * Reset the PHP OPcache.
	 *
	 * @param bool $dry_run Preview only.
	 * @return array
	 */
	private function reset_opcode_cache( bool $dry_run ): array {
		$available = function_exists( 'opcache_reset' );
		$enabled   = false;
		if ( function_exists( 'opcache_get_status' ) ) {
			$status  = @opcache_get_status( false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Some SAPIs disable opcache_get_status and emit a warning.
			$enabled = is_array( $status ) && ! empty( $status['opcache_enabled'] );
		}

		$warnings = array( 'OPcache is shared across the PHP-FPM pool; resetting it can briefly slow down every site served by the same pool.' );

		if ( ! $available || ! $enabled ) {
			return $this->success(
				array(
					'action'   => 'reset_opcode_cache',
					'dry_run'  => $dry_run,
					'reset'    => false,
					'note'     => 'OPcache is not available or not enabled on this server; nothing to reset.',
				)
			);
		}

		if ( $dry_run ) {
			return $this->success(
				array(
					'action'    => 'reset_opcode_cache',
					'dry_run'   => true,
					'would_run' => 'opcache_reset()',
					'warnings'  => $warnings,
				)
			);
		}

		$reset = opcache_reset();

		return $this->success(
			array(
				'action'   => 'reset_opcode_cache',
				'dry_run'  => false,
				'reset'    => (bool) $reset,
				'warnings' => $warnings,
			)
		);
	}

	/**
	 * Soft-flush the rewrite rules (regenerates the DB-stored rules; no .htaccess write).
	 *
	 * @param bool $dry_run Preview only.
	 * @return array
	 */
	private function flush_rewrite( bool $dry_run ): array {
		if ( $dry_run ) {
			return $this->success(
				array(
					'action'    => 'flush_rewrite_rules',
					'dry_run'   => true,
					'would_run' => 'flush_rewrite_rules( false )',
					'note'      => 'Soft flush: rebuilds the stored rewrite-rules option only; does not write .htaccess.',
				)
			);
		}

		flush_rewrite_rules( false );

		return $this->success(
			array(
				'action'  => 'flush_rewrite_rules',
				'dry_run' => false,
				'flushed' => true,
				'note'    => 'Soft flush performed (rewrite-rules option rebuilt; .htaccess untouched).',
			)
		);
	}

	/**
	 * Purge known third-party page-cache plugins.
	 *
	 * @param bool $dry_run Preview only.
	 * @return array
	 */
	private function purge_page_cache( bool $dry_run ): array {
		$providers = $this->detect_page_cache_providers();

		if ( empty( $providers ) ) {
			return $this->success(
				array(
					'action'  => 'purge_page_cache',
					'dry_run' => $dry_run,
					'purged'  => array(),
					'note'    => 'No supported page-cache plugin detected.',
				)
			);
		}

		if ( $dry_run ) {
			return $this->success(
				array(
					'action'           => 'purge_page_cache',
					'dry_run'          => true,
					'detected'         => array_keys( $providers ),
					'note'             => 'Would purge the detected page-cache provider(s).',
				)
			);
		}

		$purged = array();
		$failed = array();
		foreach ( $providers as $name => $callback ) {
			try {
				$callback();
				$purged[] = $name;
			} catch ( \Throwable $e ) {
				$failed[ $name ] = $e->getMessage();
			}
		}

		return $this->success(
			array(
				'action'  => 'purge_page_cache',
				'dry_run' => false,
				'purged'  => $purged,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * Detect active page-cache providers and the callables that purge them.
	 *
	 * Each provider is guarded by function/class/method existence so unrelated
	 * sites never fatal. Autoptimize is an asset/minification cache rather than
	 * a true page cache but is included for completeness.
	 *
	 * @return array<string, callable> Map of provider label => purge callable.
	 */
	private function detect_page_cache_providers(): array {
		$providers = array();

		if ( function_exists( 'rocket_clean_domain' ) ) {
			$providers['WP Rocket'] = static function () {
				rocket_clean_domain();
			};
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			$providers['W3 Total Cache'] = static function () {
				w3tc_flush_all();
			};
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			$providers['WP Super Cache'] = static function () {
				wp_cache_clear_cache();
			};
		}
		if ( has_action( 'litespeed_purge_all' ) || defined( 'LSCWP_V' ) ) {
			$providers['LiteSpeed Cache'] = static function () {
				do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party LiteSpeed hook
			};
		}
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && is_object( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$providers['WP Fastest Cache'] = static function () {
				$GLOBALS['wp_fastest_cache']->deleteCache( true );
			};
		}
		if ( class_exists( '\Cache_Enabler' ) && method_exists( '\Cache_Enabler', 'clear_total_cache' ) ) {
			$providers['Cache Enabler'] = static function () {
				\Cache_Enabler::clear_total_cache();
			};
		}
		if ( class_exists( '\comet_cache' ) && method_exists( '\comet_cache', 'clear' ) ) {
			$providers['Comet Cache'] = static function () {
				\comet_cache::clear();
			};
		}
		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			$providers['Autoptimize'] = static function () {
				\autoptimizeCache::clearall();
			};
		}

		return $providers;
	}
}

return new Manage_Cache();
