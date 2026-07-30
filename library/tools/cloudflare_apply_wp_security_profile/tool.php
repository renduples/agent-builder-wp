<?php
/**
 * Cloudflare Apply WordPress Security Profile Tool
 *
 * One powerful composite action that applies a strong, opinionated set of
 * defensive rules against the most common WordPress attack vectors.
 *
 * This is the "make my site much harder to attack with one command" tool
 * that the Security Assistant will frequently recommend and use.
 *
 * Actions applied (best effort):
 * - Rate limits on wp-login.php, xmlrpc.php, /wp-admin/, author archives, REST sensitive paths
 * - Basic WAF-style protections via rate limiting + challenge actions
 *
 * @package Agentic\Tools
 */

declare(strict_types = 1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apply recommended Cloudflare security hardening for WordPress.
 */
class Cloudflare_Apply_WP_Security_Profile extends \Agentic\Tool_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'cloudflare_apply_wp_security_profile';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Apply a strong, opinionated WordPress security hardening profile to a Cloudflare zone. Automatically creates 5 defensive rate limiting rules against the most common attack vectors: wp-login.php brute force, xmlrpc.php abuse, author enumeration, wp-admin area, and REST API user enumeration. Use this when the user wants "one-click" protection. Supports dry_run for preview.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_category(): string {
		return 'security';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'required'   => array( 'zone_id' ),
			'properties' => array(
				'zone_id' => array(
					'type'        => 'string',
					'description' => 'The Cloudflare zone ID to protect.',
				),
				'dry_run' => array(
					'type'        => 'boolean',
					'description' => 'If true, only describe what would be done without making changes. Default false.',
				),
				'aggressiveness' => array(
					'type'        => 'string',
					'enum'        => array( 'balanced', 'aggressive' ),
					'description' => 'How strict to be. "balanced" (default) or "aggressive".',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $arguments ): array {
		$zone_id        = sanitize_text_field( $arguments['zone_id'] ?? '' );
		$dry_run        = ! empty( $arguments['dry_run'] );
		$aggressiveness = ( $arguments['aggressiveness'] ?? 'balanced' ) === 'aggressive' ? 'aggressive' : 'balanced';

		if ( empty( $zone_id ) ) {
			return $this->tool_error( 'missing_zone', 'zone_id is required.' );
		}

		$actions_taken = array();
		$errors        = array();

		// Define the profile rules
		$rules = array(
			array(
				'path'        => '/wp-login.php',
				'threshold'   => ( $aggressiveness === 'aggressive' ) ? 3 : 5,
				'period'      => 60,
				'action'      => 'challenge',
				'description' => 'Agentic WP: Protect wp-login.php from brute force',
			),
			array(
				'path'        => '/xmlrpc.php',
				'threshold'   => 2,
				'period'      => 60,
				'action'      => 'block',
				'description' => 'Agentic WP: Block xmlrpc.php abuse',
			),
			array(
				'path'        => '/wp-admin/*',
				'threshold'   => ( $aggressiveness === 'aggressive' ) ? 10 : 20,
				'period'      => 60,
				'action'      => 'challenge',
				'description' => 'Agentic WP: Rate limit wp-admin area',
			),
			array(
				'path'        => '*/author=*',
				'threshold'   => 10,
				'period'      => 60,
				'action'      => 'challenge',
				'description' => 'Agentic WP: Mitigate author enumeration',
			),
			array(
				'path'        => '/wp-json/wp/v2/users*',
				'threshold'   => 5,
				'period'      => 60,
				'action'      => 'challenge',
				'description' => 'Agentic WP: Protect user enumeration via REST',
			),
		);

		if ( $dry_run ) {
			return $this->success( array(
				'dry_run'         => true,
				'zone_id'         => $zone_id,
				'aggressiveness'  => $aggressiveness,
				'rules_planned'   => $rules,
				'message'         => 'Dry run complete. No changes were made.',
			) );
		}

		// Apply each rule using the rate limit tool logic (direct call for reliability)
		foreach ( $rules as $rule ) {
			$tool = new Cloudflare_Create_Rate_Limit_Rule();
			$result = $tool->execute( array(
				'zone_id'     => $zone_id,
				'path'        => $rule['path'],
				'threshold'   => $rule['threshold'],
				'period'      => $rule['period'],
				'action'      => $rule['action'],
				'description' => $rule['description'],
			) );

			if ( ! empty( $result['success'] ) ) {
				$actions_taken[] = $rule['path'];
			} else {
				$errors[] = array(
					'path'    => $rule['path'],
					'error'   => $result['message'] ?? 'Unknown error',
				);
			}
		}

		return $this->success( array(
			'applied'         => count( $actions_taken ),
			'failed'          => count( $errors ),
			'protected_paths' => $actions_taken,
			'errors'          => $errors,
			'aggressiveness'  => $aggressiveness,
			'zone_id'         => $zone_id,
			'message'         => count( $actions_taken ) > 0
				? 'WordPress security profile applied successfully.'
				: 'Profile application encountered issues. Check errors.',
		) );
	}
}

return new Cloudflare_Apply_WP_Security_Profile();