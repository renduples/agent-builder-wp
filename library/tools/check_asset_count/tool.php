<?php
/**
 * Tool: check_asset_count
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

class Check_Asset_Count extends Tool_Base {
	public function get_name(): string {
		return 'check_asset_count';
	}

	public function get_description(): string {
		return 'Count CSS and JS files loaded on a page. Flags excessive asset loading that can hurt Core Web Vitals.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array(
			'url' => array(
				'type'        => 'string',
				'description' => 'The URL to check. Defaults to homepage.',
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
		$url = $args['url'] ?? home_url( '/' );

		$rendered = \Agentic\Page_Renderer::fetch(
			$url,
			array(
				'strip_assets'     => false,
				'extract_sections' => false,
				'bypass_cache'     => true,
			)
		);

		if ( ! $rendered['success'] ) {
			return array( 'error' => 'Could not fetch page: ' . ( $rendered['error'] ?? 'unknown error' ) );
		}

		$html = $rendered['html'];

		preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $css_matches );
		$css_files = $css_matches[1] ?? array();

		preg_match_all( '/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $js_matches );
		$js_files = $js_matches[1] ?? array();

		$inline_styles  = preg_match_all( '/<style[^>]*>/i', $html );
		$inline_scripts = preg_match_all( '/<script(?![^>]*src=)[^>]*>/i', $html );

		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$internal_css = 0;
		$external_css = 0;
		foreach ( $css_files as $file ) {
			$host = wp_parse_url( $file, PHP_URL_HOST );
			if ( empty( $host ) || $host === $site_host ) {
				++$internal_css;
			} else {
				++$external_css;
			}
		}

		$internal_js = 0;
		$external_js = 0;
		foreach ( $js_files as $file ) {
			$host = wp_parse_url( $file, PHP_URL_HOST );
			if ( empty( $host ) || $host === $site_host ) {
				++$internal_js;
			} else {
				++$external_js;
			}
		}

		$total_assets = count( $css_files ) + count( $js_files );

		$recommendations = array();
		if ( count( $css_files ) > 10 ) {
			$recommendations[] = count( $css_files ) . ' CSS files — consider combining or deferring.';
		}
		if ( count( $js_files ) > 15 ) {
			$recommendations[] = count( $js_files ) . ' JS files — consider deferring or removing unused.';
		}
		if ( $external_css + $external_js > 5 ) {
			$recommendations[] = ( $external_css + $external_js ) . ' third-party assets. Each adds DNS lookup time.';
		}
		if ( $inline_scripts > 10 ) {
			$recommendations[] = "{$inline_scripts} inline scripts — may block rendering.";
		}
		if ( empty( $recommendations ) ) {
			$recommendations[] = 'Asset count is within healthy range.';
		}

		return array(
			'url'             => $url,
			'css_files'       => count( $css_files ),
			'js_files'        => count( $js_files ),
			'inline_styles'   => $inline_styles,
			'inline_scripts'  => $inline_scripts,
			'internal_css'    => $internal_css,
			'external_css'    => $external_css,
			'internal_js'     => $internal_js,
			'external_js'     => $external_js,
			'total_assets'    => $total_assets,
			'health'          => $total_assets > 25 ? 'poor' : ( $total_assets > 15 ? 'moderate' : 'good' ),
			'html_size_kb'    => round( $rendered['html_size'] / 1024, 1 ),
			'recommendations' => $recommendations,
		);
	}
}

return new Check_Asset_Count();
