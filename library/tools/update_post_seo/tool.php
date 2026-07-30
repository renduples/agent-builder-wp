<?php
/**
 * Tool: update_post_seo
 *
 * Update SEO fields on a post.
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
 * Update SEO fields on a post: title, meta description, slug, and focus keyword.
 */
class Update_Post_Seo extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'update_post_seo';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Update SEO fields on a post: title, meta description, URL slug, and focus keyword. Only supply the fields you want to change. Always get explicit user approval before calling this.';
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
				'post_id'          => array(
					'type'        => 'integer',
					'description' => 'The WordPress post ID to update.',
				),
				'title'            => array(
					'type'        => 'string',
					'description' => 'New post title. Recommended: 30–60 characters including focus keyword.',
				),
				'meta_description' => array(
					'type'        => 'string',
					'description' => 'Meta description. Recommended: 120–158 characters.',
				),
				'slug'             => array(
					'type'        => 'string',
					'description' => 'URL slug — 2–5 words, hyphen-separated, keyword-rich.',
				),
				'focus_keyword'    => array(
					'type'        => 'string',
					'description' => 'Focus keyword to write to the active SEO engine meta.',
				),
			),
			'required'   => array( 'post_id' ),
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
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => 'You do not have permission to edit this post.' );
		}

		$post_data = array( 'ID' => $post_id );
		$updated   = array();

		if ( isset( $arguments['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $arguments['title'] );
			$updated[]               = 'title';
		}

		if ( isset( $arguments['meta_description'] ) ) {
			$clean_meta                = sanitize_textarea_field( $arguments['meta_description'] );
			$post_data['post_excerpt'] = $clean_meta;
			$updated[]                 = 'excerpt (meta description)';

			$seo_keys = \Agentic\Tool_Helpers::get_seo_meta_keys();
			update_post_meta( $post_id, $seo_keys['description'], $clean_meta );
			$updated[] = $seo_keys['description'];
		}

		if ( isset( $arguments['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $arguments['slug'] );
			$updated[]              = 'slug';
		}

		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
		}

		if ( isset( $arguments['focus_keyword'] ) ) {
			$seo_keys = \Agentic\Tool_Helpers::get_seo_meta_keys();
			update_post_meta( $post_id, $seo_keys['focus_keyword'], sanitize_text_field( $arguments['focus_keyword'] ) );
			$updated[] = $seo_keys['focus_keyword'];
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'updated' => $updated,
			'url'     => get_permalink( $post_id ),
			'message' => "SEO fields updated for post {$post_id}: " . implode( ', ', $updated ) . '.',
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

return new Update_Post_Seo();
