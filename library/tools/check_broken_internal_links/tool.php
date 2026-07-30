<?php
/**
 * Tool: check_broken_internal_links
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

class Check_Broken_Internal_Links extends Tool_Base {
	public function get_name(): string {
		return 'check_broken_internal_links';
	}

	public function get_description(): string {
		return 'Scan published posts for broken internal links (404s). Fetches each internal URL and reports dead links.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array(
			'post_type'  => array(
				'type'        => 'string',
				'description' => "Post type to scan. Defaults to 'post'.",
				'required'    => false,
			),
			'post_limit' => array(
				'type'        => 'integer',
				'description' => 'Max posts to scan (1-50). Defaults to 20.',
				'required'    => false,
			),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$post_type = $args['post_type'] ?? 'post';
		$limit     = min( 50, max( 1, (int) ( $args['post_limit'] ?? 20 ) ) );
		$site_url  = home_url();
		$site_host = wp_parse_url( $site_url, PHP_URL_HOST );

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$broken       = array();
		$total_links  = 0;
		$checked_urls = array();

		foreach ( $posts as $post ) {
			preg_match_all( '/href=["\']([^"\']+)["\']/i', $post->post_content, $matches );
			if ( empty( $matches[1] ) ) {
				continue;
			}

			foreach ( $matches[1] as $url ) {
				if ( str_starts_with( $url, '#' ) || str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) ) {
					continue;
				}

				// Make relative URLs absolute.
				if ( str_starts_with( $url, '/' ) ) {
					$url = $site_url . $url;
				}

				// Only check internal links.
				$link_host = wp_parse_url( $url, PHP_URL_HOST );
				if ( $link_host && $link_host !== $site_host ) {
					continue;
				}

				++$total_links;
				if ( isset( $checked_urls[ $url ] ) ) {
					if ( false === $checked_urls[ $url ] ) {
						$broken[] = array(
							'post_id'    => $post->ID,
							'post_title' => $post->post_title,
							'broken_url' => $url,
							'status'     => 'duplicate_broken',
						);
					}
					continue;
				}

				$response = wp_remote_head(
					$url,
					array(
						'timeout'     => 5,
						'redirection' => 3,
						'sslverify'   => false,
					)
				);
				if ( is_wp_error( $response ) ) {
					$checked_urls[ $url ] = false;
					$broken[]             = array(
						'post_id'    => $post->ID,
						'post_title' => $post->post_title,
						'broken_url' => $url,
						'status'     => 'error',
						'error'      => $response->get_error_message(),
					);
				} else {
					$code                 = wp_remote_retrieve_response_code( $response );
					$checked_urls[ $url ] = ( $code < 400 );
					if ( $code >= 400 ) {
						$broken[] = array(
							'post_id'    => $post->ID,
							'post_title' => $post->post_title,
							'broken_url' => $url,
							'status'     => (int) $code,
						);
					}
				}
			}
		}

		return array(
			'posts_scanned'        => count( $posts ),
			'total_internal_links' => $total_links,
			'broken_links'         => $broken,
			'broken_count'         => count( $broken ),
		);
	}
}

return new Check_Broken_Internal_Links();
