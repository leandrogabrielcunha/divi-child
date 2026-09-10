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

		function selectCity(name) {
			var pin = pinByName(name);
			var item = itemByName(name);

			if (pin) {
				pin.classList.add('is-pin-active');
			}

			if (item) {
				item.classList.add('is-active');
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

		function toggleCity(name) {
			var pin = pinByName(name);
			var item = itemByName(name);
			var isActive = (item && item.classList.contains('is-active')) ||
				(!item && pin && pin.classList.contains('is-pin-active'));

			if (isActive) {
				if (item) {
					item.classList.remove('is-active');
				}
				if (pin) {
					pin.classList.remove('is-pin-active');
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
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();