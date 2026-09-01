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
	});

	document.addEventListener('keyup', function (event) {
		if (event.key === 'Escape' || event.key === 'Esc') {
			closeMenu();
		}
	});

	document.addEventListener('click', function (event) {
		if (!header.contains(event.target)) {
			closeMenu();
		}
	});

	window.addEventListener('resize', function () {
		if (window.innerWidth >= 1024) {
			closeMenu();
		}
	});
})();
