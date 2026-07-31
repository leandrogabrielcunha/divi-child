/* SETCEB - Tela de login
A estrutura do formulario e renderizada no servidor (functions.php).
Este script apenas adiciona o comportamento do botao mostrar/ocultar senha. */
(function () {
	'use strict';

	var ICONS = {
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
		var toggles = document.querySelectorAll('.setceb-toggle-password');
		var i;
		for (i = 0; i < toggles.length; i += 1) {
			(function (toggle) {
				toggle.addEventListener('click', function () {
					var input = toggle.parentNode.querySelector('input');
					if (!input) {
						return;
					}
					var visible = input.type === 'text';
					input.type = visible ? 'password' : 'text';
					toggle.innerHTML = visible ? ICONS.eye : ICONS.eyeOff;
					toggle.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
				});
			})(toggles[i]);
		}
	});
})();
