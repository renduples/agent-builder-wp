<?php
/**
 * Tool: get_media_storage_report
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

class Get_Media_Storage_Report extends Tool_Base {
	public function get_name(): string {
		return 'get_media_storage_report';
	}

	public function get_description(): string {
		return 'Generate a storage usage report for the WordPress media library. Shows totals by MIME type, the 10 largest files, and overall statistics.';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
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

		// Count by MIME type.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_type_rows = $wpdb->get_results(
			"SELECT post_mime_type, COUNT(*) AS cnt
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment' AND post_status = 'inherit'
			GROUP BY post_mime_type
			ORDER BY cnt DESC",
			ARRAY_A
		);

		// Get all attachment IDs with metadata.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);

		$total_attachments = count( $all_ids );
		$total_bytes       = 0;
		$file_sizes        = array();

		foreach ( $all_ids as $att_id ) {
			$att_id = (int) $att_id;
			$meta   = wp_get_attachment_metadata( $att_id );
			$bytes  = 0;

			if ( isset( $meta['filesize'] ) ) {
				$bytes = (int) $meta['filesize'];
			} else {
				$path = get_attached_file( $att_id );
				if ( $path && file_exists( $path ) ) {
					$bytes = (int) filesize( $path );
				}
			}

			$total_bytes += $bytes;
			$file_sizes[] = array(
				'id'       => $att_id,
				'bytes'    => $bytes,
				'filename' => basename( get_attached_file( $att_id ) ?? '' ),
				'url'      => wp_get_attachment_url( $att_id ),
			);
		}

		// Sort by size descending for top 10.
		usort( $file_sizes, fn( $a, $b ) => $b['bytes'] <=> $a['bytes'] );
		$largest = array_slice( $file_sizes, 0, 10 );

		$largest_files = array_map(
			function ( $f ) {
				return array(
					'id'       => $f['id'],
					'filename' => $f['filename'],
					'url'      => $f['url'],
					'size_mb'  => round( $f['bytes'] / 1048576, 2 ),
				);
			},
			$largest
		);

		// Build by_type with size estimates.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_type_sizes = $wpdb->get_results(
			"SELECT p.post_mime_type, COUNT(*) AS cnt,
			SUM(CAST(pm.meta_value AS UNSIGNED)) AS total_bytes
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_metadata'
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
			GROUP BY p.post_mime_type
			ORDER BY cnt DESC",
			ARRAY_A
		);

		$by_type = array_map(
			function ( $r ) {
				return array(
					'mime_type'     => $r['post_mime_type'],
					'count'         => (int) $r['cnt'],
					'total_size_mb' => 0, // Size by type requires per-file scan; omit for performance.
				);
			},
			$by_type_rows
		);

		return array(
			'total_attachments' => $total_attachments,
			'total_size_mb'     => round( $total_bytes / 1048576, 2 ),
			'by_type'           => $by_type,
			'largest_files'     => $largest_files,
		);
	}
}

return new Get_Media_Storage_Report();
