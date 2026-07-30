<?php
/**
 * Tool: get_cron_jobs
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

class Get_Cron_Jobs extends Tool_Base {
	public function get_name(): string {
		return 'get_cron_jobs';
	}

	public function get_description(): string {
		return 'List all scheduled WordPress cron jobs with their next run time, schedule interval, and arguments. Sorted by next run time (soonest first).';
	}

	public function get_category(): string {
		return 'maintenance';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
			'required'   => array(),
		);
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function execute( array $args ): array {
		$cron_array = _get_cron_array();
		$schedules  = wp_get_schedules();

		if ( empty( $cron_array ) ) {
			return array(
				'jobs'  => array(),
				'total' => 0,
			);
		}

		$jobs = array();

		foreach ( $cron_array as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( $hook_events as $event ) {
					$schedule_name     = $event['schedule'] ?? 'single';
					$schedule_interval = 0;
					$schedule_label    = 'One-time';

					if ( $schedule_name && isset( $schedules[ $schedule_name ] ) ) {
						$schedule_interval = (int) ( $schedules[ $schedule_name ]['interval'] ?? 0 );
						$schedule_label    = $schedules[ $schedule_name ]['display'] ?? $schedule_name;
					}

					$jobs[] = array(
						'hook'                      => $hook,
						'next_run_timestamp'        => (int) $timestamp,
						'next_run_human'            => human_time_diff( (int) $timestamp ) . ( $timestamp > time() ? ' from now' : ' ago (overdue)' ),
						'schedule_name'             => $schedule_name,
						'schedule_interval_seconds' => $schedule_interval,
						'schedule_label'            => $schedule_label,
						'args'                      => $event['args'] ?? array(),
					);
				}
			}
		}

		// Sort by next_run_timestamp ascending.
		usort( $jobs, fn( $a, $b ) => $a['next_run_timestamp'] <=> $b['next_run_timestamp'] );

		$overdue = array_filter( $jobs, fn( $j ) => $j['next_run_timestamp'] < time() );

		return array(
			'jobs'    => $jobs,
			'total'   => count( $jobs ),
			'overdue' => count( $overdue ),
		);
	}
}

return new Get_Cron_Jobs();
