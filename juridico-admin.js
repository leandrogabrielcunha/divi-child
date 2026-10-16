/**
 * Painel de solicitacoes juridicas (admin).
 *
 * "Ver conversa" expande a linha e carrega o historico sob demanda.
 * Somente leitura: nao ha campo de resposta, o advogado acompanha por
 * aqui e responde na tela de edicao da solicitacao.
 */
(function () {
	'use strict';

	var lista = document.querySelector('.setceb-sol-lista');

	if (!lista) {
		return;
	}

	var nonce = (window.setcebSolAdmin && window.setcebSolAdmin.nonce) || lista.getAttribute('data-nonce') || '';
	var carregados = {};

	function abrir(botao) {
		/* A linha da conversa e IRMA da linha do botao (<tr> separado),
		   entao nao da para buscar com linha.querySelector(). O botao
		   aponta a conversa via aria-controls. */
		var painel = document.getElementById(botao.getAttribute('aria-controls'));

		if (!painel) {
			return;
		}

		var id = painel.getAttribute('data-sol-painel');
		var alvo = painel.querySelector('[data-sol-conversa]');
		var aberto = !painel.hidden;

		if (aberto) {
			painel.hidden = true;
			botao.setAttribute('aria-expanded', 'false');
			botao.textContent = 'Ver conversa';
			return;
		}

		painel.hidden = false;
		botao.setAttribute('aria-expanded', 'true');
		botao.textContent = 'Ocultar conversa';

		if (carregados[id] || !alvo) {
			return;
		}

		alvo.innerHTML = '<p class="setceb-sol-vazio">Carregando conversa…</p>';

		var corpo = new URLSearchParams();
		corpo.append('action', 'setceb_sol_conversa');
		corpo.append('sol_id', id);
		corpo.append('nonce', nonce);

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: corpo.toString()
		})
			.then(function (resposta) {
				return resposta.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data) {
					throw new Error('falha');
				}

				alvo.innerHTML = json.data.html;
				carregados[id] = true;
			})
			.catch(function () {
				alvo.innerHTML = '<p class="setceb-sol-vazio">Nao foi possivel carregar a conversa.</p>';
			});
	}

	lista.addEventListener('click', function (evento) {
		var botao = evento.target.closest('[data-sol-ver]');

		if (!botao) {
			return;
		}

		evento.preventDefault();
		abrir(botao);
	});
})();