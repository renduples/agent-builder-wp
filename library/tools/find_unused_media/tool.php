<?php
/**
 * Tool: find_unused_media
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

class Find_Unused_Media extends Tool_Base {
	public function get_name(): string {
		return 'find_unused_media';
	}

	public function get_description(): string {
		return 'Find media attachments that appear to be unused: not set as a featured image and whose URL does not appear in any post content. Note: this is a best-effort scan and may have false positives (e.g. images used in page builder meta or theme options).';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Maximum number of unused attachments to return. Defaults to 50.',
				),
				'mime_type' => array(
					'type'        => 'string',
					'description' => 'Filter by MIME type, e.g. "image/jpeg". Omit for all types.',
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
		global $wpdb;

		$limit     = (int) ( $args['limit'] ?? 50 );
		$limit     = min( max( 1, $limit ), 500 );
		$mime_type = sanitize_text_field( $args['mime_type'] ?? '' );

		// LIKE CONCAT compares against the column value p.guid (not user input) — false positive.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
		if ( $mime_type ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, p.guid, p.post_mime_type, p.post_date
					FROM {$wpdb->posts} p
					WHERE p.post_type = 'attachment'
					AND p.post_status = 'inherit'
					AND p.post_mime_type = %s
					AND p.ID NOT IN (
						SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
						WHERE meta_key = '_thumbnail_id' AND meta_value != ''
					)
					AND NOT EXISTS (
						SELECT 1 FROM {$wpdb->posts} pc
						WHERE pc.post_content LIKE CONCAT('%%', p.guid, '%%')
						AND pc.post_type != 'attachment'
						AND pc.post_status = 'publish'
					)
					ORDER BY p.post_date DESC
					LIMIT %d",
					$mime_type,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, p.guid, p.post_mime_type, p.post_date
					FROM {$wpdb->posts} p
					WHERE p.post_type = 'attachment'
					AND p.post_status = 'inherit'
					AND p.ID NOT IN (
						SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
						WHERE meta_key = '_thumbnail_id' AND meta_value != ''
					)
					AND NOT EXISTS (
						SELECT 1 FROM {$wpdb->posts} pc
						WHERE pc.post_content LIKE CONCAT('%%', p.guid, '%%')
						AND pc.post_type != 'attachment'
						AND pc.post_status = 'publish'
					)
					ORDER BY p.post_date DESC
					LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery

		$attachments = array();
		foreach ( $rows as $row ) {
			$meta       = wp_get_attachment_metadata( (int) $row['ID'] );
			$size_bytes = 0;
			if ( isset( $meta['filesize'] ) ) {
				$size_bytes = (int) $meta['filesize'];
			} else {
				$path = get_attached_file( (int) $row['ID'] );
				if ( $path && file_exists( $path ) ) {
					$size_bytes = (int) filesize( $path );
				}
			}

			$attachments[] = array(
				'attachment_id' => (int) $row['ID'],
				'filename'      => basename( $row['guid'] ),
				'url'           => wp_get_attachment_url( (int) $row['ID'] ),
				'size_bytes'    => $size_bytes,
				'mime_type'     => $row['post_mime_type'],
				'upload_date'   => $row['post_date'],
			);
		}

		return array(
			'attachments' => $attachments,
			'total_found' => count( $attachments ),
			'caveat'      => 'This scan checks post_content and featured image meta only. Images used in page builder data, widget options, theme customizer, or custom fields will not be detected and may appear as false positives.',
		);
	}
}

return new Find_Unused_Media();
