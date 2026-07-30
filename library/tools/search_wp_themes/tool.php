<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search WordPress Themes Tool
 *
 * Searches the WordPress.org theme directory for themes matching
 * a keyword, tag, or feature set.
 *
 * @package Agentic\Tools
 */
class Search_Wp_Themes extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'search_wp_themes';
	}

	public function get_description(): string {
		return 'Search the WordPress.org theme directory by keyword, tag, or feature. Returns theme names, ratings, active installs, and descriptions to help users choose a theme.';
	}

	public function get_category(): string {
		return 'themes';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'search'   => array(
					'type'        => 'string',
					'description' => 'Search keyword (e.g. "portfolio", "blog", "minimal").',
				),
				'tag'      => array(
					'type'        => 'string',
					'description' => 'Theme tag to filter by (e.g. "full-site-editing", "blog", "e-commerce").',
				),
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Number of results to return (default: 5, max: 10).',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$per_page = min( (int) ( $arguments['per_page'] ?? 5 ), 10 );

		$query_args = array(
			'per_page' => $per_page,
			'fields'   => array(
				'description'     => true,
				'rating'          => true,
				'num_ratings'     => true,
				'active_installs' => true,
				'screenshot_url'  => true,
				'requires'        => true,
				'requires_php'    => true,
			),
		);

		if ( ! empty( $arguments['search'] ) ) {
			$query_args['search'] = sanitize_text_field( $arguments['search'] );
		} elseif ( ! empty( $arguments['tag'] ) ) {
			$query_args['tag'] = sanitize_text_field( $arguments['tag'] );
		} else {
			$query_args['browse'] = 'popular';
		}

		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$api = themes_api( 'query_themes', $query_args );

		if ( is_wp_error( $api ) ) {
			return array( 'error' => $api->get_error_message() );
		}

		$themes = array();
		foreach ( $api->themes as $theme ) {
			$themes[] = array(
				'name'            => $theme->name ?? '',
				'slug'            => $theme->slug ?? '',
				'version'         => $theme->version ?? '',
				'author'          => wp_strip_all_tags( $theme->author ?? '' ),
				'description'     => wp_trim_words( wp_strip_all_tags( $theme->description ?? '' ), 30 ),
				'rating'          => $theme->rating ?? 0,
				'num_ratings'     => $theme->num_ratings ?? 0,
				'active_installs' => $theme->active_installs ?? 0,
				'requires'        => $theme->requires ?? null,
				'requires_php'    => $theme->requires_php ?? null,
				'preview_url'     => 'https://wp-themes.com/' . ( $theme->slug ?? '' ),
			);
		}

		return array(
			'query'   => $arguments['search'] ?? $arguments['tag'] ?? 'popular',
			'total'   => (int) ( $api->info['results'] ?? count( $themes ) ),
			'results' => $themes,
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Search_Wp_Themes();
