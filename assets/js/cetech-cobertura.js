(function () {
	'use strict';

	function init() {
		var config = window.cetechCobertura || {};
		var root = document.getElementById('cetech-cobertura-map');
		var loading = document.getElementById('cetech-cobertura-loading');

		if (!root || typeof window.L === 'undefined') {
			return;
		}

		var map = L.map(root, {
			zoomControl: true,
			attributionControl: true
		});

		map.setView([-15.5, -51.5], 4);

		L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
			subdomains: 'abcd',
			maxZoom: 19
		}).addTo(map);

		var drawState = false;

		function stateCenter() {
			return L.latLng(-22.5, -48.5);
		}

		function addState(geoJson) {
			L.geoJSON(geoJson, {
				style: {
					color: '#16a34a',
					weight: 2,
					opacity: 0.9,
					fillColor: '#4ade80',
					fillOpacity: 0.12,
					dashArray: null
				},
				onEachFeature: function () {
					return;
				}
			}).addTo(map).eachLayer(function (layer) {
				if (typeof layer.getElement === 'function') {
					var el = layer.getElement();
					if (el) {
						el.classList.add('cetech-cobertura__state');
					}
				}
			});

			drawState = true;
		}

		function pinHtml() {
			return '<span class="cetech-cobertura__pin-inner">' +
				'<svg viewBox="0 0 24 24" width="34" height="44" aria-hidden="true">' +
				'<path d="M12 2C7.6 2 4 5.6 4 9.8 4 15.2 12 22 12 22s8-6.8 8-12.2C20 5.6 16.4 2 12 2z" fill="#16a34a" stroke="#ffffff" stroke-width="1"/>' +
				'<circle cx="12" cy="9.8" r="3.4" fill="#ffffff"/>' +
				'</svg></span>';
		}

		function popupContent(city) {
			var wrap = document.createElement('div');
			wrap.className = 'cetech-cobertura__popup';

			var title = document.createElement('strong');
			title.className = 'cetech-cobertura__popup-title';
			title.textContent = city.name;

			wrap.appendChild(title);

			if (city.uf) {
				var uf = document.createElement('span');
				uf.className = 'cetech-cobertura__popup-uf';
				uf.textContent = city.uf;
				wrap.appendChild(uf);
			}

			if (city.bairros && city.bairros.length) {
				var bairros = document.createElement('span');
				bairros.className = 'cetech-cobertura__popup-bairros';
				bairros.textContent = city.bairros.join(' · ');
				wrap.appendChild(bairros);
			}

			return wrap;
		}

		var cities = config.cities || [];

		cities.forEach(function (city, index) {
			if (!city.lat || !city.lng) {
				return;
			}

			var marker = L.marker([city.lat, city.lng], {
				icon: L.divIcon({
					className: 'cetech-cobertura__pin',
					html: pinHtml(),
					iconSize: [34, 44],
					iconAnchor: [17, 43],
					popupAnchor: [0, -40]
				}),
				riseOnHover: true
			});

			marker.bindPopup(popupContent(city), {
				offset: [0, -6],
				autoClose: false,
				closeOnClick: false,
				closeButton: false
			});

			marker.on('add', function () {
				var el = marker.getElement();
				if (el) {
					el.style.setProperty('--i', index);
				}
			});

			marker.addTo(map);
		});

		function start() {
			if (typeof config.spUrl === 'string' && config.spUrl) {
				fetch(config.spUrl)
					.then(function (response) {
						return response.json();
					})
					.then(function (geoJson) {
						addState(geoJson);
						finish();
					})
					.catch(function () {
						finish();
					});
			} else {
				finish();
			}
		}

		function finish() {
			if (drawState) {
				map.flyTo(stateCenter(), 7, {
					duration: 2.4,
					easeLinearity: 0.2
				});
			}
			if (loading) {
				loading.parentNode && loading.parentNode.removeChild(loading);
			}
			map.invalidateSize();
		}

		start();
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