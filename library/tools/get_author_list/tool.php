<?php
/**
 * Tool: get_author_list
 *
 * Get the list of WordPress users who can author posts.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the list of WordPress users who can author posts (editors, authors, administrators).
 */
class Get_Author_List extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'get_author_list';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get the list of WordPress users who can author posts (editors, authors, administrators).';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'content';
	}

	/**
	 * Get the parameter schema.
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
	 * Execute the tool.
	 *
	 * @param array $arguments Validated arguments from the LLM.
	 * @return array Result data.
	 */
	public function execute( array $arguments ): array {
		// Try ability for each author role, merge results.
		$author_roles = array( 'administrator', 'editor', 'author' );
		$all_users    = array();

		foreach ( $author_roles as $role ) {
			$result = $this->call_ability(
				'wp-extended/get-users',
				array(
					'role'   => $role,
					'number' => 50,
				)
			);
			if ( $result && ! isset( $result['error'] ) ) {
				foreach ( $result as $u ) {
					$all_users[ $u['ID'] ?? 0 ] = array(
						'id'           => (int) ( $u['ID'] ?? 0 ),
						'display_name' => $u['display_name'] ?? '',
						'user_login'   => $u['user_login'] ?? '',
					);
				}
			}
		}

		if ( ! empty( $all_users ) ) {
			return array( 'authors' => array_values( $all_users ) );
		}

		// Fallback: ability unavailable, query directly.
		$users = get_users(
			array(
				'role__in' => $author_roles,
				'fields'   => array( 'ID', 'display_name', 'user_email', 'user_login' ),
				'orderby'  => 'display_name',
			)
		);
		return array(
			'authors' => array_map(
				fn( $u ) => array(
					'id'           => (int) $u->ID,
					'display_name' => $u->display_name,
					'user_login'   => $u->user_login,
				),
				$users
			),
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Get_Author_List();
