<?php
/**
 * CE Tech - Botão e Painel de WhatsApp (Suporte / Contato)
 *
 * - Botão flutuante fixo à direita (footer) com leve efeito de pulsação.
 * - Ao clicar, abre um painel com formulário de atendimento por setor
 *   (Suporte, Financeiro, Comercial, etc.).
 * - No setor "Comercial" são exibidos os campos de perfil
 *   (residencial/empresarial) e o plano, com base nos planos cadastrados
 *   no CPT "Planos" (cetech_plano).
 * - O envio salva o lead no CPT "Leads" (cetech_lead) e abre o WhatsApp
 *   do número configurado.
 * - Página de configuração disponível em Configurações > CE Tech WhatsApp.
 *
 * Carregado em functions.php após cetech-planos.php (reutiliza a query de planos).
 *
 * @package CE Tech Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CETECH_WA_CPT', 'cetech_lead' );
define( 'CETECH_WA_CSS_HANDLE', 'cetech-whatsapp' );
define( 'CETECH_WA_JS_HANDLE', 'cetech-whatsapp' );
define( 'CETECH_WA_OPTION_GROUP', 'cetech_whatsapp_options' );
define( 'CETECH_WA_PAGE', 'cetech-whatsapp' );

/* ------------------------------------------------------------
 * 1. Registro dos assets (CSS e JS) do front-end
 * ------------------------------------------------------------ */
