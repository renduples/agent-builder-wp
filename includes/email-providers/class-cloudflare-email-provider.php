<?php
/**
 * Cloudflare Email Provider
 *
 * Implements the Email_Provider interface using the user's deployed
 * Cloudflare Worker + Cloudflare Email Service.
 *
 * This makes Cloudflare a first-class, reliable backend for
 * transactional emails and the drip campaign system.
 *
 * @package    Agent_Builder
 * @subpackage Email
 * @since      2.11.0
 */

declare(strict_types = 1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cloudflare_Email_Provider implements Email_Provider {

	public function get_slug(): string {
		return 'cloudflare';
	}

	public function get_name(): string {
		return 'Cloudflare Email Service (via Worker)';
	}

	public function is_configured(): bool {
		return ! empty( Cloudflare_Client::get_email_worker_url() ) &&
				! empty( Cloudflare_Client::get_email_auth_token() );
	}

	public function send( array $args ): array {
		// Reuse the existing battle-tested send logic
		return Cloudflare_Client::send_via_worker( $args );
	}

	public function get_config_fields(): array {
		return array(
			'worker_url' => array(
				'label'       => 'Worker URL',
				'type'        => 'url',
				'description' => 'The HTTPS URL of your deployed Cloudflare transactional email Worker.',
			),
			'auth_token' => array(
				'label'       => 'Auth Token',
				'type'        => 'password',
				'description' => 'The secret token configured in your Worker.',
			),
		);
	}
}

// Auto-register when this file is loaded
add_action(
	'plugins_loaded',
	function () {
		Email_Provider_Registry::register( new Cloudflare_Email_Provider() );
	},
	5
);
