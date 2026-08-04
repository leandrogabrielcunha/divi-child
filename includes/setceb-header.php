<?php
/**
 * SETCEB - Header global customizado
 *
 * Estrutura do cabecalho (faixa topo + barra principal + menu) renderizada
 * no topo do conteudo via hook proprio do Divi (et_before_main_content).
 * O header nativo do Divi e ocultado por CSS (style.css).
 *
 * A pagina /perfil-do-associado/ usa template proprio, mas passa pelo
 * mesmo fluxo (get_header/get_footer), entao o hook tambem dispara la.
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
 */
function setceb_render_header() {
	if ( setceb_header_is_hidden() ) {
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

	if ( is_user_logged_in() ) {
		$user       = wp_get_current_user();
		$first_name = trim( (string) $user->first_name ) !== '' ? $user->first_name : $user->display_name;
		$label      = sprintf( 'Olá, %s', $first_name );
	} else {
		$label = 'Area do Associado';
	}

	$user_class = is_user_logged_in() ? ' setceb-header__area--user' : '';

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
					<a class="setceb-header__area<?php echo esc_attr( $user_class ); ?>" href="<?php echo esc_url( $perfil ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php if ( is_user_logged_in() ) : ?>
						<div class="setceb-header__user-wrap">
							<button class="setceb-header__user" type="button" aria-expanded="false" aria-haspopup="true" aria-label="Menu do usuário">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"></path></svg>
							</button>
							<div class="setceb-header__user-menu" role="menu">
								<span class="setceb-header__user-name"><?php echo esc_html( $label ); ?></span>
								<a href="<?php echo esc_url( wp_logout_url( $home ) ); ?>" role="menuitem">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path></svg>
									Sair
								</a>
							</div>
						</div>
					<?php else : ?>
						<a class="setceb-header__user" href="<?php echo esc_url( $perfil ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"></path></svg>
						</a>
					<?php endif; ?>
					<button class="setceb-header__burger" type="button" aria-expanded="false" aria-controls="setceb-header-menu" aria-label="Abrir menu">
						<svg class="setceb-header__burger-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M3 12h18"></path><path d="M3 18h18"></path></svg>
					</button>
				</div>
			</div>
		</div>

		<nav class="setceb-header__nav" id="setceb-header-menu" aria-label="Menu principal">
			<?php setceb_header_menu(); ?>
			<div class="setceb-header__mobile-actions">
				<a class="setceb-header__mobile-btn setceb-header__mobile-btn--area<?php echo esc_attr( $user_class ); ?>" href="<?php echo esc_url( $perfil ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php if ( ! is_user_logged_in() ) : ?>
					<a class="setceb-header__mobile-btn setceb-header__mobile-btn--associe" href="<?php echo esc_url( setceb_associe_url() ); ?>">Associe-se</a>
				<?php endif; ?>
			</div>
		</nav>
	</header>
	<?php
	return ob_get_clean();
}

/* ------------------------------------------------------------
 * 4. Menu principal com fallback estatico
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

	$current_path = trailingslashit( (string) wp_parse_url( home_url( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) );

	echo '<ul class="setceb-header__list">';
	foreach ( $items as $key => $item ) {
		if ( is_user_logged_in() && 'associe-se' === $key ) {
			continue;
		}

		$item_path  = trailingslashit( (string) wp_parse_url( $item[1], PHP_URL_PATH ) );
		$is_current = $item_path === $current_path;
		$li_class   = ( 'associe-se' === $key ) ? ' setceb-header__associe-item' : '';
		$link_class = ( 'associe-se' === $key ) ? ' setceb-header__associe' : '';

		if ( $is_current ) {
			$li_class .= ' current-menu-item';
		}

		printf(
			'<li class="setceb-header__item%s"><a class="setceb-header__link%s" href="%s">%s</a></li>',
			esc_attr( $li_class ),
			esc_attr( $link_class ),
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

/**
 * Remove o item "Associe-se" do menu real quando o usuario esta logado
 * (incluindo eventuais subitens).
 */
function setceb_header_menu_remove_associe( $items, $args ) {
	if ( ! is_user_logged_in() || ! isset( $args->theme_location ) || 'setceb-header' !== $args->theme_location ) {
		return $items;
	}

	$removed = array();
	foreach ( $items as $key => $item ) {
		if ( false !== stripos( $item->title, 'associe' ) ) {
			$removed[] = (int) $item->ID;
			unset( $items[ $key ] );
		}
	}

	if ( $removed ) {
		foreach ( $items as $key => $item ) {
			if ( isset( $item->menu_item_parent ) && in_array( (int) $item->menu_item_parent, $removed, true ) ) {
				unset( $items[ $key ] );
			}
		}
	}

	return $items;
}
add_filter( 'wp_nav_menu_objects', 'setceb_header_menu_remove_associe', 10, 2 );

/* ------------------------------------------------------------
 * 5. Ocultar o header por pagina
 *
 * Metabox "Cabecalho" no editor (paginas, posts e post types
 * publicos) com a opcao de nao exibir o header global.
 * ------------------------------------------------------------ */

/**
 * Tipos de conteudo que recebem o metabox do header.
 *
 * @return array
 */
function setceb_header_meta_post_types() {
	$post_types = get_post_types(
		array(
			'public' => true,
		),
		'names'
	);

	return array_values( $post_types );
}

/**
 * Registra o metabox de opcoes do header.
 */
function setceb_header_meta_box() {
	add_meta_box(
		'setceb_header_options',
		'Cabecalho',
		'setceb_header_meta_box_render',
		setceb_header_meta_post_types(),
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'setceb_header_meta_box' );

function setceb_header_meta_box_render( $post ) {
	wp_nonce_field( 'setceb_header_options', 'setceb_header_options_nonce' );
	$hidden = '1' === get_post_meta( $post->ID, '_setceb_hide_header', true );
	?>
	<label for="setceb_hide_header">
		<input type="checkbox" id="setceb_hide_header" name="setceb_hide_header" value="1" <?php checked( $hidden ); ?> />
		Ocultar o cabeçalho nesta página
	</label>
	<p class="description">
		Remove o cabeçalho global (faixa, logo, busca e menu) do topo desta página.
	</p>
	<?php
}

function setceb_header_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['setceb_header_options_nonce'] ) || ! wp_verify_nonce( $_POST['setceb_header_options_nonce'], 'setceb_header_options' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! empty( $_POST['setceb_hide_header'] ) ) {
		update_post_meta( $post_id, '_setceb_hide_header', '1' );
	} else {
		delete_post_meta( $post_id, '_setceb_hide_header' );
	}
}
add_action( 'save_post', 'setceb_header_meta_box_save' );

/**
 * O header deve ser ocultado na pagina atual?
 *
 * @return bool
 */
function setceb_header_is_hidden() {
	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		return false;
	}

	return '1' === get_post_meta( $post_id, '_setceb_hide_header', true );
}