function cetech_wa_register_assets() {
	wp_register_style(
		CETECH_WA_CSS_HANDLE,
		get_stylesheet_directory_uri() . '/assets/css/cetech-whatsapp.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	wp_register_script(
		CETECH_WA_JS_HANDLE,
		get_stylesheet_directory_uri() . '/assets/js/cetech-whatsapp.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'cetech_wa_register_assets' );

/* ------------------------------------------------------------
 * 2. Helpers de configuração
 * ------------------------------------------------------------ */

/**
 * O botão flutuante está habilitado?
 *
 * @return bool
 */
function cetech_wa_is_enabled() {
	return '1' === get_option( 'cetech_whatsapp_enabled', '1' );
}

/**
 * Número do WhatsApp configurado (somente dígitos, com DDI).
 *
 * @return string
 */
function cetech_wa_number() {
	return preg_replace( '/\D/', '', (string) get_option( 'cetech_whatsapp_number', '' ) );
}

/**
 * Lista de setores configurados (default: Suporte, Financeiro, Comercial).
 *
 * @return array
 */
function cetech_wa_setores() {
	$raw = get_option( 'cetech_whatsapp_setores', '' );
	if ( '' === trim( (string) $raw ) ) {
		$raw = 'Suporte, Financeiro, Comercial';
	}

	$setores = array();
	foreach ( explode( ',', (string) $raw ) as $item ) {
		$item = trim( sanitize_text_field( $item ) );
		if ( '' !== $item ) {
			$setores[] = $item;
		}
	}

	return $setores;
}

/**
 * Título exibido no cabeçalho do painel.
 *
 * @return string
 */
function cetech_wa_titulo() {
	$titulo = trim( (string) get_option( 'cetech_whatsapp_titulo', '' ) );
	return '' !== $titulo ? $titulo : __( 'Fale com a CE Tech', 'Divi' );
}

/**
 * Carrega os assets e envia os dados (número, planos, nonce) ao JS.
 */
function cetech_wa_enqueue_assets() {
	if ( ! cetech_wa_is_enabled() || '' === cetech_wa_number() ) {
		return;
	}

	wp_enqueue_style( CETECH_WA_CSS_HANDLE );
	wp_enqueue_script( CETECH_WA_JS_HANDLE );

	$plans = array();
	if ( function_exists( 'cetech_planos_get_items' ) ) {
		foreach ( cetech_planos_get_items() as $plan ) {
			$plans[] = array(
				'id'     => $plan->ID,
				'title'  => get_the_title( $plan->ID ),
				'perfil' => get_post_meta( $plan->ID, '_cetech_plano_tipo', true ),
			);
		}
	}

	wp_localize_script(
		CETECH_WA_JS_HANDLE,
		'cetechWhatsapp',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cetech_whatsapp_lead' ),
			'number'  => cetech_wa_number(),
			'plans'   => $plans,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'cetech_wa_enqueue_assets', 20 );

/* ------------------------------------------------------------
 * 3. Custom Post Type "Leads"
 * ------------------------------------------------------------ */
function cetech_wa_register_post_type() {
	$labels = array(
		'name'               => _x( 'Leads', 'post type general name', 'Divi' ),
		'singular_name'      => _x( 'Lead', 'post type singular name', 'Divi' ),
		'menu_name'          => __( 'Leads', 'Divi' ),
		'add_new'            => __( 'Adicionar novo', 'Divi' ),
		'add_new_item'       => __( 'Adicionar novo lead', 'Divi' ),
		'edit_item'          => __( 'Editar lead', 'Divi' ),
		'new_item'           => __( 'Novo lead', 'Divi' ),
		'view_item'          => __( 'Ver lead', 'Divi' ),
		'search_items'       => __( 'Pesquisar leads', 'Divi' ),
		'not_found'          => __( 'Nenhum lead encontrado.', 'Divi' ),
		'not_found_in_trash' => __( 'Nenhum lead na lixeira.', 'Divi' ),
		'all_items'          => __( 'Todos os leads', 'Divi' ),
	);

	register_post_type(
		CETECH_WA_CPT,
		array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 8,
			'menu_icon'       => 'dashicons-format-chat',
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title' ),
			'rewrite'         => false,
			'show_in_rest'    => false,
			'hierarchical'    => false,
			'has_archive'     => false,
			'query_var'       => false,
		)
	);
}
add_action( 'init', 'cetech_wa_register_post_type' );

/* ------------------------------------------------------------
 * 4. Colunas da listagem de Leads
 * ------------------------------------------------------------ */
function cetech_wa_columns( $columns ) {
	$new_columns = array(
		'cb'               => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'            => __( 'Nome', 'Divi' ),
		'cetech_wa_tipo'   => __( 'Tipo', 'Divi' ),
		'cetech_wa_fone'   => __( 'Telefone', 'Divi' ),
		'cetech_wa_setor'  => __( 'Setor', 'Divi' ),
		'cetech_wa_perfil' => __( 'Perfil', 'Divi' ),
		'cetech_wa_plano'  => __( 'Plano', 'Divi' ),
		'date'             => isset( $columns['date'] ) ? $columns['date'] : __( 'Data', 'Divi' ),
	);

	return $new_columns;
}
add_filter( 'manage_' . CETECH_WA_CPT . '_posts_columns', 'cetech_wa_columns' );

function cetech_wa_render_column( $column, $post_id ) {
	switch ( $column ) {
		case 'cetech_wa_tipo':
			$tipo = get_post_meta( $post_id, '_cetech_lead_tipo_cliente', true );
			if ( 'cliente' === $tipo ) {
				echo '<span style="color:#1a7f37;font-weight:600;">' . esc_html__( 'Já é cliente', 'Divi' ) . '</span>';
			} elseif ( 'novo' === $tipo ) {
				echo '<span style="color:#2020EE;font-weight:600;">' . esc_html__( 'Novo cliente', 'Divi' ) . '</span>';
			} else {
				echo '&mdash;';
			}
			break;

		case 'cetech_wa_fone':
			echo esc_html( get_post_meta( $post_id, '_cetech_lead_fone', true ) );
			break;

		case 'cetech_wa_setor':
			echo esc_html( get_post_meta( $post_id, '_cetech_lead_setor', true ) );
			break;

		case 'cetech_wa_perfil':
			$perfil = get_post_meta( $post_id, '_cetech_lead_perfil', true );
			echo '' !== $perfil ? esc_html( ucfirst( $perfil ) ) : '&mdash;';
			break;

		case 'cetech_wa_plano':
			$plano = get_post_meta( $post_id, '_cetech_lead_plano', true );
			echo '' !== $plano ? esc_html( $plano ) : '&mdash;';
			break;
	}
}
add_action( 'manage_' . CETECH_WA_CPT . '_posts_custom_column', 'cetech_wa_render_column', 10, 2 );

/* ------------------------------------------------------------
 * 5. AJAX - Salvar o lead
 * ------------------------------------------------------------ */
function cetech_wa_save_lead() {
	check_ajax_referer( 'cetech_whatsapp_lead', 'nonce' );

	$nome     = trim( sanitize_text_field( wp_unslash( isset( $_POST['nome'] ) ? $_POST['nome'] : '' ) ) );
	$fone     = trim( sanitize_text_field( wp_unslash( isset( $_POST['fone'] ) ? $_POST['fone'] : '' ) ) );
	$setor    = trim( sanitize_text_field( wp_unslash( isset( $_POST['setor'] ) ? $_POST['setor'] : '' ) ) );
	$tipo_cliente = isset( $_POST['tipo_cliente'] ) ? sanitize_key( wp_unslash( $_POST['tipo_cliente'] ) ) : '';
	$tipo_cliente = in_array( $tipo_cliente, array( 'cliente', 'novo' ), true ) ? $tipo_cliente : '';
	$perfil   = isset( $_POST['perfil'] ) ? sanitize_key( wp_unslash( $_POST['perfil'] ) ) : '';
	$perfil   = in_array( $perfil, array( 'residencial', 'empresarial' ), true ) ? $perfil : '';
	$plano_id = isset( $_POST['plano_id'] ) ? absint( $_POST['plano_id'] ) : 0;
	$mensagem = trim( sanitize_textarea_field( wp_unslash( isset( $_POST['mensagem'] ) ? $_POST['mensagem'] : '' ) ) );

	if ( '' === $nome || '' === $setor ) {
		wp_send_json_error(
			array(
				'message' => __( 'Preencha o nome e o tipo de atendimento.', 'Divi' ),
			)
		);
	}

	if ( '' === $tipo_cliente ) {
		wp_send_json_error(
			array(
				'message' => __( 'Informe se você já é cliente.', 'Divi' ),
			)
		);
	}

	if ( 0 === strcasecmp( $setor, 'Comercial' ) && '' === $perfil ) {
		wp_send_json_error(
			array(
				'message' => __( 'Informe o perfil (residencial ou empresarial).', 'Divi' ),
			)
		);
	}

	$plano_title = '';
	if ( $plano_id && CETECH_PLANOS_CPT === get_post_type( $plano_id ) ) {
		$plano_title = get_post_field( 'post_title', $plano_id );
	}

	$lead_id = wp_insert_post(
		array(
			'post_type'   => CETECH_WA_CPT,
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s — %s', $setor, $nome ),
		),
		true
	);

	if ( is_wp_error( $lead_id ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Não foi possível salvar o contato.', 'Divi' ),
			)
		);
	}

	update_post_meta( $lead_id, '_cetech_lead_nome', $nome );
	update_post_meta( $lead_id, '_cetech_lead_fone', $fone );
	update_post_meta( $lead_id, '_cetech_lead_setor', $setor );
	update_post_meta( $lead_id, '_cetech_lead_tipo_cliente', $tipo_cliente );
	update_post_meta( $lead_id, '_cetech_lead_perfil', $perfil );

	if ( $plano_id && '' !== $plano_title ) {
		update_post_meta( $lead_id, '_cetech_lead_plano_id', $plano_id );
		update_post_meta( $lead_id, '_cetech_lead_plano', $plano_title );
	}

	if ( '' !== $mensagem ) {
		update_post_meta( $lead_id, '_cetech_lead_mensagem', $mensagem );
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_cetech_wa_save_lead', 'cetech_wa_save_lead' );
add_action( 'wp_ajax_nopriv_cetech_wa_save_lead', 'cetech_wa_save_lead' );

/* ------------------------------------------------------------
 * 6. Página de configuração (Configurações > CE Tech WhatsApp)
 * ------------------------------------------------------------ */
function cetech_wa_settings_init() {
	register_setting(
		CETECH_WA_OPTION_GROUP,
		'cetech_whatsapp_enabled',
		array(
			'type'              => 'string',
			'default'           => '1',
			'sanitize_callback' => function ( $value ) {
				return '1' === (string) $value ? '1' : '0';
			},
		)
	);

	register_setting(
		CETECH_WA_OPTION_GROUP,
		'cetech_whatsapp_number',
		array(
			'type'              => 'string',
			'sanitize_callback' => function ( $value ) {
				return preg_replace( '/\D/', '', (string) $value );
			},
		)
	);

	register_setting(
		CETECH_WA_OPTION_GROUP,
		'cetech_whatsapp_setores',
		array(
			'type'              => 'string',
			'sanitize_callback' => function ( $value ) {
				$items = array();
				foreach ( explode( ',', (string) $value ) as $item ) {
					$item = trim( sanitize_text_field( $item ) );
					if ( '' !== $item ) {
						$items[] = $item;
					}
				}
				return implode( ', ', $items );
			},
		)
	);

	register_setting(
		CETECH_WA_OPTION_GROUP,
		'cetech_whatsapp_titulo',
		array(
			'type'              => 'string',
			'sanitize_callback' => function ( $value ) {
				return sanitize_text_field( $value );
			},
		)
	);

	add_settings_section(
		'cetech_wa_section',
		__( 'Configurações do WhatsApp', 'Divi' ),
		'cetech_wa_settings_section_render',
		CETECH_WA_PAGE
	);

	add_settings_field(
		'cetech_whatsapp_enabled',
		__( 'Ativar botão', 'Divi' ),
		'cetech_wa_field_enabled',
		CETECH_WA_PAGE,
		'cetech_wa_section'
	);

	add_settings_field(
		'cetech_whatsapp_number',
		__( 'Número do WhatsApp', 'Divi' ),
		'cetech_wa_field_number',
		CETECH_WA_PAGE,
		'cetech_wa_section'
	);

	add_settings_field(
		'cetech_whatsapp_setores',
		__( 'Setores de atendimento', 'Divi' ),
		'cetech_wa_field_setores',
		CETECH_WA_PAGE,
		'cetech_wa_section'
	);

	add_settings_field(
		'cetech_whatsapp_titulo',
		__( 'Título do painel', 'Divi' ),
		'cetech_wa_field_titulo',
		CETECH_WA_PAGE,
		'cetech_wa_section'
	);
}
add_action( 'admin_init', 'cetech_wa_settings_init' );

function cetech_wa_settings_section_render() {
	echo '<p>' . esc_html__( 'Configure o botão flutuante de WhatsApp. O lead é salvo automaticamente em "Leads" no menu lateral.', 'Divi' ) . '</p>';
}

function cetech_wa_field_enabled() {
	$enabled = get_option( 'cetech_whatsapp_enabled', '1' );
	?>
	<label for="cetech_whatsapp_enabled">
		<input type="checkbox" id="cetech_whatsapp_enabled" name="cetech_whatsapp_enabled" value="1" <?php checked( '1', $enabled ); ?> />
		<?php esc_html_e( 'Exibir o botão flutuante de WhatsApp no site', 'Divi' ); ?>
	</label>
	<?php
}

function cetech_wa_field_number() {
	$value = cetech_wa_number();
	?>
	<input type="text" class="regular-text" name="cetech_whatsapp_number" id="cetech_whatsapp_number" value="<?php echo esc_attr( $value ); ?>" placeholder="55" />
	<p class="description">
		<?php esc_html_e( 'Número com código do país, somente dígitos. Ex.: 5511999999999. É para este número que as mensagens serão enviadas.', 'Divi' ); ?>
	</p>
	<?php
}

function cetech_wa_field_setores() {
	$value = implode( ', ', cetech_wa_setores() );
	?>
	<input type="text" class="regular-text" name="cetech_whatsapp_setores" id="cetech_whatsapp_setores" value="<?php echo esc_attr( $value ); ?>" placeholder="Suporte, Financeiro, Comercial" />
	<p class="description">
		<?php esc_html_e( 'Setores separados por vírgula. O setor "Comercial" habilita a seleção de perfil e plano.', 'Divi' ); ?>
	</p>
	<?php
}

function cetech_wa_field_titulo() {
	$value = get_option( 'cetech_whatsapp_titulo', '' );
	?>
	<input type="text" class="regular-text" name="cetech_whatsapp_titulo" id="cetech_whatsapp_titulo" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php esc_attr_e( 'Fale com a CE Tech', 'Divi' ); ?>" />
	<?php
}

function cetech_wa_admin_menu() {
	add_options_page(
		__( 'CE Tech WhatsApp', 'Divi' ),
		__( 'CE Tech WhatsApp', 'Divi' ),
		'manage_options',
		CETECH_WA_PAGE,
		'cetech_wa_render_page'
	);
}
add_action( 'admin_menu', 'cetech_wa_admin_menu' );

function cetech_wa_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CE Tech WhatsApp', 'Divi' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( CETECH_WA_OPTION_GROUP ); ?>
			<?php do_settings_sections( CETECH_WA_PAGE ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ------------------------------------------------------------
 * 7. Renderização do botão flutuante + painel (footer)
 * ------------------------------------------------------------ */
function cetech_wa_render() {
	if ( ! cetech_wa_is_enabled() || '' === cetech_wa_number() ) {
		return;
	}

	$setores  = cetech_wa_setores();
	$titulo   = cetech_wa_titulo();

	ob_start();
	?>
	<div class="cetech-wa" id="cetech-wa" data-cetech-wa>
		<div class="cetech-wa__panel" id="cetech-wa-panel" role="dialog" aria-label="<?php echo esc_attr( $titulo ); ?>" aria-hidden="true">
			<header class="cetech-wa__head">
				<span class="cetech-wa__head-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
				</span>
				<div class="cetech-wa__head-text">
					<h2 class="cetech-wa__title"><?php echo esc_html( $titulo ); ?></h2>
				</div>
				<button class="cetech-wa__close" type="button" aria-label="<?php esc_attr_e( 'Fechar', 'Divi' ); ?>">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
			</header>

			<div class="cetech-wa__step" data-cetech-wa-tipo>
				<p class="cetech-wa__step-title"><?php esc_html_e( 'Você já é cliente da CE Tech?', 'Divi' ); ?></p>
				<p class="cetech-wa__step-sub"><?php esc_html_e( 'Selecione a opção para continuar o atendimento.', 'Divi' ); ?></p>
				<div class="cetech-wa__step-options">
					<button type="button" class="cetech-wa__tipo" data-tipo="cliente">
						<span class="cetech-wa__tipo-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
						</span>
						<span class="cetech-wa__tipo-label"><?php esc_html_e( 'Já sou cliente', 'Divi' ); ?></span>
					</button>
					<button type="button" class="cetech-wa__tipo" data-tipo="novo">
						<span class="cetech-wa__tipo-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
						</span>
						<span class="cetech-wa__tipo-label"><?php esc_html_e( 'Quero ser cliente', 'Divi' ); ?></span>
					</button>
				</div>
			</div>
<form class="cetech-wa__form" id="cetech-wa-form" hidden>
				<input type="hidden" name="tipo_cliente" value="" />

				<div class="cetech-wa__dados" data-cetech-wa-dados>
					<div class="cetech-wa__field">
						<label for="cetech-wa-nome"><?php esc_html_e( 'Nome', 'Divi' ); ?></label>
						<input type="text" id="cetech-wa-nome" name="nome" required autocomplete="name" placeholder="<?php esc_attr_e( 'Seu nome', 'Divi' ); ?>" />
					</div>

					<div class="cetech-wa__field">
						<label for="cetech-wa-fone"><?php esc_html_e( 'Telefone / WhatsApp', 'Divi' ); ?></label>
						<input type="tel" id="cetech-wa-fone" name="fone" autocomplete="tel" placeholder="(00) 00000-0000" />
					</div>

					<div class="cetech-wa__field">
						<label for="cetech-wa-setor"><?php esc_html_e( 'Tipo de atendimento', 'Divi' ); ?></label>
						<select id="cetech-wa-setor" name="setor" required>
							<option value=""><?php esc_html_e( 'Selecione o setor...', 'Divi' ); ?></option>
							<?php foreach ( $setores as $setor ) : ?>
								<option value="<?php echo esc_attr( $setor ); ?>"><?php echo esc_html( $setor ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<button type="button" class="cetech-wa__continue" data-cetech-wa-continue><?php esc_html_e( 'Continuar', 'Divi' ); ?></button>
				</div>

				<div class="cetech-wa__dados" data-cetech-wa-final hidden>
					<div class="cetech-wa__field" data-cetech-wa-perfil hidden>
						<label for="cetech-wa-perfil"><?php esc_html_e( 'Perfil do cliente', 'Divi' ); ?></label>
						<select id="cetech-wa-perfil" name="perfil" required>
							<option value=""><?php esc_html_e( 'Residencial ou empresarial?', 'Divi' ); ?></option>
							<option value="residencial"><?php esc_html_e( 'Residencial', 'Divi' ); ?></option>
							<option value="empresarial"><?php esc_html_e( 'Empresarial', 'Divi' ); ?></option>
						</select>
					</div>

					<div class="cetech-wa__field" data-cetech-wa-plano hidden>
						<label for="cetech-wa-plano"><?php esc_html_e( 'Plano', 'Divi' ); ?></label>
						<select id="cetech-wa-plano" name="plano" required>
							<option value=""><?php esc_html_e( 'Selecione o plano...', 'Divi' ); ?></option>
						</select>
					</div>

					<div class="cetech-wa__field">
						<label for="cetech-wa-mensagem"><?php esc_html_e( 'Mensagem', 'Divi' ); ?></label>
						<textarea id="cetech-wa-mensagem" name="mensagem" rows="3" placeholder="<?php esc_attr_e( 'Como podemos ajudar?', 'Divi' ); ?>"></textarea>
					</div>

					<div class="cetech-wa__nav">
						<button type="button" class="cetech-wa__back" data-cetech-wa-back><?php esc_html_e( 'Voltar', 'Divi' ); ?></button>
						<button type="submit" class="cetech-wa__submit"><?php esc_html_e( 'Enviar mensagem', 'Divi' ); ?></button>
					</div>
				</div>
			</form>
		</div>

		<button class="cetech-wa__btn" id="cetech-wa-btn" type="button" aria-expanded="false" aria-controls="cetech-wa-panel" aria-label="<?php esc_attr_e( 'Fale conosco pelo WhatsApp', 'Divi' ); ?>">
			<span class="cetech-wa__btn-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
			</span>
			<span class="cetech-wa__btn-label"><?php esc_html_e( 'Fale conosco', 'Divi' ); ?></span>
		</button>
	</div>
	<?php
	echo ob_get_clean();
}
add_action( 'wp_footer', 'cetech_wa_render', 15 );