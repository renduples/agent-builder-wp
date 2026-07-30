<?php
/**
 * Skills Administration
 *
 * CRUD interface for agent skills. Skills contain SKILL.md instructions
 * that teach agents when and how to use channel-specific tools.
 * Supports local creation and ClawHub import.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @since      2.5.2
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Skills_Registry;
?>
<div class="wrap agentic-admin">
<h1><?php esc_html_e( 'Skills', 'agent-builder' ); ?></h1>

<div class="agentic-card">
<?php

// ── Handle actions ────────────────────────────────────────────────────

$agentic_sk_message = '';
$agentic_sk_error   = '';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view param.
$agentic_sk_view = isset( $_GET['skill_view'] ) ? sanitize_key( $_GET['skill_view'] ) : 'list';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$agentic_sk_edit_id = isset( $_GET['skill_id'] ) ? absint( $_GET['skill_id'] ) : 0;

// ── Save / Create ─────────────────────────────────────────────────────
if ( isset( $_POST['agentic_skill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agentic_skill_nonce'] ) ), 'agentic_skill_save' ) ) {

	$agentic_sk_post = array(
		'name'        => sanitize_text_field( wp_unslash( $_POST['agentic_sk_name'] ?? '' ) ),
		'description' => sanitize_text_field( wp_unslash( $_POST['agentic_sk_description'] ?? '' ) ),
		'content'     => wp_kses_post( wp_unslash( $_POST['agentic_sk_content'] ?? '' ) ),
		'agent_slug'  => sanitize_key( wp_unslash( $_POST['agentic_sk_agent'] ?? '' ) ),
		'author'      => sanitize_text_field( wp_unslash( $_POST['agentic_sk_author'] ?? '' ) ),
		'version'     => sanitize_text_field( wp_unslash( $_POST['agentic_sk_version'] ?? '1.0.0' ) ),
		'enabled'     => isset( $_POST['agentic_sk_enabled'] ),
	);

	if ( '' === $agentic_sk_post['name'] ) {
		$agentic_sk_error = __( 'Skill name is required.', 'agent-builder' );
	} else {
		if ( $agentic_sk_edit_id > 0 ) {
			Skills_Registry::update( $agentic_sk_edit_id, $agentic_sk_post );
			$agentic_sk_message = __( 'Skill updated.', 'agent-builder' );
		} else {
			$agentic_new_id = Skills_Registry::create( $agentic_sk_post );
			if ( $agentic_new_id ) {
				$agentic_sk_message = __( 'Skill created.', 'agent-builder' );
				$agentic_sk_edit_id = $agentic_new_id;
			} else {
				$agentic_sk_error = __( 'Failed to create skill.', 'agent-builder' );
			}
		}

		\Agentic\Security_Log::log_system( 'settings_changed', 'skills', array( 'skill_id' => $agentic_sk_edit_id ) );

		// Return to list after save.
		if ( '' === $agentic_sk_error ) {
			$agentic_sk_view    = 'list';
			$agentic_sk_edit_id = 0;
		}
	}
}

// ── Delete ────────────────────────────────────────────────────────────
if ( isset( $_GET['skill_action'], $_GET['skill_id'], $_GET['_wpnonce'] )
	&& 'delete' === $_GET['skill_action']
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'agentic_skill_delete_' . absint( $_GET['skill_id'] ) )
) {
	Skills_Registry::delete( absint( $_GET['skill_id'] ) );
	$agentic_sk_message = __( 'Skill deleted.', 'agent-builder' );
	$agentic_sk_view    = 'list';
	$agentic_sk_edit_id = 0;

	\Agentic\Security_Log::log_system(
		'settings_changed',
		'skills',
		array(
			'action'   => 'deleted',
			'skill_id' => absint( $_GET['skill_id'] ),
		)
	);
}

// ── Import (Agentic AI or ClawHub) ──────────────────────────
if ( isset( $_POST['agentic_hub_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agentic_hub_nonce'] ) ), 'agentic_hub_import' ) ) {
	$agentic_hub_json = sanitize_text_field( wp_unslash( $_POST['agentic_hub_data'] ?? '' ) );
	$agentic_hub_data = json_decode( $agentic_hub_json, true );

	// Normalise field names (ClawHub uses displayName/summary).
	if ( is_array( $agentic_hub_data ) && empty( $agentic_hub_data['name'] ) && ! empty( $agentic_hub_data['displayName'] ) ) {
		$agentic_hub_data['name'] = $agentic_hub_data['displayName'];
	}

	// Determine source — skills from Agentic AI use 'agentic'.
	$agentic_import_source = sanitize_key( $agentic_hub_data['source'] ?? 'clawhub' );
	if ( ! in_array( $agentic_import_source, array( 'agentic', 'clawhub' ), true ) ) {
		$agentic_import_source = 'clawhub';
	}
	$agentic_hub_data['source'] = $agentic_import_source;

	if ( is_array( $agentic_hub_data ) && ! empty( $agentic_hub_data['name'] ) ) {
		$agentic_imported_id = Skills_Registry::import_from_hub( $agentic_hub_data );
		if ( $agentic_imported_id ) {
			$agentic_sk_message = sprintf(
				/* translators: %s: skill name */
				__( 'Skill "%s" imported.', 'agent-builder' ),
				esc_html( $agentic_hub_data['name'] )
			);
		} else {
			$agentic_sk_error = __( 'Failed to import skill.', 'agent-builder' );
		}
	} else {
		$agentic_sk_error = __( 'Invalid skill data.', 'agent-builder' );
	}
	$agentic_sk_view = 'list';
}

