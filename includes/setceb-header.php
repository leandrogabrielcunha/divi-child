<?php
/**
 * SETCEB - Header global customizado
 *
 * Estrutura do cabecalho (faixa topo + barra principal + menu) renderizada
 * no topo do conteudo via hook proprio do Divi (et_before_main_content).
 * O header nativo do Divi e ocultado por CSS (style.css).
 *
 * A pagina /perfil-do-associado/ usa template proprio (sem hooks do Divi)
 * e inclui o mesmo header chamando setceb_header_markup() diretamente.
 *
 * Carregado em functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * 1. Local de menu "Menu Principal"
 * ------------------------------------------------------------ */
function setceb_register_header_menu() {
	register_nav_menus(
		array(
			'setceb-header' => 'SETCEB - Menu Principal',
		)
	);
}
add_action( 'after_setup_theme', 'setceb_register_header_menu' );

/* ------------------------------------------------------------
 * 2. Scripts do header (menu mobile)
 * ------------------------------------------------------------ */
function setceb_header_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_script(
		'setceb-header',
		get_stylesheet_directory_uri() . '/header.js',
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'setceb_header_enqueue_assets' );

/* ------------------------------------------------------------
 * 3. Renderizacao do header
 * ------------------------------------------------------------ */

/**
 * URL da pagina "Associe-se" (slug padrao).
 *
 * @return string
 */
function setceb_associe_url() {
	static $url = null;

	if ( null !== $url ) {
		return $url;
	}

	$page = get_page_by_path( 'associe-se' );
	$url  = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page->ID ) : home_url( '/' );
	return $url;
}

/**
 * Renderiza o header global no topo do conteudo (#main-content), logo
 * apos a abertura do container principal do Divi. O header nativo do
 * Divi e ocultado por CSS, entao o customizado fica no topo visual.
 *
 * Na pagina do perfil do associado o hook nao dispara (template proprio);
 * la o markup e incluido por page-associado.php via setceb_header_markup().
 */
function setceb_render_header() {
	if ( is_page( 'perfil-do-associado' ) ) {
		return;
	}

	echo setceb_header_markup();
}
add_action( 'et_before_main_content', 'setceb_render_header', 1 );

/**
 * Markup completa do header global.
 *
 * @return string
 */
function setceb_header_markup() {
	$home   = home_url( '/' );
	$logo   = get_stylesheet_directory_uri() . '/logo-cor-02.png';
	$perfil = setceb_associado_perfil_url();
	$label  = 'Area do Associado';

	ob_start();
	?>
	<header class="setceb-header" id="setceb-header">
		<div class="setceb-header__top" aria-hidden="true"></div>

		<div class="setceb-header__main">
			<div class="setceb-header__main-inner">
				<a class="setceb-header__logo" href="<?php echo esc_url( $home ); ?>">
					<img src="<?php echo esc_url( $logo ); ?>" alt="<?php bloginfo( 'name' ); ?>">
				</a>

				<form class="setceb-header__search" role="search" method="get" action="<?php echo esc_url( $home ); ?>">
					<label class="screen-reader-text" for="setceb-header-search">Pesquisar</label>
					<input type="search" id="setceb-header-search" name="s" placeholder="Pesquisar" value="<?php echo get_search_query(); ?>">
					<button type="submit" aria-label="Buscar">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
					</button>
				</form>

				<div class="setceb-header__actions">
					<a class="setceb-header__area" href="<?php echo esc_url( $perfil ); ?>"><?php echo esc_html( $label ); ?></a>
					<a class="setceb-header__user" href="<?php echo esc_url( $perfil ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"></path></svg>
					</a>
					<button class="setceb-header__burger" type="button" aria-expanded="false" aria-controls="setceb-header-menu" aria-label="Abrir menu">
						<svg class="setceb-header__burger-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M3 12h18"></path><path d="M3 18h18"></path></svg>
					</button>
				</div>
			</div>
		</div>

		<nav class="setceb-header__nav" id="setceb-header-menu" aria-label="Menu principal">
			<?php setceb_header_menu(); ?>
			<div class="setceb-header__mobile-actions">
				<a class="setceb-header__mobile-btn setceb-header__mobile-btn--area" href="<?php echo esc_url( $perfil ); ?>"><?php echo esc_html( $label ); ?></a>
				<a class="setceb-header__mobile-btn setceb-header__mobile-btn--associe" href="<?php echo esc_url( setceb_associe_url() ); ?>">Associe-se</a>
			</div>
		</nav>
	</header>
	<?php
	return ob_get_clean();
}

/* ------------------------------------------------------------
 * 4. Menu principal (8 itens) com fallback estatico
 * ------------------------------------------------------------ */

/**
 * Renderiza o menu principal. Usa o menu atribuido em
 * Aparência > Menus > "SETCEB - Menu Principal" e, na ausencia,
 * usa o fallback estatico.
 */
function setceb_header_menu() {
	if ( has_nav_menu( 'setceb-header' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'setceb-header',
				'container'      => false,
				'menu_class'     => 'setceb-header__list',
				'depth'          => 2,
				'fallback_cb'    => 'setceb_header_menu_fallback',
			)
		);
	} else {
		setceb_header_menu_fallback();
	}
}

/**
 * Fallback estatico exibido enquanto nenhum menu e atribuido ao local.
 * Itens da especificacao do cabecalho. Dropdowns aparecem quando um
 * menu real com subitens e configurado no painel.
 */
function setceb_header_menu_fallback() {
	$items = array(
		'entidade'     => array( 'Entidade', home_url( '/entidade/' ) ),
		'noticias'     => array( 'Noticias', home_url( '/noticias/' ) ),
		'eventos'      => array( 'Eventos e Reunioes', home_url( '/eventos-e-reunioes/' ) ),
		'comjovem'     => array( 'Comjovem', home_url( '/comjovem/' ) ),
		'cursos'       => array( 'Cursos', home_url( '/cursos/' ) ),
		'servicos'     => array( 'Servicos', home_url( '/servicos/' ) ),
		'fale-conosco' => array( 'Fale Conosco', home_url( '/fale-conosco/' ) ),
		'associe-se'   => array( 'Associe-se', setceb_associe_url() ),
	);

	echo '<ul class="setceb-header__list">';
	foreach ( $items as $key => $item ) {
		$class = ( 'associe-se' === $key ) ? ' setceb-header__associe' : '';
		printf(
			'<li class="setceb-header__item%s"><a class="setceb-header__link%s" href="%s">%s</a></li>',
			esc_attr( ( 'associe-se' === $key ) ? ' setceb-header__associe-item' : '' ),
			esc_attr( $class ),
			esc_url( $item[1] ),
			esc_html( $item[0] )
		);
	}
	echo '</ul>';
}

/**
 * Marca o item "Associe-se" do menu real com a classe de destaque
 * (botao turquesa) e esconde a duplicata no menu mobile.
 */
function setceb_header_menu_link_attrs( $atts, $item, $args ) {
	if ( isset( $args->theme_location ) && 'setceb-header' === $args->theme_location && false !== stripos( $item->title, 'associe' ) ) {
		$atts['class'] = isset( $atts['class'] ) && '' !== $atts['class'] ? $atts['class'] . ' setceb-header__associe' : 'setceb-header__associe';
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'setceb_header_menu_link_attrs', 10, 3 );

function setceb_header_menu_item_classes( $classes, $item, $args ) {
	if ( isset( $args->theme_location ) && 'setceb-header' === $args->theme_location && false !== stripos( $item->title, 'associe' ) ) {
		$classes[] = 'setceb-header__associe-item';
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'setceb_header_menu_item_classes', 10, 3 );
