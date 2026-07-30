<?php
/**
 * Tool: find_oversized_images
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

class Find_Oversized_Images extends Tool_Base {
	public function get_name(): string {
		return 'find_oversized_images';
	}

	public function get_description(): string {
		return 'Find images in the media library that exceed a given width or height threshold. Useful for identifying images that should be resized before serving.';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'max_width'  => array(
					'type'        => 'integer',
					'description' => 'Maximum allowed width in pixels. Images wider than this are flagged. Defaults to 2560.',
				),
				'max_height' => array(
					'type'        => 'integer',
					'description' => 'Maximum allowed height in pixels. Images taller than this are flagged. Defaults to 2560.',
				),
				'limit'      => array(
					'type'        => 'integer',
					'description' => 'Maximum number of results to return. Defaults to 50.',
				),
			),
			'required'   => array(),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$max_width  = (int) ( $args['max_width'] ?? 2560 );
		$max_height = (int) ( $args['max_height'] ?? 2560 );
		$limit      = (int) ( $args['limit'] ?? 50 );
		$limit      = min( max( 1, $limit ), 500 );

		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$oversized = array();
		foreach ( $query->posts as $att_id ) {
			if ( count( $oversized ) >= $limit ) {
				break;
			}

			$meta = wp_get_attachment_metadata( (int) $att_id );
			if ( ! $meta ) {
				continue;
			}

			$width  = (int) ( $meta['width'] ?? 0 );
			$height = (int) ( $meta['height'] ?? 0 );

			if ( $width > $max_width || $height > $max_height ) {
				$path    = get_attached_file( (int) $att_id );
				$size_kb = ( $path && file_exists( $path ) ) ? round( filesize( $path ) / 1024, 1 ) : 0;

				$oversized[] = array(
					'attachment_id' => (int) $att_id,
					'filename'      => basename( $path ?? '' ),
					'url'           => wp_get_attachment_url( (int) $att_id ),
					'width'         => $width,
					'height'        => $height,
					'size_kb'       => $size_kb,
				);
			}
		}

		return array(
			'images'      => $oversized,
			'total_found' => count( $oversized ),
			'max_width'   => $max_width,
			'max_height'  => $max_height,
		);
	}
}

return new Find_Oversized_Images();
