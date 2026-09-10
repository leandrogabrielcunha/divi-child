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

		var municipios = mapWrap.querySelectorAll('.cetech-svg__mun');
		var pins = mapWrap.querySelectorAll('.cetech-svg__pin');
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

		/* Marca as municipalidades atendidas na cor de cada cidade. */
		items.forEach(function (item) {
			var mun = byName(municipios, item.getAttribute('data-name'));
			if (mun) {
				var cor = item.style.getPropertyValue('--cetech-pin').trim();
				if (cor) {
					mun.style.setProperty('--cetech-pin', cor);
				}
				mun.classList.add('is-served');
			}
		});

		function selectCity(name) {
			var item = byName(items, name);
			var pin = byName(pins, name);
			var mun = byName(municipios, name);

			if (item) {
				item.classList.add('is-active');
			}
			if (pin) {
				pin.classList.add('is-pin-active');
			}
			if (mun) {
				mun.classList.add('is-active');
			}
		}

		function clearSelection() {
			items.forEach(function (item) {
				item.classList.remove('is-active');
			});

			pins.forEach(function (pin) {
				pin.classList.remove('is-pin-active');
			});

			municipios.forEach(function (mun) {
				mun.classList.remove('is-active');
			});
		}

		function toggleCity(name) {
			var item = byName(items, name);
			var isActive = !!(item && item.classList.contains('is-active'));

			if (isActive) {
				clearSelection();
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

		municipios.forEach(function (mun) {
			mun.addEventListener('click', function () {
				toggleCity(mun.getAttribute('data-name'));
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();