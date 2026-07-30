<?php
/**
 * Tool: git_pull
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
 * Pull latest changes from a remote git repository.
 */
class Git_Pull extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'git_pull';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Pull the latest commits from a remote git repository into the local working copy. Use this to deploy or update code on the server. Defaults to "git pull origin" on the current branch.';
	}

	public function get_category(): string {
		return 'git';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'repo_path' => array(
					'type'        => 'string',
					'description' => 'Absolute path or path relative to the WordPress root.',
				),
				'remote'    => array(
					'type'        => 'string',
					'description' => 'Remote name (default "origin").',
				),
				'branch'    => array(
					'type'        => 'string',
					'description' => 'Branch to pull. Defaults to the current tracking branch.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$repo_path = $arguments['repo_path'] ?? '';
		$remote    = $arguments['remote'] ?? 'origin';
		$branch    = $arguments['branch'] ?? '';

		$args = array( 'pull', $remote );
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
			'message' => $success ? 'Pull completed successfully.' : 'Pull failed — see output for details.',
		);
	}

	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Git_Pull();
