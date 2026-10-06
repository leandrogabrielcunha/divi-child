(function () {
	'use strict';

	var LISTA = '#setceb-tabela-rep-lista';
	var LINHA = '[data-setceb-tabela-linha]';
	var MODELO = '#setceb-tabela-linha-modelo';

	var CAMPOS = {
		servico: 'setceb-tabela-servico-',
		valor: 'setceb-tabela-valor-',
		valor_associado: 'setceb-tabela-associado-'
	};

	var lista = document.querySelector(LISTA);

	if (!lista) {
		return;
	}

	var arrastando = null;

	function linhas() {
		return Array.prototype.slice.call(lista.querySelectorAll(LINHA));
	}

	function campos(linha) {
		return Array.prototype.slice.call(linha.querySelectorAll('[data-setceb-tabela-campo]'));
	}

	/* Renumera os campos (name/id) conforme a ordem visual das linhas. */
	function renumerar() {
		linhas().forEach(function (linha, indice) {
			campos(linha).forEach(function (input) {
				var campo = input.getAttribute('data-setceb-tabela-campo');
				var id = CAMPOS[campo] + indice;

				input.name = 'setceb_tabela_linhas[' + indice + '][' + campo + ']';
				input.id = id;

				var coluna = input.closest('.setceb-tabela-rep__col');
				var rotulo = coluna ? coluna.querySelector('label') : null;

				if (rotulo) {
					rotulo.setAttribute('for', id);
				}
			});

			var ordem = linha.querySelector('[data-setceb-tabela-ordem]');

			if (ordem) {
				ordem.textContent = String(indice + 1);
			}

			var sobe = linha.querySelector('[data-setceb-tabela-move="-1"]');
			var desce = linha.querySelector('[data-setceb-tabela-move="1"]');

			if (sobe) {
				sobe.disabled = 0 === indice;
			}

			if (desce) {
				desce.disabled = indice === linhas().length - 1;
			}
		});
	}

	/* Cria uma linha nova a partir do modelo e joga o cursor no serviço. */
	function adicionar() {
		var modelo = document.querySelector(MODELO);

		if (!modelo) {
			return;
		}

		// O modelo ja e a div da linha: usa o elemento, nao o innerHTML direto.
		var temp = document.createElement('div');

		temp.innerHTML = modelo.innerHTML.trim();

		var linha = temp.firstElementChild;

		if (!linha) {
			return;
		}

		linha.classList.add('setceb-tabela-rep__linha--nova');

		lista.appendChild(linha);

		renumerar();

		var primeiro = linha.querySelector('[data-setceb-tabela-campo="servico"]');

		if (primeiro) {
			primeiro.focus();
		}
	}

	/* Move a linha uma posicao para cima (-1) ou para baixo (1). */
	function mover(linha, direcao) {
		if (direcao < 0 && linha.previousElementSibling) {
			lista.insertBefore(linha, linha.previousElementSibling);
		} else if (direcao > 0 && linha.nextElementSibling) {
			lista.insertBefore(linha.nextElementSibling, linha);
		} else {
			return;
		}

		renumerar();
	}

	/* Remove a linha; se for a ultima, apenas limpa os campos. */
	function remover(linha) {
		var todas = linhas();

		if (1 === todas.length) {
			campos(linha).forEach(function (input) {
				input.value = '';
			});

			var servico = linha.querySelector('[data-setceb-tabela-campo="servico"]');

			if (servico) {
				servico.focus();
			}

			return;
		}

		var proxima = linha.nextElementSibling || linha.previousElementSibling;

		linha.parentNode.removeChild(linha);

		renumerar();

		if (proxima) {
			var foco = proxima.querySelector('[data-setceb-tabela-campo="servico"]');

			if (foco) {
				foco.focus();
			}
		}
	}

	/* ---------- Cliques (add / remove / mover) ---------- */

	document.addEventListener('click', function (event) {
		var alvo = event.target;

		if (!alvo || !alvo.closest) {
			return;
		}

		var botaoAdd = alvo.closest('[data-setceb-tabela-add]');

		if (botaoAdd) {
			event.preventDefault();
			adicionar();
			return;
		}

		var linha = alvo.closest(LINHA);

		if (!linha) {
			return;
		}

		var botaoMove = alvo.closest('[data-setceb-tabela-move]');

		if (botaoMove) {
			event.preventDefault();
			mover(linha, parseInt(botaoMove.getAttribute('data-setceb-tabela-move'), 10));
			return;
		}

		if (alvo.closest('[data-setceb-tabela-remove]')) {
			event.preventDefault();
			remover(linha);
		}
	});

	/* ---------- Reordenar arrastando (HTML5 drag and drop, sem libs) ---------- */

	document.addEventListener('dragstart', function (event) {
		var grip = event.target.closest ? event.target.closest('[data-setceb-tabela-grip]') : null;

		if (!grip) {
			return;
		}

		arrastando = grip.closest(LINHA);

		if (arrastando && event.dataTransfer) {
			event.dataTransfer.effectAllowed = 'move';

			// Firefox exige dados definidos para iniciar o arrasto.
			event.dataTransfer.setData('text/plain', 'linha');

			arrastando.classList.add('setceb-tabela-rep__linha--arrastando');
		}
	});

	document.addEventListener('dragover', function (event) {
		if (!arrastando) {
			return;
		}

		var alvo = event.target.closest ? event.target.closest(LINHA) : null;

		if (!alvo || alvo === arrastando || !lista.contains(alvo)) {
			return;
		}

		event.preventDefault();

		if (event.dataTransfer) {
			event.dataTransfer.dropEffect = 'move';
		}

		var caixa = alvo.getBoundingClientRect();
		var metade = event.clientY > caixa.top + (caixa.height / 2);

		lista.insertBefore(arrastando, metade ? alvo.nextElementSibling : alvo);
	});

	document.addEventListener('drop', function (event) {
		if (!arrastando) {
			return;
		}

		event.preventDefault();
		renumerar();
	});

	document.addEventListener('dragend', function () {
		if (arrastando) {
			arrastando.classList.remove('setceb-tabela-rep__linha--arrastando');
		}

		arrastando = null;

		renumerar();
	});

	/* Garante indices unicos ao carregar a tela. */
	renumerar();
})();