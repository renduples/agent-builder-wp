<?php
/**
 * Tool: get_custom_post_types_summary
 *
 * List all registered custom post types and taxonomies.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List all registered custom post types and taxonomies beyond WordPress defaults.
 */
class Get_Custom_Post_Types_Summary extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_custom_post_types_summary';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'List all registered custom post types and taxonomies beyond WordPress defaults, including slugs, public status, supported features, and post counts.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'WordPress';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'include_builtin' => array(
					'type'        => 'boolean',
					'description' => 'Include WordPress built-in types (post, page, etc). Defaults to false.',
				),
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$include_builtin = ! empty( $arguments['include_builtin'] );

		$builtin_types = array(
			'post',
			'page',
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			'wp_pattern',
		);

		$builtin_taxes = array(
			'category',
			'post_tag',
			'nav_menu',
			'link_category',
			'post_format',
			'wp_theme',
			'wp_template_part_area',
			'wp_pattern_category',
		);

		// Try abilities first.
		$ability_types = $this->call_ability( 'wp-extended/get-post-types' );
		$ability_taxes = $this->call_ability( 'wp-extended/get-taxonomies' );

		if ( $ability_types && ! isset( $ability_types['error'] ) &&
			$ability_taxes && ! isset( $ability_taxes['error'] ) ) {

			$result = array();
			foreach ( $ability_types as $pt ) {
				$slug = $pt['name'] ?? '';
				if ( ! $include_builtin && in_array( $slug, $builtin_types, true ) ) {
					continue;
				}

				$pt_obj = get_post_type_object( $slug );
				$counts = wp_count_posts( $slug );
				$total  = 0;
				if ( $counts ) {
					foreach ( (array) $counts as $sc ) {
						$total += (int) $sc;
					}
				}

				$result[] = array(
					'slug'         => $slug,
					'label'        => $pt['label'] ?? '',
					'public'       => $pt_obj ? (bool) $pt_obj->public : false,
					'has_archive'  => (bool) ( $pt['has_archive'] ?? false ),
					'hierarchical' => (bool) ( $pt['hierarchical'] ?? false ),
					'supports'     => is_array( $pt['supports'] ?? null ) ? array_keys( $pt['supports'] ) : array(),
					'post_count'   => $total,
					'builtin'      => $pt_obj ? $pt_obj->_builtin : false,
				);
			}

			$tax_result = array();
			foreach ( $ability_taxes as $tax ) {
				$slug = $tax['name'] ?? '';
				if ( ! $include_builtin && in_array( $slug, $builtin_taxes, true ) ) {
					continue;
				}

				$tax_obj    = get_taxonomy( $slug );
				$term_count = wp_count_terms(
					array(
						'taxonomy'   => $slug,
						'hide_empty' => false,
					)
				);

				$tax_result[] = array(
					'slug'         => $slug,
					'label'        => $tax['label'] ?? '',
					'public'       => $tax_obj ? (bool) $tax_obj->public : false,
					'hierarchical' => (bool) ( $tax['hierarchical'] ?? false ),
					'object_type'  => $tax['object_types'] ?? array(),
					'term_count'   => is_wp_error( $term_count ) ? 0 : (int) $term_count,
					'builtin'      => $tax_obj ? $tax_obj->_builtin : false,
				);
			}

			return array(
				'post_types'      => $result,
				'post_type_count' => count( $result ),
				'taxonomies'      => $tax_result,
				'taxonomy_count'  => count( $tax_result ),
				'include_builtin' => $include_builtin,
			);
		}

		// Fallback: abilities unavailable, query directly.
		$post_types = get_post_types( array(), 'objects' );
		$result     = array();

		foreach ( $post_types as $slug => $pt ) {
			if ( ! $include_builtin && in_array( $slug, $builtin_types, true ) ) {
				continue;
			}

			$counts = wp_count_posts( $slug );
			$total  = 0;

			if ( $counts ) {
				foreach ( (array) $counts as $status_count ) {
					$total += (int) $status_count;
				}
			}

			$supports = get_all_post_type_supports( $slug );

			$result[] = array(
				'slug'         => $slug,
				'label'        => $pt->label,
				'public'       => (bool) $pt->public,
				'has_archive'  => (bool) $pt->has_archive,
				'hierarchical' => (bool) $pt->hierarchical,
				'supports'     => array_keys( $supports ),
				'post_count'   => $total,
				'builtin'      => $pt->_builtin,
			);
		}

		$taxonomies = get_taxonomies( array(), 'objects' );
		$tax_result = array();

		foreach ( $taxonomies as $slug => $tax ) {
			if ( ! $include_builtin && in_array( $slug, $builtin_taxes, true ) ) {
				continue;
			}

			$term_count = wp_count_terms(
				array(
					'taxonomy'   => $slug,
					'hide_empty' => false,
				)
			);

			$tax_result[] = array(
				'slug'         => $slug,
				'label'        => $tax->label,
				'public'       => (bool) $tax->public,
				'hierarchical' => (bool) $tax->hierarchical,
				'object_type'  => $tax->object_type,
				'term_count'   => is_wp_error( $term_count ) ? 0 : (int) $term_count,
				'builtin'      => $tax->_builtin,
			);
		}

		return array(
			'post_types'      => $result,
			'post_type_count' => count( $result ),
			'taxonomies'      => $tax_result,
			'taxonomy_count'  => count( $tax_result ),
			'include_builtin' => $include_builtin,
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly' => true,
		);
	}
}

return new Get_Custom_Post_Types_Summary();
