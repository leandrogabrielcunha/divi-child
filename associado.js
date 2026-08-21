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
	 * Menu lateral de categorias (recollivel no mobile)
	 * ============================================================ */
	var sideToggle = root.querySelector('.assoc-side__toggle');
	var catsNav = root.querySelector('.assoc-cats');

	function isMobileNav() {
		return window.innerWidth < 1024;
	}

	function closeCats() {
		if (!sideToggle || !catsNav) {
			return;
		}
		catsNav.classList.add('is-closed');
		sideToggle.setAttribute('aria-expanded', 'false');
	}

	function openCats() {
		if (!sideToggle || !catsNav) {
			return;
		}
		catsNav.classList.remove('is-closed');
		sideToggle.setAttribute('aria-expanded', 'true');
	}

	if (sideToggle && catsNav) {
		if (isMobileNav()) {
			closeCats();
		}

		sideToggle.addEventListener('click', function () {
			if (catsNav.classList.contains('is-closed')) {
				openCats();
			} else {
				closeCats();
			}
		});

		window.addEventListener('resize', function () {
			if (!isMobileNav()) {
				openCats();
			}
		});
	}

	/* ============================================================
	 * Filtro por categoria (documentos dos paineis)
	 * ============================================================ */
	var currentYear = null;
	var currentCategory = '';
	var catButtons = qsa('.assoc-cats__item');
	var filterChip = root.querySelector('[data-filter]');
	var filterLabel = root.querySelector('[data-filter-label]');
	var filterClear = root.querySelector('[data-filter-clear]');

	function categoryButton(slug) {
		return catButtons.filter(function (btn) {
			return btn.getAttribute('data-categoria') === slug;
		})[0] || null;
	}

	function refreshEmptyPanel(panelSel, emptySel, textSel, baseMsg, includeCategory) {
		var panel = root.querySelector(panelSel);
		var emptyBox = panel ? panel.querySelector(emptySel) : null;

		if (!panel || !emptyBox) {
			return;
		}

		var docs = qsa('.assoc-doc', panel);

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
				var activeBtn = currentCategory ? categoryButton(currentCategory) : null;

				if (includeCategory && activeBtn) {
					bits.push('para a categoria "' + activeBtn.textContent.trim() + '"');
				}
				if (currentYear) {
					bits.push('em ' + currentYear);
				}

				textEl.textContent = baseMsg + (bits.length ? ' ' + bits.join(' ') : '') + '.';
			}
		}
	}

	function applyDocFilters() {
		qsa('.assoc-doc').forEach(function (doc) {
			var show = true;
			var docCat = doc.getAttribute('data-categoria');
			var docYear = doc.getAttribute('data-ano');

			if (docCat && currentCategory && docCat !== currentCategory) {
				show = false;
			}
			if (docYear && currentYear && String(docYear) !== String(currentYear)) {
				show = false;
			}
			doc.hidden = !show;
		});

		/* Categorias filtram planilhas; ano filtra planilhas e relatorios. */
		refreshEmptyPanel('#panel-planilhas', '[data-planilhas-empty]', '[data-planilhas-empty-text]', 'Nenhuma planilha disponível', true);
		refreshEmptyPanel('#panel-relatorios', '[data-relatorios-empty]', '[data-empty-text]', 'Nenhum relatório disponível', false);

		/* Chip do filtro ativo (painel de planilhas) */
		if (filterChip && filterLabel) {
			var btn = currentCategory ? categoryButton(currentCategory) : null;
			filterChip.hidden = !btn;
			if (btn) {
				filterLabel.textContent = btn.textContent.trim();
			}
		}
	}

	catButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			var slug = button.getAttribute('data-categoria');
			var wasActive = button.getAttribute('aria-pressed') === 'true';

			catButtons.forEach(function (other) {
				other.setAttribute('aria-pressed', 'false');
				other.classList.remove('is-active');
			});

			if (wasActive) {
				currentCategory = '';
			} else {
				currentCategory = slug;
				button.setAttribute('aria-pressed', 'true');
				button.classList.add('is-active');
			}

			activatePanel('planilhas', { silent: wasActive });
			closeCats();
			applyDocFilters();
		});
	});

	if (filterClear) {
		filterClear.addEventListener('click', function () {
			currentCategory = '';
			catButtons.forEach(function (other) {
				other.setAttribute('aria-pressed', 'false');
				other.classList.remove('is-active');
			});
			applyDocFilters();
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
