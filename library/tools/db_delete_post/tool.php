<?php
/**
 * Tool: db_delete_post
 *
 * Move a WordPress post to the trash.
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
 * Trash a WordPress post without permanently deleting it.
 */
class Db_Delete_Post extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'db_delete_post';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Move a WordPress post to the trash. Does not permanently delete — use the WordPress admin to empty trash.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'database';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the post to move to trash.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		$post_id = (int) ( $arguments['post_id'] ?? 0 );

		if ( $post_id < 1 ) {
			return array( 'error' => 'A valid post_id is required.' );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return array( 'error' => "Post {$post_id} not found." );
		}

		$result = wp_trash_post( $post_id );

		if ( ! $result ) {
			return array( 'error' => "Failed to trash post {$post_id}." );
		}

		return array(
			'post_id' => $post_id,
			'title'   => $post->post_title,
			'trashed' => true,
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'destructive' => true,
		);
	}
}

return new Db_Delete_Post();
