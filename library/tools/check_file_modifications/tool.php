<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check File Modifications Tool
 *
 * Scans for recently modified PHP files in key WordPress directories.
 * Unexpected modifications could indicate a compromise.
 *
 * @package Agentic\Tools
 */
class Check_File_Modifications extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'check_file_modifications';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Scan for recently modified PHP files in key WordPress directories. Unexpected modifications could indicate a compromise.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'security';
	}

	/**
	 * Get the tool parameters.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'hours'     => array(
					'type'        => 'integer',
					'description' => 'Look back this many hours. Defaults to 24.',
				),
				'directory' => array(
					'type'        => 'string',
					'description' => 'Subdirectory relative to ABSPATH. Allowed: "wp-content" (default), "wp-includes", "wp-admin".',
				),
				'limit'     => array(
					'type'        => 'integer',
					'description' => 'Max files to return (1–50). Defaults to 20.',
				),
			),
		);
	}

	/**
	 * Execute the file modifications scan.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		$hours   = max( 1, (int) ( $arguments['hours'] ?? 24 ) );
		$dir_arg = sanitize_text_field( $arguments['directory'] ?? 'wp-content' );
		$limit   = min( max( (int) ( $arguments['limit'] ?? 20 ), 1 ), 50 );
		$since   = time() - ( $hours * HOUR_IN_SECONDS );

		$allowed = array( 'wp-content', 'wp-includes', 'wp-admin' );
		if ( ! in_array( $dir_arg, $allowed, true ) ) {
			$dir_arg = 'wp-content';
		}

		$scan_path = trailingslashit( ABSPATH ) . $dir_arg;
		if ( ! is_dir( $scan_path ) ) {
			return array( 'error' => "Directory not found: {$dir_arg}" );
		}

		$modified = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $scan_path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			if ( $file->getMTime() >= $since ) {
				$modified[] = array(
					'path'     => str_replace( ABSPATH, '', $file->getPathname() ),
					'modified' => gmdate( 'Y-m-d H:i:s', $file->getMTime() ),
					'size_kb'  => round( $file->getSize() / 1024, 1 ),
				);
			}
			if ( count( $modified ) >= $limit ) {
				break;
			}
		}

		usort( $modified, fn( $a, $b ) => strtotime( $b['modified'] ) - strtotime( $a['modified'] ) );

		return array(
			'directory'          => $dir_arg,
			'period'             => "last {$hours} hours",
			'php_files_modified' => count( $modified ),
			'files'              => $modified,
			'assessment'         => count( $modified ) === 0
				? 'No PHP files modified in this period.'
				: 'Review any unexpected modifications, especially in wp-content/uploads or core directories.',
		);
	}

	/**
	 * Get the tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly' => true,
		);
	}
}

return new Check_File_Modifications();
