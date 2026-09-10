(function () {
	'use strict';

	function norm(value) {
		return String(value)
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '');
	}

	function init() {
		var listEl = document.getElementById('cetech-cobertura-list');
		var mapWrap = document.querySelector('.cetech-cobertura');

		if (!mapWrap) {
			return;
		}

		var pins = mapWrap.querySelectorAll('.cetech-svg__pin');
		var cells = mapWrap.querySelectorAll('.cetech-svg__cell');
		var items = listEl ? listEl.querySelectorAll('li') : [];

		function byName(collection, name) {
			var q = norm(name);
			var found = null;

			collection.forEach(function (el) {
				if (norm(el.getAttribute('data-name') || '') === q) {
					found = el;
				}
			});

			return found;
		}

		function selectCity(name) {
			var pin = byName(pins, name);
			var cell = byName(cells, name);
			var item = byName(items, name);

			if (pin) {
				pin.classList.add('is-pin-active');
			}

			if (cell) {
				cell.classList.add('is-active');
			}

			if (item) {
				item.classList.add('is-active');
			}
		}

		function clearSelection() {
			pins.forEach(function (pin) {
				pin.classList.remove('is-pin-active');
			});

			cells.forEach(function (cell) {
				cell.classList.remove('is-active');
			});

			items.forEach(function (item) {
				item.classList.remove('is-active');
			});
		}

		function toggleCity(name) {
			var item = byName(items, name);
			var pin = byName(pins, name);
			var cell = byName(cells, name);
			var isActive = (item && item.classList.contains('is-active')) ||
				(!item && cell && cell.classList.contains('is-active')) ||
				(!item && !cell && pin && pin.classList.contains('is-pin-active'));

			if (isActive) {
				if (item) {
					item.classList.remove('is-active');
				}
				if (pin) {
					pin.classList.remove('is-pin-active');
				}
				if (cell) {
					cell.classList.remove('is-active');
				}
			} else {
				clearSelection();
				selectCity(name);
			}
		}

		items.forEach(function (item) {
			item.addEventListener('click', function () {
				toggleCity(item.getAttribute('data-name'));
			});
		});

		pins.forEach(function (pin) {
			pin.addEventListener('click', function (event) {
				event.stopPropagation();
				toggleCity(pin.getAttribute('data-name'));
			});
		});

		cells.forEach(function (cell) {
			cell.addEventListener('click', function () {
				toggleCity(cell.getAttribute('data-name'));
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();