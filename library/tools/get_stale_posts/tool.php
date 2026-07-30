<?php
/**
 * Tool: get_stale_posts
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

class Get_Stale_Posts extends Tool_Base {
	public function get_name(): string {
		return 'get_stale_posts';
	}

	public function get_description(): string {
		return 'Find published posts that have not been modified in a specified number of months. Helps identify content that may need refreshing.';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to check. Defaults to "post".',
				),
				'months'    => array(
					'type'        => 'integer',
					'description' => 'Posts not modified in this many months are considered stale. Defaults to 12.',
				),
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Maximum number of posts to return. Defaults to 50.',
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
		$post_type = sanitize_key( $args['post_type'] ?? 'post' );
		$months    = (int) ( $args['months'] ?? 12 );
		$limit     = (int) ( $args['limit'] ?? 50 );
		$months    = max( 1, $months );
		$limit     = min( max( 1, $limit ), 500 );

		$before_date = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'date_query'     => array(
					array(
						'column' => 'post_modified',
						'before' => $before_date,
					),
				),
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			$months_stale = (int) floor( ( time() - strtotime( $post->post_modified ) ) / ( 30 * DAY_IN_SECONDS ) );

			$posts[] = array(
				'ID'            => $post->ID,
				'title'         => $post->post_title,
				'permalink'     => get_permalink( $post->ID ),
				'last_modified' => $post->post_modified,
				'months_stale'  => $months_stale,
			);
		}

		return array(
			'posts'       => $posts,
			'total_found' => $query->found_posts,
			'showing'     => count( $posts ),
			'months'      => $months,
			'before_date' => $before_date,
		);
	}
}

return new Get_Stale_Posts();
