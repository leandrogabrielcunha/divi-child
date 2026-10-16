(function () {
	'use strict';

	var header = document.getElementById('setceb-header');
	if (!header) {
		return;
	}

	var toggle = header.querySelector('.setceb-header__burger');
	var nav = header.querySelector('.setceb-header__nav');
	// O menu de navegacao pode estar oculto em pagina de perfil
	// (setceb_header_minimal), sem impedir o restante da UI.
	if (!toggle && !header.querySelector('[data-setceb-account]')) {
		return;
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
		if (nav) {
			nav.classList.remove('is-open');
		}
		if (toggle) {
			toggle.classList.remove('is-open');
			toggle.setAttribute('aria-expanded', 'false');
			toggle.setAttribute('aria-label', 'Abrir menu');
		}
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
