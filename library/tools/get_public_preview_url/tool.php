<?php
/**
 * Tool: get_public_preview_url
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

class Get_Public_Preview_Url extends Tool_Base {
	public function get_name(): string {
		return 'get_public_preview_url';
	}

	public function get_description(): string {
		return 'Generate a nonce-based public preview URL for any post so it can be shared without requiring the recipient to log in. Valid for the current WordPress session.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the post to generate a preview URL for.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$post_id = (int) ( $args['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return array( 'error' => 'post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$nonce = wp_create_nonce( 'public_post_preview_' . $post_id );
		$url   = add_query_arg(
			array(
				'preview'       => 'true',
				'preview_nonce' => $nonce,
			),
			get_permalink( $post_id )
		);

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'url'        => $url,
			'expires_in' => 'Valid for this session — regenerate if sharing externally.',
		);
	}
}

return new Get_Public_Preview_Url();
