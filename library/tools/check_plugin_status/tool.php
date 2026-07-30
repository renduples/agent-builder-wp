<?php
/**
 * Tool: check_plugin_status
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

class Check_Plugin_Status extends Tool_Base {
	public function get_name(): string {
		return 'check_plugin_status';
	}

	public function get_description(): string {
		return 'List all installed plugins with their active/inactive status, version, and available updates.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array(
			'filter' => array(
				'type'        => 'string',
				'description' => "Filter plugins by status. Defaults to 'all'.",
				'required'    => false,
				'enum'        => array( 'all', 'active', 'inactive', 'outdated' ),
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
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active_list = (array) get_option( 'active_plugins', array() );
		$updates     = get_site_transient( 'update_plugins' );
		$update_map  = (array) ( $updates->response ?? array() );
		$filter      = $args['filter'] ?? 'all';

		$counts = array(
			'total'    => count( $all_plugins ),
			'active'   => 0,
			'inactive' => 0,
			'outdated' => 0,
		);
		$result = array();

		foreach ( $all_plugins as $file => $data ) {
			$is_active  = in_array( $file, $active_list, true );
			$has_update = isset( $update_map[ $file ] );

			if ( $is_active ) {
				++$counts['active'];
			} else {
				++$counts['inactive'];
			}
			if ( $has_update ) {
				++$counts['outdated'];
			}

			if ( 'active' === $filter && ! $is_active ) {
				continue;
			}
			if ( 'inactive' === $filter && $is_active ) {
				continue;
			}
			if ( 'outdated' === $filter && ! $has_update ) {
				continue;
			}

			$entry = array(
				'slug'    => $file,
				'name'    => $data['Name'] ?? $file,
				'version' => $data['Version'] ?? 'unknown',
				'status'  => $is_active ? 'active' : 'inactive',
			);
			if ( $has_update ) {
				$entry['update_available'] = $update_map[ $file ]->new_version ?? true;
			}
			$result[] = $entry;
		}

		return array(
			'counts'  => $counts,
			'filter'  => $filter,
			'plugins' => $result,
		);
	}
}

return new Check_Plugin_Status();
