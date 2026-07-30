<?php
/**
 * Tool: Check Content Freshness
 *
 * Scan published content for freshness. Lists stale posts older than a
 * configurable threshold, flags outdated date references, and recommends
 * refresh schedules.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Check_Content_Freshness extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'check_content_freshness';
	}

	public function get_description(): string {
		return 'Scan published content for freshness. Lists stale posts older than a configurable threshold, flags outdated date references, and recommends refresh schedules.';
	}

	public function get_category(): string {
		return 'site-audit';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'stale_months' => array(
					'type'        => 'integer',
					'description' => 'Number of months after which content is considered stale.',
					'default'     => 12,
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
		$stale_months = max( 1, (int) ( $args['stale_months'] ?? 12 ) );
		$stale_cutoff = strtotime( "-{$stale_months} months" );

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$stale_posts  = array();
		$fresh_count  = 0;
		$total        = count( $posts );
		$year_refs    = array();
		$current_year = (int) gmdate( 'Y' );

		foreach ( $posts as $id ) {
			$post         = get_post( $id );
			$modified_ts  = strtotime( $post->post_modified );
			$published_ts = strtotime( $post->post_date );
			$effective_ts = max( $modified_ts, $published_ts );

			if ( $effective_ts < $stale_cutoff ) {
				$age_months    = round( ( time() - $effective_ts ) / ( 30 * DAY_IN_SECONDS ) );
				$stale_posts[] = array(
					'post_id'      => $id,
					'title'        => $post->post_title,
					'last_updated' => gmdate( 'Y-m-d', $effective_ts ),
					'months_stale' => $age_months,
					'word_count'   => str_word_count( wp_strip_all_tags( $post->post_content ) ),
				);
			} else {
				++$fresh_count;
			}

			// Check for outdated year references.
			$content = wp_strip_all_tags( $post->post_content );
			if ( preg_match_all( '/\b(20[12]\d)\b/', $content, $yrs ) ) {
				foreach ( $yrs[1] as $yr ) {
					$yr_int = (int) $yr;
					if ( $yr_int < $current_year - 1 ) {
						$year_refs[] = array(
							'post_id'        => $id,
							'title'          => $post->post_title,
							'year_mentioned' => $yr_int,
						);
					}
				}
			}
		}

		// Sort stale posts by age (oldest first).
		usort( $stale_posts, fn( $a, $b ) => $b['months_stale'] <=> $a['months_stale'] );

		$stale_pct = $total > 0 ? round( ( count( $stale_posts ) / $total ) * 100 ) : 0;

		// Refresh schedule suggestion.
		$schedule = 'monthly';
		if ( $stale_pct > 60 ) {
			$schedule = 'weekly — critical freshness deficit';
		} elseif ( $stale_pct > 30 ) {
			$schedule = 'bi-weekly — significant stale content';
		}

		return array(
			'total_posts'          => $total,
			'fresh_count'          => $fresh_count,
			'stale_count'          => count( $stale_posts ),
			'stale_pct'            => $stale_pct,
			'stale_threshold'      => "{$stale_months} months",
			'stale_posts'          => array_slice( $stale_posts, 0, 20 ),
			'outdated_years'       => array_slice( $year_refs, 0, 10 ),
			'recommended_schedule' => $schedule,
		);
	}
}
return new Check_Content_Freshness();
