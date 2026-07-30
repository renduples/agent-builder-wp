<?php
/**
 * Tool: get_email_settings
 *
 * Return the current Email channel configuration so the agent knows
 * what sender details are available before calling send_email.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.7.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return Email channel configuration status.
 */
class Get_Email_Settings extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'get_email_settings';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Return the current Email channel configuration: configured sender name, from address, and default recipient. Call this before send_email to confirm the channel is set up. Requires the add-on with the Email channel enabled.';
	}

	public function get_category(): string {
		return 'communication';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( '\Agentic\Channels\Email_Channel' ) ) {
			return array(
				'error'        => 'The Email channel requires Agent Builder Pro. Upgrade at agentic-plugin.com to enable email sending.',
				'pro_required' => true,
			);
		}

		$recipient  = \Agentic\Channels\Email_Channel::get_setting( 'recipient' );
		$from_name  = \Agentic\Channels\Email_Channel::get_setting( 'from_name' );
		$from_email = \Agentic\Channels\Email_Channel::get_setting( 'from_email' );

		return array(
			'configured'        => '' !== $recipient,
			'default_recipient' => $recipient ?: null,
			'from_name'         => $from_name ?: get_bloginfo( 'name' ),
			'from_email'        => $from_email ?: get_bloginfo( 'admin_email' ),
			'wp_mail_from'      => get_bloginfo( 'admin_email' ),
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Get_Email_Settings();
