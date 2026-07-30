<?php
/**
 * Tool: Check Authorship
 *
 * Verify content authors have proper attribution: bios, avatars, and Person
 * schema. Lists authors with missing information.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Check_Authorship extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'check_authorship';
	}

	public function get_description(): string {
		return 'Verify content authors have proper attribution: bios, avatars, and Person schema. Lists authors with missing information.';
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
		$authors = get_users(
			array(
				'capability'          => array( 'edit_posts' ),
				'has_published_posts' => true,
			)
		);

		$results        = array();
		$missing_bio    = array();
		$missing_avatar = array();

		foreach ( $authors as $author ) {
			$bio        = get_the_author_meta( 'description', $author->ID );
			$has_bio    = ! empty( $bio );
			$has_avatar = (bool) get_avatar_url( $author->ID );
			$post_count = count_user_posts( $author->ID, 'post', true );
			$author_url = get_author_posts_url( $author->ID );

			$entry = array(
				'user_id'      => $author->ID,
				'display_name' => $author->display_name,
				'has_bio'      => $has_bio,
				'bio_length'   => mb_strlen( $bio ),
				'has_avatar'   => $has_avatar,
				'post_count'   => $post_count,
				'author_url'   => $author_url,
				'issues'       => array(),
			);

			if ( ! $has_bio ) {
				$entry['issues'][] = 'No bio set. Add one in Users → Profile.';
				$missing_bio[]     = $author->display_name;
			} elseif ( mb_strlen( $bio ) < 50 ) {
				$entry['issues'][] = 'Bio is very short (' . mb_strlen( $bio ) . ' chars). Expand with credentials and expertise.';
			}

			if ( ! $has_avatar ) {
				$entry['issues'][] = 'No profile image. Set a Gravatar or upload a photo.';
				$missing_avatar[]  = $author->display_name;
			}

			$results[] = $entry;
		}

		// Check for Person schema on homepage.
		$home_response     = @wp_safe_remote_get(
			home_url(),
			array(
				'timeout'   => 8,
				'sslverify' => false,
			)
		);
		$home_html         = ! is_wp_error( $home_response ) ? wp_remote_retrieve_body( $home_response ) : '';
		$has_person_schema = (bool) preg_match( '/"@type"\s*:\s*"Person"/', $home_html );

		return array(
			'total_authors'          => count( $authors ),
			'authors'                => $results,
			'authors_missing_bio'    => $missing_bio,
			'authors_missing_avatar' => $missing_avatar,
			'has_person_schema'      => $has_person_schema,
		);
	}
}
return new Check_Authorship();
