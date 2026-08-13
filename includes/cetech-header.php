<?php
/**
 * CE Tech - Header global customizado
 *
 * Estrutura do cabecalho (logo + menu + acoes) renderizada no topo do
 * conteudo via hook proprio do Divi (et_before_main_content).
 * O header nativo do Divi e ocultado por CSS (style.css).
 *
 * Carregado em functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * 1. Local de menu "Menu Principal"
 * ------------------------------------------------------------ */
function cetech_register_header_menu() {
	register_nav_menus(
		array(
			'cetech-header' => 'CE Tech - Menu Principal',
		)
	);
}
add_action( 'after_setup_theme', 'cetech_register_header_menu' );

/* ------------------------------------------------------------
 * 2. Scripts do header (menu mobile)
 * ------------------------------------------------------------ */
function cetech_header_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_script(
		'cetech-header',
		get_stylesheet_directory_uri() . '/header.js',
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'cetech_header_enqueue_assets' );

/* ------------------------------------------------------------
 * 3. URLs dos botoes de acao
 * ------------------------------------------------------------ */

/**
 * URL do botao "2ª Via do Boleto" (CE Tech).
 *
 * @return string
 */
function cetech_boleto_url() {
	return apply_filters( 'cetech_boleto_url', home_url( '/' ) );
}

/**
 * URL do botao "Área do Cliente" (CE Tech).
 *
 * @return string
 */
function cetech_cliente_url() {
	return apply_filters( 'cetech_cliente_url', home_url( '/' ) );
}

/**
 * URL da pagina de perfil do usuario.
 *
 * @return string
 */
function cetech_perfil_url() {
	$page = get_page_by_path( 'perfil' );

	if ( ! $page || 'publish' !== $page->post_status ) {
		$pages = get_pages(
			array(
				'meta_key'   => '_wp_page_template',
				'meta_value' => 'page-perfil.php',
				'number'     => 1,
			)
		);
		$page = ! empty( $pages ) ? $pages[0] : null;
	}

	return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page->ID ) : home_url( '/' );
}

/* ------------------------------------------------------------
 * 4. Renderizacao do header
 * ------------------------------------------------------------ */

/**
 * Renderiza o header global no topo do conteudo (#main-content), logo
 * apos a abertura do container principal do Divi. O header nativo do
 * Divi e ocultado por CSS, entao o customizado fica no topo visual.
 */
function cetech_render_header() {
	if ( cetech_header_is_hidden() ) {
		return;
	}
	echo cetech_header_markup();
}
add_action( 'et_before_main_content', 'cetech_render_header', 1 );

/**
 * Markup completa do header global.
 *
 * Estrutura: logo (esquerda) | menu central (dinamico) | acoes (direita).
 * Em telas estreitas, o menu colapsa em um painel acionado pelo hamburger.
 *
 * @return string
 */
