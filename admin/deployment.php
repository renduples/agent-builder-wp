<?php
/**
 * Agentic Agent Deployment Page
 *
 * Unified management of all agent invocation methods: Scheduled Tasks,
 * Event Listeners, and Shortcodes. Each method is presented as a tab.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.7.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'agentic_manage_agents' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agent-builder' ) );
}

// Determine active tab (tabs ordered alphabetically by label below).
$agentic_active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'admin-bar' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch, not a form submission.
if ( ! in_array( $agentic_active_tab, array( 'admin-bar', 'cli', 'admin-ui', 'event-listeners', 'gutenberg-blocks', 'website', 'scheduled-tasks', 'shortcodes' ), true ) ) {
	$agentic_active_tab = 'admin-bar';
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Agent Deployment', 'agent-builder' ); ?></h1>
	<p><?php esc_html_e( 'Manage how assistants are invoked: embed them on your site with shortcodes, schedule recurring tasks, or react to WordPress events.', 'agent-builder' ); ?></p>

	<?php
	// Labels A–Z for nav order.
	$agentic_deploy_tabs  = array(
		'admin-bar'        => __( 'Admin', 'agent-builder' ),
		'cli'              => __( 'CLI', 'agent-builder' ),
		'admin-ui'         => __( 'Editor', 'agent-builder' ),
		'event-listeners'  => __( 'Event Listeners', 'agent-builder' ),
		'website'          => __( 'Frontend Modal', 'agent-builder' ),
		'gutenberg-blocks' => __( 'Gutenberg Blocks', 'agent-builder' ),
		'scheduled-tasks'  => __( 'Scheduled Tasks', 'agent-builder' ),
		'shortcodes'       => __( 'Shortcodes', 'agent-builder' ),
	);
	$agentic_deploy_items = array();
	foreach ( $agentic_deploy_tabs as $agentic_tab_slug => $agentic_tab_label ) {
		$agentic_deploy_items[] = array(
			'slug'  => $agentic_tab_slug,
			'label' => $agentic_tab_label,
			'url'   => admin_url( 'admin.php?page=agentic-deployment&tab=' . $agentic_tab_slug ),
		);
	}
	$agentic_panel_title = $agentic_deploy_tabs[ $agentic_active_tab ] ?? __( 'Deployment', 'agent-builder' );

	\Agentic\Admin_Vnav::open(
		array(
			'active'     => $agentic_active_tab,
			'items'      => $agentic_deploy_items,
			'aria_label' => __( 'Deployment methods', 'agent-builder' ),
			'id'         => 'agentic-deployment-nav',
		)
	);
	?>

	<div class="agentic-settings-panel agentic-deployment-panel">
		<div class="agentic-settings-panel__header">
			<h2 class="agentic-settings-panel__title"><?php echo esc_html( $agentic_panel_title ); ?></h2>
		</div>
		<div class="agentic-settings-panel__body">
	<?php
	switch ( $agentic_active_tab ) {
		case 'scheduled-tasks':
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-scheduled-tasks.php';
			break;

		case 'event-listeners':
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-event-listeners.php';
			break;

		case 'gutenberg-blocks':
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-gutenberg-blocks.php';
			break;

		case 'cli':
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-cli.php';
			break;

		case 'admin-ui':
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-admin-ui.php';
			break;

		case 'website':
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-modal.php';
			break;

		case 'admin-bar':
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-admin-bar.php';
			break;

		case 'shortcodes':
		default:
			include AGENT_BUILDER_DIR . 'admin/deployment/deployment-shortcodes.php';
			break;
	}
	?>
		</div><!-- .agentic-settings-panel__body -->
	</div><!-- .agentic-settings-panel -->

	<?php
	\Agentic\Admin_Vnav::close();
	?>
</div>
