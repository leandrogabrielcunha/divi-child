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

		/* Sobe o modal para o fim do <body>. No site ele é impresso dentro de
		   um módulo do Divi (.et_pb_code_inner > column > row > section), e
		   qualquer ancestrais com position + z-index cria um contexto de
		   empilhamento: o overlay fica preso naquele contexto e passa por
		   baixo do header sticky, do botão do WhatsApp e do banner de
		   cookies. No body, ele compete direto no contexto da raiz. */
		var root = modal.parentNode;

		/* O guard precisa dos dois lados: root !== body evita tentar
		   body.appendChild(body), que lança HierarchyRequestError e mata o
		   init inteiro (o modal parava de funcionar). */
		if (root && root !== document.body && root.parentNode !== document.body) {
			document.body.appendChild(root);
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

			/* Troca a classe de armazenamento pela do modal: o clone precisa
			 * ficar visivel e manter o espacamento entre as secoes. */
			clone.className = 'cetech-planos__modal-plan';
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

		/* "Contratar" com destino WhatsApp: abre o chat flutuante desta
		   própria página em vez de levar o cliente para fora do site. O
		   botão vem como <button> (não <a href>), então não há navegação a
		   evitar. Escuta no document porque o botão vive dentro do modal, que
		   é movido para o fim do <body>. */
		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('[data-cetech-planos-cta="whatsapp"]');

			if (!trigger) {
				return;
			}

			event.preventDefault();

			var planId = trigger.getAttribute('data-cetech-planos-plano');
			var api = window.cetechWhatsappApi;

			/* O modal do plano tem z-index 1000000 e o painel do WhatsApp
			   99999: com os dois abertos, o modal cobriria o chat. */
			if (!modal.hidden) {
				close();
			}

			if (api && typeof api.openWithPlan === 'function') {
				api.openWithPlan(planId);
			} else if (api && typeof api.open === 'function') {
				api.open();
			}
			/* Sem o widget de WhatsApp na página nao ha o que abrir. O PHP
			   ja cai para o link nesse caso, entao este <button> so aparece
			   com o widget disponivel. */
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();