<?php
/**
 * Tool: git_log
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
 * Show recent commit history for a git repository.
 */
class Git_Log extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'git_log';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Show recent commit history for a git repository. Returns structured commit objects: hash, author, date, and commit message. Defaults to the last 10 commits on the current branch.';
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
					'description' => 'Absolute path or path relative to the WordPress root. Defaults to the WordPress install root.',
				),
				'count'     => array(
					'type'        => 'integer',
					'description' => 'Number of commits to return (default 10, max 50).',
				),
				'branch'    => array(
					'type'        => 'string',
					'description' => 'Branch or ref to read history from. Defaults to current branch.',
				),
				'path'      => array(
					'type'        => 'string',
					'description' => 'Optional: limit log to commits that touched this file or directory path.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$repo_path = $arguments['repo_path'] ?? '';
		$count     = min( (int) ( $arguments['count'] ?? 10 ), 50 );
		$branch    = $arguments['branch'] ?? '';
		$filter    = $arguments['path'] ?? '';

		$args = array( 'log', '--format=%H|%h|%an|%ae|%at|%s', "-{$count}" );

		if ( '' !== $branch ) {
			$args[] = $branch;
		}

		if ( '' !== $filter ) {
			$args[] = '--';
			$args[] = $filter;
		}

		$result = \Agentic\Git_Runner::run( $repo_path, $args );
		if ( isset( $result['error'] ) ) {
			return $result;
		}

		$commits = \Agentic\Git_Runner::parse_log( $result['output'] );

		return array(
			'commits' => $commits,
			'count'   => count( $commits ),
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Git_Log();
