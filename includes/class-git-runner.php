<?php
/**
 * Git Runner — shared helper for executing git commands safely.
 *
 * Validates that exec() is available, the target path is a real git
 * repository, and that no path-traversal escape is attempted. Used
 * exclusively by the git_* tool family.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      2.6.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Git_Runner
 */
final class Git_Runner {

	/**
	 * Blocked top-level system paths that must never be used as a repo root.
	 */
	private const BLOCKED_ROOTS = array( '/', '/etc', '/usr', '/bin', '/sbin', '/boot', '/dev', '/proc', '/sys' );

	/**
	 * Run a git sub-command inside a validated repository directory.
	 *
	 * @param string   $repo_path Absolute or ABSPATH-relative path to the repo.
	 * @param string[] $git_args  Git sub-command and its arguments (each shell-escaped individually).
	 * @param int      $max_bytes Maximum output size in bytes. Default 131072 (128 KB).
	 * @return array{output: string, exit_code: int}|array{error: string}
	 */
	public static function run( string $repo_path, array $git_args, int $max_bytes = 131072 ): array {
		if ( ! self::exec_available() ) {
			return array( 'error' => 'exec() is disabled on this server. Git operations require shell access.' );
		}

		$path = self::validate_repo( $repo_path );
		if ( is_wp_error( $path ) ) {
			return array( 'error' => $path->get_error_message() );
		}

		$cmd = 'git -C ' . escapeshellarg( $path );
		foreach ( $git_args as $arg ) {
			$cmd .= ' ' . escapeshellarg( (string) $arg );
		}
		$cmd .= ' 2>&1';

		$lines     = array();
		$exit_code = 0;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Intentional git execution behind capability check.
		exec( $cmd, $lines, $exit_code );

		$output = implode( "\n", $lines );

		if ( strlen( $output ) > $max_bytes ) {
			$output = substr( $output, 0, $max_bytes ) . "\n[output truncated]";
		}

		return array(
			'output'    => $output,
			'exit_code' => $exit_code,
		);
	}

	/**
	 * Validate that a path is a real, accessible git repository.
	 *
	 * Accepts absolute paths or paths relative to ABSPATH.
	 *
	 * @param string $path Raw path from tool argument.
	 * @return string|\WP_Error Resolved absolute path on success.
	 */
	public static function validate_repo( string $path ): string|\WP_Error {
		if ( '' === $path ) {
			$path = ABSPATH;
		}

		if ( ! path_is_absolute( $path ) ) {
			$path = rtrim( ABSPATH, '/' ) . '/' . ltrim( $path, '/' );
		}

		$real = realpath( $path );

		if ( false === $real ) {
			return new \WP_Error( 'not_found', "Path not found: {$path}" );
		}

		if ( ! is_dir( $real ) ) {
			return new \WP_Error( 'not_dir', "Path is not a directory: {$real}" );
		}

		foreach ( self::BLOCKED_ROOTS as $blocked ) {
			if ( $real === $blocked ) {
				return new \WP_Error( 'blocked', "Blocked system path: {$real}" );
			}
		}

		// Verify it is actually a git repository.
		if ( ! self::exec_available() ) {
			return new \WP_Error( 'no_exec', 'exec() is not available.' );
		}

		$check     = array();
		$exit_code = 0;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( 'git -C ' . escapeshellarg( $real ) . ' rev-parse --git-dir 2>&1', $check, $exit_code );

		if ( 0 !== $exit_code ) {
			return new \WP_Error( 'not_git', "Not a git repository: {$real}" );
		}

		return $real;
	}

	/**
	 * Check whether exec() is usable on this server.
	 *
	 * @return bool
	 */
	public static function exec_available(): bool {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		return ! in_array( 'exec', $disabled, true );
	}

	/**
	 * Parse `git log --format` output into structured commit objects.
	 *
	 * Expected format string: '%H|%h|%an|%ae|%at|%s'
	 *
	 * @param string $raw Raw git log output.
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse_log( string $raw ): array {
		$commits = array();
		foreach ( explode( "\n", trim( $raw ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = explode( '|', $line, 6 );
			if ( count( $parts ) < 6 ) {
				continue;
			}
			$commits[] = array(
				'hash'      => $parts[0],
				'short'     => $parts[1],
				'author'    => $parts[2],
				'email'     => $parts[3],
				'timestamp' => (int) $parts[4],
				'date'      => gmdate( 'Y-m-d H:i:s', (int) $parts[4] ),
				'subject'   => $parts[5],
			);
		}
		return $commits;
	}
}
