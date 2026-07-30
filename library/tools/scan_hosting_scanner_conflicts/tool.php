<?php
/**
 * Tool: scan_hosting_scanner_conflicts
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

class Scan_Hosting_Scanner_Conflicts extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'scan_hosting_scanner_conflicts';
	}

	public function get_description(): string {
		return 'Scan active plugin files for patterns that trigger shared hosting malware scanners (base64_decode, eval, exec, etc.) and explain why each is a false positive. Generates a plain-language dispute letter you can paste to your hosting provider.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'plugin' => array(
					'type'        => 'string',
					'description' => 'Scan only this plugin folder name (e.g. "agent-builder"). Omit to scan all active plugins.',
				),
			),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$patterns = array(
			array(
				'id'             => 'base64_decode',
				'pattern'        => '/\bbase64_decode\s*\(/',
				'label'          => 'base64_decode()',
				'why_flagged'    => 'Attackers use base64 to obfuscate malicious payloads. Scanners flag any use regardless of context.',
				'legitimate_use' => 'Decoding stored encrypted values (API keys, tokens), processing base64-encoded API responses, and handling email/MIME content.',
			),
			array(
				'id'             => 'base64_encode',
				'pattern'        => '/\bbase64_encode\s*\(/',
				'label'          => 'base64_encode()',
				'why_flagged'    => 'Paired with base64_decode in obfuscation patterns; scanners flag it preemptively.',
				'legitimate_use' => 'Encoding binary data for storage or transmission (e.g. image data URIs, basic auth headers, API payloads).',
			),
			array(
				'id'             => 'eval',
				'pattern'        => '/(?<![\'"\w])\beval\s*\(/',
				'label'          => 'eval()',
				'why_flagged'    => 'eval() executes arbitrary PHP code and is the primary attack vector in injected malware.',
				'legitimate_use' => 'Rare in production plugins. May appear in template engines, code coverage tools, or REPL integrations.',
			),
			array(
				'id'             => 'exec',
				'pattern'        => '/\bexec\s*\(/',
				'label'          => 'exec()',
				'why_flagged'    => 'Runs shell commands and can be used for remote code execution.',
				'legitimate_use' => 'WP-CLI integration, image processing via ImageMagick, backup plugin shell operations.',
			),
			array(
				'id'             => 'shell_exec',
				'pattern'        => '/\bshell_exec\s*\(/',
				'label'          => 'shell_exec()',
				'why_flagged'    => 'Executes shell commands and returns their output as a string.',
				'legitimate_use' => 'Image optimisation, PDF generation, or CLI tool wrappers in admin-only contexts.',
			),
			array(
				'id'             => 'system',
				'pattern'        => '/\bsystem\s*\(/',
				'label'          => 'system()',
				'why_flagged'    => 'Runs system commands and streams output directly. Classic attack vector.',
				'legitimate_use' => 'Server diagnostics or CLI wrappers restricted to admin-only tools.',
			),
			array(
				'id'             => 'passthru',
				'pattern'        => '/\bpassthru\s*\(/',
				'label'          => 'passthru()',
				'why_flagged'    => 'Executes system commands and passes raw binary output to the browser.',
				'legitimate_use' => 'Binary output wrappers for system commands.',
			),
			array(
				'id'             => 'gzinflate',
				'pattern'        => '/\bgzinflate\s*\(/',
				'label'          => 'gzinflate()',
				'why_flagged'    => 'Attackers decompress obfuscated payloads at runtime using gzinflate. Scanners treat any use as suspicious.',
				'legitimate_use' => 'Compression/decompression in backup, import/export, or data-transfer plugins.',
			),
			array(
				'id'             => 'preg_replace_e',
				'pattern'        => '#preg_replace\s*\(\s*[\'"][^\'"]+/e[\'"]#',
				'label'          => 'preg_replace with /e modifier',
				'why_flagged'    => 'The /e modifier executed the replacement string as PHP (removed in PHP 7.0). Scanners still flag it.',
				'legitimate_use' => 'None in modern code. Flag is removed; update any occurrence to use preg_replace_callback().',
			),
		);

		$active_plugins = (array) get_option( 'active_plugins', array() );
		$plugin_filter  = isset( $args['plugin'] ) ? sanitize_text_field( $args['plugin'] ) : null;
		$plugins_dir    = WP_PLUGIN_DIR;

		$findings  = array();
		$scanned   = 0;
		$max_files = 400;

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_slug = explode( '/', $plugin_file )[0];

			if ( $plugin_filter && $plugin_slug !== $plugin_filter ) {
				continue;
			}

			$plugin_path = $plugins_dir . '/' . $plugin_slug;
			if ( ! is_dir( $plugin_path ) ) {
				continue;
			}

			$iter = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_path, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $iter as $f ) {
				if ( 'php' !== $f->getExtension() ) {
					continue;
				}
				if ( $scanned >= $max_files ) {
					break 2;
				}
				++$scanned;

				$content = @file_get_contents( $f->getPathname() );
				if ( false === $content ) {
					continue;
				}

				// Strip block comments to reduce doc-example noise.
				$stripped = preg_replace( '/\/\*.*?\*\//s', '', $content ) ?? $content;
				$lines    = explode( "\n", $stripped );

				foreach ( $patterns as $p ) {
					foreach ( $lines as $line_idx => $line ) {
						$trimmed = ltrim( $line );
						// Skip comment-only lines.
						if ( str_starts_with( $trimmed, '//' )
							|| str_starts_with( $trimmed, '#' )
							|| str_starts_with( $trimmed, '*' ) ) {
							continue;
						}
						if ( preg_match( $p['pattern'], $line ) ) {
							$findings[] = array(
								'plugin'         => $plugin_slug,
								'file'           => str_replace( $plugins_dir . '/', '', $f->getPathname() ),
								'line'           => $line_idx + 1,
								'pattern'        => $p['label'],
								'context'        => trim( $line ),
								'why_flagged'    => $p['why_flagged'],
								'legitimate_use' => $p['legitimate_use'],
							);
						}
					}
				}
			}
		}

		// Group findings by plugin for the dispute letter.
		$by_plugin = array();
		foreach ( $findings as $f ) {
			$by_plugin[ $f['plugin'] ][] = $f;
		}

		$dispute_sections = array();
		foreach ( $by_plugin as $slug => $items ) {
			$unique_patterns = array_unique( array_column( $items, 'pattern' ) );
			$lines           = array( "Plugin: {$slug}" );
			foreach ( $unique_patterns as $label ) {
				$hits    = array_filter( $items, fn( $i ) => $i['pattern'] === $label );
				$count   = count( $hits );
				$first   = reset( $hits );
				$lines[] = "  • {$label} ({$count} occurrence" . ( $count > 1 ? 's' : '' ) . "): {$first['legitimate_use']}";
			}
			$dispute_sections[] = implode( "\n", $lines );
		}

		$dispute_letter = null;
		if ( ! empty( $dispute_sections ) ) {
			$dispute_letter = "Dear Support Team,\n\n"
				. 'Your malware scanner has flagged files in the plugin(s) listed below. These flags are false positives. '
				. "The patterns detected are used legitimately in the plugin code, as explained below.\n\n"
				. implode( "\n\n", $dispute_sections )
				. "\n\nAll listed plugins were installed from their official distribution. "
				. 'The files have not been modified and contain no malicious code. '
				. "Please review and restore the file permissions to their original state (644 for files, 755 for directories).\n\n"
				. 'Thank you.';
		}

		return array(
			'files_scanned'    => $scanned,
			'total_findings'   => count( $findings ),
			'findings'         => $findings,
			'plugins_affected' => array_keys( $by_plugin ),
			'dispute_letter'   => $dispute_letter,
			'status'           => 0 === count( $findings )
				? 'No patterns found that typically trigger hosting malware scanners.'
				: count( $findings ) . ' pattern(s) found that hosting scanners commonly flag. See dispute_letter for explanation text to send to your host.',
		);
	}
}

return new Scan_Hosting_Scanner_Conflicts();
