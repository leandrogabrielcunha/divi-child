(function () {
	'use strict';

	function norm(value) {
		return String(value)
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '');
	}

	function init() {
		var input = document.getElementById('cetech-cobertura-filter');
		if (!input) {
			return;
		}

		var clearBtn = document.getElementById('cetech-cobertura-filter-clear');
		var countEl = document.getElementById('cetech-cobertura-count');
		var chipCountEl = document.getElementById('cetech-cobertura-chip-count');
		var listCountEl = document.getElementById('cetech-cobertura-list-count');
		var listEl = document.getElementById('cetech-cobertura-list');

		var mapWrap = input.closest('.cetech-cobertura');
		var pins = mapWrap ? mapWrap.querySelectorAll('.cetech-svg__pin') : [];
		var items = listEl ? listEl.querySelectorAll('li') : [];

		function pinByName(name) {
			var q = norm(name);
			var found = null;
			pins.forEach(function (pin) {
				if (norm(pin.getAttribute('data-name') || '') === q) {
					found = pin;
				}
			});
			return found;
		}

		function itemByName(name) {
			var q = norm(name);
			var found = null;
			items.forEach(function (item) {
				if (norm(item.getAttribute('data-name') || '') === q) {
					found = item;
				}
			});
			return found;
		}

		function selectCity(name, force) {
			var pin = pinByName(name);
			var item = itemByName(name);

			if (pin) {
				if (force === true || !pin.classList.contains('is-pin-active')) {
					pin.classList.add('is-pin-active');
				} else {
					pin.classList.remove('is-pin-active');
				}
			}

			if (item) {
				if (force === true || !item.classList.contains('is-active')) {
					item.classList.add('is-active');
				} else {
					item.classList.remove('is-active');
				}
			}
		}

		function clearSelection() {
			pins.forEach(function (pin) {
				pin.classList.remove('is-pin-active');
			});
			items.forEach(function (item) {
				item.classList.remove('is-active');
			});
		}

		function update() {
			var q = norm(input.value.trim());
			var total = pins.length;
			var shown = 0;

			clearSelection();

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

			items.forEach(function (item) {
				var name = norm(item.getAttribute('data-name') || '');
				var found = q === '' || name.indexOf(q) !== -1;
				item.classList.toggle('is-hidden', !found);
			});

			if (countEl) {
				countEl.textContent = q === '' ? String(total) : shown + ' / ' + total;
			}

			if (chipCountEl) {
				chipCountEl.textContent = String(shown);
			}

			if (listCountEl) {
				listCountEl.textContent = String(shown);
			}

			if (clearBtn) {
				clearBtn.classList.toggle('is-visible', q !== '');
			}
		}

		items.forEach(function (item) {
			item.addEventListener('click', function () {
				var name = item.getAttribute('data-name');
				var pin = pinByName(name);

				if (pin) {
					if (item.classList.contains('is-active')) {
						item.classList.remove('is-active');
						pin.classList.remove('is-pin-active');
					} else {
						clearSelection();
						selectCity(name, true);
					}
				} else if (item.classList.contains('is-active')) {
					item.classList.remove('is-active');
				} else {
					clearSelection();
					item.classList.add('is-active');
				}
			});
		});

		pins.forEach(function (pin) {
			pin.addEventListener('click', function () {
				var name = pin.getAttribute('data-name');
				var item = itemByName(name);

				if (pin.classList.contains('is-pin-active')) {
					pin.classList.remove('is-pin-active');
					if (item) {
						item.classList.remove('is-active');
					}
				} else {
					clearSelection();
					selectCity(name, true);
				}
			});
		});

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