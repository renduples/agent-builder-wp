<?php
/**
 * Tool: get_user_last_login
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

class Get_User_Last_Login extends Tool_Base {
	public function get_name(): string {
		return 'get_user_last_login';
	}

	public function get_description(): string {
		return 'Get the last login time for a WordPress user. Checks the agentic_last_login usermeta first, then falls back to session_tokens data.';
	}

	public function get_category(): string {
		return 'users';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'user_id'    => array(
					'type'        => 'integer',
					'description' => 'WordPress user ID. Provide either user_id or user_login.',
				),
				'user_login' => array(
					'type'        => 'string',
					'description' => 'WordPress username. Provide either user_id or user_login.',
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
		$user_id    = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;
		$user_login = sanitize_user( $args['user_login'] ?? '' );

		if ( ! $user_id && ! $user_login ) {
			return array( 'error' => 'Provide either user_id or user_login.' );
		}

		$user = $user_id ? get_userdata( $user_id ) : get_user_by( 'login', $user_login );
		if ( ! $user ) {
			return array( 'error' => 'User not found.' );
		}

		$last_login = null;
		$source     = 'unknown';

		// Check agentic_last_login first.
		$agentic_login = get_user_meta( $user->ID, 'agentic_last_login', true );
		if ( $agentic_login ) {
			$last_login = is_numeric( $agentic_login )
				? gmdate( 'c', (int) $agentic_login )
				: $agentic_login;
			$source     = 'agentic_last_login';
		}

		// Fall back to session_tokens.
		if ( ! $last_login ) {
			$sessions = get_user_meta( $user->ID, 'session_tokens', true );
			if ( is_array( $sessions ) && ! empty( $sessions ) ) {
				$latest = 0;
				foreach ( $sessions as $session ) {
					if ( isset( $session['login'] ) && $session['login'] > $latest ) {
						$latest = $session['login'];
					}
				}
				if ( $latest > 0 ) {
					$last_login = gmdate( 'c', $latest );
					$source     = 'session_tokens';
				}
			}
		}

		return array(
			'user_id'      => $user->ID,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'last_login'   => $last_login,
			'source'       => $source,
		);
	}
}

return new Get_User_Last_Login();
