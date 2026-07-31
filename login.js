/* SETCEB - Tela de login (modelo da branch main)
O layout e feito 100% por CSS (style.css). Este script apenas
melhora a tela de forma progressiva, SEM alterar a estrutura
do DOM: define os placeholders e o texto do botao de acesso. */
(function () {
	'use strict';

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

		var log = document.getElementById('user_login');
		if (log) {
			log.setAttribute('placeholder', 'Usu\u00e1rio');
		}

		var pass = document.getElementById('user_pass');
		if (pass) {
			pass.setAttribute('placeholder', 'Senha');
		}

		var submit = document.getElementById('wp-submit');
		if (submit) {
			submit.value = 'Acessar';
		}
	});
})();
