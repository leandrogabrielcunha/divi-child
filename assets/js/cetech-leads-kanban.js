(function () {
	'use strict';

	function init() {
		var board = document.querySelector('[data-cetech-leads-board]');
		var config = window.cetechLeadsBoardConfig || {};
		var notice = document.querySelector('[data-cetech-leads-notice]');

		if (!board) {
			return;
		}

		function showNotice(message, isError) {
			if (!notice) {
				return;
			}

			notice.textContent = message;
			notice.hidden = false;
			notice.classList.toggle('is-error', Boolean(isError));
		}

		function updateCounts() {
			board.querySelectorAll('[data-cetech-leads-column]').forEach(function (column) {
				var count = column.querySelectorAll('[data-cetech-lead-card]').length;
				var countElement = column.querySelector('[data-cetech-leads-count]');
				if (countElement) {
					countElement.textContent = String(count);
				}
			});
		}

		function findCard(leadId) {
			var cards = board.querySelectorAll('[data-cetech-lead-card]');
			for (var i = 0; i < cards.length; i++) {
				if (cards[i].dataset.leadId === String(leadId)) {
					return cards[i];
				}
			}
			return null;
		}

		function moveCard(card, nextStage) {
			var targetColumn = board.querySelector('[data-cetech-leads-column="' + nextStage + '"]');
			var oldColumn = card.parentElement.closest('[data-cetech-leads-column]');
			var oldStage = card.dataset.stage;
			var oldNextSibling = card.nextElementSibling;
			var select = card.querySelector('[data-cetech-lead-stage-select]');

			if (!targetColumn || !oldColumn || oldStage === nextStage || card.classList.contains('is-pending')) {
				if (select) {
					select.value = oldStage;
				}
				return;
			}

			var targetCards = targetColumn.querySelector('[data-cetech-leads-cards]');
			if (!targetCards) {
				return;
			}

			card.classList.add('is-pending');
			card.dataset.stage = nextStage;
			targetCards.appendChild(card);
			if (select) {
				select.value = nextStage;
				select.disabled = true;
			}
			updateCounts();

			var body = new URLSearchParams();
			body.append('action', 'cetech_wa_update_lead_stage');
			body.append('nonce', config.nonce || '');
			body.append('lead_id', card.dataset.leadId);
			body.append('stage', nextStage);

			fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (response) {
					if (!response || !response.success) {
						throw new Error(response && response.data && response.data.message ? response.data.message : config.error);
					}

					showNotice(config.saved || 'Etapa atualizada.', false);
				})
				.catch(function (error) {
					card.dataset.stage = oldStage;
					if (oldNextSibling && oldNextSibling.parentElement === oldColumn.querySelector('[data-cetech-leads-cards]')) {
						oldColumn.querySelector('[data-cetech-leads-cards]').insertBefore(card, oldNextSibling);
					} else {
						oldColumn.querySelector('[data-cetech-leads-cards]').appendChild(card);
					}

					if (select) {
						select.value = oldStage;
					}
					showNotice(error.message || config.error || 'Não foi possível atualizar a etapa.', true);
				})
				.finally(function () {
					card.classList.remove('is-pending');
					if (select) {
						select.disabled = false;
					}
					updateCounts();
				});
		}

		board.addEventListener('change', function (event) {
			var select = event.target.closest('[data-cetech-lead-stage-select]');
			if (!select) {
				return;
			}

			var card = select.closest('[data-cetech-lead-card]');
			if (card) {
				moveCard(card, select.value);
			}
		});

		board.addEventListener('dragstart', function (event) {
			var card = event.target.closest('[data-cetech-lead-card]');
			if (!card || event.target.closest('select, a, button')) {
				event.preventDefault();
				return;
			}

			card.classList.add('is-dragging');
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData('text/plain', card.dataset.leadId);
			}
		});

		board.addEventListener('dragend', function (event) {
			var card = event.target.closest('[data-cetech-lead-card]');
			if (card) {
				card.classList.remove('is-dragging');
			}
			board.querySelectorAll('.is-drop-target').forEach(function (column) {
				column.classList.remove('is-drop-target');
			});
		});

		board.querySelectorAll('[data-cetech-leads-column]').forEach(function (column) {
			column.addEventListener('dragover', function (event) {
				event.preventDefault();
				column.classList.add('is-drop-target');
				if (event.dataTransfer) {
					event.dataTransfer.dropEffect = 'move';
				}
			});

			column.addEventListener('dragleave', function (event) {
				if (!column.contains(event.relatedTarget)) {
					column.classList.remove('is-drop-target');
				}
			});

			column.addEventListener('drop', function (event) {
				event.preventDefault();
				column.classList.remove('is-drop-target');

				var leadId = event.dataTransfer ? event.dataTransfer.getData('text/plain') : '';
				var card = findCard(leadId);
				if (card) {
					moveCard(card, column.dataset.cetechLeadsColumn);
				}
			});
		});

		updateCounts();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
