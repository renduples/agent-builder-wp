<?php
/**
 * Tool: verify_core_integrity
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

class Verify_Core_Integrity extends Tool_Base {
	public function get_name(): string {
		return 'verify_core_integrity';
	}

	public function get_description(): string {
		return 'Compare WordPress core files against official checksums to detect modified, missing, or unexpected files.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array();
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$wp_version = get_bloginfo( 'version' );
		$locale     = get_locale();

		$url      = "https://api.wordpress.org/core/checksums/1.0/?version={$wp_version}&locale={$locale}";
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Could not fetch checksums from WordPress.org: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['checksums'] ) ) {
			return array( 'error' => "No checksums available for WordPress {$wp_version} ({$locale})." );
		}

		$checksums = $body['checksums'];
		$modified  = array();
		$missing   = array();
		$checked   = 0;

		foreach ( $checksums as $file => $expected_md5 ) {
			if ( str_starts_with( $file, 'wp-content/' ) ) {
				continue;
			}
			$full_path = ABSPATH . $file;
			++$checked;

			if ( ! file_exists( $full_path ) ) {
				$missing[] = $file;
				continue;
			}

			$actual_md5 = md5_file( $full_path );
			if ( $actual_md5 !== $expected_md5 ) {
				$modified[] = $file;
			}
		}

		$issues_count = count( $modified ) + count( $missing );
		$score        = $checked > 0 ? round( ( ( $checked - $issues_count ) / $checked ) * 100 ) : 100;

		return array(
			'wordpress_version' => $wp_version,
			'locale'            => $locale,
			'files_checked'     => $checked,
			'integrity_score'   => $score . '%',
			'modified_files'    => $modified,
			'missing_files'     => $missing,
			'status'            => 0 === $issues_count
				? 'All core files match official checksums.'
				: "{$issues_count} file(s) differ from official WordPress {$wp_version} distribution.",
		);
	}
}

return new Verify_Core_Integrity();
