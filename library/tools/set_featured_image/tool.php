<?php
/**
 * Tool: set_featured_image
 *
 * Set a media library attachment as the featured image (post thumbnail) for a post or page.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.2.7
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set a media library attachment as the featured image for a post or page.
 */
class Set_Featured_Image extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'set_featured_image';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Set a media library image as the featured image (post thumbnail) for a post or page.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'media';
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
				'post_id'       => array(
					'type'        => 'integer',
					'description' => 'WordPress post or page ID.',
				),
				'attachment_id' => array(
					'type'        => 'integer',
					'description' => 'WordPress media library attachment ID to use as the featured image.',
				),
			),
			'required'   => array( 'post_id', 'attachment_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$post_id       = (int) $arguments['post_id'];
		$attachment_id = (int) $arguments['attachment_id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => 'You do not have permission to edit post ' . $post_id . '.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post ID ' . $post_id . ' not found.' );
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return array( 'error' => 'Attachment ID ' . $attachment_id . ' does not exist in the media library.' );
		}

		$result = set_post_thumbnail( $post_id, $attachment_id );

		if ( false === $result ) {
			return array( 'error' => 'Failed to set the featured image. The post or attachment may be invalid.' );
		}

		return array(
			'success'       => true,
			'post_id'       => $post_id,
			'attachment_id' => $attachment_id,
			'image_url'     => wp_get_attachment_image_url( $attachment_id, 'full' ),
		);
	}
}

return new Set_Featured_Image();
