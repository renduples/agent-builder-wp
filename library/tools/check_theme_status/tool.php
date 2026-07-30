<?php
/**
 * Tool: check_theme_status
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

class Check_Theme_Status extends Tool_Base {
	public function get_name(): string {
		return 'check_theme_status';
	}

	public function get_description(): string {
		return 'List installed themes with status, version, parent info, update availability, and compatibility checks.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array(
			'filter' => array(
				'type'        => 'string',
				'description' => 'Filter themes by status.',
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
		$filter            = $args['filter'] ?? 'all';
		$active_theme      = wp_get_theme();
		$active_slug       = $active_theme->get_stylesheet();
		$all_themes        = wp_get_themes();
		$updates_transient = get_site_transient( 'update_themes' );
		$update_map        = (array) ( $updates_transient->response ?? array() );

		$counts = array(
			'total'    => count( $all_themes ),
			'active'   => 0,
			'inactive' => 0,
			'outdated' => 0,
		);
		$result = array();

		foreach ( $all_themes as $slug => $theme ) {
			$is_active    = ( $slug === $active_slug );
			$has_update   = isset( $update_map[ $slug ] );
			$parent_slug  = $theme->parent() ? $theme->parent()->get_stylesheet() : null;
			$requires_wp  = $theme->get( 'RequiresWP' );
			$requires_php = $theme->get( 'RequiresPHP' );

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
				'slug'    => $slug,
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'status'  => $is_active ? 'active' : 'inactive',
				'parent'  => $parent_slug,
			);

			if ( $has_update ) {
				$entry['update_available'] = $update_map[ $slug ]['new_version'] ?? true;
			}

			$compat_issues = array();
			if ( $requires_wp && version_compare( get_bloginfo( 'version' ), $requires_wp, '<' ) ) {
				$compat_issues[] = "Requires WP {$requires_wp}+";
			}
			if ( $requires_php && version_compare( PHP_VERSION, $requires_php, '<' ) ) {
				$compat_issues[] = "Requires PHP {$requires_php}+";
			}
			if ( ! empty( $compat_issues ) ) {
				$entry['compatibility_issues'] = $compat_issues;
			}

			$result[] = $entry;
		}

		return array(
			'counts' => $counts,
			'filter' => $filter,
			'themes' => $result,
		);
	}
}

return new Check_Theme_Status();
