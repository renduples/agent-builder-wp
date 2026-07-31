<?php
/**
 * Agentic Admin Dashboard — React mount.
 *
 * Full dashboard UI is rendered by build/dashboard-app.js (WordPress components).
 * Data and mutations: REST agentic/v1/dashboard.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @since      0.1.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'agentic_view_dashboard' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agent-builder' ) );
}

\Agentic\Plugin::get_instance()->load_chat_components();

if ( class_exists( '\Agentic\React_Admin' ) && \Agentic\React_Admin::enqueue( 'dashboard-app' ) ) {
	// Prefetch community agent count into transient (used by dashboard bootstrap).
	if ( false === get_transient( 'agentic_dashboard_marketplace_agent_count' ) ) {
		$agentic_api_base        = class_exists( '\Agentic\Service_Registry' )
			? \Agentic\Service_Registry::url( 'agentic-api' )
			: 'https://agentic-plugin.com';
		$agentic_response        = wp_remote_get(
			trailingslashit( untrailingslashit( $agentic_api_base ) ) . 'wp-json/agentic/v1/agents?per_page=1',
			array(
				'timeout'   => 3,
				'sslverify' => true,
			)
		);
		$agentic_community_count = 0;
		if ( ! is_wp_error( $agentic_response ) ) {
			$agentic_body = json_decode( wp_remote_retrieve_body( $agentic_response ), true );
			if ( isset( $agentic_body['total'] ) ) {
				$agentic_community_count = (int) $agentic_body['total'];
			}
		}
		set_transient( 'agentic_dashboard_marketplace_agent_count', $agentic_community_count, HOUR_IN_SECONDS );
	}

	\Agentic\React_Admin::mount( 'agentic-dashboard-app-root' );
	return;
}

// Fallback if React build is missing.
if ( class_exists( '\Agentic\React_Admin' ) ) {
	\Agentic\React_Admin::missing_build_notice( 'dashboard-app' );
} else {
	echo '<div class="wrap"><p>' . esc_html__( 'Dashboard assets unavailable.', 'agent-builder' ) . '</p></div>';
}
