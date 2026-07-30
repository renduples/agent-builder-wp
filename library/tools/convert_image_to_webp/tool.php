<?php
/**
 * Tool: convert_image_to_webp
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

class Convert_Image_To_Webp extends Tool_Base {
	public function get_name(): string {
		return 'convert_image_to_webp';
	}

	public function get_description(): string {
		return 'Convert an existing media library image to WebP format. Creates a new attachment for the WebP version. Optionally deletes the original file after conversion.';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'attachment_id'   => array(
					'type'        => 'integer',
					'description' => 'ID of the media library attachment to convert.',
				),
				'quality'         => array(
					'type'        => 'integer',
					'description' => 'WebP compression quality (1-100). Defaults to 80.',
				),
				'delete_original' => array(
					'type'        => 'boolean',
					'description' => 'If true, delete the original attachment after conversion. Defaults to false.',
				),
			),
			'required'   => array( 'attachment_id' ),
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

		$attachment_id   = (int) ( $args['attachment_id'] ?? 0 );
		$quality         = (int) ( $args['quality'] ?? 80 );
		$delete_original = (bool) ( $args['delete_original'] ?? false );
		$quality         = min( max( 1, $quality ), 100 );

		if ( ! $attachment_id ) {
			return array( 'error' => 'attachment_id is required.' );
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return array( 'error' => 'Attachment file not found on disk.' );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			return array( 'error' => "Cannot convert mime type '{$mime}' to WebP." );
		}

		if ( 'image/webp' === $mime ) {
			return array( 'error' => 'Image is already in WebP format.' );
		}

		$original_size_kb = round( filesize( $path ) / 1024, 1 );

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return array( 'error' => 'Could not load image editor: ' . $editor->get_error_message() );
		}

		$new_path = preg_replace( '/\.[^.]+$/', '.webp', $path );
		$saved    = $editor->save( $new_path, 'image/webp', array( 'quality' => $quality ) );

		if ( is_wp_error( $saved ) ) {
			return array( 'error' => 'Conversion failed: ' . $saved->get_error_message() );
		}

		$new_size_kb = round( filesize( $new_path ) / 1024, 1 );

		// Create new attachment.
		$upload_dir = wp_upload_dir();
		$new_url    = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $new_path );

		$attachment_data = array(
			'post_mime_type' => 'image/webp',
			'post_title'     => sanitize_file_name( basename( $new_path, '.webp' ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => get_post( $attachment_id )->post_parent ?? 0,
		);

		$new_attachment_id = wp_insert_attachment( $attachment_data, $new_path );
		if ( is_wp_error( $new_attachment_id ) ) {
			return array( 'error' => 'Failed to create attachment: ' . $new_attachment_id->get_error_message() );
		}

		$attach_data = wp_generate_attachment_metadata( $new_attachment_id, $new_path );
		wp_update_attachment_metadata( $new_attachment_id, $attach_data );

		if ( $delete_original ) {
			wp_delete_attachment( $attachment_id, true );
		}

		return array(
			'new_attachment_id' => $new_attachment_id,
			'new_url'           => $new_url,
			'original_size_kb'  => $original_size_kb,
			'new_size_kb'       => $new_size_kb,
			'savings_pct'       => $original_size_kb > 0 ? round( ( 1 - $new_size_kb / $original_size_kb ) * 100, 1 ) : 0,
			'original_deleted'  => $delete_original,
		);
	}
}

return new Convert_Image_To_Webp();
