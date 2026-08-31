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
	var currentCategory = '';
	var planilhasPanel = root.querySelector('#panel-planilhas');
	var catSelect = planilhasPanel ? planilhasPanel.querySelector('[data-categoria-select]') : null;
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
			var isPlanilha = doc.closest('[data-planilhas-list]') !== null;
			var docCat = doc.getAttribute('data-categoria');
			var docYear = doc.getAttribute('data-ano');

			if (docCat && currentCategory && docCat !== currentCategory) {
				show = false;
			}

			/* Planilhas paginam por categoria apenas (sem filtro de ano), para
				a paginacao aparecer sempre. O filtro de ano (currentYear) se
				aplica aos demais documentos (relatorios, convencoes etc.). */
			if (!isPlanilha && docYear && currentYear && String(docYear) !== String(currentYear)) {
				show = false;
			}
			doc.hidden = !show;
		});

		/* Categorias filtram planilhas; ano filtra planilhas e relatorios. */
		refreshEmptyPanel('#panel-planilhas', '[data-planilhas-empty]', '[data-planilhas-empty-text]', 'Nenhuma planilha disponível', true);
		refreshEmptyPanel('#panel-relatorios', '[data-relatorios-empty]', '[data-empty-text]', 'Nenhum relatório disponível', false);

		paginatePlanilhas();
	}

	/* --- Paginacao do painel de planilhas (20 por pagina) --- */
	function paginatePlanilhas() {
		if (!planilhasList || !planilhasPage) {
			return;
		}

		var itens = qsa('.assoc-list__item', planilhasList).filter(function (item) {
			return !item.hidden;
		});

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
			currentPage = Math.min(Math.ceil(qsa('.assoc-list__item', planilhasList).filter(function (i) { return !i.hidden; }).length / perPage), currentPage + 1);
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
			paginatePlanilhas();
		});
	}

	if (planilhasPage) {
		planilhasPage.addEventListener('click', onPlanilhasNavegacao);
	}

	/* Aplica filtro + paginacao inicial (mantem o grid da pagina atual). */
	if (planilhasList) {
		applyDocFilters();
		paginatePlanilhas();
	}

	if (planilhasPage) {
		window.addEventListener('hashchange', function () {
			window.setTimeout(paginatePlanilhas, 0);
		});
	}

	/* ============================================================
	 * Seletor de ano (listbox acessivel)
	 * ============================================================ */
	var yearWrap = root.querySelector('.assoc-year');
	var yearBtn = yearWrap ? yearWrap.querySelector('.assoc-year__btn') : null;
	var yearList = yearWrap ? yearWrap.querySelector('.assoc-year__list') : null;
	var yearCurrent = yearWrap ? yearWrap.querySelector('.assoc-year__current') : null;
	var yearOptions = yearList ? qsa('[role="option"]', yearList) : [];

	function closeYear() {
		if (!yearList || !yearBtn) {
			return;
		}
		yearList.hidden = true;
		yearBtn.setAttribute('aria-expanded', 'false');
	}

	function selectYear(option) {
		if (!option) {
			return;
		}

		yearOptions.forEach(function (other) {
			other.setAttribute('aria-selected', other === option ? 'true' : 'false');
			other.classList.toggle('is-selected', other === option);
		});

		if (yearCurrent) {
			yearCurrent.textContent = option.textContent.trim();
		}

		currentYear = option.getAttribute('data-ano');
		currentPage = 1;
		closeYear();
		yearBtn.focus();
		applyDocFilters();
	}

	if (yearBtn && yearList) {
		yearBtn.addEventListener('click', function () {
			var willOpen = yearList.hidden;
			yearList.hidden = !willOpen;
			yearBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

			if (willOpen) {
				var selected = yearOptions.filter(function (option) {
					return option.getAttribute('aria-selected') === 'true';
				})[0];
				(selected || yearOptions[0]).focus();
			}
		});

		yearList.addEventListener('keydown', function (event) {
			var index = yearOptions.indexOf(document.activeElement);

			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault();
					(yearOptions[index + 1] || yearOptions[0]).focus();
					break;
				case 'ArrowUp':
					event.preventDefault();
					(yearOptions[index - 1] || yearOptions[yearOptions.length - 1]).focus();
					break;
				case 'Home':
					event.preventDefault();
					yearOptions[0].focus();
					break;
				case 'End':
					event.preventDefault();
					yearOptions[yearOptions.length - 1].focus();
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					selectYear(document.activeElement);
					break;
				case 'Escape':
				case 'Tab':
					closeYear();
					if (event.key === 'Escape') {
						yearBtn.focus();
					}
					break;
			}
		});

		yearOptions.forEach(function (option) {
			option.addEventListener('click', function () {
				selectYear(option);
			});
		});

		document.addEventListener('click', function (event) {
			if (yearWrap && !yearWrap.contains(event.target)) {
				closeYear();
			}
		});
	}

	/* ============================================================
	 * Formularios: estado de envio (loading)
	 * ============================================================ */
	qsa('form[data-assoc-form]').forEach(function (form) {
		form.addEventListener('submit', function () {
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
