(function () {
	'use strict';

	var carousels = document.querySelectorAll('[data-setceb-associados-carousel]');

	Array.prototype.forEach.call(carousels, function (carousel) {
		var viewport = carousel.querySelector('[data-carousel-viewport]');
		var track = carousel.querySelector('[data-carousel-track]');
		var previous = carousel.querySelector('[data-carousel-prev]');
		var next = carousel.querySelector('[data-carousel-next]');

		if (!viewport || !track || !previous || !next) {
			return;
		}

		function updateButtons() {
			var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
			previous.disabled = viewport.scrollLeft <= 2;
			next.disabled = maxScroll <= 2 || viewport.scrollLeft >= maxScroll - 2;
		}

		function move(direction) {
			var firstSlide = track.querySelector('.setceb-associados-carousel__slide');
			var style = window.getComputedStyle(track);
			var gap = parseFloat(style.columnGap || style.gap) || 0;
			var distance = firstSlide ? firstSlide.getBoundingClientRect().width + gap : viewport.clientWidth;

			viewport.scrollBy({
				left: direction * distance,
				behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
			});
		}

		previous.addEventListener('click', function () {
			move(-1);
		});

		next.addEventListener('click', function () {
			move(1);
		});

		viewport.addEventListener('scroll', updateButtons, { passive: true });
		window.addEventListener('resize', updateButtons);
		carousel.classList.add('is-ready');
		updateButtons();
	});
})();
