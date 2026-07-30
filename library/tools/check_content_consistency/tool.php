<?php
/**
 * Tool: Check Content Consistency
 *
 * Scan for content inconsistencies: mismatched brand names, outdated year
 * references, contradictory info across pages, and content governance issues.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Check_Content_Consistency extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'check_content_consistency';
	}

	public function get_description(): string {
		return 'Scan for content inconsistencies: mismatched brand names, outdated year references, contradictory info across pages, and content governance issues.';
	}

	public function get_category(): string {
		return 'site-audit';
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
		$site_name    = get_bloginfo( 'name' );
		$current_year = (int) gmdate( 'Y' );
		$issues       = array();

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		// Track values for consistency.
		$phone_numbers  = array();
		$email_addrs    = array();
		$brand_variants = array();
		$year_refs      = array();

		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( $post->post_content );

			// Phone numbers.
			if ( preg_match_all( '/(?:\+\d{1,3}[\s.-]?)?\(?\d{2,4}\)?[\s.-]?\d{3,4}[\s.-]?\d{3,4}/', $content, $phones ) ) {
				foreach ( $phones[0] as $phone ) {
					$cleaned = preg_replace( '/\s+/', '', $phone );
					if ( strlen( $cleaned ) >= 7 ) {
						$phone_numbers[ $cleaned ][] = $post->post_title;
					}
				}
			}

			// Email addresses.
			if ( preg_match_all( '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $content, $emails ) ) {
				foreach ( $emails[0] as $email ) {
					$email_addrs[ strtolower( $email ) ][] = $post->post_title;
				}
			}

			// Outdated year references.
			if ( preg_match_all( '/\b(20[12]\d)\b/', $content, $yrs ) ) {
				foreach ( $yrs[1] as $yr ) {
					if ( (int) $yr < $current_year - 1 ) {
						$year_refs[] = array(
							'post_id' => $post->ID,
							'title'   => $post->post_title,
							'year'    => (int) $yr,
						);
					}
				}
			}

			// Brand name variations — check if site name appears in different cases/forms.
			if ( strlen( $site_name ) > 2 ) {
				if ( preg_match_all( '/' . preg_quote( strtolower( $site_name ), '/' ) . '/i', $content, $brand_matches ) ) {
					foreach ( $brand_matches[0] as $bm ) {
						if ( $bm !== $site_name ) {
							$brand_variants[ $bm ][] = $post->post_title;
						}
					}
				}
			}
		}

		// Report inconsistent phones.
		if ( count( $phone_numbers ) > 1 ) {
			$issues[] = array(
				'type'   => 'phone_inconsistency',
				'detail' => count( $phone_numbers ) . ' different phone numbers found across pages.',
				'data'   => array_keys( $phone_numbers ),
			);
		}

		// Report inconsistent emails.
		if ( count( $email_addrs ) > 3 ) {
			$issues[] = array(
				'type'   => 'email_proliferation',
				'detail' => count( $email_addrs ) . ' email addresses found — ensure primary contact email is consistent.',
				'data'   => array_keys( $email_addrs ),
			);
		}

		// Report outdated years.
		$unique_years = array_unique( array_column( $year_refs, 'year' ) );
		if ( ! empty( $unique_years ) ) {
			$issues[] = array(
				'type'   => 'outdated_year_references',
				'detail' => count( $year_refs ) . ' references to outdated years: ' . implode( ', ', $unique_years ),
				'posts'  => array_slice( $year_refs, 0, 10 ),
			);
		}

		// Report brand name variants.
		if ( ! empty( $brand_variants ) ) {
			$issues[] = array(
				'type'   => 'brand_inconsistency',
				'detail' => 'Brand name appears in different forms: "' . implode( '", "', array_keys( $brand_variants ) ) . '" vs correct "' . $site_name . '".',
			);
		}

		return array(
			'posts_scanned'         => count( $posts ),
			'issues'                => $issues,
			'issue_count'           => count( $issues ),
			'phone_numbers_found'   => count( $phone_numbers ),
			'email_addresses_found' => count( $email_addrs ),
		);
	}
}
return new Check_Content_Consistency();