function cetech_header_markup() {
	$home   = home_url( '/' );
	$logo   = 'https://cetech.net.br/wp-content/uploads/2025/07/01-Logo-Horizontal-Principal-2-scaled.png';
	$perfil = cetech_perfil_url();

	if ( is_user_logged_in() ) {
		$user       = wp_get_current_user();
		$first_name = trim( (string) $user->first_name ) !== '' ? $user->first_name : $user->display_name;
		$label      = sprintf( 'Olá, %s', $first_name );
	} else {
		$label = '';
	}

	ob_start();
	?>
	<header class="cetech-header" id="cetech-header" role="banner">
		<div class="cetech-header__box">
			<div class="cetech-header__start">
				<a class="cetech-header__logo" href="<?php echo esc_url( $home ); ?>" aria-label="CE Tech">
					<img src="<?php echo esc_url( $logo ); ?>" alt="CE Tech" width="240" height="44" loading="eager">
				</a>
			</div>

			<nav class="cetech-header__nav" id="cetech-header-menu" aria-label="Menu principal">
				<?php cetech_header_menu(); ?>

				<div class="cetech-header__mobile-actions">
					<a class="cetech-header__btn cetech-header__btn--boleto" href="<?php echo esc_url( cetech_boleto_url() ); ?>" target="_blank" rel="noopener noreferrer">2ª Via do Boleto</a>
					<a class="cetech-header__btn cetech-header__btn--cliente" href="<?php echo esc_url( cetech_cliente_url() ); ?>" target="_blank" rel="noopener noreferrer">Área do Cliente</a>
					<?php if ( is_user_logged_in() ) : ?>
						<div class="cetech-header__mobile-user">
							<span class="cetech-header__mobile-user-name"><?php echo esc_html( $label ); ?></span>
							<a class="cetech-header__mobile-user-link" href="<?php echo esc_url( wp_logout_url( $home ) ); ?>">Sair</a>
						</div>
					<?php endif; ?>
				</div>
			</nav>

			<div class="cetech-header__end">
				<a class="cetech-header__btn cetech-header__btn--boleto" href="<?php echo esc_url( cetech_boleto_url() ); ?>" target="_blank" rel="noopener noreferrer">2ª Via do Boleto</a>
				<a class="cetech-header__btn cetech-header__btn--cliente" href="<?php echo esc_url( cetech_cliente_url() ); ?>" target="_blank" rel="noopener noreferrer">Área do Cliente</a>
				<?php if ( is_user_logged_in() ) : ?>
					<div class="cetech-header__user-wrap">
						<button class="cetech-header__user" type="button" aria-expanded="false" aria-haspopup="true" aria-label="Menu do usuário">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"></path></svg>
						</button>
						<div class="cetech-header__user-menu" role="menu">
							<span class="cetech-header__user-name"><?php echo esc_html( $label ); ?></span>
							<a href="<?php echo esc_url( $perfil ); ?>" role="menuitem">Minha Conta</a>
							<a href="<?php echo esc_url( wp_logout_url( $home ) ); ?>" role="menuitem">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path></svg>
								Sair
							</a>
						</div>
					</div>
				<?php endif; ?>
				<button class="cetech-header__burger" type="button" aria-expanded="false" aria-controls="cetech-header-menu" aria-label="Abrir menu">
					<span class="cetech-header__burger-line"></span>
					<span class="cetech-header__burger-line"></span>
					<span class="cetech-header__burger-line"></span>
				</button>
			</div>
		</div>
	</header>
	<?php
	return ob_get_clean();
}

/* ------------------------------------------------------------
 * 5. Menu principal (dinamico - sem fallback)
 * ------------------------------------------------------------ */

/**
 * Renderiza o menu principal. Usa apenas o menu atribuido em
 * Aparência > Menus > "CE Tech - Menu Principal".
 * Se nenhum menu for atribuido, nada e exibido.
 */
function cetech_header_menu() {
	if ( has_nav_menu( 'cetech-header' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'cetech-header',
				'container'      => false,
				'menu_class'     => 'cetech-header__list',
				'menu_id'        => 'cetech-header-list',
				'depth'          => 2,
				'fallback_cb'    => '__return_false',
			)
		);
	}
}

/* ------------------------------------------------------------
 * 6. Ocultar o header por pagina
 *
 * Metabox "Cabeçalho" no editor (paginas, posts e post types
 * publicos) com a opcao de nao exibir o header global.
 * ------------------------------------------------------------ */

/**
 * Tipos de conteudo que recebem o metabox do header.
 *
 * @return array
 */
function cetech_header_meta_post_types() {
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
function cetech_header_meta_box() {
	add_meta_box(
		'cetech_header_options',
		'Cabecalho',
		'cetech_header_meta_box_render',
		cetech_header_meta_post_types(),
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'cetech_header_meta_box' );

function cetech_header_meta_box_render( $post ) {
	wp_nonce_field( 'cetech_header_options', 'cetech_header_options_nonce' );
	$hidden = '1' === get_post_meta( $post->ID, '_cetech_hide_header', true );
	?>
	<label for="cetech_hide_header">
		<input type="checkbox" id="cetech_hide_header" name="cetech_hide_header" value="1" <?php checked( $hidden ); ?> />
		Ocultar o cabeçalho nesta página
	</label>
	<p class="description">
		Remove o cabeçalho global (logo, menu e ações) do topo desta página.
	</p>
	<?php
}

function cetech_header_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['cetech_header_options_nonce'] ) || ! wp_verify_nonce( $_POST['cetech_header_options_nonce'], 'cetech_header_options' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! empty( $_POST['cetech_hide_header'] ) ) {
		update_post_meta( $post_id, '_cetech_hide_header', '1' );
	} else {
		delete_post_meta( $post_id, '_cetech_hide_header' );
	}
}
add_action( 'save_post', 'cetech_header_meta_box_save' );

/**
 * O header deve ser ocultado na pagina atual?
 *
 * @return bool
 */
function cetech_header_is_hidden() {
	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		return false;
	}

	return '1' === get_post_meta( $post_id, '_cetech_hide_header', true );
}
