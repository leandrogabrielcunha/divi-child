(function ($) {
	'use strict';

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
})(jQuery);
