<?php
/**
 * Tool: resize_image
 *
 * Resize or crop a media library image using WP_Image_Editor (GD/Imagick).
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.7.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resize or crop a media library image.
 */
class Resize_Image extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'resize_image';
	}

	public function get_description(): string {
		return 'Resize or crop an image from the WordPress media library. Saves the result as a new media library item. Supports hard crop (exact dimensions) and soft resize (maintain aspect ratio). Works on JPEG, PNG, GIF, and WebP. Does not use AI credits.';
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
					'description' => 'ID of the source media library attachment.',
				),
				'width'         => array(
					'type'        => 'integer',
					'description' => 'Target width in pixels.',
				),
				'height'        => array(
					'type'        => 'integer',
					'description' => 'Target height in pixels. If omitted with soft resize, height is calculated from aspect ratio.',
				),
				'mode'          => array(
					'type'        => 'string',
					'description' => '"resize" (default) — scale to fit within width×height, preserving aspect ratio. "crop" — hard crop to exact width×height from the centre.',
				),
				'suffix'        => array(
					'type'        => 'string',
					'description' => 'Optional filename suffix for the output file, e.g. "-thumb". Defaults to "-{width}x{height}".',
				),
			),
			'required'   => array( 'attachment_id', 'width' ),
		);
	}

	public function execute( array $arguments ): array {
		$att_id = (int) ( $arguments['attachment_id'] ?? 0 );
		$width  = (int) ( $arguments['width'] ?? 0 );
		$height = isset( $arguments['height'] ) ? (int) $arguments['height'] : null;
		$mode   = ( $arguments['mode'] ?? 'resize' ) === 'crop' ? 'crop' : 'resize';

		if ( $att_id <= 0 || $width <= 0 ) {
			return array( 'error' => 'attachment_id and width are required.' );
		}
		if ( $width > 8000 || ( $height && $height > 8000 ) ) {
			return array( 'error' => 'Dimensions must not exceed 8000 px.' );
		}

		$file = get_attached_file( $att_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return array( 'error' => "Attachment {$att_id} file not found." );
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return array( 'error' => 'Could not open image: ' . $editor->get_error_message() );
		}

		if ( 'crop' === $mode ) {
			$crop_height = $height ?? $width;
			$result      = $editor->resize( $width, $crop_height, true );
		} else {
			$result = $editor->resize( $width, $height, false );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'error' => 'Resize failed: ' . $result->get_error_message() );
		}

		// Build output filename.
		$size       = $editor->get_size();
		$out_w      = $size['width'] ?? $width;
		$out_h      = $size['height'] ?? ( $height ?? $width );
		$suffix     = sanitize_file_name( $arguments['suffix'] ?? "-{$out_w}x{$out_h}" );
		$path_parts = pathinfo( $file );
		$out_path   = $path_parts['dirname'] . '/' . $path_parts['filename'] . $suffix . '.' . $path_parts['extension'];

		$saved = $editor->save( $out_path );
		if ( is_wp_error( $saved ) ) {
			return array( 'error' => 'Save failed: ' . $saved->get_error_message() );
		}

		// Register as a new attachment.
		$new_id = wp_insert_attachment(
			array(
				'post_title'     => get_the_title( $att_id ) . " ({$out_w}×{$out_h})",
				'post_mime_type' => get_post_mime_type( $att_id ),
				'post_status'    => 'inherit',
			),
			$saved['path']
		);

		if ( is_wp_error( $new_id ) ) {
			return array( 'error' => 'Could not register new attachment: ' . $new_id->get_error_message() );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $new_id, wp_generate_attachment_metadata( $new_id, $saved['path'] ) );

		return array(
			'attachment_id' => $new_id,
			'url'           => wp_get_attachment_url( $new_id ),
			'width'         => $out_w,
			'height'        => $out_h,
			'file_size'     => size_format( filesize( $saved['path'] ) ),
			'mode'          => $mode,
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}
}

return new Resize_Image();
