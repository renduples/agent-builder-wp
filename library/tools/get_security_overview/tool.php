<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Security Overview Tool
 *
 * Runs a comprehensive security health check by aggregating data from
 * multiple security tools and returning a risk-level summary.
 *
 * @package Agentic\Tools
 */
class Get_Security_Overview extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_security_overview';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Run a comprehensive security health check: failed logins in the last 24 hours, plugins with available updates, administrator user count, recent registrations in the last 24 hours, and a risk level summary with prioritised action items.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'security';
	}

	/**
	 * Get the tool parameters.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	/**
	 * Execute the security overview check.
	 *
	 * @param array $arguments Tool arguments (none required).
	 * @return array
	 */
	public function execute( array $arguments ): array {
		$loader   = \Agentic\Tool_Loader::get_instance();
		$failed   = $loader->execute(
			'get_failed_logins',
			array(
				'hours' => 24,
				'limit' => 100,
			)
		);
		$plugins  = $loader->execute( 'check_plugin_updates', array( 'outdated_only' => true ) );
		$admins   = $loader->execute( 'list_privileged_users', array( 'role' => 'administrator' ) );
		$new_regs = $loader->execute(
			'get_recent_registrations',
			array(
				'days'  => 1,
				'limit' => 20,
			)
		);

		$risk_level   = 'low';
		$risk_flags   = array();
		$failed_count = $failed['total'] ?? 0;
		if ( $failed_count > 20 ) {
			$risk_level   = 'high';
			$risk_flags[] = "{$failed_count} failed login attempts in the last 24 hours — possible brute-force attack.";
		} elseif ( $failed_count > 5 ) {
			$risk_level   = 'medium';
			$risk_flags[] = "{$failed_count} failed login attempts in the last 24 hours.";
		}
		$outdated_count = $plugins['outdated_count'] ?? 0;
		if ( $outdated_count > 0 ) {
			if ( 'low' === $risk_level ) {
				$risk_level = 'medium';
			}
			$risk_flags[] = "{$outdated_count} plugin(s) have available updates — update immediately.";
		}
		$admin_count = count( $admins['users'] ?? array() );
		if ( $admin_count > 3 ) {
			$risk_flags[] = "{$admin_count} administrator accounts. Review for unexpected accounts.";
		}
		$reg_count = $new_regs['total'] ?? 0;
		if ( $reg_count > 5 ) {
			$risk_flags[] = "{$reg_count} new registrations in the last 24 hours — possible bot registration.";
		}

		return array(
			'risk_level'            => $risk_level,
			'risk_flags'            => $risk_flags,
			'failed_logins_24h'     => $failed_count,
			'outdated_plugins'      => $outdated_count,
			'admin_user_count'      => $admin_count,
			'new_registrations_24h' => $reg_count,
			'top_failed_login_ips'  => array_slice( $failed['by_ip'] ?? array(), 0, 5 ),
			'outdated_plugin_list'  => array_slice( $plugins['plugins'] ?? array(), 0, 5 ),
		);
	}

	/**
	 * Get the tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly' => true,
		);
	}
}

return new Get_Security_Overview();
