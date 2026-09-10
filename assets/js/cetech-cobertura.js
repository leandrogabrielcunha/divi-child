(function () {
	'use strict';

	var SVG_NS = 'http://www.w3.org/2000/svg';

	function norm(value) {
		return String(value)
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '');
	}

	function parsePinPos(pin) {
		var wrap = pin.parentNode;
		var tr = wrap ? wrap.getAttribute('transform') : '';
		var m = tr && tr.match(/translate\(([-\d.,]+)[, ]+([-\d.,]+)\)/);
		if (!m) {
			return null;
		}
		return {
			x: parseFloat(m[1].replace(',', '.')),
			y: parseFloat(m[2].replace(',', '.'))
		};
	}

	function init() {
		var listEl = document.getElementById('cetech-cobertura-list');
		var mapWrap = document.querySelector('.cetech-cobertura');

		if (!mapWrap) {
			return;
		}

		var svgEl = mapWrap.querySelector('svg');
		var pins = mapWrap.querySelectorAll('.cetech-svg__pin');
		var items = listEl ? listEl.querySelectorAll('li') : [];

		var hubX = parseFloat(mapWrap.getAttribute('data-hub-x'));
		var hubY = parseFloat(mapWrap.getAttribute('data-hub-y'));
		var hasHub = !isNaN(hubX) && !isNaN(hubY);
		var lineEl = null;

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

		function clearLine(animate) {
			if (!lineEl || !svgEl) {
				lineEl = null;
				return;
			}
			if (animate) {
				lineEl.style.transition = 'opacity 0.25s ease';
				lineEl.style.opacity = 0;
				window.setTimeout(function () {
					if (lineEl && lineEl.parentNode) {
						lineEl.parentNode.removeChild(lineEl);
					}
				}, 260);
			} else {
				if (lineEl.parentNode) {
					lineEl.parentNode.removeChild(lineEl);
				}
			}
			lineEl = null;
		}

		function drawLine(pin) {
			if (!svgEl || !hasHub || !pin) {
				return;
			}

			var pos = parsePinPos(pin);
			if (!pos) {
				return;
			}

			clearLine(false);

			var line = document.createElementNS(SVG_NS, 'line');
			line.setAttribute('class', 'cetech-linha');
			line.setAttribute('x1', hubX.toFixed(1));
			line.setAttribute('y1', hubY.toFixed(1));
			line.setAttribute('x2', pos.x.toFixed(1));
			line.setAttribute('y2', pos.y.toFixed(1));

			var dx = pos.x - hubX;
			var dy = pos.y - hubY;
			var length = Math.sqrt(dx * dx + dy * dy);

			line.setAttribute('stroke-dasharray', String(length));
			line.style.strokeDashoffset = String(length);

			var refNode = pins.length ? pins[0].parentNode : null;
			svgEl.insertBefore(line, refNode);

			lineEl = line;

			window.requestAnimationFrame(function () {
				line.style.transition = 'stroke-dashoffset 500ms ease-out';
				line.style.strokeDashoffset = '0';
			});
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

			drawLine(pin);
		}

		function clearSelection(animateLine) {
			pins.forEach(function (pin) {
				pin.classList.remove('is-pin-active');
			});

			items.forEach(function (item) {
				item.classList.remove('is-active');
			});

			clearLine(animateLine);
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
				clearLine(true);
			} else {
				clearSelection(false);
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