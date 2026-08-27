(function () {
	'use strict';

	function initImageField(field) {
		var input = field.querySelector('input[type="hidden"]');
		var preview = field.querySelector('.cetech-hero-image-field__preview');
		var addBtn = field.querySelector('.cetech-hero-image-field__add');
		var removeBtn = field.querySelector('.cetech-hero-image-field__remove');

		if (!input || !addBtn) {
			return;
		}

		var frame = null;

		addBtn.addEventListener('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: addBtn.getAttribute('data-media-title') || 'Selecionar imagem',
				button: { text: 'Usar imagem' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				input.value = attachment.id;

				if (preview) {
					preview.innerHTML = attachment.sizes && attachment.sizes.medium
						? '<img src="' + attachment.sizes.medium.url + '" alt="" style="max-width:240px;height:auto;border-radius:50%;display:block;">'
						: '<img src="' + attachment.url + '" alt="" style="max-width:240px;height:auto;border-radius:50%;display:block;">';
				}

				removeBtn.style.display = '';
			});

			frame.open();
		});

		if (removeBtn) {
			removeBtn.addEventListener('click', function (event) {
				event.preventDefault();
				input.value = '';
				if (preview) {
					preview.innerHTML = '';
				}
				removeBtn.style.display = 'none';
			});
		}
	}

	var fields = document.querySelectorAll('.cetech-hero-image-field');
	Array.prototype.forEach.call(fields, initImageField);
})();
