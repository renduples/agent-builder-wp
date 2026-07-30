<?php
/**
 * Tool: search_media_library
 *
 * Search the WordPress media library for images and files.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search the WordPress media library for images and files by keyword, MIME type, or date range.
 */
class Search_Media_Library extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'search_media_library';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Search the WordPress media library for images and files by keyword, MIME type, or date range.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'content';
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
				'keyword'     => array(
					'type'        => 'string',
					'description' => 'Search term for file names, titles, captions, and alt text.',
				),
				'mime_type'   => array(
					'type'        => 'string',
					'description' => 'Filter by MIME type, e.g. "image", "image/png", "application/pdf".',
				),
				'date_after'  => array(
					'type'        => 'string',
					'description' => 'Return files uploaded after this date. Format: YYYY-MM-DD.',
				),
				'date_before' => array(
					'type'        => 'string',
					'description' => 'Return files uploaded before this date. Format: YYYY-MM-DD.',
				),
				'orderby'     => array(
					'type'        => 'string',
					'description' => 'Sort by "date" (default), "title", or "modified".',
				),
				'order'       => array(
					'type'        => 'string',
					'description' => '"DESC" (default) or "ASC".',
				),
				'limit'       => array(
					'type'        => 'integer',
					'description' => 'Max results (default 20, max 50).',
				),
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$limit       = min( max( (int) ( $arguments['limit'] ?? 20 ), 1 ), 50 );
		$orderby     = in_array( $arguments['orderby'] ?? 'date', array( 'date', 'title', 'modified' ), true ) ? ( $arguments['orderby'] ?? 'date' ) : 'date';
		$order       = strtoupper( $arguments['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		$date_after  = sanitize_text_field( $arguments['date_after'] ?? '' );
		$date_before = sanitize_text_field( $arguments['date_before'] ?? '' );

		// Build base query args used in the fallback path.
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'orderby'        => $orderby,
			'order'          => $order,
		);
		if ( ! empty( $arguments['keyword'] ) ) {
			$query_args['s'] = sanitize_text_field( $arguments['keyword'] );
		}
		if ( ! empty( $arguments['mime_type'] ) ) {
			$query_args['post_mime_type'] = sanitize_text_field( $arguments['mime_type'] );
		}
		if ( $date_after || $date_before ) {
			$date_query = array( 'inclusive' => true );
			if ( $date_after ) {
				$date_query['after'] = $date_after;
			}
			if ( $date_before ) {
				$date_query['before'] = $date_before;
			}
			$query_args['date_query'] = array( $date_query );
		}

		// Try the ability first (keyword + mime_type only; date/order handled by fallback).
		if ( ! $date_after && ! $date_before && 'date' === $orderby && 'DESC' === $order ) {
			$ability_input = array( 'count' => $limit );
			if ( ! empty( $arguments['keyword'] ) ) {
				$ability_input['search'] = sanitize_text_field( $arguments['keyword'] );
			}
			if ( ! empty( $arguments['mime_type'] ) ) {
				$ability_input['mime_type'] = sanitize_text_field( $arguments['mime_type'] );
			}

			$ability_result = $this->call_ability( 'wp-extended/get-media', $ability_input );

			if ( $ability_result && ! isset( $ability_result['error'] ) ) {
				return array(
					'total_found' => count( $ability_result ),
					'attachments' => $this->format_ability_results( $ability_result ),
				);
			}
		}

		// Direct WP_Query fallback (supports all params).
		$attachments = get_posts( $query_args );
		$results     = array();
		foreach ( $attachments as $att ) {
			$meta = wp_get_attachment_metadata( $att->ID );
			$item = array(
				'id'        => $att->ID,
				'title'     => $att->post_title,
				'url'       => wp_get_attachment_url( $att->ID ),
				'mime_type' => $att->post_mime_type,
				'date'      => $att->post_date,
				'alt_text'  => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
				'caption'   => $att->post_excerpt,
			);
			if ( ! empty( $meta['width'] ) ) {
				$item['width']  = (int) $meta['width'];
				$item['height'] = (int) $meta['height'];
			}
			if ( ! empty( $meta['filesize'] ) ) {
				$item['filesize'] = size_format( (int) $meta['filesize'] );
			}
			$results[] = $item;
		}
		return array(
			'total_found' => count( $results ),
			'attachments' => $results,
		);
	}

	/**
	 * Format ability API results into the standard attachment shape.
	 *
	 * @param array $items Raw ability results.
	 * @return array
	 */
	private function format_ability_results( array $items ): array {
		$results = array();
		foreach ( $items as $a ) {
			$att_id = $a['ID'] ?? 0;
			$meta   = wp_get_attachment_metadata( $att_id );
			$post   = get_post( $att_id );
			$item   = array(
				'id'        => $att_id,
				'title'     => $a['title'] ?? '',
				'url'       => $a['url'] ?? '',
				'mime_type' => $a['mime_type'] ?? '',
				'date'      => $a['date'] ?? '',
				'alt_text'  => $a['alt_text'] ?? '',
				'caption'   => $post ? $post->post_excerpt : '',
			);
			if ( ! empty( $meta['width'] ) ) {
				$item['width']  = (int) $meta['width'];
				$item['height'] = (int) $meta['height'];
			}
			$filesize = $a['filesize'] ?? 0;
			if ( $filesize > 0 ) {
				$item['filesize'] = size_format( (int) $filesize );
			}
			$results[] = $item;
		}
		return $results;
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Search_Media_Library();
