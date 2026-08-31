<?php
/**
 * SETCEB - Paginacao numerada (substitui "Older/Next Entries" do Divi)
 *
 * Substitui o template de navegacao padrao do Divi (usado pelo modulo
 * Blog e pela navegacao de listagens) por uma paginacao com numeros de
 * paginas, mantendo o mesmo wrapper .pagination.clearfix para nao
 * quebrar a estrutura esperada pelo tema.
 *
 * Como o modulo Blog do Divi aplica o filtro get_pagenum_link (que
 * acrescenta &et_blog), o paginate_links() gera URLs numeradas corretas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;

if ( ! isset( $wp_query->max_num_pages ) || (int) $wp_query->max_num_pages < 2 ) {
	return;
}

$big   = 999999999;
$paged = max( 1, (int) get_query_var( 'paged' ) );
$total = (int) $wp_query->max_num_pages;

$links = paginate_links(
	array(
		'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
		'format'    => '?paged=%#%',
		'current'   => $paged,
		'total'     => $total,
		'type'      => 'array',
		'end_size'  => 1,
		'mid_size'  => 2,
		'prev_text' => '«',
		'next_text' => '»',
	)
);

if ( ! $links ) {
	return;
}

echo '<div class="pagination clearfix"><nav class="setceb-numeric-pagination" aria-label="Paginação de resultados">' . implode( '', $links ) . '</nav></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- links ja escapados pelo paginate_links()
