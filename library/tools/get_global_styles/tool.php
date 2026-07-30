<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Global Styles Tool
 *
 * Reads the active theme's global styles configuration from theme.json,
 * including colour palette, typography, spacing, and layout settings.
 *
 * @package Agentic\Tools
 */
class Get_Global_Styles extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'get_global_styles';
	}

	public function get_description(): string {
		return 'Read the active theme\'s global styles from theme.json: colour palette, typography (fonts, sizes), spacing, layout width, and custom CSS. Useful for understanding and guiding theme customisation.';
	}

	public function get_category(): string {
		return 'themes';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Specific section to return: "color", "typography", "spacing", "layout", or "all" (default).',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$theme = wp_get_theme();

		if ( ! $theme->is_block_theme() ) {
			return array(
				'is_block_theme' => false,
				'message'        => $theme->get( 'Name' ) . ' is a classic theme. Global styles (theme.json) are only available on block themes. Use the Customizer for classic theme settings.',
			);
		}

		$theme_json_path = $theme->get_stylesheet_directory() . '/theme.json';
		if ( ! file_exists( $theme_json_path ) ) {
			return array(
				'error' => 'No theme.json found for ' . $theme->get( 'Name' ) . '.',
			);
		}

		$raw  = file_get_contents( $theme_json_path );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return array( 'error' => 'Failed to parse theme.json.' );
		}

		$section  = $arguments['section'] ?? 'all';
		$settings = $data['settings'] ?? array();
		$styles   = $data['styles'] ?? array();

		$result = array(
			'theme'   => $theme->get( 'Name' ),
			'version' => $data['version'] ?? null,
		);

		if ( 'all' === $section || 'color' === $section ) {
			$result['color'] = array(
				'palette'   => $settings['color']['palette'] ?? array(),
				'gradients' => $settings['color']['gradients'] ?? array(),
				'custom'    => $settings['color']['custom'] ?? true,
				'styles'    => array(
					'background' => $styles['color']['background'] ?? null,
					'text'       => $styles['color']['text'] ?? null,
				),
			);
		}

		if ( 'all' === $section || 'typography' === $section ) {
			$result['typography'] = array(
				'font_families' => $settings['typography']['fontFamilies'] ?? array(),
				'font_sizes'    => $settings['typography']['fontSizes'] ?? array(),
				'line_height'   => $settings['typography']['lineHeight'] ?? null,
				'styles'        => array(
					'font_family' => $styles['typography']['fontFamily'] ?? null,
					'font_size'   => $styles['typography']['fontSize'] ?? null,
				),
			);
		}

		if ( 'all' === $section || 'spacing' === $section ) {
			$result['spacing'] = array(
				'units'     => $settings['spacing']['units'] ?? array(),
				'padding'   => $styles['spacing']['padding'] ?? null,
				'margin'    => $styles['spacing']['margin'] ?? null,
				'block_gap' => $styles['spacing']['blockGap'] ?? null,
			);
		}

		if ( 'all' === $section || 'layout' === $section ) {
			$result['layout'] = array(
				'content_size' => $settings['layout']['contentSize'] ?? null,
				'wide_size'    => $settings['layout']['wideSize'] ?? null,
			);
		}

		// User customisations (from database, overriding theme.json).
		if ( function_exists( 'wp_get_global_styles' ) ) {
			$user_styles                       = wp_get_global_styles();
			$result['has_user_customisations'] = ! empty( $user_styles );
		}

		return $result;
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Get_Global_Styles();
