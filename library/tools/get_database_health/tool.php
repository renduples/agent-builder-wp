<?php
/**
 * Tool: get_database_health
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

class Get_Database_Health extends Tool_Base {
	public function get_name(): string {
		return 'get_database_health';
	}

	public function get_description(): string {
		return 'Check WordPress database health: table sizes, overhead, autoloaded options, transients, and overall statistics.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array(
			'include_options' => array(
				'type'        => 'boolean',
				'description' => 'Include the largest autoloaded options in the response.',
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
		$include_options = ! empty( $args['include_options'] );

		// Table stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
		$tables         = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		$total_size     = 0;
		$total_overhead = 0;
		$table_info     = array();
		foreach ( $tables as $table ) {
			$size            = ( (int) ( $table['Data_length'] ?? 0 ) + (int) ( $table['Index_length'] ?? 0 ) );
			$overhead        = (int) ( $table['Data_free'] ?? 0 );
			$total_size     += $size;
			$total_overhead += $overhead;
			$table_info[]    = array(
				'name'        => $table['Name'],
				'rows'        => (int) ( $table['Rows'] ?? 0 ),
				'size_mb'     => round( $size / 1048576, 2 ),
				'overhead_mb' => round( $overhead / 1048576, 2 ),
			);
		}
		usort( $table_info, fn( $a, $b ) => $b['size_mb'] <=> $a['size_mb'] );

		// Autoloaded options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
		$autoload = $wpdb->get_row(
			"SELECT COUNT(*) AS count, SUM(LENGTH(option_value)) AS total_bytes FROM {$wpdb->options} WHERE autoload = 'yes'",
			ARRAY_A
		);

		// Transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
		$total_transients = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
		$expired = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} o1
			 WHERE o1.option_name LIKE '_transient_timeout_%'
			 AND o1.option_value < UNIX_TIMESTAMP()"
		);

		$result = array(
			'total_tables'       => count( $tables ),
			'total_size_mb'      => round( $total_size / 1048576, 2 ),
			'table_overhead_mb'  => round( $total_overhead / 1048576, 2 ),
			'autoload_count'     => (int) ( $autoload['count'] ?? 0 ),
			'autoload_size_mb'   => round( (int) ( $autoload['total_bytes'] ?? 0 ) / 1048576, 2 ),
			'total_transients'   => $total_transients,
			'expired_transients' => $expired,
			'largest_tables'     => array_slice( $table_info, 0, 10 ),
		);

		if ( $include_options ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table.
			$big_options                  = $wpdb->get_results(
				"SELECT option_name, LENGTH(option_value) AS size_bytes FROM {$wpdb->options} WHERE autoload = 'yes' ORDER BY size_bytes DESC LIMIT 10",
				ARRAY_A
			);
			$result['largest_autoloaded'] = array_map(
				fn( $r ) => array(
					'name'    => $r['option_name'],
					'size_kb' => round( (int) $r['size_bytes'] / 1024, 1 ),
				),
				$big_options
			);
		}

		return $result;
	}
}

return new Get_Database_Health();
