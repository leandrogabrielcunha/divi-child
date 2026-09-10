(function () {
	'use strict';

	function init() {
		var btn = document.getElementById('cetech-cidade-geocode');
		if (!btn) {
			return;
		}

		var config = window.cetechCoberturaAdmin || {};
		var titleInput = document.getElementById('title');
		var ufSelect = document.getElementById('cetech-cidade-uf');
		var latInput = document.getElementById('cetech-cidade-lat');
		var lngInput = document.getElementById('cetech-cidade-lng');
		var feedback = document.getElementById('cetech-cidade-geocode-feedback');

		function notify(message, type) {
			if (!feedback) {
				return;
			}
			feedback.textContent = message;
			feedback.style.color = 'error' === type ? '#b32d2e' : '#1a7f37';
			feedback.style.display = '';
		}

		btn.addEventListener('click', function () {
			var cidade = titleInput ? titleInput.value.trim() : '';
			if (!cidade) {
				notify('Informe o título (nome da cidade) antes de buscar.', 'error');
				titleInput && titleInput.focus();
				return;
			}

			btn.disabled = true;
			notify('Buscando coordenadas…', '');

			var body = new URLSearchParams();
			body.append('action', 'cetech_cobertura_geocode');
			body.append('nonce', btn.getAttribute('data-nonce') || '');
			body.append('cidade', cidade);
			body.append('uf', ufSelect ? ufSelect.value : 'SP');

			fetch(config.ajaxUrl || '', {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					if (data && data.success && data.data && data.data.lat && data.data.lng) {
						if (latInput) {
							latInput.value = String(data.data.lat);
						}
						if (lngInput) {
							lngInput.value = String(data.data.lng);
						}
						notify('Coordenadas preenchidas. Salve o post para confirmar.', 'success');
					} else {
						notify((data && data.data && data.data.message) || 'Não foi possível encontrar a cidade.', 'error');
					}
				})
				.catch(function () {
					notify('Erro de conexão. Tente novamente.', 'error');
				})
				.finally(function () {
					btn.disabled = false;
				});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();