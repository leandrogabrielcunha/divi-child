(function () {
	'use strict';

	function init() {
		var input = document.getElementById('cetech-cobertura-filter');
		if (!input) {
			return;
		}

		var clearBtn = document.getElementById('cetech-cobertura-filter-clear');
		var countEl = document.getElementById('cetech-cobertura-count');
		var chipCountEl = document.getElementById('cetech-cobertura-chip-count');

		var mapWrap = input.closest('.cetech-cobertura');
		var pins = mapWrap ? mapWrap.querySelectorAll('.cetech-svg__pin') : [];

		function norm(value) {
			return String(value)
				.toLowerCase()
				.normalize('NFD')
				.replace(/[\u0300-\u036f]/g, '');
		}

		function update() {
			var q = norm(input.value.trim());
			var total = pins.length;
			var shown = 0;

			pins.forEach(function (pin) {
				var name = norm(pin.getAttribute('data-name') || '');
				var found = q === '' || name.indexOf(q) !== -1;

				pin.classList.toggle('is-filtered-out', !found);

				if (q !== '' && found) {
					pin.classList.add('is-filtered-match');
				} else {
					pin.classList.remove('is-filtered-match');
				}

				if (found) {
					shown++;
				}
			});

			if (countEl) {
				countEl.textContent = q === '' ? String(total) : shown + ' / ' + total;
			}

			if (chipCountEl) {
				chipCountEl.textContent = String(shown);
			}

			if (clearBtn) {
				clearBtn.classList.toggle('is-visible', q !== '');
			}
		}

		input.addEventListener('input', update);

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				update();
				input.focus();
			});
		}

		update();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();