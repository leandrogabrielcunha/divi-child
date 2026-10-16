/* ============================================================
 * SETCEB - Mascaras de formulario (telefone e CEP)
 *
 * Componente compartilhado. As masks viviam dentro de contato.js, o
 * que impedia o portal do associado de usa-las: cada pagina enfileira
 * um script proprio e nao ha como levar a funcao junto.
 *
 * Aqui nao ha seletor de raiz: o script age em [data-mask-phone] e
 * [data-mask-cep] do documento inteiro, em qualquer pagina que o
 * enfileire. Para usar, basta marcar o campo:
 *
 *   <input type="tel" data-mask-phone>
 *   <input data-mask-cep>
 *
 * Sem JS o campo continua funcionando: a mascara e so apresentacao,
 * e o valor cru segue valendo no POST.
 * ============================================================ */
(function () {
	'use strict';

	/* Telefone: (00) 0000-0000 com 10 digitos, (00) 00000-0000 com 11.
	   A mascara e progressiva - cresce conforme digita, sem travar o
	   cursor no meio do texto. */
	function maskPhone(el) {
		el.addEventListener('input', function () {
			var v = el.value.replace(/\D/g, '');

			if (v.length > 11) {
				v = v.slice(0, 11);
			}

			if (v.length === 0) {
				el.value = '';
			} else if (v.length <= 2) {
				el.value = '(' + v;
			} else if (v.length <= 6) {
				el.value = '(' + v.slice(0, 2) + ') ' + v.slice(2);
			} else if (v.length === 7) {
				/* 7 digitos e o ponto de virada: sem o hifen, para
				   nao ter de apagar um caractere ao digitar o 8o. */
				el.value = '(' + v.slice(0, 2) + ') ' + v.slice(2);
			} else if (v.length <= 10) {
				el.value = '(' + v.slice(0, 2) + ') ' + v.slice(2, 6) + '-' + v.slice(6);
			} else {
				el.value = '(' + v.slice(0, 2) + ') ' + v.slice(2, 7) + '-' + v.slice(7);
			}
		});

		/* Colar um telefone ja com mascara: recalcula a partir dos
		   digitos, senao os parenteses digitados viram parte do numero. */
		el.addEventListener('paste', function () {
			window.setTimeout(function () {
				el.dispatchEvent(new Event('input', { bubbles: true }));
			}, 0);
		});
	}

	/* CEP: 00000-000. Cinco digitos, e so. */
	function maskCep(el) {
		el.addEventListener('input', function () {
			var v = el.value.replace(/\D/g, '');

			if (v.length > 8) {
				v = v.slice(0, 8);
			}

			el.value = v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v;
		});
	}

	/* Campos que ja nasceram com mascara (recuo do navegador) ou que
	   entram depois deste script rodar passam por aqui. */
	function aplicar() {
		document.querySelectorAll('[data-mask-phone]').forEach(function (el) {
			if (!el.dataset.setcebMaskPhone) {
				el.dataset.setcebMaskPhone = '1';
				maskPhone(el);
			}
		});

		document.querySelectorAll('[data-mask-cep]').forEach(function (el) {
			if (!el.dataset.setcebMaskCep) {
				el.dataset.setcebMaskCep = '1';
				maskCep(el);
			}
		});
	}

	aplicar();

	/* Formularios que chegam depois (shortcode, AJAX) sao tratados por
	   observacao em vez de por evento: um MutationObserver e mais barato
	   de acertar do que lembrar de chamar a funcao em cada lugar. */
	if (window.MutationObserver) {
		new MutationObserver(aplicar).observe(document.documentElement, {
			childList: true,
			subtree: true
		});
	}
})();
