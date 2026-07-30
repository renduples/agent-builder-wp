/**
 * Agentic Modal Widget
 *
 * Handles the floating chat trigger button, agent picker (when multiple
 * agents are enabled), and open/close behaviour for the modal chat panel.
 *
 * The actual chat logic is handled by agentic-chat-frontend.js — this
 * script only manages the modal shell around it.
 *
 * @since 2.8.3
 */
(function () {
	'use strict';

	const trigger = document.getElementById('agentic-modal-trigger');
	const widget  = document.getElementById('agentic-modal-widget');
	const picker  = document.getElementById('agentic-modal-picker');

	if (!trigger || !widget) {
		return;
	}

	const agentCount = parseInt(trigger.dataset.agentCount, 10) || 1;
	let isOpen       = false;
	let pickerOpen   = false;

	/**
	 * Open the chat panel directly (single agent) or show the picker (multi).
	 */
	trigger.addEventListener('click', function () {
		if (isOpen) {
			closeWidget();
			return;
		}

		if (agentCount > 1 && picker) {
			togglePicker();
		} else {
			openWidget();
		}
	});

	/**
	 * Close button inside the chat header.
	 */
	const closeBtn = widget.querySelector('.agentic-modal-close-btn');
	if (closeBtn) {
		closeBtn.addEventListener('click', closeWidget);
	}

	/**
	 * Agent picker: close button.
	 */
	if (picker) {
		const pickerClose = picker.querySelector('.agentic-modal-picker-close');
		if (pickerClose) {
			pickerClose.addEventListener('click', closePicker);
		}

		// Agent picker: item click.
		picker.querySelectorAll('.agentic-modal-picker-item').forEach(function (item) {
			item.addEventListener('click', function () {
				const slug    = this.dataset.agent;
				const name    = this.dataset.name;
				const icon    = this.dataset.icon;
				const welcome = this.dataset.welcome;

				// Update the chat container to point at the selected agent.
				const container = widget.querySelector('.agentic-chat-container');
				if (container) {
					container.dataset.agentId = slug;
				}

				// Update header.
				const avatar  = widget.querySelector('.agentic-agent-avatar');
				const nameEl  = widget.querySelector('#agentic-agent-name');
				if (avatar) avatar.textContent = icon;
				if (nameEl) nameEl.textContent  = name;

				// Update welcome message.
				const msgArea = widget.querySelector('#agentic-messages');
				if (msgArea && welcome) {
					msgArea.innerHTML = '<div class="agentic-message agentic-message-agent">' +
						'<div class="agentic-message-content">' +
						formatWelcome(welcome) +
						'</div></div>';
				}

				closePicker();
				openWidget();
			});
		});
	}

	function openWidget() {
		widget.style.display = 'block';
		isOpen = true;
	}

	function closeWidget() {
		widget.style.display = 'none';
		isOpen = false;
	}

	function togglePicker() {
		if (pickerOpen) {
			closePicker();
		} else {
			picker.style.display = 'block';
			pickerOpen = true;
		}
	}

	function closePicker() {
		if (picker) {
			picker.style.display = 'none';
		}
		pickerOpen = false;
	}

	/**
	 * Basic markdown formatting for welcome messages.
	 */
	function formatWelcome(text) {
		let html = text
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
		html = html.replace(/\n/g, '<br>');

		return html;
	}

	// Close widget on Escape key.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			if (pickerOpen) {
				closePicker();
			} else if (isOpen) {
				closeWidget();
			}
		}
	});

	// Close picker when clicking outside.
	document.addEventListener('click', function (e) {
		if (pickerOpen && picker && !picker.contains(e.target) && !trigger.contains(e.target)) {
			closePicker();
		}
	});
})();
