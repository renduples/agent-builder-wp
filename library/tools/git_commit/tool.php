<?php
/**
 * Tool: git_commit
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
 * Stage files and create a git commit.
 */
class Git_Commit extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'git_commit';
	}

	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Stage one or more files and create a git commit. If no files are specified, stages all already-tracked modified files (git add -u). Returns the new commit hash on success. Always call git_status first to confirm what will be committed.';
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
				'message'   => array(
					'type'        => 'string',
					'description' => 'Commit message. Should be concise and describe the change.',
				),
				'files'     => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Specific file paths (relative to repo root) to stage. Omit to stage all modified tracked files (git add -u). Does NOT add untracked files.',
				),
			),
			'required'   => array( 'message' ),
		);
	}

	public function execute( array $arguments ): array {
		$repo_path = $arguments['repo_path'] ?? '';
		$message   = trim( $arguments['message'] ?? '' );
		$files     = $arguments['files'] ?? array();

		if ( '' === $message ) {
			return array( 'error' => 'Commit message is required.' );
		}

		// Validate repo first (so we fail fast before staging).
		$valid = \Agentic\Git_Runner::validate_repo( $repo_path );
		if ( is_wp_error( $valid ) ) {
			return array( 'error' => $valid->get_error_message() );
		}

		// Stage files.
		if ( ! empty( $files ) ) {
			$add_args = array_merge( array( 'add', '--' ), array_values( (array) $files ) );
			$add      = \Agentic\Git_Runner::run( $repo_path, $add_args );
			if ( isset( $add['error'] ) || 0 !== $add['exit_code'] ) {
				return array(
					'error'  => 'git add failed.',
					'output' => $add['output'] ?? $add['error'] ?? '',
				);
			}
		} else {
			// Stage only already-tracked modified files — never adds untracked files.
			$add = \Agentic\Git_Runner::run( $repo_path, array( 'add', '-u' ) );
			if ( isset( $add['error'] ) || 0 !== $add['exit_code'] ) {
				return array(
					'error'  => 'git add -u failed.',
					'output' => $add['output'] ?? $add['error'] ?? '',
				);
			}
		}

		// Commit.
		$commit = \Agentic\Git_Runner::run( $repo_path, array( 'commit', '-m', $message ) );
		if ( isset( $commit['error'] ) ) {
			return $commit;
		}

		$success = 0 === $commit['exit_code'];

		// Extract new commit hash.
		$hash = '';
		if ( $success ) {
			$rev = \Agentic\Git_Runner::run( $repo_path, array( 'rev-parse', '--short', 'HEAD' ) );
			if ( ! isset( $rev['error'] ) ) {
				$hash = trim( $rev['output'] );
			}
		}

		return array(
			'success' => $success,
			'hash'    => $hash,
			'output'  => $commit['output'],
			'message' => $success ? "Committed as {$hash}." : 'Commit failed — see output.',
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

return new Git_Commit();
