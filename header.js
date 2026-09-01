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

	var accountWrap = header.querySelector('[data-setceb-account]');
	var accountBtn = accountWrap ? accountWrap.querySelector('.setceb-header__account-toggle') : null;
	var accountMenu = accountWrap ? accountWrap.querySelector('[data-setceb-account-menu]') : null;

	function closeMenu() {
		nav.classList.remove('is-open');
		toggle.classList.remove('is-open');
		toggle.setAttribute('aria-expanded', 'false');
		toggle.setAttribute('aria-label', 'Abrir menu');
	}

	function closeAccount() {
		if (accountWrap) {
			accountWrap.classList.remove('is-open');
		}
		if (accountBtn) {
			accountBtn.setAttribute('aria-expanded', 'false');
		}
	}

	function toggleAccount() {
		closeMenu();
		var isOpen = accountWrap.classList.toggle('is-open');
		accountBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	}

	toggle.addEventListener('click', function () {
		var isOpen = nav.classList.toggle('is-open');
		toggle.classList.toggle('is-open', isOpen);
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		toggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
		closeAccount();
	});

	if (accountBtn) {
		accountBtn.addEventListener('click', function (event) {
			event.stopPropagation();
			toggleAccount();
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

	// Fecha ao navegar para outra pagina (os links do dropdown recarregam
	// a pagina; manter aqui por consistencia se houver navegacao via JS).
	document.addEventListener('click', function (event) {
		if (accountWrap && accountWrap.classList.contains('is-open')) {
			closeAccount();
		}
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
		}
	});
})();
