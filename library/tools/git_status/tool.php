<?php
/**
 * Tool: git_status
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
 * Show the working tree status of a git repository.
 */
class Git_Status extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'git_status';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Show the working tree status of a git repository: current branch, staged changes, unstaged changes, and untracked files. Defaults to the WordPress root directory if no path is given.';
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
			),
		);
	}

	public function execute( array $arguments ): array {
		$repo_path = $arguments['repo_path'] ?? '';

		// Get current branch.
		$branch = \Agentic\Git_Runner::run( $repo_path, array( 'rev-parse', '--abbrev-ref', 'HEAD' ) );
		if ( isset( $branch['error'] ) ) {
			return $branch;
		}

		// Porcelain status for structured parsing.
		$status = \Agentic\Git_Runner::run( $repo_path, array( 'status', '--porcelain' ) );
		if ( isset( $status['error'] ) ) {
			return $status;
		}

		// Remote tracking info.
		$tracking = \Agentic\Git_Runner::run( $repo_path, array( 'status', '--branch', '--porcelain=v2' ) );

		$staged    = array();
		$unstaged  = array();
		$untracked = array();

		foreach ( explode( "\n", $status['output'] ) as $line ) {
			if ( strlen( $line ) < 3 ) {
				continue;
			}
			$x    = $line[0];
			$y    = $line[1];
			$file = trim( substr( $line, 3 ) );

			if ( '?' === $x && '?' === $y ) {
				$untracked[] = $file;
			} else {
				if ( ' ' !== $x ) {
					$staged[] = array(
						'file'   => $file,
						'status' => $x,
					);
				}
				if ( ' ' !== $y ) {
					$unstaged[] = array(
						'file'   => $file,
						'status' => $y,
					);
				}
			}
		}

		$clean = empty( $staged ) && empty( $unstaged ) && empty( $untracked );

		// Parse ahead/behind from v2 branch output.
		$ahead  = 0;
		$behind = 0;
		if ( ! isset( $tracking['error'] ) ) {
			foreach ( explode( "\n", $tracking['output'] ) as $line ) {
				if ( str_starts_with( $line, '# branch.ab' ) ) {
					preg_match( '/\+(\d+)\s+-(\d+)/', $line, $m );
					$ahead  = (int) ( $m[1] ?? 0 );
					$behind = (int) ( $m[2] ?? 0 );
				}
			}
		}

		return array(
			'branch'    => trim( $branch['output'] ),
			'clean'     => $clean,
			'staged'    => $staged,
			'unstaged'  => $unstaged,
			'untracked' => $untracked,
			'ahead'     => $ahead,
			'behind'    => $behind,
			'summary'   => $clean
				? 'Working tree is clean.'
				: sprintf(
					'%d staged, %d unstaged, %d untracked.',
					count( $staged ),
					count( $unstaged ),
					count( $untracked )
				),
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Git_Status();
