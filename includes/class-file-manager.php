<?php
/**
 * File Manager
 *
 * Centralised file I/O for the plugin. Tries WP_Filesystem_Direct when
 * available (satisfies WP.org coding standards), but detects non-direct
 * transports (FTP/SSH) that silently return false and falls back to
 * native PHP I/O so writes never fail silently on production.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      2.9.103
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helper for all plugin file-system operations.
 *
 * Usage:
 *   File_Manager::put_contents( $path, $content );
 *   File_Manager::copy( $src, $dest );
 *   File_Manager::delete( $path );
 *   File_Manager::rmdir( $path, true );  // recursive
 *   File_Manager::mkdir( $path );
 */
class File_Manager {

	/**
	 * Cached direct filesystem instance, false when unavailable.
	 *
	 * @var \WP_Filesystem_Direct|false|null  null = not yet initialised.
	 */
	private static \WP_Filesystem_Direct|false|null $fs = null;

	/**
	 * Initialise WP_Filesystem and return the Direct transport, or null.
	 *
	 * Returns null (not false) when the transport is not direct so callers
	 * can simply do `if ( self::fs() ) { ... }` without worrying about
	 * truthy-but-broken FTP/SSH objects.
	 */
	private static function fs(): ?\WP_Filesystem_Direct {
		if ( null !== self::$fs ) {
			return self::$fs instanceof \WP_Filesystem_Direct ? self::$fs : null;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		global $wp_filesystem;

		// Only use WP_Filesystem when it resolved to the direct transport.
		// FTP/SSH transports initialise successfully but their put_contents/
		// copy/delete methods silently return false without credentials —
		// making them indistinguishable from success until something is missing.
		self::$fs = ( $wp_filesystem instanceof \WP_Filesystem_Direct )
			? $wp_filesystem
			: false;

		return self::$fs instanceof \WP_Filesystem_Direct ? self::$fs : null;
	}

	/**
	 * Write content to a file, creating it if it does not exist.
	 *
	 * @param string $path    Absolute file path.
	 * @param string $content File content.
	 * @return bool True on success.
	 */
	public static function put_contents( string $path, string $content ): bool {
		if ( ! self::is_allowed_path( $path ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Agentic File_Manager: blocked write outside allowed directory: ' . $path );
			return false;
		}
		$fs = self::fs();
		if ( $fs ) {
			$result = $fs->put_contents( $path, $content, FS_CHMOD_FILE );
			if ( false !== $result ) {
				return true;
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $path, $content );
	}

	/**
	 * Read a file and return its contents, or false on failure.
	 *
	 * @param string $path Absolute file path.
	 * @return string|false
	 */
	public static function get_contents( string $path ): string|false {
		if ( ! file_exists( $path ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return file_get_contents( $path );
	}

	/**
	 * Copy a file.
	 *
	 * @param string $src       Source path.
	 * @param string $dest      Destination path.
	 * @param bool   $overwrite Overwrite destination if it exists.
	 * @return bool
	 */
	public static function copy( string $src, string $dest, bool $overwrite = false ): bool {
		if ( ! file_exists( $src ) ) {
			return false;
		}
		$fs = self::fs();
		if ( $fs ) {
			$result = $fs->copy( $src, $dest, $overwrite );
			if ( false !== $result ) {
				return true;
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		return copy( $src, $dest );
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path Absolute path to file.
	 * @return bool True on success or if the file did not exist.
	 */
	public static function delete( string $path ): bool {
		if ( ! file_exists( $path ) ) {
			return true;
		}
		if ( ! self::is_allowed_path( $path ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Agentic File_Manager: blocked delete outside allowed directory: ' . $path );
			return false;
		}
		$fs = self::fs();
		if ( $fs ) {
			$result = $fs->delete( $path );
			if ( false !== $result ) {
				return true;
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		return unlink( $path );
	}

	/**
	 * Remove a directory, optionally recursively.
	 *
	 * @param string $path      Absolute directory path.
	 * @param bool   $recursive Delete contents first.
	 * @return bool
	 */
	public static function rmdir( string $path, bool $recursive = false ): bool {
		if ( ! is_dir( $path ) ) {
			return true;
		}
		if ( $recursive ) {
			return self::rmdir_recursive( $path );
		}
		$fs = self::fs();
		if ( $fs ) {
			$result = $fs->rmdir( $path );
			if ( false !== $result ) {
				return true;
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		return rmdir( $path );
	}

	/**
	 * Create a directory (and parents) if it does not exist.
	 *
	 * Delegates to wp_mkdir_p which handles both direct and indirect
	 * filesystem methods and is universally safe.
	 *
	 * @param string $path Absolute directory path.
	 * @return bool
	 */
	public static function mkdir( string $path ): bool {
		if ( is_dir( $path ) ) {
			return true;
		}
		return wp_mkdir_p( $path );
	}

	/**
	 * Check whether a path exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function exists( string $path ): bool {
		return file_exists( $path );
	}

	/**
	 * Check whether a path is a directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_dir( string $path ): bool {
		return is_dir( $path );
	}

	/**
	 * Check whether a path is writable.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_writable( string $path ): bool {
		$fs = self::fs();
		if ( $fs ) {
			return $fs->is_writable( $path );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		return is_writable( $path );
	}

	/**
	 * Check whether $path is rooted inside one of the plugin's allowed write roots.
	 *
	 * Write operations are restricted to:
	 *   - The plugin directory itself (AGENT_BUILDER_DIR)
	 *   - The uploads directory (wp_upload_dir basedir)
	 *   - The agentic-agents user directory (AGENTIC_AGENTS_DIR)
	 *
	 * Throws nothing — returns false so callers can surface the failure cleanly.
	 *
	 * @param string $path Absolute path being written to.
	 * @return bool True if the path is within an allowed directory.
	 */
	public static function is_allowed_path( string $path ): bool {
		$allowed_roots = array(
			trailingslashit( AGENT_BUILDER_DIR ),
		);

		if ( defined( 'AGENTIC_AGENTS_DIR' ) ) {
			$allowed_roots[] = trailingslashit( AGENTIC_AGENTS_DIR );
		}

		// Knowledge Wiki (OKF) and persona knowledge files.
		if ( defined( 'AGENTIC_KNOWLEDGE_DIR' ) ) {
			$allowed_roots[] = trailingslashit( AGENTIC_KNOWLEDGE_DIR );
		}

		$upload_dir = wp_upload_dir( null, false );
		if ( ! empty( $upload_dir['basedir'] ) ) {
			$allowed_roots[] = trailingslashit( $upload_dir['basedir'] );
		}

		// Resolve symlinks to get the real path for comparison.
		$real = realpath( dirname( $path ) );
		if ( false === $real ) {
			// Parent directory doesn't exist yet — check the declared path directly.
			$real = dirname( $path );
		}
		$real = trailingslashit( $real ) . basename( $path );

		foreach ( $allowed_roots as $root ) {
			if ( str_starts_with( $real, $root ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively delete a directory and all its contents using native PHP.
	 *
	 * @param string $path Absolute directory path.
	 * @return bool
	 */
	private static function rmdir_recursive( string $path ): bool {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				rmdir( $item->getPathname() );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $item->getPathname() );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		return rmdir( $path );
	}
}
