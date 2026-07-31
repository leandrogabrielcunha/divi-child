/* SETCEB - Tela de login
Reorganiza o HTML padrao do WordPress para montar o card customizado. */
(function () {
	'use strict';

	var SVG_LOGO =
		'<svg class="setceb-logo" width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="SETCEB">' +
		'<defs>' +
		'<linearGradient id="setceb-logo-grad" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">' +
		'<stop stop-color="#123D73"/>' +
		'<stop offset="1" stop-color="#1FB7C9"/>' +
		'</linearGradient>' +
		'</defs>' +
		'<rect x="3" y="3" width="66" height="66" rx="20" fill="url(#setceb-logo-grad)"/>' +
		'<path d="M26 25C26 20 30.5 16.5 36 16.5C42.5 16.5 47 20.5 47 26C47 31 43.5 34.5 38 36C33.5 37.5 30 40.5 30 45L47 45" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>' +
		'<circle cx="30" cy="53" r="3.5" fill="#FFFFFF"/>' +
		'<circle cx="24" cy="19" r="2.5" fill="#1FB7C9"/>' +
		'</svg>';

	var ICONS = {
		user: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-user"/></svg>',
		lock: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-lock"/></svg>',
		eye: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-eye"/></svg>',
		eyeOff: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-eye-off"/></svg>'
	};

	function whenReady(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	whenReady(function () {
		var login = document.getElementById('login');
		if (!login) {
			return;
		}

		var card = document.createElement('div');
		card.className = 'setceb-card';
		while (login.firstChild) {
			card.appendChild(login.firstChild);
		}
		login.appendChild(card);

		removeDefaultElements();
		buildBrand(card);
		decorateForm(card);

		document.body.classList.add('setceb-ready');
	});

	function removeDefaultElements() {
		var h1 = document.querySelector('#login h1');
		if (h1 && !h1.classList.contains('setceb-title')) {
			h1.remove();
		}

		var backtoblog = document.getElementById('backtoblog');
		if (backtoblog) {
			backtoblog.remove();
		}

		var language = document.querySelector('.language-switcher');
		if (language) {
			language.remove();
		}
	}

	function buildBrand(card) {
		var isLostPassword = document.body.classList.contains('login-action-lostpassword');

		var brand = document.createElement('div');
		brand.className = 'setceb-brand';
		brand.innerHTML =
			SVG_LOGO +
			'<h1 class="setceb-title">' + (isLostPassword ? 'Recuperar senha' : 'Fa\u00e7a login') + '</h1>' +
			'<p class="setceb-subtitle">' + (isLostPassword ? 'informe seu usu\u00e1rio' : 'para continuar') + '</p>' +
			'<span class="setceb-divider"></span>';

		card.insertBefore(brand, card.firstChild);
	}

	function decorateForm(card) {
		var form = document.getElementById('loginform');
		if (!form) {
			return;
		}

		decorateField('.login-username', 'user', 'Usu\u00e1rio', false);
		decorateField('.login-password', 'lock', 'Senha', true);
		decorateRemember();
		decorateSubmit(form);

		var nav = document.getElementById('nav');
		if (nav) {
			nav.classList.add('setceb-nav');
		}
	}

	function decorateField(selector, icon, labelText, withToggle) {
		var field = document.querySelector(selector);
		if (!field) {
			return;
		}

		field.classList.add('setceb-field', 'setceb-field--' + icon);

		var label = field.querySelector('label');
		if (label) {
			label.textContent = labelText;
		}

		var input = field.querySelector('input');
		if (!input) {
			return;
		}

		var body = document.createElement('div');
		body.className = 'setceb-field-body';
		field.insertBefore(body, input);
		body.appendChild(input);
		body.insertAdjacentHTML('afterbegin', ICONS[icon]);

		if (withToggle) {
			var toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'setceb-toggle-password';
			toggle.setAttribute('aria-label', 'Mostrar senha');
			toggle.innerHTML = ICONS.eye;
			toggle.addEventListener('click', function () {
				var visible = input.type === 'text';
				input.type = visible ? 'password' : 'text';
				toggle.innerHTML = visible ? ICONS.eye : ICONS.eyeOff;
				toggle.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
			});
			body.appendChild(toggle);
		}
	}

	function decorateRemember() {
		var wrap = document.querySelector('.forgetmenot, .login-remember');
		if (!wrap) {
			return;
		}

		wrap.classList.add('setceb-remember');

		var checkbox = wrap.querySelector('input[type="checkbox"]');
		if (!checkbox) {
			return;
		}

		var check = document.createElement('span');
		check.className = 'setceb-checkmark';
		checkbox.parentNode.insertBefore(check, checkbox.nextSibling);
	}

	function decorateSubmit(form) {
		var submit = document.getElementById('wp-submit');
		if (submit) {
			submit.value = 'Acessar';
			submit.classList.add('setceb-submit');
		}

		var row = submit ? submit.closest('p') : form.querySelector('.submit');
		if (row) {
			row.classList.add('setceb-submit-row');
		}
	}
})();
