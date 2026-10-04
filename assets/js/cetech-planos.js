(function () {
	'use strict';

	var OPEN_CLASS = 'cetech-planos-modal-open';
	var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

	function init() {
		var roots = document.querySelectorAll('.cetech-planos');
		var modal = document.querySelector('[data-cetech-modal]');
		var dialog = modal ? modal.querySelector('[data-cetech-modal-dialog]') : null;
		var content = modal ? modal.querySelector('[data-cetech-modal-content]') : null;

		if (!roots.length || !modal || !dialog || !content) {
			return;
		}

		var lastFocused = null;

		function store(planId) {
			return document.querySelector('.cetech-planos__store-item[data-cetech-plan="' + planId + '"]');
		}

		function hasTextSelection() {
			var selection = window.getSelection();

			return !!selection && selection.toString() !== '';
		}

		function open(planId, trigger) {
			var source = store(planId);

			if (!source) {
				return;
			}

			/* Guarda o elemento que abriu o modal. Nao usa document.activeElement
			 * porque Safari nao move o foco ao clicar em um botao. */
			lastFocused = trigger || document.activeElement;

			var clone = source.cloneNode(true);

			/* Remove a classe de armazenamento: o clone precisa ser visivel. */
			clone.className = '';
			content.innerHTML = '';
			content.appendChild(clone);

			var heading = content.querySelector('.cetech-planos__modal-name');
			if (heading) {
				dialog.setAttribute('aria-label', heading.textContent.trim());
			}

			modal.hidden = false;
			document.documentElement.classList.add(OPEN_CLASS);
			dialog.scrollTop = 0;
			dialog.focus();
		}

		function close() {
			if (modal.hidden) {
				return;
			}

			modal.hidden = true;
			content.innerHTML = '';
			document.documentElement.classList.remove(OPEN_CLASS);

			if (lastFocused && typeof lastFocused.focus === 'function') {
				lastFocused.focus();
			}
		}

		/* Card inteiro e botao "Ver detalhes" abrem o modal. */
		function onCardClick(event) {
			if (event.defaultPrevented) {
				return;
			}

			var target = event.target;

			/* Links e botoes proprios (o CTA de contratar) nao sao interceptados. */
			if (target.closest('a, button:not([data-cetech-modal-open])')) {
				return;
			}

			var card = target.closest('[data-cetech-card]');

			if (!card || hasTextSelection()) {
				return;
			}

			event.preventDefault();
			open(card.getAttribute('data-cetech-card'), target.closest('[data-cetech-modal-open]'));
		}

		Array.prototype.forEach.call(roots, function (root) {
			root.addEventListener('click', onCardClick);
		});

		modal.addEventListener('click', function (event) {
			/* Fechar: overlay, botao X ou teclado. */
			if (event.target.closest('[data-cetech-modal-close]')) {
				event.preventDefault();
				close();
				return;
			}

			/* Contratar a partir do modal: mantem o fluxo atual de contratacao. */
			if (event.target.closest('.cetech-planos__btn--modal')) {
				close();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (modal.hidden) {
				return;
			}

			if (event.key === 'Escape') {
				event.preventDefault();
				close();
				return;
			}

			if (event.key !== 'Tab') {
				return;
			}

			/* Mantem o foco dentro do dialogo enquanto ele estiver aberto. */
			var items = Array.prototype.slice.call(dialog.querySelectorAll(FOCUSABLE)).filter(function (el) {
				return !el.closest('[hidden]');
			});

			if (!items.length) {
				return;
			}

			var first = items[0];
			var last = items[items.length - 1];

			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();