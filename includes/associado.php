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
 * URL da pagina de perfil do associado (page-associado.php).
 * Busca primeiro pelo slug padrao e depois por qualquer pagina
 * que use o template "Perfil do Associado".
 *
 * @return string
 */
function setceb_associado_perfil_url() {
	static $url = null;

	if ( null !== $url ) {
		return $url;
	}

	$page = get_page_by_path( 'perfil-do-associado' );

	if ( ! $page || 'publish' !== $page->post_status ) {
		$pages = get_pages(
			array(
				'meta_key'   => '_wp_page_template',
				'meta_value' => 'page-associado.php',
				'number'     => 1,
			)
		);
		$page = ! empty( $pages ) ? $pages[0] : null;
	}

	$url = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page->ID ) : home_url( '/' );
	return $url;
}

/**
 * Garante que a pagina /perfil-do-associado/ use o template da area
 * do associado, mesmo que o modelo nao esteja selecionado no editor.
 */
function setceb_force_associado_template( $template ) {
	if ( is_page( 'perfil-do-associado' ) ) {
		$custom = get_stylesheet_directory() . '/page-associado.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}
add_filter( 'template_include', 'setceb_force_associado_template', 99 );

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

/* ------------------------------------------------------------
 * 5. Area do associado - conteudos (categorias, anos, documentos)
 * ------------------------------------------------------------ */

/**
 * Assets exclusivos da pagina da area do associado.
 *
 * Usa a fonte de icones Dashicons (embutida no WordPress) e o script
 * de interacoes (abas, seletor de ano, filtros e estados dos forms).
 */
function setceb_associado_enqueue_assets() {
	if ( ! is_page( 'perfil-do-associado' ) && ! is_page_template( 'page-associado.php' ) ) {
		return;
	}

	$theme = wp_get_theme();

	wp_enqueue_style( 'dashicons' );

	wp_enqueue_script(
		'setceb-associado',
		get_stylesheet_directory_uri() . '/associado.js',
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'setceb_associado_enqueue_assets' );

/**
 * Categorias exibidas no menu lateral da area do associado.
 *
 * @return array slug => rotulo
 */
function setceb_associado_categorias() {
	$categorias = array(
		'container'               => 'Container',
		'fracionada'              => 'Fracionada',
		'frigorificada'           => 'Frigorificada',
		'graos-e-solidos'         => 'Grãos e Sólidos',
		'internacional'           => 'Internacional',
		'liquida'                 => 'Líquida',
		'lotacao'                 => 'Lotação',
		'maquinas-e-equipamentos' => 'Máquinas e Equipamentos',
		'veiculos'                => 'Veículos',
	);

	return apply_filters( 'setceb_associado_categorias', $categorias );
}

/**
 * Anos disponiveis no seletor (atual ate 2015, decrescente).
 *
 * @return int[]
 */
function setceb_associado_anos() {
	$anos = range( (int) gmdate( 'Y' ), 2015 );

	return apply_filters( 'setceb_associado_anos', $anos );
}

/**
 * Planilhas disponibilizadas ao associado.
 *
 * Cada item: array com 'titulo', 'url' e opcionalmente 'descricao',
 * 'ano' e 'categoria' (slug de setceb_associado_categorias - usado
 * pelo filtro do menu lateral). Popule via filtro setceb_planilhas
 * ou integre a uma fonte de dados quando existir.
 *
 * @return array[]
 */
function setceb_planilhas() {
	return apply_filters( 'setceb_planilhas', array() );
}

/**
 * Relatorios disponibilizados ao associado.
 *
 * Cada item: array com 'titulo', 'categoria' (slug de
 * setceb_associado_categorias), 'ano', 'url' e opcionalmente
 * 'destaque' => true para receber o card em evidencia.
 *
 * @return array[]
 */
function setceb_relatorios() {
	return apply_filters( 'setceb_relatorios', array() );
}

/**
 * Convencoes coletivas disponibilizadas ao associado.
 *
 * Cada item: array com 'titulo', 'url' e opcionalmente 'ano' e
 * 'descricao'.
 *
 * @return array[]
 */
function setceb_convencoes() {
	return apply_filters( 'setceb_convencoes', array() );
}

/**
 * Assuntos do formulario Fale Conosco.
 *
 * @return array slug => rotulo
 */
function setceb_contato_assuntos() {
	$assuntos = array(
		'associacao' => 'Associação',
		'boletos'    => 'Boletos e mensalidades',
		'juridico'   => 'Assessoria jurídica',
		'cursos'     => 'Cursos e eventos',
		'convencoes' => 'Convenções coletivas',
		'outros'     => 'Outros assuntos',
	);

	return apply_filters( 'setceb_contato_assuntos', $assuntos );
}

/**
 * Destinatario dos formularios da area do associado.
 *
 * @param string $contexto 'juridico' ou 'fale-conosco'.
 * @return string
 */
function setceb_form_recipient( $contexto ) {
	return apply_filters( 'setceb_form_recipient', get_option( 'admin_email' ), $contexto );
}

/**
 * Mensagem de sucesso/erro retornada pelo envio do formulario,
 * lida dos parametros de query apos o redirect do admin-post.
 *
 * @return array|null
 */
function setceb_associado_form_notice() {
	if ( ! isset( $_GET['assoc_status'] ) ) {
		return null;
	}

	$status = sanitize_key( wp_unslash( $_GET['assoc_status'] ) );
	$forma  = isset( $_GET['assoc_form'] ) ? sanitize_key( wp_unslash( $_GET['assoc_form'] ) ) : '';

	$mensagens = array(
		'ok'       => array(
			'tipo' => 'sucesso',
			'texto' => 'Mensagem enviada com sucesso. Em breve nossa equipe entrará em contato.',
		),
		'throttle' => array(
			'tipo' => 'erro',
			'texto' => 'Aguarde alguns instantes antes de enviar uma nova mensagem.',
		),
		'campos'   => array(
			'tipo' => 'erro',
			'texto' => 'Verifique os campos obrigatórios e tente novamente.',
		),
		'erro'     => array(
			'tipo' => 'erro',
			'texto' => 'Não foi possível enviar sua mensagem agora. Tente novamente em alguns minutos.',
		),
	);

	if ( ! isset( $mensagens[ $status ] ) || ! in_array( $forma, array( 'juridico', 'fale-conosco' ), true ) ) {
		return null;
	}

	return array(
		'forma'  => $forma,
		'tipo'   => $mensagens[ $status ]['tipo'],
		'texto'  => $mensagens[ $status ]['texto'],
	);
}

/**
 * Processa o envio dos formularios (admin-post.php) e volta para a
 * pagina do perfil com status na query string.
 *
 * @param string $contexto 'juridico' ou 'fale-conosco'.
 */
function setceb_associado_process_form( $contexto ) {
	$perfil_url = setceb_associado_perfil_url();

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( $perfil_url ) );
		exit;
	}

	if ( ! setceb_is_associado() && ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( $perfil_url );
		exit;
	}

	$redirect = wp_get_referer();

	if ( ! $redirect || false === strpos( $redirect, home_url() ) ) {
		$redirect = $perfil_url;
	}

	$redirect = remove_query_arg( array( 'assoc_status', 'assoc_form' ), $redirect );

	$voltar = static function ( $status ) use ( $contexto, $redirect ) {
		$url = add_query_arg(
			array(
				'assoc_status' => $status,
				'assoc_form'   => $contexto,
			),
			$redirect
		);

		wp_safe_redirect( $url . '#panel-' . $contexto );
		exit;
	};

	// Honeypot: bots que preenchem o campo escondido sao descartados como sucesso.
	if ( ! empty( $_POST['setceb_website'] ) ) {
		$voltar( 'ok' );
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_form_' . $contexto ) ) {
		$voltar( 'erro' );
		return;
	}

	$throttle_key = 'setceb_form_' . get_current_user_id();

	if ( get_transient( $throttle_key ) ) {
		$voltar( 'throttle' );
		return;
	}

	$nome     = isset( $_POST['setceb_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_nome'] ) ) : '';
	$email    = isset( $_POST['setceb_email'] ) ? sanitize_email( wp_unslash( $_POST['setceb_email'] ) ) : '';
	$telefone = isset( $_POST['setceb_telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_telefone'] ) ) : '';
	$empresa  = isset( $_POST['setceb_empresa'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_empresa'] ) ) : '';
	$mensagem = isset( $_POST['setceb_mensagem'] ) ? sanitize_textarea_field( wp_unslash( $_POST['setceb_mensagem'] ) ) : '';

	$assunto_rotulo = '';

	if ( 'fale-conosco' === $contexto ) {
		$assuntos       = setceb_contato_assuntos();
		$assunto_slug   = isset( $_POST['setceb_assunto'] ) ? sanitize_key( wp_unslash( $_POST['setceb_assunto'] ) ) : '';
		$assunto_valido = isset( $assuntos[ $assunto_slug ] ) ? $assunto_slug : '';

		if ( '' !== $assunto_valido ) {
			$assunto_rotulo = $assuntos[ $assunto_valido ];
		}
	}

	if ( '' === $nome || '' === $mensagem || ! is_email( $email ) || ( 'fale-conosco' === $contexto && '' === $assunto_rotulo ) ) {
		$voltar( 'campos' );
		return;
	}

	$servicos = array(
		'juridico'     => 'Assessoria Jurídica',
		'fale-conosco' => 'Fale Conosco',
	);

	$user = wp_get_current_user();
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	$linhas = array(
		'Nova mensagem enviada pela Área do Associado.',
		'',
		'Serviço: ' . $servicos[ $contexto ],
		'Assunto: ' . ( '' !== $assunto_rotulo ? $assunto_rotulo : '-' ),
		'Nome: ' . $nome,
		'E-mail: ' . $email,
		'' !== $telefone ? 'Telefone: ' . $telefone : null,
		'' !== $empresa ? 'Empresa: ' . $empresa : null,
		'Usuário: ' . $user->user_login,
		'IP: ' . $ip,
		'',
		'Mensagem:',
		$mensagem,
	);

	$corpo = implode(
		"\n",
		array_values(
			array_filter(
				$linhas,
				static function ( $linha ) {
					return null !== $linha;
				}
			)
		)
	);

	$headers = array(
		'Reply-To: ' . $nome . ' <' . $email . '>',
		'Content-Type: text/plain; charset=UTF-8',
	);

	$enviado = wp_mail(
		setceb_form_recipient( $contexto ),
		sprintf( '[%s] Área do Associado - %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $servicos[ $contexto ] ),
		$corpo,
		$headers
	);

	if ( ! $enviado ) {
		$voltar( 'erro' );
		return;
	}

	set_transient( $throttle_key, 1, 30 );
	$voltar( 'ok' );
}

function setceb_associado_process_juridico() {
	setceb_associado_process_form( 'juridico' );
}

function setceb_associado_process_fale_conosco() {
	setceb_associado_process_form( 'fale-conosco' );
}
add_action( 'admin_post_setceb_juridico', 'setceb_associado_process_juridico' );
add_action( 'admin_post_nopriv_setceb_juridico', 'setceb_associado_process_juridico' );
add_action( 'admin_post_setceb_fale_conosco', 'setceb_associado_process_fale_conosco' );
add_action( 'admin_post_nopriv_setceb_fale_conosco', 'setceb_associado_process_fale_conosco' );

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
