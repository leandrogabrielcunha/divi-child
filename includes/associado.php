<?php
/**
 * SETCEB - Regras de negocio do Associado
 *
 * Nivel de acesso "associado" (fora do painel), restricao de noticias
 * exclusivas para associados, area do associado (pagina de perfil) e
 * redirecionamento de login.
 *
 * Carregado em functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * 1. Perfil "associado"
 * ------------------------------------------------------------ */
function setceb_register_associado_role() {
	if ( ! get_role( 'associado' ) ) {
		add_role(
			'associado',
			'Associado',
			array(
				'read'         => true,
				'edit_posts'   => false,
				'upload_files' => false,
			)
		);
	}
}
add_action( 'init', 'setceb_register_associado_role' );

/**
 * Verifica se o usuario possui o perfil de associado.
 *
 * @param WP_User|int|null $user Usuario (padrao: atual).
 * @return bool
 */
function setceb_is_associado( $user = null ) {
	if ( is_numeric( $user ) || 0 === $user ) {
		$user = get_userdata( (int) $user );
	}
	if ( ! $user ) {
		$user = wp_get_current_user();
	}
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return in_array( 'associado', (array) $user->roles, true );
}

/**
 * URL da pagina de perfil do associado (template page-associado.php).
 *
 * @return string
 */
function setceb_associado_perfil_url() {
	static $url = null;

	if ( null !== $url ) {
		return $url;
	}

	$pages = get_pages(
		array(
			'meta_key'   => '_wp_page_template',
			'meta_value' => 'page-associado.php',
			'number'     => 1,
		)
	);

	$url = ! empty( $pages ) ? get_permalink( $pages[0]->ID ) : home_url( '/' );
	return $url;
}

/* ------------------------------------------------------------
 * 2. Redirecionamentos (associado nao usa o painel)
 * ------------------------------------------------------------ */

/**
 * Impede o associado de acessar /wp-admin.
 */
function setceb_block_associado_admin() {
	if ( wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	$user = wp_get_current_user();

	if ( ! is_admin() || ! setceb_is_associado( $user ) || user_can( $user, 'manage_options' ) ) {
		return;
	}

	wp_safe_redirect( setceb_associado_perfil_url() );
	exit;
}
add_action( 'admin_init', 'setceb_block_associado_admin' );

/**
 * Remove a barra de administracao do associado.
 */
function setceb_hide_admin_bar_for_associado( $show ) {
	if ( is_user_logged_in() && setceb_is_associado() && ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	return $show;
}
add_filter( 'show_admin_bar', 'setceb_hide_admin_bar_for_associado' );

/**
 * Apos o login, o associado vai para a area do associado (ou volta ao
 * conteudo que tentava acessar). Nunca para o painel.
 */
function setceb_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! isset( $user->ID ) || ! setceb_is_associado( $user ) || user_can( $user, 'manage_options' ) ) {
		return $redirect_to;
	}

	// Volta ao conteudo solicitado (ex.: noticia restrita), se for na frente do site.
	if ( ! empty( $requested_redirect_to ) && false === strpos( $requested_redirect_to, 'wp-admin' ) ) {
		return $requested_redirect_to;
	}

	return setceb_associado_perfil_url();
}
add_filter( 'login_redirect', 'setceb_login_redirect', 10, 3 );

/* ------------------------------------------------------------
 * 3. Noticias restritas a associados
 * ------------------------------------------------------------ */

/**
 * A noticia esta marcada como exclusiva para associados?
 */
function setceb_is_news_restricted( $post_id ) {
	return '1' === get_post_meta( $post_id, '_setceb_associado_only', true );
}

/**
 * O usuario atual pode ver conteudo restrito?
 */
function setceb_can_view_restricted_news() {
	$user = wp_get_current_user();
	return setceb_is_associado( $user ) || user_can( $user, 'manage_options' );
}

