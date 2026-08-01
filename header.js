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

	var userWrap = header.querySelector('.setceb-header__user-wrap');
	var userBtn = header.querySelector('.setceb-header__user');

	function closeMenu() {
		nav.classList.remove('is-open');
		toggle.classList.remove('is-open');
		toggle.setAttribute('aria-expanded', 'false');
		toggle.setAttribute('aria-label', 'Abrir menu');
	}

	function closeUserMenu() {
		if (userWrap) {
			userWrap.classList.remove('is-open');
		}
		if (userBtn) {
			userBtn.setAttribute('aria-expanded', 'false');
		}
	}

	toggle.addEventListener('click', function () {
		var isOpen = nav.classList.toggle('is-open');
		toggle.classList.toggle('is-open', isOpen);
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		toggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
	});

	if (userBtn && userWrap) {
		userBtn.addEventListener('click', function (event) {
			event.stopPropagation();
			closeMenu();
			var isOpen = userWrap.classList.toggle('is-open');
			userBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		});
	}

	document.addEventListener('keyup', function (event) {
		if (event.key === 'Escape' || event.key === 'Esc') {
			closeMenu();
			closeUserMenu();
		}
	});

	document.addEventListener('click', function (event) {
		if (!header.contains(event.target)) {
			closeMenu();
			closeUserMenu();
		}
	});

	window.addEventListener('resize', function () {
		if (window.innerWidth >= 1024) {
			closeMenu();
		}
	});
})();
