<?php
/**
 * Directory Submission — the one deliberate external call the Agent-Ready
 * Score feature ever makes, and only when an administrator explicitly
 * clicks "Submit to Directory."
 *
 * Never triggered automatically — not by the weekly re-scan cron, not by
 * activation, not by viewing the Agent-Ready page. See readme.txt's "Site
 * Passport Directory (Optional)" External Services entry for the exact
 * payload this sends, and SUBMISSION-NOTES.md for the compliance framing.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      3.3.90
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submits this site's URL and a minimal score summary to Site Passport.
 */
class Directory_Submission {

	/**
	 * Directory API endpoint.
	 *
	 * TODO: confirm the real path against sitepassport.org's own API docs
	 * before this ships — this is a placeholder pending that contract.
	 */
	public const SUBMIT_URL = 'https://sitepassport.org/api/v1/submissions';

	/**
	 * Option storing the last submission's outcome.
	 */
	public const OPTION = 'agentic_directory_submission';

	/**
	 * Submit this site to the directory.
	 *
	 * Deliberately sends only a minimal score summary — overall score, grade,
	 * and when it was last checked — not the full per-check breakdown.
	 *
	 * @return array{submitted_at:string,status:string,response_id:?string,error:?string}
	 */
	public static function submit(): array {
		$score = class_exists( Agent_Ready_Score::class ) ? Agent_Ready_Score::get_latest() : array();

		$body = array(
			'site_url'            => home_url( '/' ),
			'webmcp_manifest_url' => home_url( '/.well-known/webmcp.json' ),
			'score'               => array(
				'overall'    => $score['overall'] ?? 0,
				'grade'      => $score['grade'] ?? '',
				'checked_at' => $score['checked_at'] ?? '',
			),
		);

		$response = wp_remote_post(
			self::SUBMIT_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		$result = array(
			'submitted_at' => gmdate( 'Y-m-d H:i:s' ),
			'status'       => 'error',
			'response_id'  => null,
			'error'        => null,
		);

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				$data                 = json_decode( wp_remote_retrieve_body( $response ), true );
				$result['status']     = 'submitted';
				$result['response_id'] = is_array( $data ) ? ( $data['id'] ?? null ) : null;
			} else {
				$result['error'] = sprintf( 'Directory responded with HTTP %d.', $code );
			}
		}

		update_option( self::OPTION, $result, false );

		return $result;
	}
}
