(function () {
	'use strict';

	var hero = document.getElementById('cetech-hero');
	if (!hero) {
		return;
	}

	/* ---------- Navegação entre múltiplos Heroes (dot) ---------- */
	var slides = Array.prototype.slice.call(hero.querySelectorAll('.cetech-hero__slide'));
	var dots = Array.prototype.slice.call(hero.querySelectorAll('.cetech-hero__nav-dot'));
	var current = 0;

	function show(index) {
		if (index < 0 || index >= slides.length || index === current) {
			return;
		}

		slides[current].classList.remove('is-active');
		dots[current] && dots[current].classList.remove('is-active');
		dots[current] && dots[current].setAttribute('aria-selected', 'false');

		current = index;

		slides[current].classList.add('is-active');
		dots[current] && dots[current].classList.add('is-active');
		dots[current] && dots[current].setAttribute('aria-selected', 'true');
	}

	function showIndex(index) {
		if (index < 0) {
			index = slides.length - 1;
		}
		if (index >= slides.length) {
			index = 0;
		}
		show(index);
	}

	dots.forEach(function (dot) {
		dot.addEventListener('click', function () {
			showIndex(parseInt(dot.getAttribute('data-goto'), 10) || 0);
			startAutoplay();
		});
	});

	/* ---------- Troca automática (carrossel) ---------- */
	var timer = null;
	var interval = 5000;

	function startAutoplay() {
		stopAutoplay();
		if (slides.length < 2) {
			return;
		}
		timer = setInterval(function () {
			showIndex(current + 1);
		}, interval);
	}

	function stopAutoplay() {
		if (timer) {
			clearInterval(timer);
			timer = null;
		}
	}

	startAutoplay();

	hero.addEventListener('mouseenter', stopAutoplay);
	hero.addEventListener('mouseleave', startAutoplay);
	hero.addEventListener('focusin', stopAutoplay);
	hero.addEventListener('focusout', startAutoplay);

	/* ---------- Rotação suave do anel decorativo ---------- */
	var ring = hero.querySelector('.cetech-hero__orbit-ring--2');
	if (!ring || typeof window.requestAnimationFrame === 'undefined') {
		return;
	}

	var angle = 0;

	function tick() {
		angle = (angle + 0.04) % 360;
		ring.style.transform = 'rotate(' + angle + 'deg)';
		window.requestAnimationFrame(tick);
	}

	window.requestAnimationFrame(tick);
})();
