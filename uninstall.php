<?php
/**
 * Uninstall handler for Agent Builder.
 *
 * Cleans up all plugin data (options, tables, cron, user meta, transients,
 * and the chat page) when the plugin is deleted via WordPress admin.
 *
 * @package Agent_Builder
 * @since   1.0.0
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/*
 * -----------------------------------------------------------------–––––––––––––--------
 * Notify Agentic AI that this site is being removed — only when the admin has
 * explicitly opted in (agentic_allow_deregister_on_uninstall) AND an API key is
 * configured on a Pro install. The endpoint is disclosed under
 * "== External Services ==" in readme.txt.
 * ----------------------------------------------------------------------–––––––––––––---
 */
$agentic_api_svc     = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"SELECT endpoint FROM {$wpdb->prefix}agentic_providers WHERE slug = %s LIMIT 1",
		'agentic-api'
	)
);
$agentic_api_base    = $agentic_api_svc ? (string) $agentic_api_svc->endpoint : 'https://agentic-plugin.com';
$agentic_api_key_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"SELECT api_key FROM {$wpdb->prefix}agentic_providers WHERE slug = %s LIMIT 1",
		'agentic'
	)
);
$agentic_api_key     = $agentic_api_key_row ? $agentic_api_key_row->api_key : get_option( 'agentic_ai_api_key_builtin', '' );

if ( ! empty( $agentic_api_key )
	&& class_exists( '\Agentic\Pro' )
	&& '1' === get_option( 'agentic_allow_deregister_on_uninstall', '0' )
) {
	wp_remote_post(
		$agentic_api_base . '/wp-json/agentic/v1/deregister',
		array(
			'timeout'  => 5,
			'blocking' => false,
			'body'     => array(
				'api_key'  => $agentic_api_key,
				'site_url' => home_url(),
			),
		)
	);
}

/*
 * -------------------------------------------------------------------------
 * Respect user's data-retention preference set in the deactivation modal.
 *
 * If the admin chose "Keep my data" (default), we stop here — tables and
 * options are preserved so they survive a reinstall.
 * -------------------------------------------------------------------------
 */
$agentic_delete_data = get_option( 'agentic_deactivate_delete_data', '0' );
if ( '1' !== $agentic_delete_data ) {
	return; // User chose to keep data — nothing more to do.
}

/*
 * -------------------------------------------------------------------------
 * 1. Drop custom database tables.
 *
 * Query information_schema for every agentic_* table so future tables are
 * caught automatically without maintaining a manual list.
 * -------------------------------------------------------------------------
 */
$agentic_tables = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE %s',
		$wpdb->esc_like( $wpdb->prefix . 'agentic_' ) . '%'
	)
);

/*
 * Preserve owner-owned definition tables even when the admin opted into
 * "delete all data". These hold agents the owner purchased or created, and
 * custom skills — destroying them would lose paid and user work that a
 * reinstall is meant to find already present (§8.7). Bundled rows they may
 * also contain are simply re-seeded on the next activation, so keeping the
 * tables is safe. Everything else agentic_* is dropped as before.
 */
$agentic_preserve_tables = array(
	$wpdb->prefix . 'agentic_agent_library',
	$wpdb->prefix . 'agentic_skills',
);
$agentic_tables          = array_diff( (array) $agentic_tables, $agentic_preserve_tables );

if ( $agentic_tables ) {
	$agentic_table_list = implode( ', ', array_map( 'esc_sql', $agentic_tables ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names sourced from information_schema; esc_sql applied.
	$wpdb->query( "DROP TABLE IF EXISTS {$agentic_table_list}" );
}

/*
 * -------------------------------------------------------------------------
 * 2. Delete plugin options.
 *
 * Use a single wildcard DELETE to catch every agentic_* option — including
 * any added in future versions — without maintaining a manual list.
 * -------------------------------------------------------------------------
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Targeted plugin option cleanup.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'agentic\_%'
	)
);

/*
 * -------------------------------------------------------------------------
 * 3. Delete transients.
 * -------------------------------------------------------------------------
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Targeted transient cleanup.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_agentic_%',
		'_transient_timeout_agentic_%'
	)
);

/*
 * -------------------------------------------------------------------------
 * 4. Clean up user meta.
 * -------------------------------------------------------------------------
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of this plugin's usermeta only.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'agentic_' ) . '%'
	)
);

/*
 * -------------------------------------------------------------------------
 * 5. Clear scheduled cron events.
 * -------------------------------------------------------------------------
 */
$agentic_cron_events = _get_cron_array();
if ( is_array( $agentic_cron_events ) ) {
	foreach ( $agentic_cron_events as $agentic_cron_timestamp => $agentic_cron_hooks ) {
		foreach ( array_keys( $agentic_cron_hooks ) as $agentic_hook ) {
			if ( str_starts_with( $agentic_hook, 'agentic_' ) ) {
				wp_clear_scheduled_hook( $agentic_hook );
			}
		}
	}
}
