<?php
/**
 * Tool: git_push
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.6.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AGENT_BUILDER_DIR . 'includes/class-git-runner.php';

/**
 * Push local commits to a remote git repository.
 */
class Git_Push extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'git_push';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Push local commits to a remote git repository. Requires the repository to have push credentials configured (SSH key or HTTPS token pre-configured on the server). Force-push is not supported. Always confirm with the user before pushing to a shared or production remote.';
	}

	public function get_category(): string {
		return 'git';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'repo_path'    => array(
					'type'        => 'string',
					'description' => 'Absolute path or path relative to the WordPress root.',
				),
				'remote'       => array(
					'type'        => 'string',
					'description' => 'Remote name (default "origin").',
				),
				'branch'       => array(
					'type'        => 'string',
					'description' => 'Branch to push. Defaults to the current branch.',
				),
				'set_upstream' => array(
					'type'        => 'boolean',
					'description' => 'Pass --set-upstream to track the remote branch. Useful for a first push of a new branch.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$repo_path    = $arguments['repo_path'] ?? '';
		$remote       = $arguments['remote'] ?? 'origin';
		$branch       = $arguments['branch'] ?? '';
		$set_upstream = ! empty( $arguments['set_upstream'] );

		$args = array( 'push' );

		if ( $set_upstream ) {
			$args[] = '--set-upstream';
		}

		$args[] = $remote;

		if ( '' !== $branch ) {
			$args[] = $branch;
		}

		$result = \Agentic\Git_Runner::run( $repo_path, $args );
		if ( isset( $result['error'] ) ) {
			return $result;
		}

		$success = 0 === $result['exit_code'];

		return array(
			'success' => $success,
			'output'  => $result['output'],
			'message' => $success ? 'Push completed successfully.' : 'Push failed — see output for details.',
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}
}

return new Git_Push();
