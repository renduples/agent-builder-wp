<?php
/**
 * Tool: compress_image
 *
 * Reduce an image's file size by lowering JPEG/WebP quality or converting PNG to JPEG.
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
 * Compress a media library image by adjusting quality.
 */
class Compress_Image extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'compress_image';
	}

	public function get_description(): string {
		return 'Compress a media library image to reduce its file size by lowering JPEG/WebP quality. Saves the compressed version as a new media library item. Quality 80 is a good balance; below 60 is visibly lossy. Does not use AI credits.';
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
				'quality'       => array(
					'type'        => 'integer',
					'description' => 'Output quality 1–100. Defaults to 80. For JPEG/WebP: 80 = good quality, 60 = smaller file with some loss.',
				),
				'suffix'        => array(
					'type'        => 'string',
					'description' => 'Filename suffix for the output, e.g. "-compressed". Defaults to "-q{quality}".',
				),
			),
			'required'   => array( 'attachment_id' ),
		);
	}

	public function execute( array $arguments ): array {
		$att_id  = (int) ( $arguments['attachment_id'] ?? 0 );
		$quality = min( max( (int) ( $arguments['quality'] ?? 80 ), 1 ), 100 );

		if ( $att_id <= 0 ) {
			return array( 'error' => 'attachment_id is required.' );
		}

		$mime = get_post_mime_type( $att_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/webp', 'image/png' ), true ) ) {
			return array( 'error' => "compress_image supports JPEG, WebP, and PNG. This attachment is {$mime}." );
		}

		$file = get_attached_file( $att_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return array( 'error' => "Attachment {$att_id} file not found." );
		}

		$original_size = filesize( $file );

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return array( 'error' => 'Could not open image: ' . $editor->get_error_message() );
		}

		$editor->set_quality( $quality );

		$path_parts = pathinfo( $file );
		$suffix     = sanitize_file_name( $arguments['suffix'] ?? "-q{$quality}" );
		$out_path   = $path_parts['dirname'] . '/' . $path_parts['filename'] . $suffix . '.' . $path_parts['extension'];

		$saved = $editor->save( $out_path );
		if ( is_wp_error( $saved ) ) {
			return array( 'error' => 'Save failed: ' . $saved->get_error_message() );
		}

		$new_size  = filesize( $saved['path'] );
		$saved_pct = $original_size > 0 ? round( ( 1 - $new_size / $original_size ) * 100, 1 ) : 0;

		$new_id = wp_insert_attachment(
			array(
				'post_title'     => get_the_title( $att_id ) . " (compressed q{$quality})",
				'post_mime_type' => $mime,
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
			'attachment_id'   => $new_id,
			'url'             => wp_get_attachment_url( $new_id ),
			'quality'         => $quality,
			'original_size'   => size_format( $original_size ),
			'compressed_size' => size_format( $new_size ),
			'saved_percent'   => $saved_pct,
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

return new Compress_Image();
