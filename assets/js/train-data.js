/**
 * Agent Builder — Train on Data
 *
 * Handles website content scanning, document uploads, source management,
 * and credit/usage display via AJAX proxying to the RAG API.
 *
 * @package Agent_Builder
 * @since   2.3.0
 */

/* global jQuery, agenticTrainData */
(function ($) {
	'use strict';

	// ─── Helpers ─────────────────────────────────────────────────────────────
	function ajax(action, data, opts) {
		var payload = $.extend({ action: action, _ajax_nonce: agenticTrainData.nonce }, data);
		return $.ajax($.extend({
			url:      agenticTrainData.ajaxUrl,
			method:   'POST',
			data:     payload,
			dataType: 'json'
		}, opts || {}));
	}

	function formatNumber(n) {
		if (n === null || n === undefined) return '—';
		return Number(n).toLocaleString();
	}

	function formatDate(iso) {
		if (!iso) return '—';
		var d = new Date(iso);
		return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
	}

	function formatBytes(bytes) {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / 1048576).toFixed(1) + ' MB';
	}

	function logEntry(containerId, msg, cls) {
		var $log = $(containerId);
		$log.append('<div class="agentic-td-log-entry ' + (cls || 'info') + '">' + msg + '</div>');
		$log.scrollTop($log[0].scrollHeight);
	}

	// ─── Load Balance Bar (all tabs) ─────────────────────────────────────────
	function loadBalanceBar() {
		ajax('agentic_td_get_overview').done(function (res) {
			if (!res.success) return;
			var d = res.data;
			$('#agentic-td-credits').text(formatNumber(d.balance));
			$('#agentic-td-source-count').text(formatNumber(d.source_count));
			$('#agentic-td-vector-count').text(formatNumber(d.vector_count));
		});
	}

	// ═════════════════════════════════════════════════════════════════════════
	// TAB: Scan Website
	// ═════════════════════════════════════════════════════════════════════════
	function initScanTab() {
		var selectedItems = {};

		$('#agentic-scan-fetch').on('click', function () {
			var $btn = $(this).prop('disabled', true).text('Scanning…');
			var type = $('input[name=agentic_scan_type]:checked').val();
			var limit = $('#agentic-scan-limit').val();
			var cat = $('#agentic-scan-category').val();

			ajax('agentic_td_scan_content', {
				post_type: type,
				limit:     limit,
				category:  cat
			}).done(function (res) {
				if (!res.success) {
					agenticUI.toast(res.data || 'Failed to fetch content.', 'error');
					return;
				}
				renderContentList(res.data.items);
			}).fail(function () {
				agenticUI.toast('Request failed. Please try again.', 'error');
			}).always(function () {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top:4px;"></span> Fetch Content List');
			});
		});

		function renderContentList(items) {
			var $list = $('#agentic-scan-list').empty();
			var $results = $('#agentic-scan-results');
			selectedItems = {};

			if (!items || items.length === 0) {
				$list.html('<p class="agentic-td-empty" style="padding:20px;">No content found matching your criteria.</p>');
				$results.show();
				return;
			}

			items.forEach(function (item) {
				var trained = item.trained ? ' <span class="agentic-td-content-status trained">✓ Trained</span>' : '';
				var typeBadge = '<span class="agentic-td-content-type">' + item.type + '</span>';
				var $row = $('<div class="agentic-td-content-item" data-id="' + item.id + '">' +
					'<label>' +
					'<input type="checkbox" value="' + item.id + '" />' +
					'<span>' + $('<span>').text(item.title).html() + '</span>' +
					'</label>' +
					'<span class="agentic-td-content-meta">' + typeBadge + ' · ' + formatNumber(item.word_count) + ' words' + trained + '</span>' +
					'</div>');
				$list.append($row);
			});

			$results.show();
			updateSelectedCount();

			// Checkbox events.
			$list.off('change').on('change', 'input[type=checkbox]', function () {
				var id = $(this).val();
				if (this.checked) {
					selectedItems[id] = items.find(function (i) { return String(i.id) === String(id); });
				} else {
					delete selectedItems[id];
				}
				updateSelectedCount();
			});

			$('#agentic-scan-select-all').off('change').on('change', function () {
				var checked = this.checked;
				$list.find('input[type=checkbox]').prop('checked', checked);
				if (checked) {
					items.forEach(function (i) { selectedItems[i.id] = i; });
				} else {
					selectedItems = {};
				}
				updateSelectedCount();
			});
		}

		function updateSelectedCount() {
			var count = Object.keys(selectedItems).length;
			$('#agentic-scan-selected-count').text(count + ' selected');
			$('#agentic-scan-train').prop('disabled', count === 0);

			// Estimate tokens.
			var totalWords = 0;
			Object.values(selectedItems).forEach(function (item) {
				totalWords += item.word_count || 0;
			});
			var estTokens = Math.ceil(totalWords * 1.3);
			var estCredits = Math.ceil(estTokens / 1000);
			if (count > 0) {
				$('#agentic-scan-estimate').text('≈ ' + formatNumber(estTokens) + ' tokens · ' + formatNumber(estCredits) + ' credits');
			} else {
				$('#agentic-scan-estimate').text('');
			}
		}

		$('#agentic-scan-train').on('click', async function () {
			var ids = Object.keys(selectedItems);
			if (ids.length === 0) return;

			if (!await agenticUI.confirm('Train ' + ids.length + ' item(s)? Credits will be deducted based on content size.', { confirmText: 'Train' })) return;

			$('#agentic-scan-results').hide();
			$('#agentic-scan-progress').show();
			$('#agentic-scan-progress-log').empty();

			var total = ids.length;
			var done = 0;
			var failed = 0;

			function trainNext() {
				if (ids.length === 0) {
					$('#agentic-scan-progress-text').text('Complete: ' + (done - failed) + ' succeeded, ' + failed + ' failed of ' + total);
					$('#agentic-scan-progress-fill').css('width', '100%');
					loadBalanceBar();
					return;
				}

				var id = ids.shift();
				var item = selectedItems[id];
				$('#agentic-scan-progress-text').text('Training ' + (done + 1) + ' of ' + total + ': ' + (item ? item.title : id));

				ajax('agentic_td_train_post', { post_id: id }).done(function (res) {
					done++;
					if (res.success) {
						logEntry('#agentic-scan-progress-log', '✓ ' + (item ? item.title : id) + ' — ' + (res.data.chunks || 0) + ' chunks, ' + (res.data.credits_used || 0) + ' credits', 'success');
					} else {
						failed++;
						var errMsg = res.data || 'Unknown error';
						logEntry('#agentic-scan-progress-log', '✗ ' + (item ? item.title : id) + ': ' + errMsg, 'error');
						// Stop immediately on rate-limit errors — no point continuing.
						var errLower = typeof errMsg === 'string' ? errMsg.toLowerCase() : '';
						var isLimitError = errLower.indexOf('insufficient credits') !== -1 || errLower.indexOf('quota') !== -1 || errLower.indexOf('daily') !== -1 || errLower.indexOf('rate limit') !== -1;
						if (isLimitError) {
							var skipped = ids.length;
							var limitMsg = '⏹ Stopped — ' + errMsg + ' ' + skipped + ' item(s) skipped.';
							logEntry('#agentic-scan-progress-log', limitMsg, 'error');
							$('#agentic-scan-progress-text').text('Stopped: ' + (done - failed) + ' succeeded, ' + failed + ' failed, ' + skipped + ' skipped');
							$('#agentic-scan-progress-fill').css('width', '100%');
							loadBalanceBar();
							return;
						}
					}
					$('#agentic-scan-progress-fill').css('width', Math.round((done / total) * 100) + '%');
					trainNext();
				}).fail(function () {
					done++;
					failed++;
					logEntry('#agentic-scan-progress-log', '✗ ' + (item ? item.title : id) + ': Request failed', 'error');
					$('#agentic-scan-progress-fill').css('width', Math.round((done / total) * 100) + '%');
					trainNext();
				});
			}

			trainNext();
		});
	}

	// ═════════════════════════════════════════════════════════════════════════
	// TAB: Upload Documents
	// ═════════════════════════════════════════════════════════════════════════
	function initUploadTab() {
		var fileQueue = [];
		var MAX_SIZE = 10 * 1024 * 1024; // 10 MB

		var $zone = $('#agentic-upload-zone');
		var $input = $('#agentic-upload-input');

		$zone.on('click', function () { $input.trigger('click'); });

		$zone.on('dragover', function (e) {
			e.preventDefault();
			$zone.addClass('drag-over');
		}).on('dragleave drop', function () {
			$zone.removeClass('drag-over');
		}).on('drop', function (e) {
			e.preventDefault();
			addFiles(e.originalEvent.dataTransfer.files);
		});

		$input.on('change', function () {
			addFiles(this.files);
			this.value = '';
		});

		function addFiles(files) {
			for (var i = 0; i < files.length; i++) {
				var f = files[i];
				var ext = f.name.split('.').pop().toLowerCase();
				if (['pdf', 'txt', 'text'].indexOf(ext) === -1) {
					agenticUI.toast(f.name + ': Unsupported format. Only PDF and TXT files allowed.', 'warning');
					continue;
				}
				if (f.size > MAX_SIZE) {
					agenticUI.toast(f.name + ': File too large. Maximum 10 MB.', 'warning');
					continue;
				}
				fileQueue.push(f);
			}
			renderFileQueue();
		}

		function renderFileQueue() {
			var $list = $('#agentic-upload-list').empty();
			if (fileQueue.length === 0) {
				$('#agentic-upload-queue').hide();
				return;
			}

			fileQueue.forEach(function (f, idx) {
				var icon = f.name.endsWith('.pdf') ? 'dashicons-pdf' : 'dashicons-media-text';
				$list.append(
					'<div class="agentic-td-file-item" data-idx="' + idx + '">' +
					'<span class="dashicons ' + icon + '"></span>' +
					'<span class="agentic-td-file-name">' + $('<span>').text(f.name).html() + '</span>' +
					'<span class="agentic-td-file-size">' + formatBytes(f.size) + '</span>' +
					'<a href="#" class="agentic-td-file-remove" data-idx="' + idx + '">Remove</a>' +
					'</div>'
				);
			});

			$('#agentic-upload-queue').show();
		}

		$(document).on('click', '.agentic-td-file-remove', function (e) {
			e.preventDefault();
			fileQueue.splice($(this).data('idx'), 1);
			renderFileQueue();
		});

		$('#agentic-upload-clear').on('click', function () {
			fileQueue = [];
			renderFileQueue();
		});

		$('#agentic-upload-start').on('click', async function () {
			if (fileQueue.length === 0) return;
			if (!await agenticUI.confirm('Upload and train ' + fileQueue.length + ' file(s)? Credits will be deducted.', { confirmText: 'Upload' })) return;

			$('#agentic-upload-queue').hide();
			$('#agentic-upload-progress').show();
			$('#agentic-upload-progress-log').empty();
			$zone.hide();

			var total = fileQueue.length;
			var done = 0;
			var failed = 0;

			function uploadNext() {
				if (fileQueue.length === 0) {
					$('#agentic-upload-progress-text').text('Complete: ' + (done - failed) + ' succeeded, ' + failed + ' failed of ' + total);
					$('#agentic-upload-progress-fill').css('width', '100%');
					loadBalanceBar();
					$zone.show();
					return;
				}

				var f = fileQueue.shift();
				$('#agentic-upload-progress-text').text('Uploading ' + (done + 1) + ' of ' + total + ': ' + f.name);

				var formData = new FormData();
				formData.append('action', 'agentic_td_upload_file');
				formData.append('_ajax_nonce', agenticTrainData.nonce);
				formData.append('file', f);

				$.ajax({
					url:         agenticTrainData.ajaxUrl,
					method:      'POST',
					data:        formData,
					processData: false,
					contentType: false,
					dataType:    'json'
				}).done(function (res) {
					done++;
					if (res.success) {
						logEntry('#agentic-upload-progress-log', '✓ ' + f.name + ' — ' + (res.data.chunks || 0) + ' chunks, ' + (res.data.credits_used || 0) + ' credits', 'success');
					} else {
						failed++;
						logEntry('#agentic-upload-progress-log', '✗ ' + f.name + ': ' + (res.data || 'Unknown error'), 'error');
					}
					$('#agentic-upload-progress-fill').css('width', Math.round((done / total) * 100) + '%');
					uploadNext();
				}).fail(function () {
					done++;
					failed++;
					logEntry('#agentic-upload-progress-log', '✗ ' + f.name + ': Upload failed', 'error');
					$('#agentic-upload-progress-fill').css('width', Math.round((done / total) * 100) + '%');
					uploadNext();
				});
			}

			uploadNext();
		});
	}

	// ═════════════════════════════════════════════════════════════════════════
	// TAB: Training Sources
	// ═════════════════════════════════════════════════════════════════════════
	function initSourcesTab() {
		loadSources();
		$('#agentic-sources-refresh').on('click', loadSources);

		function loadSources() {
			$('#agentic-sources-loading').show();
			$('#agentic-sources-table, #agentic-sources-empty').hide();

			ajax('agentic_td_get_sources').done(function (res) {
				$('#agentic-sources-loading').hide();
				if (!res.success || !res.data.sources || res.data.sources.length === 0) {
					$('#agentic-sources-empty').show();
					return;
				}

				var $tbody = $('#agentic-sources-tbody').empty();
				res.data.sources.forEach(function (src) {
					var statusClass = 'ready';
					var statusText = 'Ready';
					$tbody.append(
						'<tr>' +
						'<td>' + $('<span>').text(src.source || src.source_name || '—').html() + '</td>' +
						'<td>' + $('<span>').text(src.source_type || 'unknown').html() + '</td>' +
						'<td>' + formatNumber(src.chunk_count || src.chunks || 0) + '</td>' +
						'<td>' + formatNumber(src.total_tokens || src.tokens || 0) + '</td>' +
						'<td><span class="agentic-td-status-badge ' + statusClass + '">' + statusText + '</span></td>' +
						'<td>' + formatDate(src.trained_at || src.created_at) + '</td>' +
						'<td>' +
						'<button type="button" class="button button-small agentic-source-delete" ' +
						'data-source="' + $('<span>').text(src.source || src.source_name).html() + '">' +
						'Delete</button>' +
						'</td>' +
						'</tr>'
					);
				});
				$('#agentic-sources-table').show();
			}).fail(function () {
				$('#agentic-sources-loading').hide();
				$('#agentic-sources-empty').show();
			});
		}

		$(document).on('click', '.agentic-source-delete', async function () {
			var source = $(this).data('source');
			if (!await agenticUI.confirm('Delete source "' + source + '"? This will permanently remove all its training data from the Vector Store.', { danger: true, confirmText: 'Delete' })) return;

			var $btn = $(this).prop('disabled', true).text('Deleting…');

			ajax('agentic_td_delete_source', { source: source }).done(function (res) {
				if (res.success) {
					loadSources();
					loadBalanceBar();
				} else {
					agenticUI.toast(res.data || 'Failed to delete source.', 'error');
					$btn.prop('disabled', false).text('Delete');
				}
			}).fail(function () {
				agenticUI.toast('Request failed.', 'error');
				$btn.prop('disabled', false).text('Delete');
			});
		});
	}

	// ═════════════════════════════════════════════════════════════════════════
	// TAB: Credits & Usage
	// ═════════════════════════════════════════════════════════════════════════
	function initUsageTab() {
		loadCredits();
		loadPricing();
		loadTransactions(0);

		var offset = 0;
		$('#agentic-transactions-load-more').on('click', function () {
			offset += 20;
			loadTransactions(offset, true);
		});
	}

	function loadCredits() {
		ajax('agentic_td_get_credits').done(function (res) {
			if (!res.success) return;
			var d = res.data;
			$('#agentic-usage-balance').text(formatNumber(d.balance));
			if (d.credit_value) {
				$('#agentic-usage-balance-usd').text('≈ $' + (d.balance * d.credit_value).toFixed(2));
				$('#agentic-usage-rate').text('$' + Number(d.credit_value).toFixed(4));
			}
			$('#agentic-usage-purchased').text(formatNumber(d.lifetime_purchased));
			$('#agentic-usage-spent').text(formatNumber(d.lifetime_used));
			if (d.services) {
				$('#agentic-usage-rag').text(formatNumber(d.services.rag));
				$('#agentic-usage-imagegen').text(formatNumber(d.services.imagegen));
				$('#agentic-usage-tts').text(formatNumber(d.services.tts));
			}
		});
	}

	function loadPricing() {
		ajax('agentic_td_get_pricing').done(function (res) {
			if (!res.success) return;
			var $tbody = $('#agentic-pricing-tbody').empty();
			var p = res.data;
			var ops = p.operations || {};

			// RAG operations
			$tbody.append('<tr><td>Text Embedding (Training)</td><td>' + formatNumber(ops.embed ? ops.embed.cost_credits : (p.embed_cost || 1)) + ' credit(s)</td><td>' + (ops.embed ? ops.embed.unit : 'per 1,000 tokens') + '</td></tr>');
			$tbody.append('<tr><td>Semantic Query</td><td>' + formatNumber(ops.query ? ops.query.cost_credits : (p.query_cost || 1)) + ' credit(s)</td><td>' + (ops.query ? ops.query.unit : 'per query') + '</td></tr>');

			// TTS operations
			if (ops.tts_standard) {
				$tbody.append('<tr><td>' + ops.tts_standard.description + '</td><td>' + ops.tts_standard.cost_credits + ' credit(s)</td><td>' + ops.tts_standard.unit + '</td></tr>');
			}
			if (ops.tts_neural2) {
				$tbody.append('<tr><td>' + ops.tts_neural2.description + '</td><td>' + ops.tts_neural2.cost_credits + ' credit(s)</td><td>' + ops.tts_neural2.unit + '</td></tr>');
			}
			if (ops.tts_journey) {
				$tbody.append('<tr><td>' + ops.tts_journey.description + '</td><td>' + ops.tts_journey.cost_credits + ' credit(s)</td><td>' + ops.tts_journey.unit + '</td></tr>');
			}

			// Image generation operations
			if (ops.imagegen_fast) {
				$tbody.append('<tr><td>' + ops.imagegen_fast.description + '</td><td>' + ops.imagegen_fast.cost_credits + ' credit(s)</td><td>' + ops.imagegen_fast.unit + '</td></tr>');
			}
			if (ops.imagegen_standard) {
				$tbody.append('<tr><td>' + ops.imagegen_standard.description + '</td><td>' + ops.imagegen_standard.cost_credits + ' credit(s)</td><td>' + ops.imagegen_standard.unit + '</td></tr>');
			}
			if (ops.imagegen_ultra) {
				$tbody.append('<tr><td>' + ops.imagegen_ultra.description + '</td><td>' + ops.imagegen_ultra.cost_credits + ' credit(s)</td><td>' + ops.imagegen_ultra.unit + '</td></tr>');
			}

			if (p.credit_value) {
				$tbody.append('<tr><td>Credit Value</td><td>$' + Number(p.credit_value).toFixed(4) + '</td><td>per credit</td></tr>');
			}
		});
	}

	function loadTransactions(offset, append) {
		if (!append) {
			$('#agentic-transactions-loading').show();
			$('#agentic-transactions-table, #agentic-transactions-empty').hide();
		}

		ajax('agentic_td_get_transactions', { offset: offset, limit: 20 }).done(function (res) {
			$('#agentic-transactions-loading').hide();
			if (!res.success || !res.data.transactions || res.data.transactions.length === 0) {
				if (!append) {
					$('#agentic-transactions-empty').show();
				}
				$('#agentic-transactions-load-more').hide();
				return;
			}

			var $tbody = append ? $('#agentic-transactions-tbody') : $('#agentic-transactions-tbody').empty();
			res.data.transactions.forEach(function (tx) {
				var amountClass = tx.amount > 0 ? 'style="color:#00a32a;"' : 'style="color:#d63638;"';
				var prefix = tx.amount > 0 ? '+' : '';
				$tbody.append(
					'<tr>' +
					'<td>' + formatDate(tx.created_at || tx.date) + '</td>' +
					'<td>' + $('<span>').text(tx.type || tx.transaction_type || '—').html() + '</td>' +
					'<td ' + amountClass + '>' + prefix + formatNumber(tx.amount) + '</td>' +
					'<td>' + formatNumber(tx.balance_after) + '</td>' +
					'<td>' + $('<span>').text(tx.description || '—').html() + '</td>' +
					'</tr>'
				);
			});

			$('#agentic-transactions-table').show();
			$('#agentic-transactions-load-more').toggle(res.data.transactions.length >= 20);
		}).fail(function () {
			$('#agentic-transactions-loading').hide();
			if (!append) {
				$('#agentic-transactions-empty').show();
			}
		});
	}

	// ═════════════════════════════════════════════════════════════════════════
	// Init — run on document ready
	// ═════════════════════════════════════════════════════════════════════════
	$(function () {
		loadBalanceBar();

		var tab = agenticTrainData.activeTab || 'scan';
		if (tab === 'scan')    initScanTab();
		if (tab === 'upload')  initUploadTab();
		if (tab === 'sources') initSourcesTab();
		if (tab === 'usage')   initUsageTab();
	});

})(jQuery);
