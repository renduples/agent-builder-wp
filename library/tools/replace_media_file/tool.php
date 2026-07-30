<?php
/**
 * Tool: replace_media_file
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

class Replace_Media_File extends Tool_Base {
	public function get_name(): string {
		return 'replace_media_file';
	}

	public function get_description(): string {
		return 'Replace the file behind an existing media attachment with a new file downloaded from a URL. Preserves the attachment ID so all existing references remain valid.';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'attachment_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the existing media attachment to replace.',
				),
				'new_file_url'  => array(
					'type'        => 'string',
					'description' => 'HTTPS URL of the new file to download and use as the replacement.',
				),
			),
			'required'   => array( 'attachment_id', 'new_file_url' ),
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

		$attachment_id = (int) ( $args['attachment_id'] ?? 0 );
		$new_file_url  = trim( $args['new_file_url'] ?? '' );

		if ( ! $attachment_id ) {
			return array( 'error' => 'attachment_id is required.' );
		}
		if ( ! $new_file_url ) {
			return array( 'error' => 'new_file_url is required.' );
		}
		if ( strpos( $new_file_url, 'https://' ) !== 0 ) {
			return array( 'error' => 'new_file_url must use HTTPS.' );
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return array( 'error' => 'Attachment not found.' );
		}

		$old_filename = basename( get_attached_file( $attachment_id ) );

		// Download the new file to a temp location.
		$tmp_file = download_url( $new_file_url );
		if ( is_wp_error( $tmp_file ) ) {
			return array( 'error' => 'Failed to download file: ' . $tmp_file->get_error_message() );
		}

		// Prepare for wp_handle_sideload.
		$file_array = array(
			'name'     => basename( wp_parse_url( $new_file_url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp_file,
		);

		$uploaded = wp_handle_sideload(
			$file_array,
			array( 'test_form' => false )
		);

		if ( isset( $uploaded['error'] ) ) {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
			return array( 'error' => 'Upload failed: ' . $uploaded['error'] );
		}

		$new_file_path = $uploaded['file'];
		$new_filename  = basename( $new_file_path );

		// Update the attachment to point at the new file.
		update_attached_file( $attachment_id, $new_file_path );
		$new_metadata = wp_generate_attachment_metadata( $attachment_id, $new_file_path );
		wp_update_attachment_metadata( $attachment_id, $new_metadata );

		// Update MIME type.
		wp_update_post(
			array(
				'ID'             => $attachment_id,
				'post_mime_type' => $uploaded['type'],
			)
		);

		return array(
			'attachment_id' => $attachment_id,
			'new_url'       => wp_get_attachment_url( $attachment_id ),
			'old_filename'  => $old_filename,
			'new_filename'  => $new_filename,
		);
	}
}

return new Replace_Media_File();
