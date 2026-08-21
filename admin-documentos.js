(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var target = event.target;

		if (!target || !target.closest || !target.closest('#setceb-doc-media')) {
			return;
		}

		event.preventDefault();

		if (typeof wp === 'undefined' || !wp.media) {
			window.alert('Biblioteca de mídia indisponível. Cole a URL do arquivo manualmente.');
			return;
		}

		var frame = wp.media({
			title: 'Selecionar arquivo',
			multiple: false,
			button: { text: 'Usar este arquivo' }
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var input = document.getElementById('setceb-doc-url');

			if (input && attachment.url) {
				input.value = attachment.url;
			}
		});

		frame.open();
	});
})();
