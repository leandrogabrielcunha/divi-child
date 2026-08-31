(function () {
	'use strict';

	var root = document.querySelector('[data-setceb-contact]');
	if (!root) {
		return;
	}

	var form = root.querySelector('.setceb-contact-form__form');
	if (!form) {
		return;
	}

	var cfg = window.SETCEB_CONTATO || {};
	var errorsBox = root.querySelector('[data-form-errors]');
	var noticeBox = root.querySelector('[data-form-notice]');
	var submitBtn = root.querySelector('[data-submit]');
	var submitLabel = root.querySelector('.setceb-contact-form__submit-label');

	/* ============================================================
	 * Helpers
	 * ============================================================ */
	function showErrors(errors) {
		if (!errorsBox) {
			return;
		}
		var lines = [];
		Object.keys(errors).forEach(function (key) {
			lines.push('<li>' + escapeHtml(errors[key]) + '</li>');
		});
		errorsBox.hidden = lines.length === 0;
		errorsBox.innerHTML = lines.length ? '<strong>Verifique os campos:</strong><ul>' + lines.join('') + '</ul>' : '';
		errorsBox.setAttribute('aria-hidden', lines.length ? 'false' : 'true');
	}

	function showNotice(message, isSuccess) {
		if (!noticeBox) {
			return;
		}
		noticeBox.hidden = !message;
		noticeBox.textContent = message || '';
		noticeBox.className = 'setceb-contact-form__notice' + (isSuccess ? ' is-success' : ' is-error');
		noticeBox.setAttribute('role', isSuccess ? 'status' : 'alert');
		noticeBox.removeAttribute('aria-hidden');
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function getFieldValue(name) {
		var field = form.querySelector('[name="' + name + '"]');
		if (!field) {
			return '';
		}
		if (field.type === 'radio') {
			var checked = form.querySelector('[name="' + name + '"]:checked');
			return checked ? checked.value : '';
		}
		return field.value;
	}

	function setLoading(loading) {
		if (!submitBtn) {
			return;
		}
		submitBtn.disabled = loading;
		submitBtn.classList.toggle('is-loading', loading);
		if (submitLabel) {
			submitLabel.textContent = loading ? 'Enviando…' : 'Enviar';
		}
	}

	/* ============================================================
	 * Mascaras (telefone e CEP)
	 * ============================================================ */
	function maskPhone(el) {
		el.addEventListener('input', function () {
			var v = el.value.replace(/\D/g, '');
			if (v.length > 11) {
				v = v.slice(0, 11);
			}
			if (v.length > 0) {
				if (v.length <= 2) {
					v = '(' + v;
				} else if (v.length <= 6) {
					v = '(' + v.slice(0, 2) + ') ' + v.slice(2);
				} else if (v.length <= 10) {
					v = '(' + v.slice(0, 2) + ') ' + v.slice(2, 6) + '-' + v.slice(6);
				} else {
					v = '(' + v.slice(0, 2) + ') ' + v.slice(2, 7) + '-' + v.slice(7);
				}
			}
			el.value = v;
		});
	}

	function maskCep(el) {
		el.addEventListener('input', function () {
			var v = el.value.replace(/\D/g, '');
			if (v.length > 8) {
				v = v.slice(0, 8);
			}
			if (v.length > 5) {
				v = v.slice(0, 5) + '-' + v.slice(5);
			}
			el.value = v;
		});
	}

	root.querySelectorAll('[data-mask-phone]').forEach(maskPhone);
	root.querySelectorAll('[data-mask-cep]').forEach(maskCep);

	/* ============================================================
	 * Contador de caracteres
	 * ============================================================ */
	var charInput = root.querySelector('[data-char-input]');
	var counter = root.querySelector('[data-char-counter]');

	if (charInput && counter) {
		var max = parseInt(charInput.getAttribute('maxlength'), 10) || 180;

		function updateCounter() {
			var len = charInput.value.length;
			counter.textContent = len + ' / ' + max;
		}

		charInput.addEventListener('input', updateCounter);
		updateCounter();
	}

	/* ============================================================
	 * Submissao (AJAX)
	 * ============================================================ */
	form.addEventListener('submit', function (event) {
		event.preventDefault();

		if (submitBtn && submitBtn.disabled) {
			return;
		}

		showErrors({});
		showNotice('', true);

		if (!cfg.ajaxUrl) {
			form.submit();
			return;
		}

		// Coleta valores (sem usar FormData para compatibilidade total).
		var data = new FormData(form);
		data.append('action', 'setceb_contato');
		data.append('setceb_contato_nonce', cfg.nonce || '');

		setLoading(true);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: data,
			credentials: 'same-origin'
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (result) {
				setLoading(false);

				if (result && result.success) {
					showNotice(cfg.messages.success || '', true);
					form.reset();
					if (counter) {
						var len = charInput.value.length;
						counter.textContent = len + ' / ' + (max || 180);
					}
					if (charInput) {
						charInput.dispatchEvent(new Event('input'));
					}
				} else if (result && result.errors) {
					showErrors(result.errors);
					showNotice('', false);
				} else {
					showNotice(cfg.messages.error || '', false);
				}
			})
			.catch(function () {
				setLoading(false);
				showNotice(cfg.messages.error || '', false);
			});
	});
})();
