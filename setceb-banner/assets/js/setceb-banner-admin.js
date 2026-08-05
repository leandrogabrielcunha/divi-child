/**
 * SETCEB Banner - seletor de imagens no admin.
 *
 * Usa a Media Library do WordPress (wp.media) sem jQuery.
 */
(function () {
	'use strict';

	function initField(field) {
		var input = field.querySelector('input[type="hidden"]');
		var preview = field.querySelector('.setceb-image-field__preview');
		var addBtn = field.querySelector('.setceb-image-field__add');
		var removeBtn = field.querySelector('.setceb-image-field__remove');
		var frame = null;
		var cache = {};

		if (!input || !addBtn || !removeBtn) {
			return;
		}

		function setPreview(id, url) {
			preview.innerHTML = '';

			if (id && url) {
				var img = document.createElement('img');
				img.src = url;
				img.alt = '';
				preview.appendChild(img);
				removeBtn.style.display = '';
			} else {
				removeBtn.style.display = 'none';
			}
		}

		function render(id) {
			var url = cache[id];

			if (!url) {
				var attachment = wp.media.attachment(id);

				if (attachment.get('url')) {
					url = attachment.get('url');
				} else {
					attachment.fetch().done(function () {
						if (attachment.get('url')) {
							cache[id] = attachment.get('url');
							render(id);
						}
					});
					return;
				}
			}

			cache[id] = url;
			input.value = id;
			setPreview(id, url);
		}

		function clear() {
			input.value = '';
			setPreview(0, '');
		}

		addBtn.addEventListener('click', function (event) {
			event.preventDefault();

			if (!frame) {
				frame = wp.media({
					title: addBtn.getAttribute('data-media-title') || 'Selecionar imagem',
					button: {
						text: 'Usar esta imagem'
					},
					multiple: false,
					library: {
						type: 'image'
					}
				});

				frame.on('select', function () {
					var selection = frame.state().get('selection');

					if (selection && selection.first) {
						var attachment = selection.first().toJSON();
						cache[attachment.id] = attachment.url;
						render(attachment.id);
					}
				});
			}

			frame.open();
		});

		removeBtn.addEventListener('click', function (event) {
			event.preventDefault();
			clear();
		});

		if (input.value) {
			render(input.value);
		}
	}

	function init() {
		var fields = document.querySelectorAll('.setceb-image-field');

		Array.prototype.forEach.call(fields, initField);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
