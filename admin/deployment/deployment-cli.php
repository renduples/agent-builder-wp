<?php
/**
 * Deployment — CLI Tab
 *
 * Two-section admin page:
 *   1. WP-CLI Command Whitelist  — enable/disable individual commands grouped
 *      by Read / Write / Admin risk. Saved into the run_wp_cli tool's
 *      parameters column in wp_agentic_tools.
 *   2. Agent CLI Privileges       — per-agent matrix with two toggles each:
 *        • Allow CLI invocation   (wp agent prompt / run-task from shell)
 *        • Allow run_wp_cli tool  (agent can call the tool in conversation)
 *      Stored in wp_agentic_agent_settings via Agent_Settings.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @since      2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tools_Registry;
use Agentic\Agent_Settings;
use Agentic\Tools\Run_Wp_Cli;

// -------------------------------------------------------------------------
// Ensure the tool class is loaded so we can reach the static default list.
if ( ! class_exists( 'Agentic\Tools\Run_Wp_Cli' ) ) {
	$agentic_tool_path = AGENT_BUILDER_DIR . 'admin/tools/run_wp_cli/tool.php';
	if ( file_exists( $agentic_tool_path ) ) {
		include_once $agentic_tool_path;
	}
}

// Seed the tool if it hasn't been registered yet (e.g. first visit after install).
if ( ! Tools_Registry::get( 'run_wp_cli' ) ) {
	$agentic_seed_loader = \Agentic\Tool_Loader::get_instance();
	$agentic_seed_loader->sync_to_registry();
	\Agentic\Tools_Registry::seed_core_tools( array( 'run_wp_cli' => 'cli' ) );
}

// Load the current allowed_commands list — Deployments table first, tools table fallback.
$agentic_tool_row = Tools_Registry::get( 'run_wp_cli' );

$agentic_allowed = array();

if ( class_exists( '\Agentic\Deployments' ) ) {
	$agentic_cli_rows = \Agentic\Deployments::all( \Agentic\Deployments::TYPE_CLI );
	foreach ( $agentic_cli_rows as $agentic_cli_dr ) {
		$agentic_allowed[] = array(
			'pattern' => $agentic_cli_dr['config']['pattern'] ?? '',
			'label'   => $agentic_cli_dr['label'],
			'group'   => $agentic_cli_dr['config']['group'] ?? 'write',
			'enabled' => $agentic_cli_dr['enabled'],
		);
	}
}

if ( empty( $agentic_allowed ) ) {
	// Fall back to tools table if Deployments not yet populated.
	$agentic_params = array();
	if ( $agentic_tool_row ) {
		$agentic_params = $agentic_tool_row['parameters'] ?? array();
		if ( is_string( $agentic_params ) ) {
			$agentic_params = json_decode( $agentic_params, true ) ?? array();
		}
	}

	$agentic_allowed = isset( $agentic_params['_allowed_commands'] ) && is_array( $agentic_params['_allowed_commands'] )
		? $agentic_params['_allowed_commands']
		: ( class_exists( 'Agentic\Tools\Run_Wp_Cli' ) ? Run_Wp_Cli::default_allowed_commands() : array() );
}

// Group commands by group key.
$agentic_groups = array(
	'read'  => array(
		'label'    => __( 'Read', 'agent-builder' ),
		'color'    => '#2271b1',
		'commands' => array(),
	),
	'write' => array(
		'label'    => __( 'Write', 'agent-builder' ),
		'color'    => '#996800',
		'commands' => array(),
	),
	'admin' => array(
		'label'    => __( 'Admin', 'agent-builder' ),
		'color'    => '#d63638',
		'commands' => array(),
	),
);
foreach ( $agentic_allowed as $agentic_idx => $agentic_cmd ) {
	$agentic_grp = $agentic_cmd['group'] ?? 'write';
	if ( ! isset( $agentic_groups[ $agentic_grp ] ) ) {
		$agentic_grp = 'write';
	}
	$agentic_groups[ $agentic_grp ]['commands'][] = array_merge( $agentic_cmd, array( '_idx' => $agentic_idx ) );
}

// -------------------------------------------------------------------------
// Load agent list + their current CLI settings.
// -------------------------------------------------------------------------
$agentic_registry  = \Agentic_Agent_Registry::get_instance();
$agentic_instances = $agentic_registry->get_all_instances();

$agentic_agent_cli = array(); // slug => [ 'cli_enabled' => bool, 'cli_invocation' => bool ].
foreach ( $agentic_instances as $agentic_agent ) {
	$agentic_slug                       = $agentic_agent->get_id();
	$agentic_agent_cli[ $agentic_slug ] = array(
		'name'           => $agentic_agent->get_name(),
		'cli_enabled'    => 'yes' === Agent_Settings::get( $agentic_slug, 'cli_enabled', 'no' ),
		'cli_invocation' => 'yes' === Agent_Settings::get( $agentic_slug, 'cli_invocation', 'no' ),
	);
}

// -------------------------------------------------------------------------
// Check WP-CLI availability.
// -------------------------------------------------------------------------
$agentic_wpcli_available = defined( 'WP_CLI' ) && WP_CLI;

// -------------------------------------------------------------------------
// Nonces for AJAX calls.
// -------------------------------------------------------------------------
$agentic_nonce_whitelist  = wp_create_nonce( 'agentic_cli_whitelist' );
$agentic_nonce_privileges = wp_create_nonce( 'agentic_cli_privileges' );
$agentic_ajax_url         = admin_url( 'admin-ajax.php' );
?>

<?php if ( ! $agentic_wpcli_available ) : ?>
<div class="notice notice-info inline agentic-notice-mt">
	<p>
		<?php esc_html_e( 'WP-CLI is not active in this browser request — the run_wp_cli tool runs via WP-CLI only. Settings configured here will apply when WP-CLI is loaded.', 'agent-builder' ); ?>
	</p>
</div>
<?php endif; ?>

<?php if ( ! $agentic_tool_row ) : ?>
<div class="notice notice-info inline agentic-mb-12">
	<p><?php esc_html_e( 'The run_wp_cli tool has not yet been seeded. Visit the Tools page once to initialise it.', 'agent-builder' ); ?></p>
</div>
<?php endif; ?>

<!-- =====================================================================
	Section 1: WP-CLI Command Whitelist
	===================================================================== -->
<div class="agentic-cli-section">
	<h2 class="agentic-section-h2">
		<?php esc_html_e( 'WP-CLI Command Whitelist', 'agent-builder' ); ?>
	</h2>
	<p class="description">
		<?php esc_html_e( 'Only commands switched ON here can be run by agents via the run_wp_cli tool. Commands are grouped by risk. You can also add custom patterns.', 'agent-builder' ); ?>
	</p>

	<?php foreach ( $agentic_groups as $agentic_grp_key => $agentic_grp ) : ?>
	<details open class="agentic-cli-details">
		<summary class="agentic-cli-summary">
			<span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo esc_attr( $agentic_grp['color'] ); ?>;"></span>
			<?php echo esc_html( $agentic_grp['label'] ); ?>
			<span class="agentic-cli-count-<?php echo esc_attr( $agentic_grp_key ); ?> agentic-cli-count">
				(<?php echo count( $agentic_grp['commands'] ); ?>)
			</span>
		</summary>

		<table class="widefat striped agentic-cli-table">
			<thead>
				<tr>
					<th class="agentic-col-44"><?php esc_html_e( 'On', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Command', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Label', 'agent-builder' ); ?></th>
					<th class="agentic-col-80"></th>
				</tr>
			</thead>
			<tbody id="agentic-cli-group-<?php echo esc_attr( $agentic_grp_key ); ?>">
			<?php foreach ( $agentic_grp['commands'] as $agentic_cmd ) : ?>
				<tr data-idx="<?php echo esc_attr( $agentic_cmd['_idx'] ); ?>" data-group="<?php echo esc_attr( $agentic_grp_key ); ?>">
					<td>
						<label class="agentic-cli-toggle">
							<input type="checkbox"
								class="agentic-cli-cmd-toggle"
								data-idx="<?php echo esc_attr( $agentic_cmd['_idx'] ); ?>"
								<?php checked( ! empty( $agentic_cmd['enabled'] ) ); ?>
							>
							<span class="agentic-cli-slider"></span>
						</label>
					</td>
					<td>
						<code class="agentic-cli-pattern" contenteditable="true" data-idx="<?php echo esc_attr( $agentic_cmd['_idx'] ); ?>">
							<?php echo esc_html( $agentic_cmd['pattern'] ); ?>
						</code>
					</td>
					<td>
						<span class="agentic-cli-label" contenteditable="true" data-idx="<?php echo esc_attr( $agentic_cmd['_idx'] ); ?>">
							<?php echo esc_html( $agentic_cmd['label'] ?? '' ); ?>
						</span>
					</td>
					<td>
						<button type="button" class="button button-small agentic-cli-remove-cmd" data-idx="<?php echo esc_attr( $agentic_cmd['_idx'] ); ?>">
							<?php esc_html_e( 'Remove', 'agent-builder' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="agentic-cli-footer">
			<input type="text" class="agentic-cli-new-pattern regular-text" placeholder="<?php esc_attr_e( 'wp cache flush', 'agent-builder' ); ?>" class="agentic-input-mono">
			<input type="text" class="agentic-cli-new-label regular-text" placeholder="<?php esc_attr_e( 'Human-readable label', 'agent-builder' ); ?>">
			<input type="hidden" class="agentic-cli-new-group" value="<?php echo esc_attr( $agentic_grp_key ); ?>">
			<button type="button" class="button agentic-cli-add-cmd" data-group="<?php echo esc_attr( $agentic_grp_key ); ?>">
				+ <?php esc_html_e( 'Add', 'agent-builder' ); ?>
			</button>
		</div>
	</details>
	<?php endforeach; ?>

	<div class="agentic-cli-add-row">
		<button type="button" id="agentic-cli-save-whitelist" class="button button-primary">
			<?php esc_html_e( 'Save Whitelist', 'agent-builder' ); ?>
		</button>
		<span id="agentic-cli-whitelist-status" class="agentic-cli-status-msg"></span>
	</div>
</div>

<!-- =====================================================================
	Section 2: Agent CLI Privileges
	===================================================================== -->
<div class="agentic-cli-section">
	<h2 class="agentic-section-h2">
		<?php esc_html_e( 'Agent CLI Privileges', 'agent-builder' ); ?>
	</h2>
	<p class="description">
		<?php esc_html_e( 'Control which agents can be invoked via WP-CLI and which can use the run_wp_cli tool during conversations. Grants are per-agent. Both columns default to off.', 'agent-builder' ); ?>
	</p>

	<?php if ( empty( $agentic_agent_cli ) ) : ?>
		<p><?php esc_html_e( 'No agents installed.', 'agent-builder' ); ?></p>
	<?php else : ?>
	<table class="widefat striped agentic-table-780">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Assistant', 'agent-builder' ); ?></th>
				<th class="agentic-col-180 agentic-td-center">
					<?php esc_html_e( 'CLI invocation', 'agent-builder' ); ?>
					<br><small class="agentic-small-muted"><?php esc_html_e( 'wp agent prompt/run-task', 'agent-builder' ); ?></small>
				</th>
				<th class="agentic-col-180 agentic-td-center">
					<?php esc_html_e( 'run_wp_cli tool', 'agent-builder' ); ?>
					<br><small class="agentic-small-muted"><?php esc_html_e( 'use in chat / tasks', 'agent-builder' ); ?></small>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $agentic_agent_cli as $agentic_slug => $agentic_info ) : ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $agentic_info['name'] ); ?></strong>
					<br><code class="agentic-code-muted"><?php echo esc_html( $agentic_slug ); ?></code>
				</td>
				<td class="agentic-td-center">
					<label class="agentic-cli-toggle">
						<input type="checkbox"
							class="agentic-cli-priv-toggle"
							data-slug="<?php echo esc_attr( $agentic_slug ); ?>"
							data-key="cli_invocation"
							<?php checked( $agentic_info['cli_invocation'] ); ?>
						>
						<span class="agentic-cli-slider"></span>
					</label>
				</td>
				<td class="agentic-td-center">
					<label class="agentic-cli-toggle">
						<input type="checkbox"
							class="agentic-cli-priv-toggle"
							data-slug="<?php echo esc_attr( $agentic_slug ); ?>"
							data-key="cli_enabled"
							<?php checked( $agentic_info['cli_enabled'] ); ?>
						>
						<span class="agentic-cli-slider"></span>
					</label>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="agentic-cli-status-note">
		<?php esc_html_e( 'Privilege changes save automatically on toggle.', 'agent-builder' ); ?>
	</p>
	<?php endif; ?>
</div>

<!-- =====================================================================
	Inline CSS
	===================================================================== -->
<!-- =====================================================================
	Inline JS
	===================================================================== -->
<script>
(function($) {
	'use strict';

	const ajaxUrl    = <?php echo wp_json_encode( $agentic_ajax_url ); ?>;
	const nonceWL    = <?php echo wp_json_encode( $agentic_nonce_whitelist ); ?>;
	const noncePriv  = <?php echo wp_json_encode( $agentic_nonce_privileges ); ?>;

	// -----------------------------------------------------------------------
	// Section 1: Build the current in-memory commands list from the DOM,
	// then POST it to agentic_cli_save_whitelist.
	// -----------------------------------------------------------------------

	function buildCommandsList() {
		const commands = [];
		$('#agentic-cli-group-read, #agentic-cli-group-write, #agentic-cli-group-admin').each(function() {
			const group = this.id.replace('agentic-cli-group-', '');
			$(this).find('tr').each(function() {
				const $tr = $(this);
				commands.push({
					pattern : $tr.find('.agentic-cli-pattern').text().trim(),
					label   : $tr.find('.agentic-cli-label').text().trim(),
					group   : $tr.data('group') || group,
					enabled : $tr.find('.agentic-cli-cmd-toggle').is(':checked'),
				});
			});
		});
		return commands;
	}

	$('#agentic-cli-save-whitelist').on('click', function() {
		const $btn    = $(this);
		const $status = $('#agentic-cli-whitelist-status');

		$btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Saving…', 'agent-builder' ) ); ?>);
		$status.text('');

		$.post(ajaxUrl, {
			action   : 'agentic_cli_save_whitelist',
			_ajax_nonce: nonceWL,
			commands : JSON.stringify( buildCommandsList() ),
		})
		.done(function(resp) {
			if (resp.success) {
				$status.css('color', '#2271b1').text(<?php echo wp_json_encode( __( '✓ Saved', 'agent-builder' ) ); ?>);
			} else {
				$status.css('color', '#d63638').text(resp.data || <?php echo wp_json_encode( __( 'Error saving.', 'agent-builder' ) ); ?>);
			}
		})
		.fail(function() {
			$status.css('color', '#d63638').text(<?php echo wp_json_encode( __( 'Request failed.', 'agent-builder' ) ); ?>);
		})
		.always(function() {
			$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Save Whitelist', 'agent-builder' ) ); ?>);
		});
	});

	// Add new command row.
	$('.agentic-cli-add-cmd').on('click', function() {
		const $btn     = $(this);
		const $wrap    = $btn.closest('details');
		const group    = $btn.data('group');
		const $pattern = $wrap.find('.agentic-cli-new-pattern');
		const $label   = $wrap.find('.agentic-cli-new-label');
		const pattern  = $pattern.val().trim();
		const label    = $label.val().trim();

		if ( ! pattern ) {
			$pattern.focus();
			return;
		}

		const idx = Date.now(); // Temporary unique key.
		const $tbody = $('#agentic-cli-group-' + group);
		$tbody.append(
			'<tr data-idx="' + idx + '" data-group="' + group + '">' +
			'<td><label class="agentic-cli-toggle"><input type="checkbox" class="agentic-cli-cmd-toggle" data-idx="' + idx + '" checked><span class="agentic-cli-slider"></span></label></td>' +
			'<td><code class="agentic-cli-pattern" contenteditable="true" data-idx="' + idx + '">' + $('<div>').text(pattern).html() + '</code></td>' +
			'<td><span class="agentic-cli-label" contenteditable="true" data-idx="' + idx + '">' + $('<div>').text(label || pattern).html() + '</span></td>' +
			'<td><button type="button" class="button button-small agentic-cli-remove-cmd" data-idx="' + idx + '">' + <?php echo wp_json_encode( __( 'Remove', 'agent-builder' ) ); ?> + '</button></td>' +
			'</tr>'
		);

		$pattern.val('');
		$label.val('');
	});

	// Remove command row (delegated — works for dynamically added rows).
	$(document).on('click', '.agentic-cli-remove-cmd', function() {
		$(this).closest('tr').remove();
	});

	// -----------------------------------------------------------------------
	// Section 2: Per-agent privilege toggles — save immediately on change.
	// -----------------------------------------------------------------------
	$(document).on('change', '.agentic-cli-priv-toggle', function() {
		const $cb  = $(this);
		const slug = $cb.data('slug');
		const key  = $cb.data('key');
		const val  = $cb.is(':checked') ? 'yes' : 'no';

		$cb.prop('disabled', true);

		$.post(ajaxUrl, {
			action     : 'agentic_cli_save_privilege',
			_ajax_nonce: noncePriv,
			agent_slug : slug,
			meta_key   : key,
			meta_value : val,
		})
		.done(function(resp) {
			if ( ! resp.success ) {
				// Revert on failure.
				$cb.prop('checked', val !== 'yes');
			}
		})
		.fail(function() {
			$cb.prop('checked', val !== 'yes');
		})
		.always(function() {
			$cb.prop('disabled', false);
		});
	});

})(jQuery);
</script>
