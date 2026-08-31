/**
 * SETCEB - Eventos
 * Filtro por ano e mes da lista de eventos exibida pelo shortcode
 * [setceb_eventos]. Funciona 100% no cliente: os cards carregam
 * data-ano e data-mes e sao exibidos/ocultados conforme o filtro.
 */
( function () {
	'use strict';

	function init( root ) {
		var ano   = root.querySelector( '[data-setceb-eventos-ano]' );
		var mes   = root.querySelector( '[data-setceb-eventos-mes]' );
		var lista = root.querySelector( '[data-setceb-eventos-lista]' );

		if ( ! ano || ! mes || ! lista ) {
			return;
		}

		var itens = lista.querySelectorAll( '.setceb-eventos__item' );

		// Meses disponiveis por ano, lidos dos cards ja renderizados.
		function mesesDoAno( year ) {
			var found = [];
			var i;

			for ( i = 0; i < itens.length; i++ ) {
				var item = itens[ i ];
				var itemAno = item.getAttribute( 'data-ano' );
				var itemMes = item.getAttribute( 'data-mes' );
				if ( itemAno === year && itemMes && found.indexOf( itemMes ) === -1 ) {
					found.push( itemMes );
				}
			}

			return found.sort( function ( a, b ) {
				return parseInt( a, 10 ) - parseInt( b, 10 );
			} );
		}

		var MESES = [
			'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
			'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
		];

		function labelMes( num ) {
			var idx = parseInt( num, 10 ) - 1;
			return MESES[ idx ] || num;
		}

		// Preenche o select de mes com os meses do ano selecionado.
		function populaMeses() {
			var year = ano.value;
			var meses = year ? mesesDoAno( year ) : [];
			var html = '<option value="">Todos os meses</option>';
			var i;

			for ( i = 0; i < meses.length; i++ ) {
				html += '<option value="' + meses[ i ] + '">' + labelMes( meses[ i ] ) + '</option>';
			}

			mes.innerHTML = html;
			mes.disabled = ( meses.length === 0 );
			mes.value = '';
		}

		// Aplica o filtro atual aos cards.
		function aplicar() {
			var year = ano.value;
			var month = mes.value;
			var visiveis = 0;
			var i;

			for ( i = 0; i < itens.length; i++ ) {
				var item = itens[ i ];
				var temAno = item.getAttribute( 'data-ano' );
				var temMes = item.getAttribute( 'data-mes' );
				var mostra = true;

				if ( year && temAno !== year ) {
					mostra = false;
				}
				if ( mostra && month && temMes !== month ) {
					mostra = false;
				}

				item.hidden = !mostra;
				if ( mostra ) {
					visiveis++;
				}
			}

			var empty = root.querySelector( '.setceb-eventos__empty-result' );
			if ( empty ) {
				empty.hidden = visiveis !== 0;
			}
		}

		ano.addEventListener( 'change', function () {
			populaMeses();
			aplicar();
		} );

		mes.addEventListener( 'change', aplicar );

		populaMeses();
		aplicar();
	}

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var raizes = document.querySelectorAll( '[data-setceb-eventos]' );
		var i;
		for ( i = 0; i < raizes.length; i++ ) {
			init( raizes[ i ] );
		}
	} );
}() );
