(function () {
	'use strict';

	var root = document.querySelector('.setceb-perfil');
	if (!root) {
		return;
	}

	function qsa(selector, scope) {
		return Array.prototype.slice.call((scope || root).querySelectorAll(selector));
	}

	/* ============================================================
	 * Abas / atalhos
	 * ============================================================ */
	var tabs = qsa('[role="tab"][data-panel]');
	var panels = {};
	qsa('.assoc-panel').forEach(function (panel) {
		panels[panel.id.replace('panel-', '')] = panel;
	});
	var panelsBox = root.querySelector('.assoc-panels');
	var navDetails = root.querySelector('.assoc-nav__details');

	/* Menu de modulos recolhido em telas pequenas (o resumo abre a lista). */
	if (navDetails && window.matchMedia && window.matchMedia('(max-width: 1024px)').matches) {
		navDetails.removeAttribute('open');
	}

	function activatePanel(key, options) {
		options = options || {};

		if (!panels[key]) {
			return false;
		}

		tabs.forEach(function (tab) {
			var isActive = tab.getAttribute('data-panel') === key;
			tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
			tab.tabIndex = isActive ? 0 : -1;
		});

		Object.keys(panels).forEach(function (name) {
			panels[name].classList.toggle('is-active', name === key);
		});

		if (options.scroll && panelsBox && typeof panelsBox.scrollIntoView === 'function') {
			panelsBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		if (!options.silent && window.history && window.history.replaceState) {
			window.history.replaceState(null, '', '#' + key);
		}

		return true;
	}

	tabs.forEach(function (tab, index) {
		tab.addEventListener('click', function () {
			activatePanel(tab.getAttribute('data-panel'));

			/* No mobile o menu volta a ficar recolhido apos a escolha. */
			if (navDetails && window.matchMedia('(max-width: 1024px)').matches) {
				navDetails.removeAttribute('open');
			}
		});

		tab.addEventListener('keydown', function (event) {
			var nextIndex = null;

			switch (event.key) {
				case 'ArrowRight':
				case 'ArrowDown':
					nextIndex = (index + 1) % tabs.length;
					break;
				case 'ArrowLeft':
				case 'ArrowUp':
					nextIndex = (index - 1 + tabs.length) % tabs.length;
					break;
				case 'Home':
					nextIndex = 0;
					break;
				case 'End':
					nextIndex = tabs.length - 1;
					break;
			}

			if (nextIndex !== null) {
				event.preventDefault();
				var nextTab = tabs[nextIndex];
				nextTab.focus();
				activatePanel(nextTab.getAttribute('data-panel'));
			}
		});
	});

	/* Botoes que abrem um painel especifico (ex.: Emissao de Boletos) */
	qsa('[data-open-panel]').forEach(function (button) {
		button.addEventListener('click', function () {
			activatePanel(button.getAttribute('data-open-panel'), { scroll: true });
		});
	});

	/* Deep-link via hash (#financeiro, #fale-conosco...) */
	var initialHash = window.location.hash.replace('#', '').replace(/^panel-/, '');

	if (initialHash) {
		activatePanel(initialHash, { silent: true, scroll: true });
	}

	/* Se a pagina carregou com aviso de formulario (GET), rola ate o painel. */
	var noticePanel = document.querySelector('.assoc-panel.is-active');
	if (window.location.search.indexOf('assoc_status=') !== -1 && noticePanel) {
		window.setTimeout(function () {
			noticePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}, 60);
	}

	/* ============================================================
	 * Filtros por categoria (painel de planilhas) + paginacao
	 * ============================================================ */
	var currentYear = (window.SETCEB_ASSOC && window.SETCEB_ASSOC.anoAtual) ? String(window.SETCEB_ASSOC.anoAtual) : null;
	var planilhasPanel = root.querySelector('#panel-planilhas');
	var catSelect = planilhasPanel ? planilhasPanel.querySelector('[data-categoria-select]') : null;
	var currentCategory = (catSelect && catSelect.options && catSelect.options[catSelect.selectedIndex]) ? String(catSelect.options[catSelect.selectedIndex].value) : '';
	var planilhasList = planilhasPanel ? planilhasPanel.querySelector('[data-planilhas-list]') : null;
	var planilhasPage = planilhasPanel ? planilhasPanel.querySelector('[data-planilhas-pagination]') : null;
	var perPage = planilhasList ? parseInt(planilhasList.getAttribute('data-per-page') || '20', 10) || 20 : 20;
	var currentPage = 1;

	function refreshEmptyPanel(panelSel, emptySel, textSel, baseMsg, includeCategory) {
		var panel = root.querySelector(panelSel);
		var emptyBox = panel ? panel.querySelector(emptySel) : null;

		if (!panel || !emptyBox) {
			return;
		}

		var docs = qsa('.assoc-list__item', panel);

		if (!docs.length) {
			return; /* Sem conteudo: estado vazio estatico do servidor. */
		}

		var visible = docs.some(function (doc) {
			return !doc.hidden;
		});

		emptyBox.hidden = visible;

		if (!visible) {
			var textEl = panel.querySelector(textSel);

			if (textEl) {
				var bits = [];
				var activeCat = currentCategory ? (catSelect ? catSelect.options[catSelect.selectedIndex] : null) : null;

				if (includeCategory && activeCat && activeCat.value) {
					bits.push('para a categoria "' + activeCat.textContent.trim() + '"');
				}
				if (currentYear) {
					bits.push('em ' + currentYear);
				}

				textEl.textContent = baseMsg + (bits.length ? ' ' + bits.join(' ') : '') + '.';
			}
		}
	}

	function applyDocFilters() {
		qsa('.assoc-list__item').forEach(function (doc) {
			var show = true;
			var docCat = doc.getAttribute('data-categoria');
			var docYear = doc.getAttribute('data-ano');

			if (docCat && currentCategory && docCat !== currentCategory) {
				show = false;
			}

			/* O ano filtra todos os documentos (planilhas, relatorios etc.);
			   itens sem ano (data-ano vazio) nao sofrem filtro. */
			if (docYear && currentYear && String(docYear) !== String(currentYear)) {
				show = false;
			}
			doc.hidden = !show;
		});

		/* Categorias e ano filtram todos os documentos. */
		refreshEmptyPanel('#panel-planilhas', '[data-planilhas-empty]', '[data-planilhas-empty-text]', 'Nenhuma planilha disponível', true);
		refreshEmptyPanel('#panel-relatorios', '[data-relatorios-empty]', '[data-empty-text]', 'Nenhum relatório disponível', false);

		/* Convencoes, outros materiais e simuladores tambem tem ano: o
		   filtro de ano selecionado aqui esconde os itens, e sem um
		   estado vazio o usuario veria uma lista em branco sem nenhum
		   aviso de que aquilo era o filtro. */
		refreshEmptyPanel('#panel-convencoes', '[data-convencoes-empty]', '[data-convencoes-empty-text]', 'Nenhuma convenção coletiva', false);
		refreshEmptyPanel('#panel-outros-materiais', '[data-outros-materiais-empty]', '[data-outros-materiais-empty-text]', 'Nenhum material', false);
		refreshEmptyPanel('#panel-simuladores', '[data-simuladores-empty]', '[data-simuladores-empty-text]', 'Nenhum simulador', false);

		/* Paginacao recomputada do zero aqui, para que qualquer
		   filtro/ano/categoria mantenha a paginacao correto. */
		paginatePlanilhas();
	}

	/* Itens de planilhas que passam nos filtros ativos (categoria + ano). */
	function planilhasFilteredItens() {
		return qsa('.assoc-list__item', planilhasList).filter(function (item) {
			var docCat = item.getAttribute('data-categoria');
			var docYear = item.getAttribute('data-ano');

			if (docCat && currentCategory && docCat !== currentCategory) {
				return false;
			}

			if (docYear && currentYear && String(docYear) !== String(currentYear)) {
				return false;
			}

			return true;
		});
	}

	/* --- Paginacao do painel de planilhas (10 por pagina) --- */
	function paginatePlanilhas() {
		if (!planilhasList || !planilhasPage) {
			return;
		}

		/* Candidatos = itens que passam nos filtros (categoria + ano),
		   independentemente do estado hidden atual (que mistura filtro +
		   corte de pagina). Assim a paginacao e recomputada do zero. */
		var itens = planilhasFilteredItens();

		var totalPaginas = Math.max(1, Math.ceil(itens.length / perPage));

		if (currentPage > totalPaginas) {
			currentPage = totalPaginas;
		}
		if (currentPage < 1) {
			currentPage = 1;
		}

		itens.forEach(function (item, index) {
			var pos = index + 1;
			var inPage = pos > (currentPage - 1) * perPage && pos <= currentPage * perPage;
			item.hidden = !inPage;
		});

		renderPlanilhasPagination(itens.length, totalPaginas);
	}

	function renderPlanilhasPagination(totalItens, totalPaginas) {
		if (!planilhasPage) {
			return;
		}

		if (totalPaginas <= 1) {
			planilhasPage.hidden = true;
			planilhasPage.innerHTML = '';
			return;
		}

		planilhasPage.hidden = false;

		var frag = document.createDocumentFragment();
		var mkBtn = function (type, page, label, disabled) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'assoc-pagination__btn';
			b.textContent = label;
			b.setAttribute('data-page', page);
			b.setAttribute('data-type', type);
			if (disabled) {
				b.disabled = true;
			}
			if (type === 'prev') {
				b.setAttribute('aria-label', 'Página anterior');
				if (currentPage <= 1) {
					b.disabled = true;
				}
			} else if (type === 'next') {
				b.setAttribute('aria-label', 'Próxima página');
				if (currentPage >= totalPaginas) {
					b.disabled = true;
				}
			} else if (type === 'page' && page === currentPage) {
				b.classList.add('is-current');
				b.setAttribute('aria-current', 'page');
			}
			return b;
		};

		frag.appendChild(mkBtn('prev', Math.max(1, currentPage - 1), '‹'));

		var pages = [];
		for (var i = 1; i <= totalPaginas; i++) {
			if (i === 1 || i === totalPaginas || Math.abs(i - currentPage) <= 1) {
				pages.push(i);
			} else if (pages[pages.length - 1] !== '…') {
				pages.push('…');
			}
		}

		pages.forEach(function (pg) {
			if (pg === '…') {
				var dot = document.createElement('span');
				dot.className = 'assoc-pagination__dots';
				dot.textContent = '…';
				frag.appendChild(dot);
				return;
			}
			frag.appendChild(mkBtn('page', pg, String(pg)));
		});

		frag.appendChild(mkBtn('next', Math.min(totalPaginas, currentPage + 1), '›'));

		planilhasPage.innerHTML = '';
		planilhasPage.appendChild(frag);
	}

	function onPlanilhasNavegacao(event) {
		var btn = event.target.closest ? event.target.closest('button[data-page]') : null;
		var type;
		var page;

		if (!btn) {
			return;
		}

		type = btn.getAttribute('data-type');
		page = parseInt(btn.getAttribute('data-page'), 10);

		if (type === 'prev') {
			currentPage = Math.max(1, currentPage - 1);
		} else if (type === 'next') {
			currentPage = Math.min(Math.max(1, Math.ceil(planilhasFilteredItens().length / perPage)), currentPage + 1);
		} else if (type === 'page' && !isNaN(page)) {
			currentPage = page;
		}

		paginatePlanilhas();
	}

	if (catSelect) {
		catSelect.addEventListener('change', function () {
			currentCategory = catSelect.value || '';
			currentPage = 1;
			applyDocFilters();
		});
	}

	if (planilhasPage) {
		planilhasPage.addEventListener('click', onPlanilhasNavegacao);
	}

	/* Aplica filtro + paginacao inicial (mantem o grid da pagina atual). */
	if (planilhasList) {
		applyDocFilters();
	}

	if (planilhasPage) {
		window.addEventListener('hashchange', function () {
			window.setTimeout(paginatePlanilhas, 0);
		});
	}

	/* ============================================================
	 * Juridico: lista de solicitacoes + painel da conversa
	 * ============================================================ */

	/* replaceState lanca excecao em documento de origem nula (file://),
	   iframe com sandbox ou URL de outra origem. Sem isso o clique morria
	   no meio e o browser seguia o link, recarregando a pagina inteira
	   e jogando o associado de volta para o topo do modulo. */
	function trocarUrl( url ) {
		if ( ! url || ! window.history || ! window.history.replaceState ) {
			return false;
		}

		try {
			window.history.replaceState( null, '', url );
			return true;
		} catch ( e ) {
			return false;
		}
	}

	function paneExiste( id ) {
		return solPanes.some( function ( pane ) {
			return pane.getAttribute( 'data-sol-pane' ) === String( id );
		} );
	}
	var solLinks = qsa('[data-sol-link]');
	var solPanes = qsa('[data-sol-pane]');
	var solNovaLinks = qsa('[data-sol-nova]');
	var solBox = root.querySelector('.assoc-sol');

	function activateSolicitacao(id, options) {
		options = options || {};

		var alvo = String(id);
		var existe = solPanes.some(function (pane) {
			return pane.getAttribute('data-sol-pane') === alvo;
		});

		if (!existe) {
			return false;
		}

		solLinks.forEach(function (link) {
			var ativo = link.getAttribute('data-sol-link') === alvo;
			link.classList.toggle('is-active', ativo);
			link.setAttribute('aria-current', ativo ? 'true' : 'false');
		});

		solNovaLinks.forEach(function (link) {
			var novaAtiva = 'nova' === alvo;
			link.classList.toggle('is-active', novaAtiva);
			link.setAttribute('aria-current', novaAtiva ? 'true' : 'false');
		});

		solPanes.forEach(function (pane) {
			pane.hidden = pane.getAttribute('data-sol-pane') !== alvo;
		});

		/* No mobile a lista fica escondida enquanto a conversa esta aberta
		   (modo detalhe). Escolher uma solicitacao fecha o modo lista. */
		if (solBox) {
			solBox.classList.remove('is-list');
		}

		if ( options.url ) {
			trocarUrl( options.url );
		}

		/* Leva a conversa para a area visivel quando ela nao cabe na tela.

		   A condicao e medida, nao por breakpoint: o grid do juridico nao
		   fixa mais colunas, entao o empilhamento (lista acima da conversa)
		   acontece no desktop tambem. Quando a conversa ja esta a vista -
	   caso do modo detalhe do mobile, que ocupa a tela inteira - nao ha
		   o que rolar e um salto ali seria apenas ruido. */
		if ( options.scroll ) {
			var paneAlvo = root.querySelector('[data-sol-pane="' + alvo + '"]');

			if ( paneAlvo && typeof paneAlvo.getBoundingClientRect === 'function' ) {
				var caixa = paneAlvo.getBoundingClientRect();
				var margem = 80;
				var precisaRolar = caixa.top > margem || caixa.bottom > window.innerHeight + margem;

				if ( precisaRolar && typeof paneAlvo.scrollIntoView === 'function' ) {
					paneAlvo.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			}
		}

		return true;
	}

	solLinks.forEach(function (link) {
		link.addEventListener('click', function (event) {
			var id = link.getAttribute('data-sol-link');

			/* Sem JS funcional o proprio link (?sol_id=N#juridico) abre a
			   solicitacao. Com JS, o cancelamento vem ANTES de tudo para
			   que nenhuma excecao deixe o browser navegar. */
			if (!paneExiste(id)) {
				return;
			}

			event.preventDefault();

			activateSolicitacao(id, {
				url: link.getAttribute('href'),
				scroll: true
			});
		});
	});

	solNovaLinks.forEach(function (link) {
		link.addEventListener('click', function (event) {
			if (!paneExiste('nova')) {
				return;
			}

			event.preventDefault();

			activateSolicitacao('nova', {
				url: link.getAttribute('href'),
				scroll: true
			});
		});
	});

	/* Voltar para as solicitacoes: so aparece no mobile, onde a lista fica
	   oculta enquanto a conversa esta aberta. O href e real (?sol_lista=1),
	   entao sem JS o botao funciona por reload da pagina. */
	qsa('[data-sol-voltar]').forEach(function (link) {
		link.addEventListener('click', function (event) {
			if (!solBox) {
				return;
			}

			event.preventDefault();

			solBox.classList.add('is-list');
			trocarUrl(link.getAttribute('href'));

			var side = solBox.querySelector('.assoc-sol__side');

			if (side && typeof side.scrollIntoView === 'function') {
				side.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	});

	/* ============================================================
	 * Seletor de ano (listbox acessivel)
	 * ============================================================ */
	var yearWidgets = [];
	qsa('.assoc-year').forEach(function (wrap) {
		var yb = wrap.querySelector('.assoc-year__btn');
		var yl = wrap.querySelector('.assoc-year__list');
		var yc = wrap.querySelector('.assoc-year__current');
		var yo = yl ? qsa('[role="option"]', yl) : [];
		yearWidgets.push({ wrap: wrap, btn: yb, list: yl, current: yc, options: yo });
	});

	function closeYearWrap(dw) {
		if (!dw || !dw.list || !dw.btn) {
			return;
		}
		dw.list.hidden = true;
		dw.btn.setAttribute('aria-expanded', 'false');
	}

	function closeAllYears() {
		yearWidgets.forEach(closeYearWrap);
	}

	function syncYearSelection() {
		yearWidgets.forEach(function (dw) {
			if (dw.current) {
				dw.current.textContent = currentYear || '';
			}
			if (dw.options) {
				dw.options.forEach(function (opt) {
					opt.setAttribute('aria-selected', 'false');
					opt.classList.remove('is-selected');
					if (opt.getAttribute('data-ano') === String(currentYear)) {
						opt.setAttribute('aria-selected', 'true');
						opt.classList.add('is-selected');
					}
				});
			}
		});
	}

	function selectYearOption(option, dw) {
		if (!option) {
			return;
		}
		currentYear = option.getAttribute('data-ano');
		currentPage = 1;
		closeYearWrap(dw);
		syncYearSelection();
		if (dw && dw.btn) {
			dw.btn.focus();
		}
		applyDocFilters();
	}

	yearWidgets.forEach(function (dw) {
		if (!dw.btn || !dw.list) {
			return;
		}
		dw.btn.addEventListener('click', function () {
			var willOpen = dw.list.hidden;
			closeAllYears();
			dw.list.hidden = !willOpen;
			dw.btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			if (willOpen) {
				var sel = (dw.options || []).filter(function (o) {
					return o.getAttribute('aria-selected') === 'true';
				})[0];
				(sel || dw.options[0] || dw.list).focus();
			}
		});
		dw.list.addEventListener('keydown', function (event) {
			var index = (dw.options || []).indexOf(document.activeElement);
			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault();
					((dw.options[index + 1]) || dw.options[0]).focus();
					break;
				case 'ArrowUp':
					event.preventDefault();
					((dw.options[index - 1]) || dw.options[dw.options.length - 1]).focus();
					break;
				case 'Home':
					event.preventDefault();
					dw.options[0].focus();
					break;
				case 'End':
					event.preventDefault();
					dw.options[dw.options.length - 1].focus();
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					selectYearOption(document.activeElement, dw);
					break;
				case 'Escape':
				case 'Tab':
					closeYearWrap(dw);
					if (event.key === 'Escape') {
						dw.btn.focus();
					}
					break;
			}
		});
		(dw.options || []).forEach(function (option) {
			option.addEventListener('click', function () {
				selectYearOption(option, dw);
			});
		});
	});

	document.addEventListener('click', function (event) {
		var inside = yearWidgets.some(function (dw) {
			return dw.wrap && dw.wrap.contains(event.target);
		});
		if (!inside) {
			closeAllYears();
		}
	});


	/* ============================================================
	 * Formularios: estado de envio (loading)
	 * ============================================================ */
	qsa('form[data-assoc-form]').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			/* Se o navegador impediu o envio por validacao, nao trava
			   o botao: o usuario ainda pode corrigir e tentar de novo. */
			if (event.defaultPrevented) {
				return;
			}

			var button = form.querySelector('[type="submit"]');
			var label = form.querySelector('.assoc-btn__label');

			if (button && label && !button.disabled) {
				label.setAttribute('data-original', label.textContent);
				label.textContent = 'Enviando…';
				button.disabled = true;
				button.classList.add('is-loading');
			}
		});
	});
})();
