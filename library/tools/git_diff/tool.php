<?php
/**
 * Tool: git_diff
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
 * Show diff output for a git repository.
 */
class Git_Diff extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'git_diff';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Show changes in a git repository as a unified diff. By default shows unstaged changes. Pass staged: true for staged (index) changes. Pass a file path to diff a single file. Returns the full diff text and a --stat summary.';
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
				'staged'    => array(
					'type'        => 'boolean',
					'description' => 'Show staged (index) changes instead of unstaged. Default false.',
				),
				'path'      => array(
					'type'        => 'string',
					'description' => 'Limit the diff to a specific file or directory.',
				),
				'commit'    => array(
					'type'        => 'string',
					'description' => 'Compare against a specific commit hash or branch instead of the working tree.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		$repo_path = $arguments['repo_path'] ?? '';
		$staged    = ! empty( $arguments['staged'] );
		$filter    = $arguments['path'] ?? '';
		$commit    = $arguments['commit'] ?? '';

		// Stat summary.
		$stat_args = array( 'diff', '--stat' );
		if ( $staged ) {
			$stat_args[] = '--staged';
		}
		if ( '' !== $commit ) {
			$stat_args[] = $commit;
		}
		if ( '' !== $filter ) {
			$stat_args[] = '--';
			$stat_args[] = $filter;
		}
		$stat = \Agentic\Git_Runner::run( $repo_path, $stat_args );
		if ( isset( $stat['error'] ) ) {
			return $stat;
		}

		// Full diff (capped at 64 KB).
		$diff_args = array( 'diff' );
		if ( $staged ) {
			$diff_args[] = '--staged';
		}
		if ( '' !== $commit ) {
			$diff_args[] = $commit;
		}
		if ( '' !== $filter ) {
			$diff_args[] = '--';
			$diff_args[] = $filter;
		}
		$diff = \Agentic\Git_Runner::run( $repo_path, $diff_args, 65536 );
		if ( isset( $diff['error'] ) ) {
			return $diff;
		}

		$empty = '' === trim( $diff['output'] );

		return array(
			'stat'  => $stat['output'],
			'diff'  => $diff['output'],
			'empty' => $empty,
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Git_Diff();