// ── Load data ─────────────────────────────────────────────────────────
$agentic_sk_all  = Skills_Registry::get_all();
$agentic_sk_edit = ( 'edit' === $agentic_sk_view && $agentic_sk_edit_id > 0 ) ? Skills_Registry::get( $agentic_sk_edit_id ) : null;

// Active agents for the dropdown.
$agentic_sk_registry  = \Agentic_Agent_Registry::get_instance();
$agentic_sk_agents    = $agentic_sk_registry->get_active_agents();
$agentic_sk_instances = $agentic_sk_registry->get_all_instances();

?>

<?php if ( $agentic_sk_message ) : ?>
<div class="notice notice-success"><p><?php echo esc_html( $agentic_sk_message ); ?></p></div>
<?php endif; ?>

<?php if ( $agentic_sk_error ) : ?>
<div class="notice notice-error"><p><?php echo esc_html( $agentic_sk_error ); ?></p></div>
<?php endif; ?>

<?php if ( 'edit' === $agentic_sk_view || 'new' === $agentic_sk_view ) : ?>

	<?php
	$agentic_sk_name    = $agentic_sk_edit['name'] ?? '';
	$agentic_sk_desc    = $agentic_sk_edit['description'] ?? '';
	$agentic_sk_content = $agentic_sk_edit['content'] ?? '';
	$agentic_sk_agent   = $agentic_sk_edit['agent_slug'] ?? '';
	$agentic_sk_author  = $agentic_sk_edit['author'] ?? '';
	$agentic_sk_version = $agentic_sk_edit['version'] ?? '1.0.0';
	$agentic_sk_enabled = $agentic_sk_edit ? ( '1' === ( $agentic_sk_edit['enabled'] ?? '0' ) ) : true;
	$agentic_sk_source  = $agentic_sk_edit['source'] ?? 'local';
	?>

	<div class="agentic-max-800">
		<h2 class="agentic-h2-flex">
			<?php echo $agentic_sk_edit_id ? esc_html__( 'Edit Skill', 'agent-builder' ) : esc_html__( 'Create Skill', 'agent-builder' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-skills' ) ); ?>" class="button agentic-ml-auto">&larr; <?php esc_html_e( 'Back to Skills', 'agent-builder' ); ?></a>
		</h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=agentic-skills&skill_view=edit&skill_id=' . $agentic_sk_edit_id ) ); ?>">
			<?php wp_nonce_field( 'agentic_skill_save', 'agentic_skill_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="agentic_sk_name"><?php esc_html_e( 'Name', 'agent-builder' ); ?></label></th>
					<td>
						<input type="text" id="agentic_sk_name" name="agentic_sk_name" value="<?php echo esc_attr( $agentic_sk_name ); ?>" class="regular-text" required />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="agentic_sk_description"><?php esc_html_e( 'Description', 'agent-builder' ); ?></label></th>
					<td>
						<input type="text" id="agentic_sk_description" name="agentic_sk_description" value="<?php echo esc_attr( $agentic_sk_desc ); ?>" class="large-text" />
						<p class="description"><?php esc_html_e( 'Brief summary of what this skill teaches the assistant to do.', 'agent-builder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="agentic_sk_content"><?php esc_html_e( 'SKILL.md Content', 'agent-builder' ); ?></label></th>
					<td>
						<textarea id="agentic_sk_content" name="agentic_sk_content" rows="16" class="large-text code agentic-textarea-mono"><?php echo esc_textarea( $agentic_sk_content ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Markdown instructions that get injected into the assistant\'s system prompt. Teaches the assistant when and how to use specific tools.', 'agent-builder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="agentic_sk_agent"><?php esc_html_e( 'Assign to Assistant', 'agent-builder' ); ?></label></th>
					<td>
						<select id="agentic_sk_agent" name="agentic_sk_agent">
							<option value=""><?php esc_html_e( 'All Assistants', 'agent-builder' ); ?></option>
							<?php foreach ( $agentic_sk_agents as $agentic_sk_slug ) : ?>
								<?php
								$agentic_sk_inst  = $agentic_sk_instances[ $agentic_sk_slug ] ?? null;
								$agentic_sk_label = $agentic_sk_inst ? $agentic_sk_inst->get_name() : ucwords( str_replace( '-', ' ', $agentic_sk_slug ) );
								?>
								<option value="<?php echo esc_attr( $agentic_sk_slug ); ?>" <?php selected( $agentic_sk_agent, $agentic_sk_slug ); ?>>
									<?php echo esc_html( $agentic_sk_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Leave as "All Assistants" to make this skill available globally, or assign it to a specific assistant.', 'agent-builder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="agentic_sk_author"><?php esc_html_e( 'Author', 'agent-builder' ); ?> <small>(<?php esc_html_e( 'optional', 'agent-builder' ); ?>)</small></label></th>
					<td>
						<input type="text" id="agentic_sk_author" name="agentic_sk_author" value="<?php echo esc_attr( $agentic_sk_author ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="agentic_sk_version"><?php esc_html_e( 'Version', 'agent-builder' ); ?></label></th>
					<td>
						<input type="text" id="agentic_sk_version" name="agentic_sk_version" value="<?php echo esc_attr( $agentic_sk_version ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'agent-builder' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="agentic_sk_enabled" value="1" <?php checked( $agentic_sk_enabled ); ?> />
							<?php esc_html_e( 'Active — inject this skill into assistant system prompts', 'agent-builder' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php if ( 'clawhub' === $agentic_sk_source ) : ?>
				<p class="agentic-text-dim-xs">
					<?php esc_html_e( 'This skill was imported from ClawHub. Local edits will be preserved unless you re-import.', 'agent-builder' ); ?>
				</p>
			<?php endif; ?>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php echo $agentic_sk_edit_id ? esc_attr__( 'Update Skill', 'agent-builder' ) : esc_attr__( 'Create Skill', 'agent-builder' ); ?>" />
			</p>
		</form>
	</div>

<?php elseif ( 'hub' === $agentic_sk_view ) : ?>

	<div class="agentic-max-800">
		<h2 class="agentic-h2-flex">
			<?php esc_html_e( 'Import from ClawHub', 'agent-builder' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-skills' ) ); ?>" class="button agentic-ml-auto">&larr; <?php esc_html_e( 'Back to Skills', 'agent-builder' ); ?></a>
		</h2>
		<p><?php esc_html_e( 'Search the ClawHub skill registry for community-contributed skills.', 'agent-builder' ); ?></p>

		<div class="agentic-flex-gap-8-mb-20">
			<input type="text" id="agentic-hub-search" class="regular-text agentic-flex-1" placeholder="<?php esc_attr_e( 'Search skills...', 'agent-builder' ); ?>" />
			<button type="button" id="agentic-hub-search-btn" class="button button-primary"><?php esc_html_e( 'Search', 'agent-builder' ); ?></button>
		</div>

		<div id="agentic-hub-results" class="agentic-hub-results">
			<p class="agentic-text-dim"><?php esc_html_e( 'Enter a search term above to find skills on ClawHub.', 'agent-builder' ); ?></p>
		</div>

		<!-- Hidden import form -->
		<form method="post" id="agentic-hub-import-form" style="display:none;">
			<?php wp_nonce_field( 'agentic_hub_import', 'agentic_hub_nonce' ); ?>
			<input type="hidden" name="agentic_hub_data" id="agentic-hub-data" value="" />
		</form>
	</div>

	<script>
	(function() {
		'use strict';
		var searchInput = document.getElementById('agentic-hub-search');
		var searchBtn   = document.getElementById('agentic-hub-search-btn');
		var resultsDiv  = document.getElementById('agentic-hub-results');
		var importForm      = document.getElementById('agentic-hub-import-form');
		var importDataField = document.getElementById('agentic-hub-data');

		var CLAWHUB_API = 'https://wry-manatee-359.convex.site/api/v1';

		function doSearch() {
			var query = searchInput.value.trim();
			if (!query) return;

			resultsDiv.innerHTML = '<p class="agentic-text-dim"><?php echo esc_js( __( 'Searching...', 'agent-builder' ) ); ?></p>';
			searchBtn.disabled = true;

			fetch(CLAWHUB_API + '/search?q=' + encodeURIComponent(query), {
				method: 'GET',
				headers: { 'Accept': 'application/json' },
			})
			.then(function(r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function(data) {
				searchBtn.disabled = false;
				var skills = data.results || data.items || [];

				if (!skills.length) {
					resultsDiv.innerHTML = '<p class="agentic-text-dim"><?php echo esc_js( __( 'No skills found. Try a different search term.', 'agent-builder' ) ); ?></p>';
					return;
				}

				var html = '<table class="widefat striped"><thead><tr>' +
					'<th><?php echo esc_js( __( 'Name', 'agent-builder' ) ); ?></th>' +
					'<th><?php echo esc_js( __( 'Description', 'agent-builder' ) ); ?></th>' +
					'<th class="agentic-col-80"><?php echo esc_js( __( 'Version', 'agent-builder' ) ); ?></th>' +
					'<th class="agentic-col-min-90"><?php echo esc_js( __( 'Action', 'agent-builder' ) ); ?></th>' +
					'</tr></thead><tbody>';

				skills.forEach(function(skill) {
					// Map ClawHub fields to our import format.
					var importData = {
						name: skill.displayName || skill.slug || 'Untitled',
						description: skill.summary || '',
						id: skill.slug || '',
						version: skill.version || '1.0.0',
						slug: skill.slug || '',
					};
					html += '<tr>';
					html += '<td><strong>' + escHtml(importData.name) + '</strong><br><code class="agentic-code-xs">' + escHtml(skill.slug || '') + '</code></td>';
					html += '<td>' + escHtml(importData.description) + '</td>';
					html += '<td><code class="agentic-code-sm">' + escHtml(importData.version) + '</code></td>';
					html += '<td><button type="button" class="button agentic-hub-import-btn" data-slug="' + escAttr(skill.slug || '') + '" data-skill=\'' + escAttr(JSON.stringify(importData)) + '\'><?php echo esc_js( __( 'Import', 'agent-builder' ) ); ?></button></td>';
					html += '</tr>';
				});

				html += '</tbody></table>';
				resultsDiv.innerHTML = html;

				resultsDiv.querySelectorAll('.agentic-hub-import-btn').forEach(function(btn) {
					btn.addEventListener('click', async function() {
						if (!await agenticUI.confirm('<?php echo esc_js( __( 'Import this skill?', 'agent-builder' ) ); ?>', { confirmText: '<?php echo esc_js( __( 'Import', 'agent-builder' ) ); ?>' })) return;
						var btn = this;
						var skillData = JSON.parse(btn.dataset.skill);
						var slug = btn.dataset.slug;
						btn.disabled = true;
						btn.textContent = '<?php echo esc_js( __( 'Importing...', 'agent-builder' ) ); ?>';

						// Fetch skill detail + SKILL.md content from GitHub.
						fetch(CLAWHUB_API + '/skills/' + encodeURIComponent(slug))
						.then(function(r) { return r.ok ? r.json() : null; })
						.then(function(detail) {
							if (detail && detail.skill) {
								skillData.description = detail.skill.summary || skillData.description;
								if (detail.latestVersion) {
									skillData.version = detail.latestVersion.version || skillData.version;
								}
								if (detail.owner) {
									skillData.author = detail.owner.displayName || detail.owner.handle || '';
								}
							}
							// Fetch SKILL.md from GitHub repo using owner handle and canonical slug.
							var ownerHandle = (detail && detail.owner) ? (detail.owner.handle || '') : '';
							var canonicalSlug = (detail && detail.skill) ? (detail.skill.slug || slug) : slug;
							if (ownerHandle) {
								return fetch('https://raw.githubusercontent.com/openclaw/skills/main/skills/' + encodeURIComponent(ownerHandle) + '/' + encodeURIComponent(canonicalSlug) + '/SKILL.md')
								.then(function(r) { return r.ok ? r.text() : ''; })
								.then(function(md) { skillData.content = md || ''; })
								.catch(function() {});
							}
						})
						.then(function() {
							importDataField.value = JSON.stringify(skillData);
							importForm.submit();
						})
						.catch(function() {
							// Import with search data only.
							importDataField.value = JSON.stringify(skillData);
							importForm.submit();
						});
					});
				});
			})
			.catch(function(err) {
				searchBtn.disabled = false;
				resultsDiv.innerHTML = '<div class="notice notice-error"><p><?php echo esc_js( __( 'Failed to connect to ClawHub. Please try again.', 'agent-builder' ) ); ?> (' + escHtml(err.message || '') + ')</p></div>';
			});
		}

		function escHtml(str) {
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}

		function escAttr(str) {
			return str.replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		}

		searchBtn.addEventListener('click', doSearch);
		searchInput.addEventListener('keydown', function(e) {
			if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
		});
	})();
	</script>

<?php else : ?>

	<div class="agentic-max-900">
		<h2 class="agentic-h2-flex">
			<?php esc_html_e( 'Skills', 'agent-builder' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-skills&skill_view=new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Skill', 'agent-builder' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-skills&skill_view=hub' ) ); ?>" class="button"><?php esc_html_e( 'Browse Community', 'agent-builder' ); ?></a>
		</h2>
		<p><?php esc_html_e( 'Skills contain SKILL.md instructions that teach assistants when and how to use specific tools. They are injected into the system prompt at runtime.', 'agent-builder' ); ?></p>

		<!-- Recommended Skills from agentic-plugin.com -->
		<div id="agentic-recommended-skills" class="agentic-recommended-grid">
			<h3 class="agentic-h3-mb-8"><?php esc_html_e( 'Recommended Skills', 'agent-builder' ); ?></h3>
			<p class="agentic-mb-12-text-dim"><?php esc_html_e( 'Curated skills verified to work with Agent Builder.', 'agent-builder' ); ?></p>
			<div id="agentic-recommended-grid" class="agentic-min-h-60">
				<p class="agentic-text-dim"><?php esc_html_e( 'Loading...', 'agent-builder' ); ?></p>
			</div>
		</div>

		<!-- Hidden import form for recommended skills -->
		<form method="post" id="agentic-rec-import-form" style="display:none;">
			<?php wp_nonce_field( 'agentic_hub_import', 'agentic_hub_nonce' ); ?>
			<input type="hidden" name="agentic_hub_data" id="agentic-rec-data" value="" />
		</form>

		<script>
		(function() {
			'use strict';
			var SKILLS_API = 'https://agentic-plugin.com/wp-json/agentic/v1/skills';
			var grid = document.getElementById('agentic-recommended-grid');
			var importForm = document.getElementById('agentic-rec-import-form');
			var importField = document.getElementById('agentic-rec-data');

			fetch(SKILLS_API + '/featured')
			.then(function(r) { return r.ok ? r.json() : []; })
			.then(function(skills) {
				if (!skills.length) {
					// Fallback: try search endpoint
					return fetch(SKILLS_API + '?per_page=12')
					.then(function(r) { return r.ok ? r.json() : { skills: [] }; })
					.then(function(d) { return d.skills || []; });
				}
				return skills;
			})
			.then(function(skills) {
				if (!skills || !skills.length) {
					grid.innerHTML = '<p class="agentic-text-dim"><?php echo esc_js( __( 'No recommended skills available yet. Create your own or browse the community.', 'agent-builder' ) ); ?></p>';
					return;
				}

				var html = '<div class="agentic-skills-js-grid">';
				skills.forEach(function(skill) {
					var channels = (skill.channels || []).map(function(c) {
						return '<span class="agentic-badge-indigo-sm">' + escHtml(c) + '</span>';
					}).join(' ');

					html += '<div class="agentic-skill-card-js">';
					html += '<div class="agentic-skill-card-head">';
					html += '<strong class="agentic-skill-card-title">' + escHtml(skill.name) + '</strong>';
					if (skill.featured) html += '<span class="agentic-badge-featured">Featured</span>';
					html += '</div>';
					html += '<p class="agentic-skill-card-desc">' + escHtml(skill.description || '') + '</p>';
					html += '<div class="agentic-skill-card-tags">';
					html += channels;
					html += '<span class="agentic-skill-card-ver">v' + escHtml(skill.version || '1.0.0') + '</span>';
					html += '</div>';
					html += '<div class="agentic-skill-card-btns">';
					html += '<button type="button" class="button button-small agentic-rec-import" data-slug="' + escAttr(skill.slug) + '" data-skill=\'' + escAttr(JSON.stringify(skill)) + '\'><?php echo esc_js( __( 'Import', 'agent-builder' ) ); ?></button>';
					html += '</div>';
					html += '</div>';
				});
				html += '</div>';
				grid.innerHTML = html;

				grid.querySelectorAll('.agentic-rec-import').forEach(function(btn) {
					btn.addEventListener('click', async function() {
						if (!await agenticUI.confirm('<?php echo esc_js( __( 'Import this skill?', 'agent-builder' ) ); ?>', { confirmText: '<?php echo esc_js( __( 'Import', 'agent-builder' ) ); ?>' })) return;
						var slug = this.dataset.slug;
						var skillData = JSON.parse(this.dataset.skill);
						this.disabled = true;
						this.textContent = '<?php echo esc_js( __( 'Importing...', 'agent-builder' ) ); ?>';

						// Fetch full content from our API.
						fetch(SKILLS_API + '/' + encodeURIComponent(slug))
						.then(function(r) { return r.ok ? r.json() : null; })
						.then(function(detail) {
							var importData = {
								name: detail ? detail.name : skillData.name,
								description: detail ? detail.description : skillData.description,
								content: detail ? (detail.content || '') : '',
								version: detail ? detail.version : skillData.version,
								author: detail ? detail.author : (skillData.author || ''),
								slug: slug,
								source: 'agentic',
							};
							importField.value = JSON.stringify(importData);
							importForm.submit();
						})
						.catch(function() {
							importField.value = JSON.stringify(skillData);
							importForm.submit();
						});

						// Track the import.
						fetch(SKILLS_API + '/' + encodeURIComponent(slug) + '/import', { method: 'POST' }).catch(function(){});
					});
				});
			})
			.catch(function() {
				grid.innerHTML = '<p class="agentic-text-dim"><?php echo esc_js( __( 'Could not load recommended skills. Create your own or browse the community.', 'agent-builder' ) ); ?></p>';
			});

			function escHtml(str) { var d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
			function escAttr(str) { return str.replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
		})();
		</script>

		<?php if ( ! empty( $agentic_sk_all ) ) : ?>
		<h3 class="agentic-th-mt-32"><?php esc_html_e( 'Your Skills', 'agent-builder' ); ?></h3>
		<?php endif; ?>

		<?php if ( empty( $agentic_sk_all ) ) : ?>
			<h3 class="agentic-th-mt-32"><?php esc_html_e( 'Your Skills', 'agent-builder' ); ?></h3>
			<div class="notice notice-info agentic-notice-mt-0">
				<p>
					<strong><?php esc_html_e( 'No skills installed yet.', 'agent-builder' ); ?></strong>
					<?php esc_html_e( 'Import a recommended skill above, create your own, or browse the community.', 'agent-builder' ); ?>
				</p>
			</div>
		<?php else : ?>
			<table class="widefat striped agentic-table-mt-16">
				<thead>
					<tr>
						<th class="agentic-col-60"><?php esc_html_e( 'Status', 'agent-builder' ); ?></th>
						<th class="agentic-col-200"><?php esc_html_e( 'Name', 'agent-builder' ); ?></th>
						<th><?php esc_html_e( 'Description', 'agent-builder' ); ?></th>
						<th class="agentic-col-140"><?php esc_html_e( 'Assistant', 'agent-builder' ); ?></th>
						<th class="agentic-col-80"><?php esc_html_e( 'Source', 'agent-builder' ); ?></th>
						<th class="agentic-col-80"><?php esc_html_e( 'Version', 'agent-builder' ); ?></th>
						<th class="agentic-col-140"><?php esc_html_e( 'Actions', 'agent-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $agentic_sk_all as $agentic_sk_item ) : ?>
					<tr<?php echo '1' !== ( $agentic_sk_item['enabled'] ?? '0' ) ? ' class="agentic-opacity-60"' : ''; ?>>
						<td class="agentic-text-center">
							<?php if ( '1' === ( $agentic_sk_item['enabled'] ?? '0' ) ) : ?>
								<span class="agentic-status-dot-active" title="<?php esc_attr_e( 'Active', 'agent-builder' ); ?>"></span>
							<?php else : ?>
								<span class="agentic-status-dot-inactive" title="<?php esc_attr_e( 'Disabled', 'agent-builder' ); ?>"></span>
							<?php endif; ?>
						</td>
						<td>
							<strong><?php echo esc_html( $agentic_sk_item['name'] ); ?></strong>
						</td>
						<td>
							<?php echo esc_html( $agentic_sk_item['description'] ); ?>
						</td>
						<td>
							<?php
							$agentic_sk_a_slug = $agentic_sk_item['agent_slug'] ?? '';
							if ( '' === $agentic_sk_a_slug ) {
								echo '<span class="agentic-text-dim">' . esc_html__( 'All', 'agent-builder' ) . '</span>';
							} else {
								$agentic_sk_a_inst = $agentic_sk_instances[ $agentic_sk_a_slug ] ?? null;
								echo esc_html( $agentic_sk_a_inst ? $agentic_sk_a_inst->get_name() : ucwords( str_replace( '-', ' ', $agentic_sk_a_slug ) ) );
							}
							?>
						</td>
						<td>
							<?php
							$agentic_sk_src = $agentic_sk_item['source'] ?? 'local';
							if ( 'agentic' === $agentic_sk_src ) :
								?>
								<span class="agentic-badge-agentic">Agentic</span>
							<?php elseif ( 'clawhub' === $agentic_sk_src ) : ?>
								<span class="agentic-badge-clhub">ClawHub</span>
							<?php else : ?>
								<span class="agentic-badge-local"><?php esc_html_e( 'Local', 'agent-builder' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<code class="agentic-code-sm"><?php echo esc_html( $agentic_sk_item['version'] ?? '1.0.0' ); ?></code>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-skills&skill_view=edit&skill_id=' . $agentic_sk_item['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'agent-builder' ); ?></a>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=agentic-skills&skill_action=delete&skill_id=' . $agentic_sk_item['id'] ), 'agentic_skill_delete_' . $agentic_sk_item['id'] ) ); ?>" class="button button-small agentic-link-danger" data-agentic-confirm="<?php echo esc_attr( __( 'Delete this skill?', 'agent-builder' ) ); ?>" data-agentic-confirm-danger data-agentic-confirm-ok="<?php echo esc_attr( __( 'Delete', 'agent-builder' ) ); ?>"><?php esc_html_e( 'Delete', 'agent-builder' ); ?></a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<div class="agentic-about-skills-box">
			<h3 class="agentic-about-skills-h3"><?php esc_html_e( 'About Skills', 'agent-builder' ); ?></h3>
			<ul class="agentic-list-disc-pl20">
				<li><?php esc_html_e( 'Skills contain SKILL.md instructions — markdown that gets injected into the assistant\'s system prompt.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'They teach assistants when and how to use channel-specific tools (e.g., "when a user asks to speak to a human, use the Slack handoff tool").', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Skills never contain API keys or direct HTTP calls — they operate above channels in the stack: assistant → skill → channel → tool.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Assign a skill to a specific assistant, or leave it as "All" to make it available globally.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Import curated skills from the Recommended section, browse community skills, or create your own.', 'agent-builder' ); ?></li>
			</ul>
		</div>
	</div>
</div><!-- /.agentic-card -->

<?php endif; ?>
