<?php
/**
 * Tool: set_auto_featured_image
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

class Set_Auto_Featured_Image extends Tool_Base {
	public function get_name(): string {
		return 'set_auto_featured_image';
	}

	public function get_description(): string {
		return 'Automatically set a featured image for a post by finding the first image in its content. Tries to match an existing attachment first; if not found, sideloads the image into the media library.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'   => array(
					'type'        => 'integer',
					'description' => 'ID of the post to set the featured image on.',
				),
				'overwrite' => array(
					'type'        => 'boolean',
					'description' => 'If false (default), skip posts that already have a featured image set.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$post_id   = (int) ( $args['post_id'] ?? 0 );
		$overwrite = (bool) ( $args['overwrite'] ?? false );

		if ( ! $post_id ) {
			return array( 'error' => 'post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		// Check if already has thumbnail.
		if ( ! $overwrite && has_post_thumbnail( $post_id ) ) {
			$existing_id  = get_post_thumbnail_id( $post_id );
			$existing_url = wp_get_attachment_url( $existing_id );
			return array(
				'skipped'        => true,
				'reason'         => 'Post already has a featured image. Pass overwrite:true to replace it.',
				'attachment_id'  => $existing_id,
				'attachment_url' => $existing_url,
			);
		}

		// Find first image in post content.
		$content = $post->post_content;
		if ( ! preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches ) ) {
			return array( 'error' => 'No images found in post content.' );
		}

		$src = $matches[1];

		// Try to find existing attachment.
		$attachment_id  = attachment_url_to_postid( $src );
		$was_sideloaded = false;

		if ( ! $attachment_id ) {
			// Sideload the image.
			$attachment_id = media_sideload_image( $src, $post_id, null, 'id' );
			if ( is_wp_error( $attachment_id ) ) {
				return array( 'error' => 'Failed to sideload image: ' . $attachment_id->get_error_message() );
			}
			$was_sideloaded = true;
		}

		$result = set_post_thumbnail( $post_id, $attachment_id );
		if ( ! $result ) {
			return array( 'error' => 'Failed to set post thumbnail.' );
		}

		return array(
			'post_id'        => $post_id,
			'attachment_id'  => $attachment_id,
			'attachment_url' => wp_get_attachment_url( $attachment_id ),
			'was_sideloaded' => $was_sideloaded,
			'source_src'     => $src,
		);
	}
}

return new Set_Auto_Featured_Image();
