<?php
/**
 * Tool: find_inactive_users
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

class Find_Inactive_Users extends Tool_Base {
	public function get_name(): string {
		return 'find_inactive_users';
	}

	public function get_description(): string {
		return 'Find users who have not been seen (based on session_tokens or agentic_last_login) for a specified number of days. Users with no sessions are also included.';
	}

	public function get_category(): string {
		return 'users';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'days'  => array(
					'type'        => 'integer',
					'description' => 'Users inactive for this many days are included. Defaults to 90.',
				),
				'role'  => array(
					'type'        => 'string',
					'description' => 'Filter by role, e.g. "subscriber". Omit for all roles.',
				),
				'limit' => array(
					'type'        => 'integer',
					'description' => 'Maximum number of users to return. Defaults to 50.',
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
		$days  = (int) ( $args['days'] ?? 90 );
		$role  = sanitize_key( $args['role'] ?? '' );
		$limit = (int) ( $args['limit'] ?? 50 );
		$days  = max( 1, $days );
		$limit = min( max( 1, $limit ), 500 );

		$cutoff = time() - ( $days * DAY_IN_SECONDS );

		$user_args = array(
			'fields' => 'all',
			'number' => 1000, // Process in batches.
		);
		if ( $role ) {
			$user_args['role'] = $role;
		}

		$users    = get_users( $user_args );
		$inactive = array();

		foreach ( $users as $user ) {
			if ( count( $inactive ) >= $limit ) {
				break;
			}

			$last_seen = null;

			// Check agentic_last_login.
			$agentic_login = get_user_meta( $user->ID, 'agentic_last_login', true );
			if ( $agentic_login && is_numeric( $agentic_login ) ) {
				$last_seen = (int) $agentic_login;
			}

			// Check session_tokens.
			$sessions = get_user_meta( $user->ID, 'session_tokens', true );
			if ( is_array( $sessions ) ) {
				foreach ( $sessions as $session ) {
					if ( isset( $session['login'] ) && $session['login'] > ( $last_seen ?? 0 ) ) {
						$last_seen = $session['login'];
					}
				}
			}

			// Include if last seen before cutoff (or never seen).
			if ( null === $last_seen || $last_seen < $cutoff ) {
				$roles      = (array) $user->roles;
				$inactive[] = array(
					'user_id'      => $user->ID,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
					'role'         => ! empty( $roles ) ? $roles[0] : 'none',
					'last_seen'    => $last_seen ? gmdate( 'c', $last_seen ) : null,
				);
			}
		}

		return array(
			'users'       => $inactive,
			'total_found' => count( $inactive ),
			'days'        => $days,
			'note'        => 'Last seen is determined from agentic_last_login meta or session_tokens. Users who never logged in or whose sessions have expired will show no last_seen date.',
		);
	}
}

return new Find_Inactive_Users();
