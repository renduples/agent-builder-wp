<?php
/**
 * Tool: get_site_context
 *
 * Get site name, description, active categories, common tags, and post/page totals.
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
 * Get site name, description, active categories, common tags, and post/page totals.
 */
class Get_Site_Context extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_site_context';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get site name, description, active categories, common tags, and post/page totals. Call this once at the start of a session to understand the site before creating content.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'content';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		// Delegate taxonomy queries to abilities.
		$cats_result = $this->call_ability( 'wp-extended/get-categories', array( 'hide_empty' => false ) );
		$tags_result = $this->call_ability( 'wp-extended/get-tags', array() );

		if ( $cats_result && ! isset( $cats_result['error'] ) ) {
			$categories = array_slice(
				array_map(
					fn( $c ) => array(
						'id'    => $c['term_id'] ?? 0,
						'name'  => $c['name'] ?? '',
						'count' => $c['count'] ?? 0,
					),
					$cats_result
				),
				0,
				30
			);
		} else {
			$cats       = get_categories(
				array(
					'hide_empty' => false,
					'number'     => 30,
				)
			);
			$categories = array_map(
				fn( $c ) => array(
					'id'    => $c->term_id,
					'name'  => $c->name,
					'count' => $c->count,
				),
				$cats
			);
		}

		if ( $tags_result && ! isset( $tags_result['error'] ) ) {
			$popular_tags = array_slice(
				array_map(
					fn( $t ) => array(
						'id'    => $t['term_id'] ?? 0,
						'name'  => $t['name'] ?? '',
						'count' => $t['count'] ?? 0,
					),
					$tags_result
				),
				0,
				20
			);
		} else {
			$tags         = get_tags(
				array(
					'orderby' => 'count',
					'order'   => 'DESC',
					'number'  => 20,
				)
			);
			$popular_tags = array_map(
				fn( $t ) => array(
					'id'    => $t->term_id,
					'name'  => $t->name,
					'count' => $t->count,
				),
				$tags
			);
		}

		return array(
			'site_name'        => get_bloginfo( 'name' ),
			'site_description' => get_bloginfo( 'description' ),
			'site_url'         => get_bloginfo( 'url' ),
			'categories'       => $categories,
			'popular_tags'     => $popular_tags,
			'total_posts'      => wp_count_posts()->publish,
			'total_pages'      => wp_count_posts( 'page' )->publish,
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Get_Site_Context();
