<?php
/**
 * Tool: purge_expired_transients
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

class Purge_Expired_Transients extends Tool_Base {
	public function get_name(): string {
		return 'purge_expired_transients';
	}

	public function get_description(): string {
		return 'Delete expired transients from the WordPress options table. Finds transients whose timeout has passed and deletes both the timeout record and the value record.';
	}

	public function get_category(): string {
		return 'caching';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'dry_run' => array(
					'type'        => 'boolean',
					'description' => 'If true, count expired transients without deleting. Defaults to false.',
				),
			),
			'required'   => array(),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => true,
		);
	}

	public function execute( array $args ): array {
		global $wpdb;

		$dry_run = (bool) ( $args['dry_run'] ?? false );
		$now     = time();

		// Find all expired transient timeout keys.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired_timeouts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, LENGTH(option_value) AS size_bytes
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND CAST(option_value AS UNSIGNED) < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$now
			),
			ARRAY_A
		);

		$purged_count = 0;
		$freed_bytes  = 0;

		foreach ( $expired_timeouts as $row ) {
			$timeout_key   = $row['option_name'];
			$transient_key = str_replace( '_transient_timeout_', '_transient_', $timeout_key );

			// Get size of value record for estimate.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$value_size = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT LENGTH(option_value) FROM {$wpdb->options} WHERE option_name = %s",
					$transient_key
				)
			);

			$freed_bytes += (int) $row['size_bytes'] + $value_size;

			if ( ! $dry_run ) {
				delete_option( $timeout_key );
				delete_option( $transient_key );
			}

			++$purged_count;
		}

		// Also check site transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired_site = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, LENGTH(option_value) AS size_bytes
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND CAST(option_value AS UNSIGNED) < %d",
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				$now
			),
			ARRAY_A
		);

		foreach ( $expired_site as $row ) {
			$timeout_key   = $row['option_name'];
			$transient_key = str_replace( '_site_transient_timeout_', '_site_transient_', $timeout_key );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$value_size = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT LENGTH(option_value) FROM {$wpdb->options} WHERE option_name = %s",
					$transient_key
				)
			);

			$freed_bytes += (int) $row['size_bytes'] + $value_size;

			if ( ! $dry_run ) {
				delete_option( $timeout_key );
				delete_option( $transient_key );
			}

			++$purged_count;
		}

		return array(
			'purged_count' => $purged_count,
			'dry_run'      => $dry_run,
			'freed_kb'     => round( $freed_bytes / 1024, 1 ),
		);
	}
}

return new Purge_Expired_Transients();
