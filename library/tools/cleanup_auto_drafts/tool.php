<?php
/**
 * Tool: cleanup_auto_drafts
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

class Cleanup_Auto_Drafts extends Tool_Base {
	public function get_name(): string {
		return 'cleanup_auto_drafts';
	}

	public function get_description(): string {
		return 'Delete old auto-draft posts that WordPress creates when you start editing but never save. These accumulate over time and waste database space.';
	}

	public function get_category(): string {
		return 'maintenance';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'days_old' => array(
					'type'        => 'integer',
					'description' => 'Only delete auto-drafts older than this many days. Defaults to 7.',
				),
				'dry_run'  => array(
					'type'        => 'boolean',
					'description' => 'If true, count without deleting. Defaults to false.',
				),
			),
			'required'   => array(),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => false,
			'destructive' => true,
		);
	}

	public function execute( array $args ): array {
		$days_old = (int) ( $args['days_old'] ?? 7 );
		$dry_run  = (bool) ( $args['dry_run'] ?? false );
		$days_old = max( 1, $days_old );

		$auto_drafts = get_posts(
			array(
				'post_status'    => 'auto-draft',
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'before' => "-{$days_old} days",
					),
				),
			)
		);

		$deleted_count = 0;

		if ( ! $dry_run ) {
			foreach ( $auto_drafts as $post_id ) {
				if ( wp_delete_post( (int) $post_id, true ) ) {
					++$deleted_count;
				}
			}
		} else {
			$deleted_count = count( $auto_drafts );
		}

		return array(
			'deleted_count' => $deleted_count,
			'dry_run'       => $dry_run,
			'days_old'      => $days_old,
		);
	}
}

return new Cleanup_Auto_Drafts();
