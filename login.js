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

	var LOGO_HTML = window.SetcebLogin && window.SetcebLogin.logo
		? '<img class="setceb-logo" src="' + window.SetcebLogin.logo + '" alt="SETCEB" width="265" height="62" />'
		: SVG_LOGO;

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
		var brand = document.createElement('div');
		brand.className = 'setceb-brand';
		brand.innerHTML = LOGO_HTML;
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
		buildActions();
	}

	function buildActions() {
		var row = document.querySelector('.setceb-submit-row');
		if (!row) {
			return;
		}

		var nav = document.getElementById('nav');
		var link = nav ? nav.querySelector('a') : null;
		if (link && document.body.classList.contains('login-action-login')) {
			link.textContent = 'Recuperar senha';
		}

		var actions = document.createElement('div');
		actions.className = 'setceb-actions';
		row.parentNode.insertBefore(actions, row);
		actions.appendChild(row);

		if (nav) {
			nav.classList.add('setceb-nav');
			actions.appendChild(nav);
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
		input.setAttribute('placeholder', labelText);

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
