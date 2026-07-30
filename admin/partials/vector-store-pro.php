<?php
/**
 * Vector Store panels (Pro-only). IDs match assets/js/train-data.js.
 *
 * @package Agent_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$agentic_td_tab = isset( $_GET['vtab'] ) ? sanitize_key( wp_unslash( $_GET['vtab'] ) ) : 'scan';
if ( ! in_array( $agentic_td_tab, array( 'scan', 'upload', 'sources' ), true ) ) {
	$agentic_td_tab = 'scan';
}
?>
<div class="agentic-td agentic-vector-pro">
	<p class="agentic-td-subtitle">
		<?php esc_html_e( 'Hosted vector store for large corpora. Keep curated business facts in the Knowledge Wiki (OKF).', 'agent-builder' ); ?>
		<a href="https://agentic-plugin.com/api-credits/" target="_blank" rel="noopener"><?php esc_html_e( 'Credit pricing →', 'agent-builder' ); ?></a>
	</p>
	<nav class="agentic-vector-subnav" aria-label="<?php esc_attr_e( 'Vector store sections', 'agent-builder' ); ?>">
		<a class="<?php echo 'scan' === $agentic_td_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-train-data&tab=vector&vtab=scan' ) ); ?>"><?php esc_html_e( 'Scan website', 'agent-builder' ); ?></a>
		<a class="<?php echo 'upload' === $agentic_td_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-train-data&tab=vector&vtab=upload' ) ); ?>"><?php esc_html_e( 'Upload documents', 'agent-builder' ); ?></a>
		<a class="<?php echo 'sources' === $agentic_td_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-train-data&tab=vector&vtab=sources' ) ); ?>"><?php esc_html_e( 'Sources', 'agent-builder' ); ?></a>
	</nav>

	<!-- Balance bar (shown on all tabs) -->
	<div class="agentic-td-balance-bar" id="agentic-td-balance-bar">
		<div class="agentic-td-balance-item">
			<span class="agentic-td-balance-label"><?php esc_html_e( 'Credits', 'agent-builder' ); ?></span>
			<span class="agentic-td-balance-value" id="agentic-td-credits">—</span>
		</div>
		<div class="agentic-td-balance-item">
			<span class="agentic-td-balance-label"><?php esc_html_e( 'Sources', 'agent-builder' ); ?></span>
			<span class="agentic-td-balance-value" id="agentic-td-source-count">—</span>
		</div>
		<div class="agentic-td-balance-item">
			<span class="agentic-td-balance-label"><?php esc_html_e( 'Vectors', 'agent-builder' ); ?></span>
			<span class="agentic-td-balance-value" id="agentic-td-vector-count">—</span>
		</div>
		<a href="https://agentic-plugin.com/api-credits/" target="_blank" class="button button-small agentic-td-buy-btn"><?php esc_html_e( 'Buy Credits', 'agent-builder' ); ?></a>
	</div>

	<?php if ( 'scan' === $agentic_td_tab ) : ?>
	<!-- ═══════════════════════════════════════════════════════════════════ -->
	<!-- TAB: Scan Website                                                  -->
	<!-- ═══════════════════════════════════════════════════════════════════ -->
	<div class="agentic-td-panel">
		<h3><?php esc_html_e( 'Scan Your Website Content', 'agent-builder' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Select which content to train your assistants on. Published pages and posts will be sent to the Vector Store for embedding.', 'agent-builder' ); ?></p>

		<div class="agentic-td-scan-options">
			<div class="agentic-td-scan-type">
				<label>
					<input type="radio" name="agentic_scan_type" value="pages" checked />
					<?php esc_html_e( 'Pages', 'agent-builder' ); ?>
				</label>
				<label>
					<input type="radio" name="agentic_scan_type" value="posts" />
					<?php esc_html_e( 'Posts', 'agent-builder' ); ?>
				</label>
				<label>
					<input type="radio" name="agentic_scan_type" value="both" />
					<?php esc_html_e( 'Pages & Posts', 'agent-builder' ); ?>
				</label>
			</div>

			<div class="agentic-td-scan-filters">
				<div class="agentic-td-filter-row">
					<label for="agentic-scan-limit"><?php esc_html_e( 'Max items:', 'agent-builder' ); ?></label>
					<select id="agentic-scan-limit">
						<option value="10">10</option>
						<option value="25" selected>25</option>
						<option value="50">50</option>
						<option value="100">100</option>
						<option value="-1"><?php esc_html_e( 'All', 'agent-builder' ); ?></option>
					</select>
				</div>
				<div class="agentic-td-filter-row">
					<label for="agentic-scan-category"><?php esc_html_e( 'Category:', 'agent-builder' ); ?></label>
					<?php
					wp_dropdown_categories(
						array(
							'show_option_all' => __( 'All categories', 'agent-builder' ),
							'id'              => 'agentic-scan-category',
							'name'            => 'agentic_scan_category',
							'orderby'         => 'name',
							'hide_empty'      => true,
						)
					);
					?>
				</div>
			</div>
		</div>

		<button type="button" id="agentic-scan-fetch" class="button button-primary">
			<span class="dashicons dashicons-search agentic-di-mt4"></span>
			<?php esc_html_e( 'Fetch Content List', 'agent-builder' ); ?>
		</button>

		<!-- Content list (populated by JS) -->
		<div id="agentic-scan-results" class="agentic-td-results" style="display:none;">
			<div class="agentic-td-results-header">
				<label>
					<input type="checkbox" id="agentic-scan-select-all" />
					<?php esc_html_e( 'Select All', 'agent-builder' ); ?>
				</label>
				<span id="agentic-scan-selected-count">0 selected</span>
			</div>
			<div id="agentic-scan-list" class="agentic-td-content-list"></div>
			<div class="agentic-td-results-footer">
				<button type="button" id="agentic-scan-train" class="button button-primary" disabled>
					<span class="dashicons dashicons-cloud-upload agentic-di-mt4"></span>
					<?php esc_html_e( 'Train Selected', 'agent-builder' ); ?>
				</button>
				<span id="agentic-scan-estimate" class="agentic-td-estimate"></span>
			</div>
		</div>

		<!-- Progress (shown during training) -->
		<div id="agentic-scan-progress" class="agentic-td-progress" style="display:none;">
			<h4><?php esc_html_e( 'Training in Progress', 'agent-builder' ); ?></h4>
			<div class="agentic-td-progress-bar">
				<div class="agentic-td-progress-fill" id="agentic-scan-progress-fill"></div>
			</div>
			<p id="agentic-scan-progress-text"></p>
			<div id="agentic-scan-progress-log" class="agentic-td-log"></div>
		</div>
	</div>

	<?php elseif ( 'upload' === $agentic_td_tab ) : ?>
	<!-- ═══════════════════════════════════════════════════════════════════ -->
	<!-- TAB: Upload Documents                                              -->
	<!-- ═══════════════════════════════════════════════════════════════════ -->
	<div class="agentic-td-panel">
		<h3><?php esc_html_e( 'Upload Documents', 'agent-builder' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Upload PDF or text files to train your assistants. Files are chunked, embedded, and stored in your private Vector Store.', 'agent-builder' ); ?></p>

		<div class="agentic-td-upload-zone" id="agentic-upload-zone">
			<div class="agentic-td-upload-icon">
				<span class="dashicons dashicons-upload"></span>
			</div>
			<p class="agentic-td-upload-text"><?php esc_html_e( 'Drag & drop files here, or click to browse', 'agent-builder' ); ?></p>
			<p class="agentic-td-upload-hint"><?php esc_html_e( 'Supported: PDF, TXT — Max 10 MB per file', 'agent-builder' ); ?></p>
			<input type="file" id="agentic-upload-input" multiple accept=".pdf,.txt,.text" style="display:none;" />
		</div>

		<!-- File queue -->
		<div id="agentic-upload-queue" class="agentic-td-upload-queue" style="display:none;">
			<h4><?php esc_html_e( 'Files Ready', 'agent-builder' ); ?></h4>
			<div id="agentic-upload-list" class="agentic-td-file-list"></div>
			<div class="agentic-td-upload-actions">
				<button type="button" id="agentic-upload-start" class="button button-primary">
					<span class="dashicons dashicons-cloud-upload agentic-di-mt4"></span>
					<?php esc_html_e( 'Upload & Train', 'agent-builder' ); ?>
				</button>
				<button type="button" id="agentic-upload-clear" class="button"><?php esc_html_e( 'Clear All', 'agent-builder' ); ?></button>
			</div>
		</div>

		<!-- Upload progress -->
		<div id="agentic-upload-progress" class="agentic-td-progress" style="display:none;">
			<h4><?php esc_html_e( 'Uploading & Training', 'agent-builder' ); ?></h4>
			<div class="agentic-td-progress-bar">
				<div class="agentic-td-progress-fill" id="agentic-upload-progress-fill"></div>
			</div>
			<p id="agentic-upload-progress-text"></p>
			<div id="agentic-upload-progress-log" class="agentic-td-log"></div>
		</div>
	</div>

	<?php elseif ( 'sources' === $agentic_td_tab ) : ?>
	<!-- ═══════════════════════════════════════════════════════════════════ -->
	<!-- TAB: Training Sources                                              -->
	<!-- ═══════════════════════════════════════════════════════════════════ -->
	<div class="agentic-td-panel">
		<h3><?php esc_html_e( 'Training Sources', 'agent-builder' ); ?></h3>
		<p class="description"><?php esc_html_e( 'All data sources currently in your Vector Store. You can re-train or delete individual sources.', 'agent-builder' ); ?></p>

		<div class="agentic-td-sources-toolbar">
			<button type="button" id="agentic-sources-refresh" class="button">
				<span class="dashicons dashicons-update agentic-di-mt4"></span>
				<?php esc_html_e( 'Refresh', 'agent-builder' ); ?>
			</button>
		</div>

		<div id="agentic-sources-loading" class="agentic-td-loading">
			<span class="spinner is-active"></span>
			<?php esc_html_e( 'Loading sources…', 'agent-builder' ); ?>
		</div>

		<table id="agentic-sources-table" class="widefat striped" style="display:none;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Source', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Type', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Chunks', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Tokens', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Trained', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'agent-builder' ); ?></th>
				</tr>
			</thead>
			<tbody id="agentic-sources-tbody"></tbody>
		</table>

		<div id="agentic-sources-empty" class="agentic-td-empty" style="display:none;">
			<span class="dashicons dashicons-database agentic-di-icon-lg"></span>
			<p><?php esc_html_e( 'No training sources yet. Use the Scan Website or Upload Documents tabs to get started.', 'agent-builder' ); ?></p>
		</div>
	</div>

	<?php endif; ?>

</div>
