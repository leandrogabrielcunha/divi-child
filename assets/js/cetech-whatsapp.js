(function () {
	'use strict';

	function init() {
		var root = document.getElementById('cetech-wa');
		if (!root) {
			return;
		}

		var config = window.cetechWhatsapp || {};

		var btn = root.querySelector('#cetech-wa-btn');
		var panel = root.querySelector('#cetech-wa-panel');
		var closeBtn = root.querySelector('.cetech-wa__close');
		var form = root.querySelector('#cetech-wa-form');
		var setor = root.querySelector('select[name="setor"]');
		var perfil = root.querySelector('select[name="perfil"]');
		var planoSelect = root.querySelector('select[name="plano"]');
		var perfilWrap = root.querySelector('[data-cetech-wa-perfil]');
		var planoWrap = root.querySelector('[data-cetech-wa-plano]');

		var isOpen = false;
		var isComercial = false;

		function open() {
			if (isOpen) {
				return;
			}
			isOpen = true;
			root.classList.add('is-open');
			panel.setAttribute('aria-hidden', 'false');
			btn.setAttribute('aria-expanded', 'true');
			if (btn) {
				btn.querySelector('.cetech-wa__btn-label').style.display = 'none';
			}
		}

		function close() {
			if (!isOpen) {
				return;
			}
			isOpen = false;
			root.classList.remove('is-open');
			panel.setAttribute('aria-hidden', 'true');
			btn.setAttribute('aria-expanded', 'false');
			btn.querySelector('.cetech-wa__btn-label').style.display = '';
		}

		if (btn) {
			btn.addEventListener('click', function (event) {
				event.stopPropagation();
				if (isOpen) {
					close();
				} else {
					open();
				}
			});
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', close);
		}

		document.addEventListener('keyup', function (event) {
			if (event.key === 'Escape' || event.key === 'Esc') {
				close();
			}
		});

		document.addEventListener('click', function (event) {
			if (!root.contains(event.target)) {
				close();
			}
		});

		function setPlanoOptions() {
			var plans = config.plans || [];
			var perfilVal = perfil && perfil.value ? perfil.value : '';

			planoSelect.innerHTML = '';

			var firstOption = document.createElement('option');
			firstOption.value = '';
			firstOption.textContent = 'Selecione o plano...';
			planoSelect.appendChild(firstOption);

			plans.forEach(function (plan) {
				if (perfilVal && plan.perfil !== perfilVal) {
					return;
				}
				var opt = document.createElement('option');
				opt.value = plan.id;
				opt.textContent = plan.title;
				planoSelect.appendChild(opt);
			});
		}

		function resetComercialFields() {
			perfilWrap.hidden = true;
			planoWrap.hidden = true;
			if (perfil) {
				perfil.value = '';
			}
			planoSelect.innerHTML = '';
		}

		if (setor) {
			setor.addEventListener('change', function () {
				isComercial = 'comercial' === setor.value.trim().toLowerCase();
				if (isComercial) {
					perfilWrap.hidden = false;
					perfil.value = '';
					planoWrap.hidden = true;
					planoSelect.innerHTML = '';
				} else {
					resetComercialFields();
				}
			});
		}

		if (perfil) {
			perfil.addEventListener('change', function () {
				if ('' === perfil.value) {
					planoWrap.hidden = true;
					planoSelect.innerHTML = '';
					return;
				}
				setPlanoOptions();
				planoWrap.hidden = false;
			});
		}

		function selectedPlanTitle(planId) {
			var plans = config.plans || [];
			for (var i = 0; i < plans.length; i++) {
				if (String(plans[i].id) === String(planId)) {
					return plans[i].title;
				}
			}
			return '';
		}

		if (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();

				if (!form.reportValidity()) {
					return;
				}

				var data = new FormData(form);
				var nome = (data.get('nome') || '').trim();
				var fone = (data.get('fone') || '').trim();
				var setorVal = (data.get('setor') || '').trim();
				var mensagem = (data.get('mensagem') || '').trim();
				var perfilVal = data.get('perfil') ? String(data.get('perfil')) : '';
				var planoId = data.get('plano') ? String(data.get('plano')) : '';

				if (!config.number) {
					return;
				}

				var lines = [];
				lines.push('Olá! Vim pelo site da CE Tech.');
				lines.push('Nome: ' + nome);
				if (fone) {
					lines.push('Telefone: ' + fone);
				}
				lines.push('Tipo de atendimento: ' + setorVal);
				if (isComercial) {
					lines.push('Perfil: ' + ('empresarial' === perfilVal ? 'Empresarial' : 'Residencial'));
					if (planoId) {
						lines.push('Plano: ' + selectedPlanTitle(planoId));
					}
				}
				if (mensagem) {
					lines.push('Mensagem: ' + mensagem);
				}

				var waUrl = 'https://wa.me/' + config.number + '?text=' + encodeURIComponent(lines.join('\n'));

				var body = new URLSearchParams();
				body.append('action', 'cetech_wa_save_lead');
				body.append('nonce', config.nonce || '');
				body.append('nome', nome);
				body.append('fone', fone);
				body.append('setor', setorVal);
				body.append('perfil', isComercial ? perfilVal : '');
				body.append('plano_id', isComercial ? planoId : '');
				body.append('mensagem', mensagem);

				fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				})
					.then(function (response) {
						return response.json();
					})
					.catch(function () {
						return null;
					})
					.finally(function () {
						window.open(waUrl, '_blank', 'noopener');
					});

				close();
			});
		}
	}

	function whenReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	whenReady(init);
})();