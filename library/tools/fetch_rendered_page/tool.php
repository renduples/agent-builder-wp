<?php
/**
 * Tool: fetch_rendered_page
 *
 * Fetch the fully rendered HTML of a page on this site.
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
 * Fetch and analyse the fully rendered HTML output of a site page.
 */
class Fetch_Rendered_Page extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'fetch_rendered_page';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Fetch the fully rendered HTML of a page on this site. Returns the complete output including header, footer, navigation menus, widgets, schema markup, and all dynamic content — exactly what search engines and AI crawlers see. Use this instead of reading post_content from the database when you need to analyse how a page actually looks and what metadata it contains. Automatically extracts SEO metadata (title, description, canonical, Open Graph, JSON-LD schema types), heading structure (H1/H2), and word/link/image counts.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'seo';
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
				'url'     => array(
					'type'        => 'string',
					'description' => 'Full URL of the page to fetch (must be on this site). Example: https://example.com/about/',
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Alternatively, provide a post ID instead of a URL. The permalink will be resolved automatically.',
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$post_id = (int) ( $arguments['post_id'] ?? 0 );
		$url     = $arguments['url'] ?? '';

		if ( $post_id > 0 ) {
			$result = \Agentic\Page_Renderer::fetch_by_post_id( $post_id );
		} elseif ( ! empty( $url ) ) {
			$result = \Agentic\Page_Renderer::fetch( sanitize_url( $url ) );
		} else {
			return array( 'error' => 'Provide either a url or post_id argument.' );
		}

		if ( ! $result['success'] ) {
			return array( 'error' => $result['error'] ?? 'Failed to fetch page.' );
		}

		$html = $result['html'];

		if ( strlen( $html ) > 60000 ) {
			$html = substr( $html, 0, 60000 ) . "\n<!-- truncated -->";
		}

		return array(
			'url'       => $result['url'],
			'status'    => $result['status'],
			'html_size' => $result['html_size'],
			'meta'      => $result['meta'],
			'sections'  => $result['sections'],
			'headers'   => $result['headers'],
			'html'      => $html,
			'cached'    => $result['cached'],
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
		);
	}
}

return new Fetch_Rendered_Page();
