(function () {
	'use strict';

	var header = document.getElementById('setceb-header');
	if (!header) {
		return;
	}

	var toggle = header.querySelector('.setceb-header__burger');
	var nav = header.querySelector('.setceb-header__nav');
	if (!toggle || !nav) {
		return;
	}

	// Barra principal fixa no topo ao rolar + menu oculto (desktop).
	var mainBar = header.querySelector('.setceb-header__main');
	var mainSpacer = header.querySelector('[data-setceb-main-spacer]');
	var topStrip = header.querySelector('.setceb-header__top');
	var stripH = topStrip ? topStrip.offsetHeight : 0;
	var stickyAt = 0;
	var isSticky = false;
	var raf = null;

	function updateStickyAt() {
		var rect = header.getBoundingClientRect();
		stickyAt = rect.top + window.scrollY + stripH;
	}

	function applySticky(state) {
		if (state === isSticky) {
			return;
		}
		isSticky = state;
		header.classList.toggle('is-sticky', isSticky);
		if (mainBar && mainSpacer) {
			mainSpacer.style.height = isSticky ? mainBar.offsetHeight + 'px' : '';
		}
	}

	function onScroll() {
		if (raf) {
			return;
		}
		raf = window.requestAnimationFrame(function () {
			raf = null;
			if (window.innerWidth >= 1024) {
				applySticky(window.scrollY >= stickyAt);
			}
		});
	}

	if (mainBar) {
		updateStickyAt();
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', function () {
			updateStickyAt();
			if (window.innerWidth >= 1024) {
				applySticky(window.scrollY >= stickyAt);
			} else {
				applySticky(false);
			}
		});
		applySticky(window.innerWidth >= 1024 && window.scrollY >= stickyAt);
	}

	var accountWrap = header.querySelector('[data-setceb-account]');
	var accountBtn = accountWrap ? accountWrap.querySelector('.setceb-header__account-toggle') : null;
	var accountMenu = accountWrap ? accountWrap.querySelector('[data-setceb-account-menu]') : null;

	function closeAccount() {
		if (accountWrap) {
			accountWrap.classList.remove('is-open');
		}
		if (accountBtn) {
			accountBtn.setAttribute('aria-expanded', 'false');
		}
	}

	if (accountBtn) {
		accountBtn.addEventListener('click', function (event) {
			event.stopPropagation();
			closeMenu();
			var isOpen = accountWrap.classList.toggle('is-open');
			accountBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		});
	}

	// Navegacao por teclado dentro do dropdown da conta.
	if (accountMenu) {
		accountMenu.addEventListener('keydown', function (event) {
			var items = Array.prototype.filter.call(accountMenu.querySelectorAll('a'), function (el) {
				return el.offsetParent !== null;
			});

			if (!items.length) {
				return;
			}

			if (accountBtn && accountBtn.getAttribute('aria-expanded') !== 'true') {
				return;
			}

			var index = items.indexOf(document.activeElement);

			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault();
					(items[index + 1] || items[0]).focus();
					break;
				case 'ArrowUp':
					event.preventDefault();
					(index - 1 >= 0 ? items[index - 1] : items[items.length - 1]).focus();
					break;
				case 'Home':
					event.preventDefault();
					items[0].focus();
					break;
				case 'End':
					event.preventDefault();
					items[items.length - 1].focus();
					break;
				case 'Tab':
					if (!event.shiftKey && index === items.length - 1) {
						closeAccount();
					} else if (event.shiftKey && index === 0) {
						closeAccount();
					}
					break;
			}
		});
	}

	function closeMenu() {
		nav.classList.remove('is-open');
		toggle.classList.remove('is-open');
		toggle.setAttribute('aria-expanded', 'false');
		toggle.setAttribute('aria-label', 'Abrir menu');
	}

	toggle.addEventListener('click', function () {
		var isOpen = nav.classList.toggle('is-open');
		toggle.classList.toggle('is-open', isOpen);
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		toggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
		closeAccount();
	});

	document.addEventListener('keyup', function (event) {
		if (event.key === 'Escape' || event.key === 'Esc') {
			closeMenu();
			closeAccount();
		}
	});

	document.addEventListener('click', function (event) {
		if (!header.contains(event.target)) {
			closeMenu();
			closeAccount();
		}
	});

	window.addEventListener('resize', function () {
		if (window.innerWidth >= 1024) {
			closeMenu();
			closeAccount();
		}
	});
})();
