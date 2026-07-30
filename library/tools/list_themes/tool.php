<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List Themes Tool
 *
 * Lists all installed WordPress themes with version, status,
 * block theme support, and update availability.
 *
 * @package Agentic\Tools
 */
class List_Themes extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'list_themes';
	}

	public function get_description(): string {
		return 'List all installed WordPress themes with version, active status, block theme support, and available updates.';
	}

	public function get_category(): string {
		return 'themes';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
		);
	}

	public function execute( array $arguments ): array {
		$themes  = wp_get_themes();
		$active  = get_stylesheet();
		$updates = get_site_transient( 'update_themes' );
		$result  = array();

		foreach ( $themes as $stylesheet => $theme ) {
			$entry = array(
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'stylesheet'       => $stylesheet,
				'is_active'        => ( $stylesheet === $active ),
				'is_child_theme'   => $theme->parent() !== false,
				'parent_theme'     => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
				'is_block_theme'   => $theme->is_block_theme(),
				'author'           => $theme->get( 'Author' ),
				'update_available' => isset( $updates->response[ $stylesheet ] ),
			);

			if ( $entry['update_available'] ) {
				$entry['update_version'] = $updates->response[ $stylesheet ]['new_version'] ?? null;
			}

			$result[] = $entry;
		}

		// Sort: active first, then alphabetical.
		usort(
			$result,
			function ( $a, $b ) {
				if ( $a['is_active'] !== $b['is_active'] ) {
					return $a['is_active'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return array(
			'total'        => count( $result ),
			'active_theme' => $active,
			'themes'       => $result,
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new List_Themes();
