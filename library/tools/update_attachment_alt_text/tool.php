<?php
/**
 * Tool: update_attachment_alt_text
 *
 * Update the alt text (and optionally title, caption, description) of a
 * WordPress media library attachment.
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
 * Update the alt text and metadata of a WordPress media library attachment.
 */
class Update_Attachment_Alt_Text extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'update_attachment_alt_text';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Update the alt text, title, caption, or description of a media library attachment. Use this to fix accessibility issues and improve SEO for images.';
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
				'attachment_id' => array(
					'type'        => 'integer',
					'description' => 'WordPress media library attachment ID.',
				),
				'alt_text'      => array(
					'type'        => 'string',
					'description' => 'New alt text for the image. Should describe the image content concisely for screen readers and SEO.',
				),
				'title'         => array(
					'type'        => 'string',
					'description' => 'New attachment title (optional).',
				),
				'caption'       => array(
					'type'        => 'string',
					'description' => 'New caption text displayed below the image (optional).',
				),
				'description'   => array(
					'type'        => 'string',
					'description' => 'New long description of the image (optional).',
				),
			),
			'required'   => array( 'attachment_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$attachment_id = (int) $arguments['attachment_id'];

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return array( 'error' => 'You do not have permission to edit attachment ' . $attachment_id . '.' );
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return array( 'error' => 'Attachment ID ' . $attachment_id . ' does not exist in the media library.' );
		}

		$updated = array();

		if ( isset( $arguments['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $arguments['alt_text'] ) );
			$updated['alt_text'] = $arguments['alt_text'];
		}

		$post_data = array( 'ID' => $attachment_id );

		if ( isset( $arguments['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $arguments['title'] );
			$updated['title']        = $arguments['title'];
		}

		if ( isset( $arguments['caption'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $arguments['caption'] );
			$updated['caption']        = $arguments['caption'];
		}

		if ( isset( $arguments['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $arguments['description'] );
			$updated['description']    = $arguments['description'];
		}

		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
		}

		if ( empty( $updated ) ) {
			return array( 'error' => 'No fields provided to update. Specify at least one of: alt_text, title, caption, description.' );
		}

		return array(
			'success'       => true,
			'attachment_id' => $attachment_id,
			'updated'       => $updated,
			'url'           => wp_get_attachment_url( $attachment_id ),
		);
	}
}
return new Update_Attachment_Alt_Text();
