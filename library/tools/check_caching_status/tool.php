<?php
/**
 * Tool: check_caching_status
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;

class Check_Caching_Status extends Tool_Base {
	public function get_name(): string {
		return 'check_caching_status';
	}

	public function get_description(): string {
		return 'Detect active caching mechanisms: object cache, page cache plugins, opcode cache, and cache constants.';
	}

	public function get_category(): string {
		return 'caching';
	}

	public function get_parameters(): array {
		return array();
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$result = array(
			'object_cache'    => array(),
			'page_cache'      => array(),
			'opcode_cache'    => array(),
			'constants'       => array(),
			'recommendations' => array(),
		);

		// Object cache drop-in.
		$dropin = WP_CONTENT_DIR . '/object-cache.php';
		if ( file_exists( $dropin ) ) {
			$result['object_cache']['enabled'] = true;
			$result['object_cache']['dropin']  = 'object-cache.php present';
			$contents                          = file_get_contents( $dropin );
			$provider                          = 'unknown';
			if ( false !== stripos( $contents, 'redis' ) ) {
				$provider = 'Redis';
			} elseif ( false !== stripos( $contents, 'memcache' ) ) {
				$provider = 'Memcached';
			} elseif ( false !== stripos( $contents, 'apcu' ) ) {
				$provider = 'APCu';
			}
			$result['object_cache']['provider'] = $provider;
		} else {
			$result['object_cache']['enabled'] = false;
			$result['recommendations'][]       = 'No persistent object cache detected. Consider Redis or Memcached.';
		}
		$result['object_cache']['wp_using_ext_object_cache'] = wp_using_ext_object_cache();

		// Page cache plugins.
		$page_cache_plugins = array(
			'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
			'w3-total-cache/w3-total-cache.php'   => 'W3 Total Cache',
			'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
			'wp-rocket/wp-rocket.php'             => 'WP Rocket',
			'comet-cache/comet-cache.php'         => 'Comet Cache',
			'cache-enabler/cache-enabler.php'     => 'Cache Enabler',
		);

		$detected       = array();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		foreach ( $page_cache_plugins as $plugin_file => $name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$detected[] = $name;
			}
		}

		if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			$result['page_cache']['advanced_cache_dropin'] = true;
		}

		$result['page_cache']['detected_plugins'] = $detected;
		$result['page_cache']['enabled']          = ! empty( $detected ) || file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );

		if ( empty( $detected ) && ! file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			$result['recommendations'][] = 'No page caching detected. Consider a page cache plugin.';
		}
		if ( count( $detected ) > 1 ) {
			$result['recommendations'][] = 'Multiple page cache plugins: ' . implode( ', ', $detected ) . '. Use only one.';
		}

		// Opcode cache.
		$result['opcode_cache']['opcache_enabled'] = function_exists( 'opcache_get_status' ) && ! empty( @opcache_get_status( false ) );
		if ( $result['opcode_cache']['opcache_enabled'] ) {
			$status = @opcache_get_status( false );
			if ( $status ) {
				$result['opcode_cache']['memory_used_mb'] = isset( $status['memory_usage']['used_memory'] ) ? round( $status['memory_usage']['used_memory'] / 1048576, 1 ) : null;
				$result['opcode_cache']['hit_rate']       = isset( $status['opcache_statistics']['opcache_hit_rate'] ) ? round( $status['opcache_statistics']['opcache_hit_rate'], 1 ) . '%' : null;
			}
		} else {
			$result['recommendations'][] = 'OPcache is not enabled. Enabling it improves PHP performance.';
		}

		// Cache constants.
		$cache_constants = array( 'WP_CACHE', 'ENABLE_CACHE', 'WP_REDIS_HOST', 'WP_REDIS_PORT', 'MEMCACHED_SERVERS' );
		foreach ( $cache_constants as $const ) {
			if ( defined( $const ) ) {
				$val                           = constant( $const );
				$result['constants'][ $const ] = is_bool( $val ) ? ( $val ? 'true' : 'false' ) : (string) $val;
			}
		}

		return $result;
	}
}

return new Check_Caching_Status();
