/**
 * SETCEB Banner - inicialização do Swiper.
 *
 * Vanilla JS, sem jQuery. Lê a configuração do atributo data-setceb-banner.
 */
(function () {
	'use strict';

	function initCarousel(container) {
		if (typeof window.Swiper !== 'function') {
			return;
		}

		if (container.dataset.setcebSwiperInit === '1') {
			return;
		}

		var config;

		try {
			config = JSON.parse(container.getAttribute('data-setceb-banner'));
		} catch (error) {
			return;
		}

		var prevEl = container.querySelector('.swiper-button-prev');
		var nextEl = container.querySelector('.swiper-button-next');
		var paginationEl = container.querySelector('.swiper-pagination');

		var options = {
			slidesPerView: 1,
			spaceBetween: 0,
			speed: config.speed || 500,
			loop: Boolean(config.loop),
			watchOverflow: true,
			grabCursor: true,
			keyboard: {
				enabled: true,
				onlyInViewport: true
			},
			autoplay: false,
			navigation: false,
			pagination: false,
			on: {
				slideChange: function () {
					// Placeholder reservado para ganchos futuros.
				}
			}
		};

		if (config.autoplay && config.delay > 0) {
			options.autoplay = {
				delay: config.delay,
				disableOnInteraction: false,
				pauseOnMouseEnter: true
			};
		}

		if (config.arrows && prevEl && nextEl) {
			options.navigation = {
				prevEl: prevEl,
				nextEl: nextEl
			};
		}

		if (config.bullets && paginationEl) {
			options.pagination = {
				el: paginationEl,
				clickable: true,
				dynamicBullets: false
			};
		}

		try {
			/* global Swiper */
			new Swiper(container, options);
			container.dataset.setcebSwiperInit = '1';
		} catch (error) {
			container.dataset.setcebSwiperInit = '1';
		}
	}

	function initAll() {
		var containers = document.querySelectorAll('.setceb-banner');

		Array.prototype.forEach.call(containers, initCarousel);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