function setceb_news_meta_box() {
	add_meta_box(
		'setceb_news_access',
		'Acesso restrito',
		'setceb_news_meta_box_render',
		'post',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'setceb_news_meta_box' );

function setceb_news_meta_box_render( $post ) {
	wp_nonce_field( 'setceb_news_access', 'setceb_news_access_nonce' );
	$restricted = get_post_meta( $post->ID, '_setceb_associado_only', true );
	?>
	<label for="setceb_associado_only">
		<input type="checkbox" id="setceb_associado_only" name="setceb_associado_only" value="1" <?php checked( $restricted, '1' ); ?> />
		Conteudo restrito a associados
	</label>
	<p class="description">
		A noticia continua sendo listada no site, mas o conteudo completo so e
		exibido apos o login de associado.
	</p>
	<?php
}

function setceb_news_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['setceb_news_access_nonce'] ) || ! wp_verify_nonce( $_POST['setceb_news_access_nonce'], 'setceb_news_access' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! empty( $_POST['setceb_associado_only'] ) ) {
		update_post_meta( $post_id, '_setceb_associado_only', '1' );
	} else {
		delete_post_meta( $post_id, '_setceb_associado_only' );
	}
}
add_action( 'save_post', 'setceb_news_meta_box_save' );

/**
 * Bloqueia o conteudo completo de noticias restritas.
 * - Single: mostra chamada + pedido de login.
 * - Listas/RSS: mostra apenas chamada generica (sem vazar conteudo).
 */
function setceb_gate_news_content( $content ) {
	$post_id = get_the_ID();

	if ( ! $post_id || ! setceb_is_news_restricted( $post_id ) || setceb_can_view_restricted_news() ) {
		return $content;
	}

	if ( is_singular( 'post' ) ) {
		return setceb_news_login_prompt();
	}

	return '<p class="setceb-restricted-teaser">Conteudo exclusivo para associados. Faca login para ler a noticia completa.</p>';
}
add_filter( 'the_content', 'setceb_gate_news_content', 5 );

/**
 * Garante que o resumo de noticias restritas nao vaze conteudo
 * (arquivos, feeds, listagens que usam the_excerpt).
 */
function setceb_gate_news_excerpt( $excerpt, $post ) {
	if ( $post && setceb_is_news_restricted( $post->ID ) && ! setceb_can_view_restricted_news() ) {
		return 'Conteudo exclusivo para associados.';
	}
	return $excerpt;
}
add_filter( 'get_the_excerpt', 'setceb_gate_news_excerpt', 10, 2 );

/**
 * Bloco de login exibido no lugar do conteudo restrito.
 */
function setceb_news_login_prompt() {
	$login_url = wp_login_url( get_permalink() );
	ob_start();
	?>
	<div class="setceb-news-gate">
		<span class="setceb-news-gate__icon" aria-hidden="true"><?php echo setceb_svg_icon( 'lock' ); ?></span>
		<h3>Noticia exclusiva para associados</h3>
		<p>Esta noticia e de acesso restrito. Entre com sua conta de associado para ler o conteudo completo.</p>
		<a class="setceb-news-gate__btn" href="<?php echo esc_url( $login_url ); ?>">Entrar como associado</a>
	</div>
	<?php
	return ob_get_clean();
}

/* ------------------------------------------------------------
 * 4. Area do associado - links de boletos e icones
 * ------------------------------------------------------------ */

/**
 * Boletos disponiveis na area do associado.
 *
 * @return array
 */
function setceb_boletos() {
	return array(
		array(
			'id'    => 'mensalidade',
			'label' => 'Emissão de boleto da mensalidade',
			'url'   => 'https://setcebba.sindis.com.br/sindis/pub/process/BoletoContribuicao?ACAO=contribuicao&tipo=Social',
			'icon'  => 'calendar',
		),
		array(
			'id'    => 'assistencial',
			'label' => 'Emissão de Boleto de CONTRIBUIÇÃO ASSISTENCIAL',
			'url'   => 'https://setcebba.sindis.com.br/sindis/pub/process/BoletoContribuicao?ACAO=contribuicao&tipo=Confederativa',
			'icon'  => 'heart',
		),
		array(
			'id'    => 'sindical',
			'label' => 'Emissão de Boleto de CONTRIBUIÇÃO SINDICAL',
			'url'   => 'https://setcebba.sindis.com.br/sindis/pub/process/BoletoContribuicao',
			'icon'  => 'users',
		),
	);
}

/**
 * Retorna o SVG de um icone usado na area do associado.
 *
 * @param string $name Nome do icone.
 * @return string
 */
function setceb_svg_icon( $name ) {
	$icons = array(
		'lock'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"></rect><path d="M8 11V8a4 4 0 0 1 8 0v3"></path></svg>',
		'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path></svg>',
		'heart'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>',
		'users'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
	);

	return isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['lock'];
}
