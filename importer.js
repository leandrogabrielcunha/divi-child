(function () {
	'use strict';

	var loader = document.querySelector('[data-importer-loader]');
	var loaderText = loader ? loader.querySelector('[data-importer-loader-text]') : null;

	function showLoader(message) {
		if (!loader) {
			return;
		}
		if (loaderText && message) {
			loaderText.textContent = message;
		}
		loader.setAttribute('aria-hidden', 'false');
		loader.classList.add('is-visible');
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		var action;

		if (!form) {
			return;
		}

		action = form.getAttribute('action') || '';

		if (/action=scan|action=cat-scan/.test(action)) {
			showLoader('Enviando e processando o arquivo ZIP…');
			return;
		}

		if (/action=import|action=cat-import/.test(action)) {
			showLoader('Importando planilhas para o site…');
			return;
		}
	});
})();
