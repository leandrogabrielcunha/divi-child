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
		filemtime( get_stylesheet_directory() . '/assets/css/cetech-whatsapp.css' )
	);

	wp_register_script(
		CETECH_WA_JS_HANDLE,
		get_stylesheet_directory_uri() . '/assets/js/cetech-whatsapp.js',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/js/cetech-whatsapp.js' ),
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
 * A campanha é exibida durante novembro, sem manter a oferta ativa fora do mês.
 *
 * @return bool
 */
function cetech_wa_black_november_is_active() {
	return '11' === current_time( 'm' );
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
			'blackNovember' => cetech_wa_black_november_is_active()
				? array(
					'sessionKey' => 'cetech-black-november-' . current_time( 'Y' ),
					'delay'      => 5000,
					'message'    => __( 'Olá! Vim pelo site e quero saber mais sobre as condições especiais de Black November para novos clientes.', 'Divi' ),
				)
				: null,
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
			'show_in_menu'    => 'cetech-leads-kanban',
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

/**
 * Etapas permitidas para o quadro de leads.
 *
 * @return array<string, string>
 */
function cetech_wa_lead_stages() {
	return array(
		'novo'        => __( 'Novo', 'Divi' ),
		'contato'     => __( 'Em contato', 'Divi' ),
		'qualificado' => __( 'Qualificado', 'Divi' ),
		'convertido'  => __( 'Convertido', 'Divi' ),
		'perdido'     => __( 'Perdido', 'Divi' ),
	);
}

/**
 * Retorna uma etapa válida para um lead, usando "Novo" como padrão.
 *
 * @param int $post_id ID do lead.
 * @return string
 */
function cetech_wa_get_lead_stage( $post_id ) {
	$stage  = (string) get_post_meta( $post_id, '_cetech_lead_stage', true );
	$stages = cetech_wa_lead_stages();

	return isset( $stages[ $stage ] ) ? $stage : 'novo';
}

/* ------------------------------------------------------------
 * 4. Colunas da listagem de Leads
 * ------------------------------------------------------------ */
function cetech_wa_columns( $columns ) {
	$new_columns = array(
		'cb'               => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'            => __( 'Nome', 'Divi' ),
		'cetech_wa_tipo'   => __( 'Tipo', 'Divi' ),
		'cetech_wa_stage'  => __( 'Etapa', 'Divi' ),
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

		case 'cetech_wa_stage':
			$stages = cetech_wa_lead_stages();
			echo esc_html( $stages[ cetech_wa_get_lead_stage( $post_id ) ] );
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
	update_post_meta( $lead_id, '_cetech_lead_stage', 'novo' );

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
 * 6. Quadro Kanban de Leads
 * ------------------------------------------------------------ */
function cetech_wa_add_leads_kanban_menu() {
	add_menu_page(
		__( 'Pipeline de leads', 'Divi' ),
		__( 'Leads', 'Divi' ),
		'edit_posts',
		'cetech-leads-kanban',
		'cetech_wa_render_kanban_page',
		'dashicons-format-chat',
		8
	);
}
add_action( 'admin_menu', 'cetech_wa_add_leads_kanban_menu', 8 );

/**
 * Carrega os assets somente na tela do quadro.
 *
 * @param string $hook_suffix Identificador da tela atual.
 */
function cetech_wa_kanban_admin_assets( $hook_suffix ) {
	if ( 'toplevel_page_cetech-leads-kanban' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'cetech-leads-kanban',
		get_stylesheet_directory_uri() . '/assets/css/cetech-leads-kanban.css',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/css/cetech-leads-kanban.css' )
	);

	wp_enqueue_script(
		'cetech-leads-kanban',
		get_stylesheet_directory_uri() . '/assets/js/cetech-leads-kanban.js',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/js/cetech-leads-kanban.js' ),
		true
	);

	wp_localize_script(
		'cetech-leads-kanban',
		'cetechLeadsBoardConfig',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cetech_leads_board' ),
			'error'   => __( 'Não foi possível atualizar a etapa. Tente novamente.', 'Divi' ),
			'saved'   => __( 'Etapa atualizada.', 'Divi' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cetech_wa_kanban_admin_assets' );

/**
 * Renderiza o quadro visual dos leads.
 */
function cetech_wa_render_kanban_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$per_page = 60;
	$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$query    = new WP_Query(
		array(
			'post_type'      => CETECH_WA_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $search,
		)
	);
	$stages   = cetech_wa_lead_stages();
	?>
	<div class="wrap cetech-leads">
		<header class="cetech-leads__header">
			<div>
				<p class="cetech-leads__eyebrow"><?php esc_html_e( 'Relacionamento comercial', 'Divi' ); ?></p>
				<h1><?php esc_html_e( 'Pipeline de leads', 'Divi' ); ?></h1>
				<p><?php esc_html_e( 'Arraste os cartões entre as etapas para acompanhar cada oportunidade.', 'Divi' ); ?></p>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . CETECH_WA_CPT ) ); ?>">
				<?php esc_html_e( 'Adicionar lead', 'Divi' ); ?>
			</a>
		</header>

		<form class="cetech-leads__search" method="get">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( CETECH_WA_CPT ); ?>" />
			<input type="hidden" name="page" value="cetech-leads-kanban" />
			<label class="screen-reader-text" for="cetech-leads-search"><?php esc_html_e( 'Pesquisar leads', 'Divi' ); ?></label>
			<input id="cetech-leads-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Pesquisar por nome ou título...', 'Divi' ); ?>" />
			<button class="button" type="submit"><?php esc_html_e( 'Pesquisar', 'Divi' ); ?></button>
		</form>

		<div class="cetech-leads__notice" data-cetech-leads-notice role="status" aria-live="polite" hidden></div>

		<?php if ( $query->have_posts() ) : ?>
			<p class="cetech-leads__summary">
				<?php
				printf(
					/* translators: 1: number of leads on this page, 2: total number of matching leads. */
					esc_html__( 'Exibindo %1$d de %2$d leads mais recentes. As contagens das colunas consideram esta página.', 'Divi' ),
					(int) $query->post_count,
					(int) $query->found_posts
				);
				?>
			</p>
			<div class="cetech-leads__board" data-cetech-leads-board>
				<?php foreach ( $stages as $stage_key => $stage_label ) : ?>
					<section class="cetech-leads__column" data-cetech-leads-column="<?php echo esc_attr( $stage_key ); ?>" aria-label="<?php echo esc_attr( $stage_label ); ?>">
						<header class="cetech-leads__column-head">
							<h2><?php echo esc_html( $stage_label ); ?></h2>
							<span class="cetech-leads__count" data-cetech-leads-count>0</span>
						</header>
						<div class="cetech-leads__cards" data-cetech-leads-cards>
							<?php
							foreach ( $query->posts as $lead ) :
								$lead_stage = cetech_wa_get_lead_stage( $lead->ID );
								if ( $stage_key !== $lead_stage ) {
									continue;
								}

								$name    = get_post_meta( $lead->ID, '_cetech_lead_nome', true );
								$name    = '' !== $name ? $name : get_the_title( $lead->ID );
								$phone   = get_post_meta( $lead->ID, '_cetech_lead_fone', true );
								$digits  = preg_replace( '/\D/', '', (string) $phone );
								$type    = get_post_meta( $lead->ID, '_cetech_lead_tipo_cliente', true );
								$sector  = get_post_meta( $lead->ID, '_cetech_lead_setor', true );
								$profile = get_post_meta( $lead->ID, '_cetech_lead_perfil', true );
								$plan    = get_post_meta( $lead->ID, '_cetech_lead_plano', true );
								$message = get_post_meta( $lead->ID, '_cetech_lead_mensagem', true );

								if ( 10 === strlen( $digits ) || 11 === strlen( $digits ) ) {
									$digits = '55' . $digits;
								}
								?>
								<article class="cetech-leads__card" draggable="true" tabindex="0" data-cetech-lead-card data-lead-id="<?php echo esc_attr( $lead->ID ); ?>" data-stage="<?php echo esc_attr( $lead_stage ); ?>">
									<div class="cetech-leads__card-top">
										<span class="cetech-leads__type <?php echo 'novo' === $type ? 'is-new' : 'is-customer'; ?>">
											<?php echo 'cliente' === $type ? esc_html__( 'Cliente', 'Divi' ) : esc_html__( 'Novo cliente', 'Divi' ); ?>
										</span>
										<time datetime="<?php echo esc_attr( get_post_time( 'c', true, $lead ) ); ?>"><?php echo esc_html( get_the_date( 'd/m/Y', $lead ) ); ?></time>
									</div>
									<h3><?php echo esc_html( $name ); ?></h3>
									<?php if ( '' !== $phone ) : ?>
										<p class="cetech-leads__detail"><?php echo esc_html( $phone ); ?></p>
									<?php endif; ?>
									<div class="cetech-leads__meta">
										<?php if ( '' !== $sector ) : ?><span><?php echo esc_html( $sector ); ?></span><?php endif; ?>
										<?php if ( '' !== $profile ) : ?><span><?php echo esc_html( ucfirst( $profile ) ); ?></span><?php endif; ?>
										<?php if ( '' !== $plan ) : ?><span><?php echo esc_html( $plan ); ?></span><?php endif; ?>
									</div>
									<?php if ( '' !== $message ) : ?>
										<p class="cetech-leads__message"><?php echo esc_html( wp_trim_words( sanitize_textarea_field( $message ), 16 ) ); ?></p>
									<?php endif; ?>
									<div class="cetech-leads__card-foot">
										<label class="screen-reader-text" for="cetech-lead-stage-<?php echo esc_attr( $lead->ID ); ?>"><?php esc_html_e( 'Mover lead para', 'Divi' ); ?></label>
										<select id="cetech-lead-stage-<?php echo esc_attr( $lead->ID ); ?>" data-cetech-lead-stage-select>
											<?php foreach ( $stages as $option_key => $option_label ) : ?>
												<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $lead_stage, $option_key ); ?>><?php echo esc_html( $option_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<div class="cetech-leads__actions">
											<a href="<?php echo esc_url( get_edit_post_link( $lead->ID ) ); ?>"><?php esc_html_e( 'Editar', 'Divi' ); ?></a>
											<?php if ( '' !== $digits ) : ?>
												<a href="<?php echo esc_url( 'https://wa.me/' . $digits ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WhatsApp', 'Divi' ); ?></a>
											<?php endif; ?>
										</div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>

			<?php if ( $query->max_num_pages > 1 ) : ?>
				<nav class="cetech-leads__pagination" aria-label="<?php esc_attr_e( 'Paginação dos leads', 'Divi' ); ?>">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $query->max_num_pages,
								'prev_text' => __( '← Anterior', 'Divi' ),
								'next_text' => __( 'Próxima →', 'Divi' ),
							)
						)
					);
					?>
				</nav>
			<?php endif; ?>
		<?php else : ?>
			<div class="cetech-leads__empty">
				<strong><?php esc_html_e( 'Nenhum lead encontrado.', 'Divi' ); ?></strong>
				<p><?php esc_html_e( 'Quando alguém entrar em contato pelo site, o lead aparecerá aqui.', 'Divi' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
	wp_reset_postdata();
}

/**
 * Atualiza a etapa do lead após validar permissão e nonce.
 */
function cetech_wa_update_lead_stage() {
	check_ajax_referer( 'cetech_leads_board', 'nonce' );

	$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
	$stage   = isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : '';
	$stages  = cetech_wa_lead_stages();

	if ( ! $lead_id || CETECH_WA_CPT !== get_post_type( $lead_id ) || ! current_user_can( 'edit_post', $lead_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Você não tem permissão para alterar este lead.', 'Divi' ) ), 403 );
	}

	if ( ! isset( $stages[ $stage ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Etapa inválida.', 'Divi' ) ), 400 );
	}

	$updated = update_post_meta( $lead_id, '_cetech_lead_stage', $stage );
	if ( false === $updated && $stage !== get_post_meta( $lead_id, '_cetech_lead_stage', true ) ) {
		wp_send_json_error( array( 'message' => __( 'Não foi possível salvar a etapa do lead.', 'Divi' ) ), 500 );
	}

	wp_send_json_success( array( 'stage' => $stage ) );
}
add_action( 'wp_ajax_cetech_wa_update_lead_stage', 'cetech_wa_update_lead_stage' );

/* ------------------------------------------------------------
 * 7. Página de configuração (Configurações > CE Tech WhatsApp)
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
 * 8. Renderização do botão flutuante + painel (footer)
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
				<button type="button" class="cetech-wa__backicon" data-cetech-wa-back-tipo aria-label="<?php esc_attr_e( 'Voltar', 'Divi' ); ?>">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
					</button>

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
						<select id="cetech-wa-perfil" name="perfil">
							<option value=""><?php esc_html_e( 'Residencial ou empresarial?', 'Divi' ); ?></option>
							<option value="residencial"><?php esc_html_e( 'Residencial', 'Divi' ); ?></option>
							<option value="empresarial"><?php esc_html_e( 'Empresarial', 'Divi' ); ?></option>
						</select>
					</div>

					<div class="cetech-wa__field" data-cetech-wa-plano hidden>
						<label for="cetech-wa-plano"><?php esc_html_e( 'Plano', 'Divi' ); ?></label>
						<select id="cetech-wa-plano" name="plano">
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

		<?php if ( cetech_wa_black_november_is_active() ) : ?>
			<div class="cetech-blacknovember" data-cetech-blacknovember hidden aria-hidden="true">
				<button class="cetech-blacknovember__backdrop" type="button" data-cetech-blacknovember-close aria-label="<?php esc_attr_e( 'Fechar oferta Black November', 'Divi' ); ?>"></button>
				<section class="cetech-blacknovember__dialog" role="dialog" aria-modal="true" aria-labelledby="cetech-blacknovember-title" aria-describedby="cetech-blacknovember-description" tabindex="-1">
					<button class="cetech-blacknovember__close" type="button" data-cetech-blacknovember-close aria-label="<?php esc_attr_e( 'Fechar', 'Divi' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m18 6-12 12M6 6l12 12"/></svg>
					</button>
					<span class="cetech-blacknovember__badge"><?php esc_html_e( 'Oferta especial', 'Divi' ); ?></span>
					<p class="cetech-blacknovember__eyebrow"><?php esc_html_e( 'BLACK', 'Divi' ); ?></p>
					<h2 id="cetech-blacknovember-title"><?php esc_html_e( 'NOVEMBER', 'Divi' ); ?></h2>
					<p class="cetech-blacknovember__copy" id="cetech-blacknovember-description">
						<?php esc_html_e( 'Condições especiais para novos clientes. Fale com nosso time e descubra a melhor opção para você.', 'Divi' ); ?>
					</p>
					<button class="cetech-blacknovember__cta" type="button" data-cetech-blacknovember-cta>
						<?php esc_html_e( 'Quero aproveitar', 'Divi' ); ?>
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
					</button>
					<p class="cetech-blacknovember__footnote"><?php esc_html_e( 'Atendimento direto pelo WhatsApp', 'Divi' ); ?></p>
				</section>
			</div>
		<?php endif; ?>

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
