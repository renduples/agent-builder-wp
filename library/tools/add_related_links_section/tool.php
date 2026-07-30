<?php
/**
 * Tool: add_related_links_section
 *
 * Append a Related Articles section to a post's content.
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
 * Append a "Related Articles" section as Gutenberg blocks to a post.
 */
class Add_Related_Links_Section extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'add_related_links_section';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Append a "Related Articles" section (as Gutenberg blocks) to the end of a post\'s content. Fixes dead-end pages by adding outbound internal links. Will not duplicate if a Related Articles section already exists.';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post ID to append the section to.',
				),
				'links'   => array(
					'type'        => 'array',
					'description' => 'Array of related pages to link to.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'title' => array(
								'type'        => 'string',
								'description' => 'Anchor text / link label.',
							),
							'url'   => array(
								'type'        => 'string',
								'description' => 'The target URL (relative or absolute).',
							),
						),
						'required'   => array( 'title', 'url' ),
					),
				),
				'heading' => array(
					'type'        => 'string',
					'description' => 'Section heading text. Defaults to "Related Articles".',
				),
			),
			'required'   => array( 'post_id', 'links' ),
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
		$links   = $arguments['links'] ?? array();
		$heading = sanitize_text_field( $arguments['heading'] ?? 'Related Articles' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => 'You do not have permission to edit this post.' );
		}
		if ( empty( $links ) ) {
			return array( 'error' => 'No links provided.' );
		}
		if ( preg_match( '/<h[23][^>]*>\s*Related\s+Articles\s*<\/h[23]>/i', $post->post_content ) ) {
			return array(
				'error'   => 'A "Related Articles" section already exists on this post.',
				'post_id' => $post_id,
			);
		}

		$list_items = '';
		foreach ( $links as $link ) {
			$title = esc_html( $link['title'] ?? '' );
			$url   = esc_url( $link['url'] ?? '' );
			if ( $title && $url ) {
				$list_items .= '<li><a href="' . $url . '">' . $title . '</a></li>';
			}
		}

		if ( empty( $list_items ) ) {
			return array( 'error' => 'No valid links after sanitisation.' );
		}

		$section  = "\n\n<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
		$section .= "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . esc_html( $heading ) . "</h3>\n<!-- /wp:heading -->\n\n";
		$section .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $list_items . "</ul>\n<!-- /wp:list -->";

		$updated_content = rtrim( $post->post_content ) . $section;
		$result          = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $updated_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array(
			'success'     => true,
			'post_id'     => $post_id,
			'title'       => $post->post_title,
			'links_added' => count( $links ),
			'url'         => get_permalink( $post_id ),
			'message'     => sprintf( 'Added "%s" section with %d link(s) to "%s".', $heading, count( $links ), $post->post_title ),
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'destructive' => false,
		);
	}
}

return new Add_Related_Links_Section();
