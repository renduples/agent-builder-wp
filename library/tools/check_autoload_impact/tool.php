<?php
/**
 * Tool: check_autoload_impact
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

class Check_Autoload_Impact extends Tool_Base {
	public function get_name(): string {
		return 'check_autoload_impact';
	}

	public function get_description(): string {
		return 'Estimate page load impact of autoloaded wp_options. Returns total size, top offenders, and recommendations.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array(
			'limit' => array(
				'type'        => 'integer',
				'description' => 'Max top offenders to return (1-30). Defaults to 15.',
				'required'    => false,
			),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		global $wpdb;
		$limit = min( max( (int) ( $args['limit'] ?? 15 ), 1 ), 30 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
		$total = $wpdb->get_row(
			"SELECT COUNT(*) AS option_count, SUM(LENGTH(option_value)) AS total_bytes FROM {$wpdb->options} WHERE autoload = 'yes'",
			ARRAY_A
		);

		$total_bytes = (int) ( $total['total_bytes'] ?? 0 );
		$total_count = (int) ( $total['option_count'] ?? 0 );
		$total_kb    = round( $total_bytes / 1024, 1 );
		$total_mb    = round( $total_bytes / 1048576, 2 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
		$top = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) AS size_bytes FROM {$wpdb->options} WHERE autoload = 'yes' ORDER BY size_bytes DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$offenders = array_map(
			fn( $row ) => array(
				'option_name' => $row['option_name'],
				'size_kb'     => round( (int) $row['size_bytes'] / 1024, 1 ),
				'size_bytes'  => (int) $row['size_bytes'],
			),
			$top
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
		$transient_data = $wpdb->get_row(
			"SELECT COUNT(*) AS count, SUM(LENGTH(option_value)) AS total_bytes FROM {$wpdb->options} WHERE autoload = 'yes' AND option_name LIKE '_transient_%'",
			ARRAY_A
		);

		$transient_count = (int) ( $transient_data['count'] ?? 0 );
		$transient_bytes = (int) ( $transient_data['total_bytes'] ?? 0 );

		$recommendations = array();
		if ( $total_kb > 1000 ) {
			$recommendations[] = "Autoloaded data is {$total_mb} MB — loaded on EVERY request. Target under 800 KB.";
		}
		if ( $transient_bytes > 102400 ) {
			$recommendations[] = "{$transient_count} transients autoloaded totalling " . round( $transient_bytes / 1024 ) . ' KB. Consider object cache.';
		}
		foreach ( $offenders as $opt ) {
			if ( $opt['size_kb'] > 100 ) {
				$recommendations[] = "Option \"{$opt['option_name']}\" is {$opt['size_kb']} KB — investigate if autoload needed.";
			}
		}
		if ( empty( $recommendations ) ) {
			$recommendations[] = 'Autoloaded options are within healthy range.';
		}

		return array(
			'total_autoloaded'  => $total_count,
			'total_size_kb'     => $total_kb,
			'total_size_mb'     => $total_mb,
			'health'            => $total_kb > 1000 ? 'warning' : ( $total_kb > 500 ? 'moderate' : 'good' ),
			'top_offenders'     => $offenders,
			'transient_count'   => $transient_count,
			'transient_size_kb' => round( $transient_bytes / 1024, 1 ),
			'recommendations'   => $recommendations,
		);
	}
}

return new Check_Autoload_Impact();
