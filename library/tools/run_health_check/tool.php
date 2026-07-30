<?php
/**
 * Tool: run_health_check
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;

class Run_Health_Check extends Tool_Base {
	public function get_name(): string {
		return 'run_health_check';
	}

	public function get_description(): string {
		return 'Run a comprehensive health check across database, content, and plugins. Returns an overall score and grade.';
	}

	public function get_category(): string {
		return 'site-health';
	}

	public function get_parameters(): array {
		return array();
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$loader   = \Agentic\Tool_Loader::get_instance();
		$db       = $loader->execute( 'get_database_health', array() );
		$orphaned = $loader->execute( 'get_orphaned_content', array() );
		$plugins  = $loader->execute( 'check_plugin_status', array() );

		$score  = 100;
		$issues = array();

		// DB health scoring.
		if ( isset( $db['table_overhead_mb'] ) && $db['table_overhead_mb'] > 50 ) {
			$score   -= 10;
			$issues[] = 'Database overhead exceeds 50 MB.';
		}
		if ( isset( $db['autoload_size_mb'] ) && $db['autoload_size_mb'] > 1 ) {
			$score   -= 10;
			$issues[] = 'Autoloaded options exceed 1 MB (' . $db['autoload_size_mb'] . ' MB).';
		}
		if ( isset( $db['expired_transients'] ) && $db['expired_transients'] > 100 ) {
			$score   -= 5;
			$issues[] = $db['expired_transients'] . ' expired transients found.';
		}

		// Orphaned content scoring.
		if ( isset( $orphaned['counts'] ) ) {
			$c = $orphaned['counts'];
			if ( ( $c['auto_drafts'] ?? 0 ) > 50 ) {
				$score   -= 5;
				$issues[] = ( $c['auto_drafts'] ) . ' auto-drafts accumulating.';
			}
			if ( ( $c['revisions'] ?? 0 ) > 500 ) {
				$score   -= 5;
				$issues[] = ( $c['revisions'] ) . ' post revisions stored.';
			}
			if ( ( $c['trash'] ?? 0 ) > 20 ) {
				$score   -= 3;
				$issues[] = ( $c['trash'] ) . ' items in trash.';
			}
			if ( ( $c['spam_comments'] ?? 0 ) > 50 ) {
				$score   -= 5;
				$issues[] = ( $c['spam_comments'] ) . ' spam comments pending cleanup.';
			}
		}

		// Plugin scoring.
		if ( isset( $plugins['counts'] ) ) {
			$pc = $plugins['counts'];
			if ( ( $pc['outdated'] ?? 0 ) > 0 ) {
				$score   -= (int) $pc['outdated'] * 3;
				$issues[] = ( $pc['outdated'] ) . ' plugin(s) have pending updates.';
			}
			if ( ( $pc['inactive'] ?? 0 ) > 3 ) {
				$score   -= 5;
				$issues[] = ( $pc['inactive'] ) . ' inactive plugins — consider removing them.';
			}
		}

		$score = max( 0, $score );

		return array(
			'score'   => $score,
			'grade'   => \Agentic\Tool_Helpers::score_to_grade( $score ),
			'issues'  => $issues,
			'summary' => array(
				'database'         => $db,
				'orphaned_content' => $orphaned,
				'plugins'          => $plugins,
			),
		);
	}
}

return new Run_Health_Check();
