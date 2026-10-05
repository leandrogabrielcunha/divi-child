(function ($) {
	'use strict';

	var APPS_FIELD = /^cetech_planos_apps\[[^\]]*\]/;

	/* ==========================================================
	 * Repetidor de benefícios (textos simples)
	 * ========================================================== */
	function initBenefits() {
		var wrap = $('[data-cetech-benefits]');
		if (!wrap.length) {
			return;
		}

		var list = wrap.find('[data-cetech-benefits-list]');

		function rowTemplate() {
			var $row = $('<div class="cetech-benefits__row">');
			$('<input type="text" class="regular-text cetech-benefits__input" name="_cetech_plano_benefits[]" value="" />').appendTo($row);
			$('<button type="button" class="button cetech-benefits__up" aria-label="Mover para cima">&uarr;</button>').appendTo($row);
			$('<button type="button" class="button cetech-benefits__down" aria-label="Mover para baixo">&darr;</button>').appendTo($row);
			$('<button type="button" class="button-link delete cetech-benefits__remove" aria-label="Remover benefício">Remover</button>').appendTo($row);
			return $row;
		}

		wrap.on('click', '.cetech-benefits__add', function () {
			list.append(rowTemplate());
		});

		wrap.on('click', '.cetech-benefits__remove', function () {
			var $rows = list.find('.cetech-benefits__row');
			if ($rows.length === 1) {
				$rows.first().find('.cetech-benefits__input').val('');
			} else {
				$(this).closest('.cetech-benefits__row').remove();
			}
		});

		wrap.on('click', '.cetech-benefits__up', function () {
			var $row = $(this).closest('.cetech-benefits__row');
			var $prev = $row.prev('.cetech-benefits__row');
			if ($prev.length) {
				$row.insertBefore($prev);
			}
		});

		wrap.on('click', '.cetech-benefits__down', function () {
			var $row = $(this).closest('.cetech-benefits__row');
			var $next = $row.next('.cetech-benefits__row');
			if ($next.length) {
				$row.insertAfter($next);
			}
		});
	}

	/* ==========================================================
	 * Repetidor de apps/canais (nome, logo, desc, detalhes, ativo)
	 * ========================================================== */
	function initApps() {
		var wrap = $('[data-cetech-apps]');
		if (!wrap.length) {
			return;
		}

var list = wrap.find('[data-cetech-apps-list]');

	function rowTemplate() {
			return $(
				'<div class="cetech-apps__row" data-cetech-app-row>' +
				'<div class="cetech-apps__logo">' +
				'<input type="hidden" class="cetech-apps__image-id" name="cetech_planos_apps[__i__][imagem]" value="0" />' +
				'<div class="cetech-apps__preview" data-cetech-app-preview></div>' +
				'<button type="button" class="button cetech-apps__image-select" data-media-title="Selecionar logo do app">Logo</button>' +
				'<button type="button" class="button-link delete cetech-apps__image-remove" style="display:none;">Remover</button>' +
				'</div>' +
				'<div class="cetech-apps__fields">' +
				'<input type="text" class="regular-text" name="cetech_planos_apps[__i__][nome]" value="" placeholder="Nome (opcional) — Ex.: Netflix" />' +
				'<input type="text" class="large-text" name="cetech_planos_apps[__i__][desc]" value="" placeholder="Descrição (opcional): Ex.: Streaming de filmes e séries" />' +
				'<input type="text" class="large-text" name="cetech_planos_apps[__i__][detalhes]" value="" placeholder="Detalhes (opcional): Ex.: Incluído sem custo adicional" />' +
				'<label class="cetech-apps__active"><input type="checkbox" name="cetech_planos_apps[__i__][ativo]" value="1" checked="checked" /> Ativo</label>' +
				'</div>' +
				'<div class="cetech-apps__actions">' +
				'<button type="button" class="button cetech-apps__up" aria-label="Mover para cima">&uarr;</button>' +
				'<button type="button" class="button cetech-apps__down" aria-label="Mover para baixo">&darr;</button>' +
				'<button type="button" class="button-link delete cetech-apps__remove" aria-label="Remover app/canal">Remover</button>' +
				'</div>' +
				'</div>'
			);
		}

		/* Mantem os indices do name[] sequenciais apos add/remove/movimentacao. */
		function reindex() {
			list.find('[data-cetech-app-row]').each(function (index) {
				$(this).find('input, select, textarea').each(function () {
					var name = $(this).attr('name');
					if (name && APPS_FIELD.test(name)) {
						$(this).attr('name', name.replace(APPS_FIELD, 'cetech_planos_apps[' + index + ']'));
					}
				});
			});
		}

		wrap.on('click', '.cetech-apps__add', function () {
			list.append(rowTemplate());
			reindex();
		});

		wrap.on('click', '.cetech-apps__remove', function () {
			var $rows = list.find('[data-cetech-app-row]');
			var $row = $(this).closest('[data-cetech-app-row]');

			if ($rows.length === 1) {
				/* Mantem sempre uma linha no formulario: zera os campos
				 * e devolve a linha ao estado de "app novo". */
				$row.find('input[type="text"], textarea').val('');
				$row.find('input[type="checkbox"]').prop('checked', true);
				$row.find('.cetech-apps__image-id').val('0');
				$row.find('[data-cetech-app-preview]').empty();
				$row.find('.cetech-apps__image-remove').hide();
			} else {
				$row.remove();
			}

			reindex();
		});

		wrap.on('click', '.cetech-apps__up', function () {
			var $row = $(this).closest('[data-cetech-app-row]');
			var $prev = $row.prev('[data-cetech-app-row]');
			if ($prev.length) {
				$row.insertBefore($prev);
				reindex();
			}
		});

		wrap.on('click', '.cetech-apps__down', function () {
			var $row = $(this).closest('[data-cetech-app-row]');
			var $next = $row.next('[data-cetech-app-row]');
			if ($next.length) {
				$row.insertAfter($next);
				reindex();
			}
		});

		/* --- Seletor de logo (Media Library) --- */
		wrap.on('click', '.cetech-apps__image-select', function () {
			var btn = this;
			var $row = $(btn).closest('[data-cetech-app-row]');

			if (btn.cetechFrame) {
				btn.cetechFrame.open();
				return;
			}

			var frame = wp.media({
				title: $(btn).data('media-title') || 'Selecionar logo do app',
				button: { text: 'Usar logo' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var src = (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) || attachment.url;

				$row.find('.cetech-apps__image-id').val(attachment.id);
				$row.find('[data-cetech-app-preview]').html($('<img>', { src: src, alt: '' }));
				$row.find('.cetech-apps__image-remove').show();
			});

			btn.cetechFrame = frame;
			frame.open();
		});

		wrap.on('click', '.cetech-apps__image-remove', function () {
			var $row = $(this).closest('[data-cetech-app-row]');

			$row.find('.cetech-apps__image-id').val('0');
			$row.find('[data-cetech-app-preview]').empty();
			$(this).hide();
		});

		reindex();
	}

	$(initBenefits);
	$(initApps);
})(jQuery);