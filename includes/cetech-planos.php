<?php
/**
 * CE Tech - Planos (cards via shortcode)
 *
 * Registra o Custom Post Type "Planos", os campos customizados no editor
 * (incluindo as listas dinamicas de beneficios e de apps/canais do plano),
 * sementia os planos iniciais e expoe o shortcode [planos] que renderiza
 * SOMENTE os cards dos planos ativos, ordenados pela ordem cadastrada,
 * com o modal de detalhes de cada plano.
 *
 * Carregado em functions.php.
 *
 * @package CE Tech Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * Constantes
 * ------------------------------------------------------------ */
define( 'CETECH_PLANOS_CPT', 'cetech_plano' );
define( 'CETECH_PLANOS_CSS_HANDLE', 'cetech-planos' );
define( 'CETECH_PLANOS_JS_HANDLE', 'cetech-planos' );

/* Meta key da lista de apps/canais que o cliente pode escolher em cada plano. */
define( 'CETECH_PLANOS_APPS_META', '_cetech_plano_apps' );

/**
 * Meta do destino do botão "Contratar".
 *
 * "whatsapp" abre o chat flutuante da propria pagina (sem sair do site);
 * "link" segue o link cadastrado no campo "Link do botão".
 */
define( 'CETECH_PLANOS_CTA_TARGET_META', '_cetech_plano_cta_target' );

/* Meta key e padrao da palavra separadora queprefixa cada app. */
define( 'CETECH_PLANOS_APPS_SEPARATOR_META', '_cetech_plano_apps_separator' );
/* Legado: "ocultar separador". Continua valendo -- se estiver "1", o
   separador nao aparece, mesmo que a flag nova de exibir esteja ligada. */
define( 'CETECH_PLANOS_APPS_SEPARATOR_HIDE_META', '_cetech_plano_apps_separator_hide' );

/* Meta do separador entre apps. PADRAO DESLIGADO: sem separador a
   vitrine mostra 3 apps por linha; com o separador, 2 por linha. */
define( 'CETECH_PLANOS_APPS_SEPARATOR_SHOW_META', '_cetech_plano_apps_separator_show' );
define( 'CETECH_PLANOS_APPS_SEPARATOR_DEFAULT', 'OU' );

/* ------------------------------------------------------------
 * 1. Registro do CSS/JS do front-end (isolado)
 * ------------------------------------------------------------ */
/**
 * Versao de cache-busting de um asset do tema.
 *
 * Usa a data de modificacao do arquivo junto com a versao do tema. Assim o
 * navegador busca o arquivo de novo sempre que ele for editado, mesmo que a
 * versao do tema ainda nao tenha sido incrementada. Se o arquivo nao estiver
 * legivel, cai na versao do tema para nunca devolver string vazia.
 *
 * @param string $relative Caminho relativo dentro do tema filho.
 * @return string Versao para wp_enqueue_style/script.
 */
function cetech_planos_asset_version( $relative ) {
	$theme_version = wp_get_theme()->get( 'Version' );
	$relative      = ltrim( (string) $relative, '/' );

	/* Só aceita caminho interno do tema: evita que a busca de arquivo
	   escape do tema filho e leia algo fora dele. */
	if ( '' === $relative || false !== strpos( $relative, '../' ) ) {
		return (string) $theme_version;
	}

	$path = get_stylesheet_directory() . '/' . $relative;

	if ( ! is_readable( $path ) ) {
		return (string) $theme_version;
	}

	$mtime = filemtime( $path );

	if ( ! $mtime ) {
		return (string) $theme_version;
	}

	return $theme_version . '.' . $mtime;
}

function cetech_planos_register_assets() {
	wp_register_style(
		CETECH_PLANOS_CSS_HANDLE,
		get_stylesheet_directory_uri() . '/assets/css/cetech-planos.css',
		array(),
		cetech_planos_asset_version( 'assets/css/cetech-planos.css' )
	);

	wp_register_script(
		CETECH_PLANOS_JS_HANDLE,
		get_stylesheet_directory_uri() . '/assets/js/cetech-planos.js',
		array(),
		cetech_planos_asset_version( 'assets/js/cetech-planos.js' ),
		true
	);
}

function cetech_planos_enqueue_assets() {
	/* Enfileira direto no wp_enqueue_scripts (o shortcode renderiza
	 * tarde no Divi e nao imprime style no frontend). */
	wp_enqueue_style( CETECH_PLANOS_CSS_HANDLE );
	wp_enqueue_script( CETECH_PLANOS_JS_HANDLE );
}
add_action( 'wp_enqueue_scripts', 'cetech_planos_register_assets' );
add_action( 'wp_enqueue_scripts', 'cetech_planos_enqueue_assets', 20 );

/* ------------------------------------------------------------
 * 2. Registro do Custom Post Type "Planos"
 * ------------------------------------------------------------ */
function cetech_planos_register_post_type() {
	$labels = array(
		'name'               => _x( 'Planos', 'post type general name', 'Divi' ),
		'singular_name'      => _x( 'Plano', 'post type singular name', 'Divi' ),
		'menu_name'          => __( 'Planos', 'Divi' ),
		'add_new'            => __( 'Adicionar novo', 'Divi' ),
		'add_new_item'       => __( 'Adicionar novo plano', 'Divi' ),
		'edit_item'          => __( 'Editar plano', 'Divi' ),
		'new_item'           => __( 'Novo plano', 'Divi' ),
		'view_item'          => __( 'Ver plano', 'Divi' ),
		'search_items'       => __( 'Pesquisar planos', 'Divi' ),
		'not_found'          => __( 'Nenhum plano encontrado.', 'Divi' ),
		'not_found_in_trash' => __( 'Nenhum plano na lixeira.', 'Divi' ),
		'all_items'          => __( 'Todos os planos', 'Divi' ),
	);

	register_post_type(
		CETECH_PLANOS_CPT,
		array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 7,
			'menu_icon'       => 'dashicons-screenoptions',
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
add_action( 'init', 'cetech_planos_register_post_type' );

/* ------------------------------------------------------------
 * 3. Colunas da listagem do CPT
 * ------------------------------------------------------------ */
function cetech_planos_columns( $columns ) {
	$new_columns = array(
		'cb'          => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'       => isset( $columns['title'] ) ? $columns['title'] : __( 'Plano', 'Divi' ),
		'cetech_tipo' => __( 'Tipo', 'Divi' ),
		'cetech_ord'  => __( 'Ordem', 'Divi' ),
		'cetech_stt'  => __( 'Status', 'Divi' ),
	);

	return $new_columns;
}
add_filter( 'manage_' . CETECH_PLANOS_CPT . '_posts_columns', 'cetech_planos_columns' );

function cetech_planos_render_column( $column, $post_id ) {
	switch ( $column ) {
		case 'cetech_tipo':
			$tipo = get_post_meta( $post_id, '_cetech_plano_tipo', true );
			$tipo = in_array( $tipo, array( 'residencial', 'empresarial' ), true ) ? $tipo : 'residencial';
			echo esc_html( ucfirst( $tipo ) );
			break;

		case 'cetech_ord':
			$order = get_post_field( 'menu_order', $post_id );
			echo esc_html( (string) absint( $order ) );
			break;

		case 'cetech_stt':
			$active = get_post_meta( $post_id, '_cetech_plano_active', true );
			if ( '1' === (string) $active ) {
				echo '<span style="color:#1a7f37;font-weight:600;">' . esc_html__( 'Ativo', 'Divi' ) . '</span>';
			} else {
				echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Inativo', 'Divi' ) . '</span>';
			}
			break;
	}
}
add_action( 'manage_' . CETECH_PLANOS_CPT . '_posts_custom_column', 'cetech_planos_render_column', 10, 2 );

function cetech_planos_sortable_columns( $columns ) {
	$columns['cetech_ord'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-' . CETECH_PLANOS_CPT . '_sortable_columns', 'cetech_planos_sortable_columns' );

/* ------------------------------------------------------------
 * 4. Metabox com os campos do Plano
 * ------------------------------------------------------------ */
function cetech_planos_add_meta_box() {
	add_meta_box(
		'cetech_plano_fields',
		__( 'Configurações do Plano', 'Divi' ),
		'cetech_planos_meta_box_render',
		CETECH_PLANOS_CPT,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'cetech_planos_add_meta_box' );

function cetech_planos_meta_box_render( $post ) {
	wp_nonce_field( 'cetech_plano_fields', 'cetech_plano_fields_nonce' );

	$speed        = get_post_meta( $post->ID, '_cetech_plano_speed', true );
	$tipo         = get_post_meta( $post->ID, '_cetech_plano_tipo', true );
	$price_old    = get_post_meta( $post->ID, '_cetech_plano_price_old', true );
	$price        = get_post_meta( $post->ID, '_cetech_plano_price', true );
	$period       = get_post_meta( $post->ID, '_cetech_plano_period', true );
	$badge        = get_post_meta( $post->ID, '_cetech_plano_badge', true );
	$desc         = get_post_meta( $post->ID, '_cetech_plano_desc', true );
	$benefits     = get_post_meta( $post->ID, '_cetech_plano_benefits', true );
	$apps         = cetech_planos_get_apps_raw( $post->ID );
	$separator    = cetech_planos_get_separator( $post->ID );
	$sep_shown    = '1' === (string) get_post_meta( $post->ID, CETECH_PLANOS_APPS_SEPARATOR_SHOW_META, true );
	$sep_hidden   = '1' === (string) get_post_meta( $post->ID, CETECH_PLANOS_APPS_SEPARATOR_HIDE_META, true );
	/* O legado "ocultar" desligado sem a flag nova ligada significa o padrao:
	   sem separador. */
	$sep_shown    = $sep_shown && ! $sep_hidden;
	$btn_text     = get_post_meta( $post->ID, '_cetech_plano_btn_text', true );
	$btn_link     = get_post_meta( $post->ID, '_cetech_plano_btn_link', true );
	$cta_target   = cetech_planos_cta_target( $post->ID );
	$active       = get_post_meta( $post->ID, '_cetech_plano_active', true );
	$order        = absint( get_post_field( 'menu_order', $post->ID ) );

	if ( ! is_array( $benefits ) ) {
		$benefits = array();
	}
	if ( '' === $active ) {
		$active = '1';
	}
	if ( '' === $btn_text ) {
		$btn_text = 'Contratar';
	}
	if ( '' === $tipo ) {
		$tipo = 'residencial';
	}
	?>
	<div class="cetech-planos-admin">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="cetech-plano-speed"><?php esc_html_e( 'Velocidade', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_plano_speed" id="cetech-plano-speed" value="<?php echo esc_attr( $speed ); ?>" placeholder="<?php esc_attr_e( 'Ex.: 250 Mega', 'Divi' ); ?>" />
					<p class="description"><?php esc_html_e( 'Ex.: "250 Mega". O número é destacado no card.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tipo de plano', 'Divi' ); ?></th>
				<td>
					<label for="cetech-plano-tipo-residencial" style="margin-right:12px;">
						<input type="radio" name="_cetech_plano_tipo" id="cetech-plano-tipo-residencial" value="residencial" <?php checked( 'residencial', $tipo ); ?> />
						<?php esc_html_e( 'Residencial', 'Divi' ); ?>
					</label>
					<label for="cetech-plano-tipo-empresarial">
						<input type="radio" name="_cetech_plano_tipo" id="cetech-plano-tipo-empresarial" value="empresarial" <?php checked( 'empresarial', $tipo ); ?> />
						<?php esc_html_e( 'Empresarial', 'Divi' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Use o filtro "tipo" no shortcode para exibir apenas planos residenciais ou empresariais.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-price-old"><?php esc_html_e( 'Preço anterior', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_plano_price_old" id="cetech-plano-price-old" value="<?php echo esc_attr( $price_old ); ?>" placeholder="<?php esc_attr_e( 'Ex.: 99,90', 'Divi' ); ?>" />
					<p class="description"><?php esc_html_e( 'Valor riscado (sem "R$"). Deixe vazio para não exibir.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-price"><?php esc_html_e( 'Preço atual', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_plano_price" id="cetech-plano-price" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php esc_attr_e( 'Ex.: 79,90', 'Divi' ); ?>" />
					<p class="description"><?php esc_html_e( 'Valor em destaque (sem "R$").', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-period"><?php esc_html_e( 'Texto de periodicidade', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_plano_period" id="cetech-plano-period" value="<?php echo esc_attr( $period ); ?>" placeholder="<?php esc_attr_e( 'Ex.: /mês', 'Divi' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-badge"><?php esc_html_e( 'Badge de destaque', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_plano_badge" id="cetech-plano-badge" value="<?php echo esc_attr( $badge ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Mais vendido', 'Divi' ); ?>" />
					<p class="description"><?php esc_html_e( 'Se preenchido, o card ganha destaque com o selo no topo.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-desc"><?php esc_html_e( 'Descrição curta', 'Divi' ); ?></label>
				</th>
				<td>
					<textarea class="large-text" rows="3" name="_cetech_plano_desc" id="cetech-plano-desc"><?php echo esc_textarea( $desc ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-btn-text"><?php esc_html_e( 'Texto do botão', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_plano_btn_text" id="cetech-plano-btn-text" value="<?php echo esc_attr( $btn_text ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-cta-target"><?php esc_html_e( 'Ao clicar em Contratar', 'Divi' ); ?></label>
				</th>
				<td>
					<select name="<?php echo esc_attr( CETECH_PLANOS_CTA_TARGET_META ); ?>" id="cetech-plano-cta-target" data-cetech-cta-target>
						<option value="whatsapp" <?php selected( 'whatsapp', $cta_target ); ?>><?php esc_html_e( 'Abrir o WhatsApp desta página', 'Divi' ); ?></option>
						<option value="link" <?php selected( 'link', $cta_target ); ?>><?php esc_html_e( 'Ir para o link informado abaixo', 'Divi' ); ?></option>
					</select>
					<p class="description" data-cetech-cta-target-help>
						<?php if ( cetech_planos_tem_whatsapp() ) : ?>
							<?php esc_html_e( 'O cliente continua no site: abre o chat flutuante com este plano já selecionado. O campo "Link do botão" abaixo só é usado na segunda opção.', 'Divi' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'O chat flutuante está desligado ou sem número em Configurações > CE Tech WhatsApp, então o botão usa o link abaixo.', 'Divi' ); ?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-btn-link"><?php esc_html_e( 'Link do botão', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="url" class="regular-text" name="_cetech_plano_btn_link" id="cetech-plano-btn-link" value="<?php echo esc_url( $btn_link ); ?>" placeholder="https://" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Lista de benefícios', 'Divi' ); ?>
				</th>
				<td>
					<div class="cetech-benefits" data-cetech-benefits>
						<div class="cetech-benefits__list" data-cetech-benefits-list>
							<?php if ( empty( $benefits ) ) : ?>
								<div class="cetech-benefits__row">
									<input type="text" class="regular-text cetech-benefits__input" name="_cetech_plano_benefits[]" value="" placeholder="<?php esc_attr_e( 'Ex.: 100% Fibra Óptica', 'Divi' ); ?>" />
									<button type="button" class="button cetech-benefits__up" aria-label="<?php esc_attr_e( 'Mover para cima', 'Divi' ); ?>">&uarr;</button>
									<button type="button" class="button cetech-benefits__down" aria-label="<?php esc_attr_e( 'Mover para baixo', 'Divi' ); ?>">&darr;</button>
									<button type="button" class="button-link delete cetech-benefits__remove" aria-label="<?php esc_attr_e( 'Remover benefício', 'Divi' ); ?>"><?php esc_html_e( 'Remover', 'Divi' ); ?></button>
								</div>
							<?php else : ?>
								<?php foreach ( $benefits as $benefit ) : ?>
									<div class="cetech-benefits__row">
										<input type="text" class="regular-text cetech-benefits__input" name="_cetech_plano_benefits[]" value="<?php echo esc_attr( $benefit ); ?>" />
										<button type="button" class="button cetech-benefits__up" aria-label="<?php esc_attr_e( 'Mover para cima', 'Divi' ); ?>">&uarr;</button>
										<button type="button" class="button cetech-benefits__down" aria-label="<?php esc_attr_e( 'Mover para baixo', 'Divi' ); ?>">&darr;</button>
										<button type="button" class="button-link delete cetech-benefits__remove" aria-label="<?php esc_attr_e( 'Remover benefício', 'Divi' ); ?>"><?php esc_html_e( 'Remover', 'Divi' ); ?></button>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<button type="button" class="button cetech-benefits__add"><?php esc_html_e( '+ Adicionar benefício', 'Divi' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Adicione, reordene ou remova os benefícios. A ordem aqui é a ordem exibida no card.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Apps / Canais', 'Divi' ); ?>
				</th>
				<td>
					<?php cetech_planos_render_apps_field( $apps, $separator, $sep_shown ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-plano-order"><?php esc_html_e( 'Ordem de exibição', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="number" class="small-text" name="cetech_plano_order" id="cetech-plano-order" value="<?php echo esc_attr( (string) $order ); ?>" min="0" step="1" />
					<p class="description"><?php esc_html_e( 'Menor valor é exibido primeiro.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'Divi' ); ?></th>
				<td>
					<label for="cetech-plano-active" style="margin-right:12px;">
						<input type="radio" name="_cetech_plano_active" id="cetech-plano-active" value="1" <?php checked( '1', $active ); ?> />
						<?php esc_html_e( 'Ativo', 'Divi' ); ?>
					</label>
					<label for="cetech-plano-inactive">
						<input type="radio" name="_cetech_plano_active" id="cetech-plano-inactive" value="0" <?php checked( '0', $active ); ?> />
						<?php esc_html_e( 'Inativo', 'Divi' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Somente planos ativos são exibidos pelo shortcode.', 'Divi' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
	<?php
}

/**
 * Le a lista de apps/canais do plano exatamente como esta no meta,
 * normalizando a estrutura para o formulario do admin.
 *
 * @param int $post_id ID do plano.
 * @return array<int,array<string,mixed>> Lista vazia quando nao ha apps.
 */
function cetech_planos_get_apps_raw( $post_id ) {
	$apps = get_post_meta( $post_id, CETECH_PLANOS_APPS_META, true );

	if ( ! is_array( $apps ) ) {
		return array();
	}

	$out = array();

	foreach ( $apps as $app ) {
		if ( ! is_array( $app ) ) {
			continue;
		}

		$out[] = array(
			'nome'     => isset( $app['nome'] ) ? (string) $app['nome'] : '',
			'imagem'   => isset( $app['imagem'] ) ? absint( $app['imagem'] ) : 0,
			'desc'     => isset( $app['desc'] ) ? (string) $app['desc'] : '',
			'detalhes' => isset( $app['detalhes'] ) ? (string) $app['detalhes'] : '',
			'ativo'    => ! isset( $app['ativo'] ) || '1' === (string) $app['ativo'] ? '1' : '0',
		);
	}

	return $out;
}

/**
 * Palavra separadora exibida entre os dois grupos de apps.
 *
 * Texto vazio cai no padrao "OU": qualquer gravacao que nao passe pelo
 * metabox (edicao rapida, bulk edit, REST, revisao) pode gravar vazio e
 * desligaria o separador de forma permanente e silenciosa. Ocultar e uma
 * decisao explicita, feita pelo checkbox dedicado.
 *
 * @param int|WP_Post $post Post ou ID do plano.
 * @return string String vazia quando o separador foi ocultado.
 */
function cetech_planos_get_separator( $post ) {
	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;

	/* Padrao e NAO exibir: a vitrine mostra 3 apps por linha sem a palavra.
	   O legado "ocutar" continua mandando, para um plano que ja estava com o
	   separador escondido nao voltar a mostrar. */
	if ( '1' === (string) get_post_meta( $post_id, CETECH_PLANOS_APPS_SEPARATOR_HIDE_META, true ) ) {
		return '';
	}

	if ( '1' !== (string) get_post_meta( $post_id, CETECH_PLANOS_APPS_SEPARATOR_SHOW_META, true ) ) {
		return '';
	}

	$value = trim( (string) get_post_meta( $post_id, CETECH_PLANOS_APPS_SEPARATOR_META, true ) );

	return '' !== $value ? $value : CETECH_PLANOS_APPS_SEPARATOR_DEFAULT;
}

/**
 * Indica se um app/canal esta completamente vazio no cadastro.
 *
 * O nome e opcional: um item so e descartado quando nao possui
 * nome, logo, descricao nem detalhes.
 *
 * @param array<string,mixed> $app Item normalizado do app.
 * @return bool
 */
function cetech_planos_app_is_empty( $app ) {
	$nome     = isset( $app['nome'] ) ? trim( (string) $app['nome'] ) : '';
	$imagem   = isset( $app['imagem'] ) ? absint( $app['imagem'] ) : 0;
	$desc     = isset( $app['desc'] ) ? trim( (string) $app['desc'] ) : '';
	$detalhes = isset( $app['detalhes'] ) ? trim( (string) $app['detalhes'] ) : '';

	return '' === $nome && 0 === $imagem && '' === $desc && '' === $detalhes;
}

/**
 * Le a lista de apps/canais ja filtrada para exibicao no frontend.
 *
 * Descarta inativos e itens totalmente vazios, e resolve a URL do logo a
 * partir do attachment ID cadastrado pelo administrador.
 *
 * @param int|WP_Post $post Post ou ID do plano.
 * @return array<int,array{name:string,logo:string,desc:string,detalhes:string}>
 */
function cetech_planos_get_apps( $post ) {
	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$apps    = array();

	foreach ( cetech_planos_get_apps_raw( $post_id ) as $app ) {
		if ( '1' !== $app['ativo'] || cetech_planos_app_is_empty( $app ) ) {
			continue;
		}

		$logo = $app['imagem'] ? wp_get_attachment_image_url( $app['imagem'], 'medium' ) : '';

		$apps[] = array(
			'name'     => trim( $app['nome'] ),
			'logo'     => $logo ? $logo : '',
			'desc'     => trim( $app['desc'] ),
			'detalhes' => trim( $app['detalhes'] ),
		);
	}

	return $apps;
}

/**
 * Renderiza o repeater de apps/canais no metabox do plano.
 *
 * @param array<int,array<string,mixed>> $apps      Lista de apps ja normalizada.
 * @param string                         $separator Palavra separadora dos apps.
 * @param bool                           $hidden    Se o separador foi ocultado.
 * @return void
 */
function cetech_planos_render_apps_field( $apps, $separator, $shown = false ) {
	/* Só o total de apps ativos: o cliente escolhe um entre eles, e não
	   existe contagem por grupo. O total aqui é só para a nota de
	   "exibição atual" no admin. */
	$total = 0;

	foreach ( $apps as $app ) {
		if ( '1' !== $app['ativo'] || cetech_planos_app_is_empty( $app ) ) {
			continue;
		}

		$total++;
	}

	?>
	<div class="cetech-apps" data-cetech-apps>
		<div class="cetech-apps__list" data-cetech-apps-list>
			<?php if ( empty( $apps ) ) : ?>
				<?php echo cetech_planos_render_app_row( cetech_planos_empty_app(), 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado na funcao. ?>
			<?php else : ?>
				<?php foreach ( $apps as $index => $app ) : ?>
					<?php echo cetech_planos_render_app_row( $app, (int) $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado na funcao. ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<p>
			<button type="button" class="button cetech-apps__add"><?php esc_html_e( '+ Adicionar app/canal', 'Divi' ); ?></button>
		</p>
	</div>
	<p class="description"><?php esc_html_e( 'Apps e canais de streaming que o cliente pode escolher. Nome, descrição e detalhes são opcionais: basta o logo. Envie o logo em PNG ou SVG quadrado, com fundo transparente. A ordem aqui é a ordem exibida no card e no modal.', 'Divi' ); ?></p>
	<p class="cetech-apps__separator-field">
		<label class="cetech-apps__separator-show">
			<input type="checkbox" name="<?php echo esc_attr( CETECH_PLANOS_APPS_SEPARATOR_SHOW_META ); ?>" value="1" <?php checked( '1', $shown ); ?> />
			<?php esc_html_e( 'Mostrar separador entre os apps', 'Divi' ); ?>
		</label>
		<label for="cetech-plano-apps-separator"><?php esc_html_e( 'Palavra separadora', 'Divi' ); ?></label>
		<input type="text" class="small-text" id="cetech-plano-apps-separator" name="<?php echo esc_attr( CETECH_PLANOS_APPS_SEPARATOR_META ); ?>" value="<?php echo esc_attr( $separator ); ?>" placeholder="<?php esc_attr_e( 'Ex.: OU', 'Divi' ); ?>" maxlength="20" />
		<span class="description"><?php esc_html_e( 'Desligado (padrão): 3 apps por linha, sem a palavra. Ligado: 2 apps por linha com a palavra entre eles. Deixe a palavra vazia para usar "OU".', 'Divi' ); ?></span>
	</p>
	<p class="cetech-apps__preview-note <?php echo $total > 0 ? 'is-ok' : 'is-warning'; ?>"<?php echo ( ! $shown && 0 === $total ) ? ' style="display:none;"' : ''; ?>>
		<?php if ( 0 === $total ) : ?>
			<?php esc_html_e( 'Nenhum app ativo cadastrado ainda. Adicione o primeiro logo acima.', 'Divi' ); ?>
		<?php elseif ( 1 === $total ) : ?>
			<?php
			printf(
				/* translators: %s: palavra separadora. */
				esc_html__( 'Com um app nao ha alternativa: a palavra "%s" e ignorada na exibicao.', 'Divi' ),
				esc_html( $separator )
			);
			?>
		<?php else : ?>
			<?php
			if ( $shown ) {
				printf(
					/* translators: 1: quantidade de apps, 2: palavra separadora. */
					esc_html__( 'Exibicao atual: %1$d apps, 2 por linha com a palavra "%2$s" entre eles.', 'Divi' ),
					(int) $total,
					esc_html( $separator )
				);
			} else {
				printf(
					/* translators: %d: quantidade de apps. */
					esc_html__( 'Exibicao atual: %d apps, 3 por linha sem separador.', 'Divi' ),
					(int) $total
				);
			}
			?>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Modelo vazio de um app/canal, usado como linha inicial do repeater.
 *
 * @return array<string,mixed>
 */
function cetech_planos_empty_app() {
	return array(
		'nome'     => '',
		'imagem'   => 0,
		'desc'     => '',
		'detalhes' => '',
		'ativo'    => '1',
	);
}

/**
 * Renderiza uma linha do repeater de apps/canais.
 *
 * @param array<string,mixed> $app  Dados do app.
 * @param int                 $index Indice usado no name dos campos.
 * @return string HTML da linha.
 */
function cetech_planos_render_app_row( $app, $index ) {
	$app      = wp_parse_args( $app, cetech_planos_empty_app() );
	$field    = 'cetech_planos_apps[' . (int) $index . ']';
	$logo_url = $app['imagem'] ? wp_get_attachment_image_url( $app['imagem'], 'thumbnail' ) : '';

	ob_start();
	?>
	<div class="cetech-apps__row" data-cetech-app-row>
		<div class="cetech-apps__logo">
			<input type="hidden" class="cetech-apps__image-id" name="<?php echo esc_attr( $field . '[imagem]' ); ?>" value="<?php echo esc_attr( (string) $app['imagem'] ); ?>" />
			<div class="cetech-apps__preview" data-cetech-app-preview>
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<button type="button" class="button cetech-apps__image-select" data-media-title="<?php echo esc_attr__( 'Selecionar logo do app', 'Divi' ); ?>"><?php esc_html_e( 'Logo', 'Divi' ); ?></button>
			<button type="button" class="button-link delete cetech-apps__image-remove" <?php echo $app['imagem'] ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remover', 'Divi' ); ?></button>
		</div>

		<div class="cetech-apps__fields">
			<input type="text" class="regular-text" name="<?php echo esc_attr( $field . '[nome]' ); ?>" value="<?php echo esc_attr( $app['nome'] ); ?>" placeholder="<?php esc_attr_e( 'Nome (opcional) — Ex.: Netflix', 'Divi' ); ?>" />
			<input type="text" class="large-text" name="<?php echo esc_attr( $field . '[desc]' ); ?>" value="<?php echo esc_attr( $app['desc'] ); ?>" placeholder="<?php esc_attr_e( 'Descrição (opcional): Ex.: Streaming de filmes e séries', 'Divi' ); ?>" />
			<input type="text" class="large-text" name="<?php echo esc_attr( $field . '[detalhes]' ); ?>" value="<?php echo esc_attr( $app['detalhes'] ); ?>" placeholder="<?php esc_attr_e( 'Detalhes (opcional): Ex.: Incluído sem custo adicional', 'Divi' ); ?>" />
			<label class="cetech-apps__active">
				<input type="checkbox" name="<?php echo esc_attr( $field . '[ativo]' ); ?>" value="1" <?php checked( '1', $app['ativo'] ); ?> />
				<?php esc_html_e( 'Ativo', 'Divi' ); ?>
			</label>
		</div>

		<div class="cetech-apps__actions">
			<button type="button" class="button cetech-apps__up" aria-label="<?php esc_attr_e( 'Mover para cima', 'Divi' ); ?>">&uarr;</button>
			<button type="button" class="button cetech-apps__down" aria-label="<?php esc_attr_e( 'Mover para baixo', 'Divi' ); ?>">&darr;</button>
			<button type="button" class="button-link delete cetech-apps__remove" aria-label="<?php esc_attr_e( 'Remover app/canal', 'Divi' ); ?>"><?php esc_html_e( 'Remover', 'Divi' ); ?></button>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

function cetech_planos_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['cetech_plano_fields_nonce'] ) || ! wp_verify_nonce( $_POST['cetech_plano_fields_nonce'], 'cetech_plano_fields' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$text_keys = array(
		'_cetech_plano_speed',
		'_cetech_plano_price_old',
		'_cetech_plano_price',
		'_cetech_plano_period',
		'_cetech_plano_badge',
		'_cetech_plano_btn_text',
	);

	foreach ( $text_keys as $key ) {
		$value = sanitize_text_field( isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '' );
		$value = trim( $value );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	$btn_link = esc_url_raw( trim( isset( $_POST['_cetech_plano_btn_link'] ) ? wp_unslash( $_POST['_cetech_plano_btn_link'] ) : '' ) );
	if ( '' === $btn_link ) {
		delete_post_meta( $post_id, '_cetech_plano_btn_link' );
	} else {
		update_post_meta( $post_id, '_cetech_plano_btn_link', $btn_link );
	}

	$cta_target = isset( $_POST[ CETECH_PLANOS_CTA_TARGET_META ] )
		? sanitize_key( wp_unslash( $_POST[ CETECH_PLANOS_CTA_TARGET_META ] ) )
		: 'whatsapp';

	if ( ! in_array( $cta_target, array( 'whatsapp', 'link' ), true ) ) {
		$cta_target = 'whatsapp';
	}

	update_post_meta( $post_id, CETECH_PLANOS_CTA_TARGET_META, $cta_target );

	$tipo = isset( $_POST['_cetech_plano_tipo'] ) ? sanitize_key( wp_unslash( $_POST['_cetech_plano_tipo'] ) ) : 'residencial';
	$tipo = in_array( $tipo, array( 'residencial', 'empresarial' ), true ) ? $tipo : 'residencial';
	update_post_meta( $post_id, '_cetech_plano_tipo', $tipo );

	$desc = sanitize_textarea_field( isset( $_POST['_cetech_plano_desc'] ) ? wp_unslash( $_POST['_cetech_plano_desc'] ) : '' );
	$desc = trim( $desc );
	if ( '' === $desc ) {
		delete_post_meta( $post_id, '_cetech_plano_desc' );
	} else {
		update_post_meta( $post_id, '_cetech_plano_desc', $desc );
	}

	// Benefícios: sanitiza e remove vazios, preservando a ordem.
	$benefits = array();
	if ( ! empty( $_POST['_cetech_plano_benefits'] ) && is_array( $_POST['_cetech_plano_benefits'] ) ) {
		foreach ( wp_unslash( $_POST['_cetech_plano_benefits'] ) as $benefit ) {
			$benefit = trim( (string) $benefit );
			if ( '' !== $benefit ) {
				$benefits[] = sanitize_text_field( $benefit );
			}
		}
	}
	$benefits = array_values( array_unique( $benefits ) );

	if ( empty( $benefits ) ) {
		delete_post_meta( $post_id, '_cetech_plano_benefits' );
	} else {
		update_post_meta( $post_id, '_cetech_plano_benefits', $benefits );
	}

	// Apps/canais: sanitiza cada item, preservando a ordem do repeater.
	$apps = array();

	if ( ! empty( $_POST['cetech_planos_apps'] ) && is_array( $_POST['cetech_planos_apps'] ) ) {
		foreach ( wp_unslash( $_POST['cetech_planos_apps'] ) as $app ) {
			if ( ! is_array( $app ) ) {
				continue;
			}

			$item = array(
				'nome'     => isset( $app['nome'] ) ? trim( sanitize_text_field( $app['nome'] ) ) : '',
				'imagem'   => isset( $app['imagem'] ) ? absint( $app['imagem'] ) : 0,
				'desc'     => isset( $app['desc'] ) ? trim( sanitize_text_field( $app['desc'] ) ) : '',
				'detalhes' => isset( $app['detalhes'] ) ? trim( sanitize_text_field( $app['detalhes'] ) ) : '',
				'ativo'    => ! empty( $app['ativo'] ) ? '1' : '0',
			);

			/* Nome e opcional: so e descartado o item totalmente vazio. */
			if ( cetech_planos_app_is_empty( $item ) ) {
				continue;
			}

			$apps[] = $item;
		}
	}

	$apps = array_values( $apps );

	if ( empty( $apps ) ) {
		delete_post_meta( $post_id, CETECH_PLANOS_APPS_META );
	} else {
		update_post_meta( $post_id, CETECH_PLANOS_APPS_META, $apps );
	}

	/* So grava quando o metabox foi realmente submetido. Edicao rapida,
	 * bulk edit e REST nao enviesam estes campos, e sobrescrever aqui
	 * desligaria o separador sem ninguem pedir. */
	if ( isset( $_POST[ CETECH_PLANOS_APPS_SEPARATOR_META ] ) ) {
		$separator = trim( sanitize_text_field( wp_unslash( $_POST[ CETECH_PLANOS_APPS_SEPARATOR_META ] ) ) );
		$separator = '' !== $separator ? $separator : CETECH_PLANOS_APPS_SEPARATOR_DEFAULT;

		update_post_meta( $post_id, CETECH_PLANOS_APPS_SEPARATOR_META, $separator );
	}

	$show = ! empty( $_POST[ CETECH_PLANOS_APPS_SEPARATOR_SHOW_META ] ) ? '1' : '0';
	$hide = ! empty( $_POST[ CETECH_PLANOS_APPS_SEPARATOR_HIDE_META ] ) ? '1' : '0';

	/* As duas flags nao podem ficar ligadas ao mesmo tempo: exibir manda. */
	if ( '1' === $show ) {
		$hide = '0';
	}

	update_post_meta( $post_id, CETECH_PLANOS_APPS_SEPARATOR_SHOW_META, $show );
	update_post_meta( $post_id, CETECH_PLANOS_APPS_SEPARATOR_HIDE_META, $hide );

	$active = '1' === ( isset( $_POST['_cetech_plano_active'] ) ? (string) $_POST['_cetech_plano_active'] : '' ) ? '1' : '0';
	update_post_meta( $post_id, '_cetech_plano_active', $active );

	$order = isset( $_POST['cetech_plano_order'] ) ? absint( $_POST['cetech_plano_order'] ) : 0;

	global $wpdb;
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Operação nativa do campo menu_order.
		$wpdb->posts,
		array( 'menu_order' => $order ),
		array( 'ID' => $post_id ),
		array( '%d' ),
		array( '%d' )
	);

	clean_post_cache( $post_id );
}
add_action( 'save_post_' . CETECH_PLANOS_CPT, 'cetech_planos_meta_box_save' );

/* ------------------------------------------------------------
 * 5. Assets do admin (repeater de beneficios)
 * ------------------------------------------------------------ */
function cetech_planos_admin_enqueue( $hook_suffix ) {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$is_editor = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true )
		&& CETECH_PLANOS_CPT === $screen->post_type;

	if ( ! $is_editor ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_style(
		'cetech-planos-admin',
		get_stylesheet_directory_uri() . '/assets/css/cetech-planos-admin.css',
		array(),
		cetech_planos_asset_version( 'assets/css/cetech-planos-admin.css' )
	);

	wp_enqueue_script(
		'cetech-planos-admin',
		get_stylesheet_directory_uri() . '/assets/js/cetech-planos-admin.js',
		array( 'jquery', 'media-editor' ),
		cetech_planos_asset_version( 'assets/js/cetech-planos-admin.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'cetech_planos_admin_enqueue' );

/* ------------------------------------------------------------
 * 6. Sementeira dos planos iniciais (conteúdo real da CeTech)
 * ------------------------------------------------------------ */
function cetech_planos_seed() {
	if ( get_option( '_cetech_planos_seeded' ) ) {
		return;
	}

	$existing = get_posts(
		array(
			'post_type'      => CETECH_PLANOS_CPT,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $existing ) ) {
		update_option( '_cetech_planos_seeded', 1 );
		return;
	}

	$plans = array(
		array(
			'speed'   => '250 Mega',
			'old'     => '99,90',
			'current' => '79,90',
			'badge'   => 'Oferta especial',
			'order'   => 1,
		),
		array(
			'speed'   => '400 Mega',
			'old'     => '109,90',
			'current' => '89,90',
			'badge'   => '',
			'order'   => 2,
		),
		array(
			'speed'   => '600 Mega',
			'old'     => '129,90',
			'current' => '99,90',
			'badge'   => 'Mais vendido',
			'order'   => 3,
		),
		array(
			'speed'   => '800 Mega',
			'old'     => '159,90',
			'current' => '129,90',
			'badge'   => '',
			'order'   => 4,
		),
	);

	$benefits = array(
		'100% Fibra Óptica',
		'Wi-Fi Incluso',
		'Suporte Especializado',
		'Instalação Grátis',
		'Contrato anual',
	);

	foreach ( $plans as $plan ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => CETECH_PLANOS_CPT,
				'post_status'  => 'publish',
				'post_title'   => $plan['speed'],
				'menu_order'   => $plan['order'],
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		update_post_meta( $post_id, '_cetech_plano_speed', $plan['speed'] );
		update_post_meta( $post_id, '_cetech_plano_tipo', 'residencial' );
		update_post_meta( $post_id, '_cetech_plano_price_old', $plan['old'] );
		update_post_meta( $post_id, '_cetech_plano_price', $plan['current'] );
		update_post_meta( $post_id, '_cetech_plano_period', '/mês' );
		update_post_meta( $post_id, '_cetech_plano_badge', $plan['badge'] );
		update_post_meta( $post_id, '_cetech_plano_desc', 'Internet fibra óptica com alta velocidade e estabilidade para toda a sua casa.' );
		update_post_meta( $post_id, '_cetech_plano_benefits', $benefits );
		update_post_meta( $post_id, '_cetech_plano_btn_text', 'Contratar' );
		update_post_meta( $post_id, '_cetech_plano_btn_link', 'https://wa.me/5599999999999' );
		update_post_meta( $post_id, '_cetech_plano_active', '1' );
	}

	update_option( '_cetech_planos_seeded', 1 );
}
add_action( 'init', 'cetech_planos_seed', 20 );

/**
 * Backfill: garante que planos ja existentes sem tipo sejam
 * classificados como residencial (valor padrao).
 */
function cetech_planos_backfill_tipo() {
	$plans = get_posts(
		array(
			'post_type'      => CETECH_PLANOS_CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_cetech_plano_tipo',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_cetech_plano_tipo',
					'value' => '',
				),
			),
		)
	);

	foreach ( $plans as $post_id ) {
		update_post_meta( $post_id, '_cetech_plano_tipo', 'residencial' );
	}
}
add_action( 'init', 'cetech_planos_backfill_tipo', 25 );

/* ------------------------------------------------------------
 * 7. Consulta dos planos ativos ordenados
 * ------------------------------------------------------------ */
function cetech_planos_get_items( $tipo = '' ) {
	$meta_query = array(
		array(
			'key'   => '_cetech_plano_active',
			'value' => '1',
		),
	);

	if ( in_array( $tipo, array( 'residencial', 'empresarial' ), true ) ) {
		$meta_query[] = array(
			'key'   => '_cetech_plano_tipo',
			'value' => $tipo,
		);
	}

	$query = new WP_Query(
		array(
			'post_type'           => CETECH_PLANOS_CPT,
			'post_status'         => 'publish',
			'posts_per_page'      => -1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CPT pequeno, busca simples por status ativo/tipo.
			'meta_query'          => $meta_query,
		)
	);

	return $query->posts;
}

/* ------------------------------------------------------------
 * 8. Shortcode [planos]
 * ------------------------------------------------------------ */
function cetech_planos_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'colunas' => 0,
			'tipo'    => '',
		),
		$atts,
		'planos'
	);

	$tipo  = sanitize_key( (string) $atts['tipo'] );
	$plans = cetech_planos_get_items( $tipo );

	if ( empty( $plans ) ) {
		return '';
	}

	$colunas = absint( $atts['colunas'] );

	$style = '';
	if ( $colunas > 0 ) {
		$style = ' style="--cetech-planos-cols:' . esc_attr( (string) $colunas ) . '"';
	}

	$html  = '<div class="cetech-planos"' . $style . '>';
	$html .= '<div class="cetech-planos__grid">';

	foreach ( $plans as $plan ) {
		$html .= cetech_planos_render_card( $plan );
	}

	$html .= '</div>';
	$html .= '</div>';

	/* O modal fica fora de .cetech-planos para nao herdar o contexto de
	 * empilhamento de secoes do Divi (position:fixedANCORA em containers
	 * com transform). O design segue as mesmas variaveis CSS. */
	$html .= cetech_planos_render_modal_store( $plans );

	return $html;
}
add_shortcode( 'planos', 'cetech_planos_shortcode' );

/**
 * Guarda o detalhe completo de cada plano (invisivel no DOM) e imprime
 * uma unica casca de modal, reaproveitada por todos os planos.
 *
 * O JS clona o conteudo do plano clicado para dentro do modal, evitando
 * duplicar o markup das apps e beneficios no HTML final.
 *
 * @param array<int,WP_Post> $plans Planos renderizados no shortcode.
 * @return string
 */
function cetech_planos_render_modal_store( $plans ) {
	$html = '<div class="cetech-planos__modal-root">';
	$html .= '<div class="cetech-planos__store" hidden>';

	foreach ( $plans as $plan ) {
		$html .= '<div class="cetech-planos__store-item" data-cetech-plan="' . esc_attr( (string) $plan->ID ) . '">';
		$html .= cetech_planos_render_modal_content( $plan );
		$html .= '</div>';
	}

	$html .= '</div>';
	$html .= cetech_planos_render_modal();
	$html .= '</div>';

	return $html;
}

/**
 * Casca do modal de detalhes (vazia; o JS preenche o conteudo).
 *
 * @return string
 */
function cetech_planos_render_modal() {
	static $rendered = false;

	if ( $rendered ) {
		return '';
	}

	$rendered = true;

	return '<div class="cetech-planos__modal" data-cetech-modal hidden>'
		. '<div class="cetech-planos__modal-overlay" data-cetech-modal-close></div>'
		. '<div class="cetech-planos__modal-dialog" data-cetech-modal-dialog role="dialog" aria-modal="true" tabindex="-1">'
		. '<button type="button" class="cetech-planos__modal-close" data-cetech-modal-close aria-label="' . esc_attr__( 'Fechar', 'Divi' ) . '"><svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M2 2l12 12M14 2L2 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>'
		. '<div class="cetech-planos__modal-content" data-cetech-modal-content></div>'
		. '</div>'
		. '</div>';
}

/* ------------------------------------------------------------
 * 9. Blocos reutilizaveis de renderizacao
 *    (usados tanto no card quanto no modal)
 * ------------------------------------------------------------ */

/**
 * Cabecalho do plano: velocidade, rotulo e preco.
 *
 * @param string $speed_num   Numero da velocidade.
 * @param string $speed_label Rotulo da velocidade.
 * @param string $price_old   Preco anterior.
 * @param string $price       Preco atual.
 * @param string $period      Texto de periodicidade.
 * @return string
 */
function cetech_planos_render_head( $speed_num, $speed_label, $price_old, $price, $period ) {
	$html = '<div class="cetech-planos__head">';

	if ( '' !== (string) $speed_num ) {
		$html .= '<div class="cetech-planos__speed"><span class="cetech-planos__speed-num">' . esc_html( $speed_num ) . '</span><span class="cetech-planos__speed-label">' . esc_html( $speed_label ) . '</span></div>';
	}

	$html .= '<div class="cetech-planos__vel">' . esc_html__( 'de velocidade', 'Divi' ) . '</div>';
	$html .= cetech_planos_render_price( $price_old, $price, $period );
	$html .= '</div>';

	return $html;
}

/**
 * Bloco de preco, com o valor atual quebrado em inteiro e centavos.
 *
 * @param string $price_old Preco anterior.
 * @param string $price     Preco atual.
 * @param string $period    Texto de periodicidade.
 * @return string
 */
function cetech_planos_render_price( $price_old, $price, $period ) {
	$html = '<div class="cetech-planos__price">';

	if ( '' !== (string) $price_old ) {
		$html .= '<span class="cetech-planos__price-old">' . sprintf( __( 'de R$ %s', 'Divi' ), esc_html( $price_old ) ) . '</span>';
	}

	if ( '' !== (string) $price ) {
		$parts = explode( ',', (string) $price );
		$int   = isset( $parts[0] ) ? $parts[0] : $price;
		$dec   = isset( $parts[1] ) ? ',' . $parts[1] : '';

		$html .= '<span class="cetech-planos__price-current">';
		$html .= '<span class="cetech-planos__cifrao">R$</span>';
		$html .= '<span class="cetech-planos__price-int">' . esc_html( $int ) . '</span>';
		if ( '' !== $dec ) {
			$html .= '<span class="cetech-planos__price-dec">' . esc_html( $dec ) . '</span>';
		}
		$html .= '</span>';
	}

	if ( '' !== (string) $period ) {
		$html .= '<div class="cetech-planos__period">' . esc_html( $period ) . '</div>';
	}

	$html .= '</div>';

	return $html;
}

/**
 * Lista de beneficios com o icone de check.
 *
 * Exibida somente no modal de detalhes do plano.
 *
 * @param array $benefits Beneficios do plano.
 * @return string
 */
function cetech_planos_render_benefits( $benefits ) {
	if ( empty( $benefits ) ) {
		return '';
	}

	$html = '<ul class="cetech-planos__benefits">';

	foreach ( $benefits as $benefit ) {
		$html .= '<li class="cetech-planos__benefit"><span class="cetech-planos__check" aria-hidden="true"><svg viewBox="0 0 12 12" width="11" height="11"><path d="M2 6l3 3 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>' . esc_html( $benefit ) . '</span></li>';
	}

	$html .= '</ul>';

	return $html;
}

/**
 * Monta o conteudo interno de um app/canal (logo + textos).
 *
 * O nome e a descricao aparecem SOMENTE no modal de detalhes: na listagem
 * a vitrine mostra apenas o logo, para ficar visualmente uniforme.
 *
 * @param array $app      App normalizado por cetech_planos_get_apps().
 * @param bool  $detailed Variante detalhada (modal) inclui nome/descricao/detalhes.
 * @return string HTML interno, ou string vazia se nada for exibido.
 */
function cetech_planos_render_app_inner( $app, $detailed ) {
	/* Na vitrine compacta so aparece o logo: sem ele o box ficaria vazio. */
	if ( ! $detailed && '' === $app['logo'] ) {
		return '';
	}

	$html = '';

	if ( '' !== $app['logo'] ) {
		/* O nome vira o alt: na listagem ele nao aparece escrito, mas continua
		 * sendo o rotulo acessivel da imagem. Nome vazio gera alt="". */
		$html .= '<span class="cetech-planos__app-logo"><img src="' . esc_url( $app['logo'] ) . '" alt="' . esc_attr( $app['name'] ) . '" loading="lazy" decoding="async" /></span>';
	}

	if ( ! $detailed ) {
		return $html;
	}

	$info = '';
	if ( '' !== $app['name'] ) {
		$info .= '<span class="cetech-planos__app-name">' . esc_html( $app['name'] ) . '</span>';
	}
	if ( '' !== $app['desc'] ) {
		$info .= '<span class="cetech-planos__app-desc">' . esc_html( $app['desc'] ) . '</span>';
	}
	if ( '' !== $app['detalhes'] ) {
		$info .= '<span class="cetech-planos__app-details">' . esc_html( $app['detalhes'] ) . '</span>';
	}

	if ( '' !== $info ) {
		$html .= '<span class="cetech-planos__app-info">' . $info . '</span>';
	}

	return $html;
}

/**
 * Vitrine de apps/canais: uma unica linha horizontal de logos.
 *
 * O cliente escolhe UM unico app, entao todos os logos sao alternativas e
 * ficam lado a lado, separados pela palavra "ou". Nao ha mais dois blocos
 * (incluso x escolha), que desalinhavam as colunas entre si.
 *
 * A palavra e um conector: sai entre um app e o seguinte, nunca antes do
 * primeiro. Por isso o primeiro item nao recebe "ou" e a linha comeca
 * sempre com um logo.
 *
 * @param array  $apps      Apps/canais ja filtrados por cetech_planos_get_apps().
 * @param string $variant   "tile" para o card (compacto) ou "card" para o modal.
 * @param bool   $featured  Se o plano de origem usa o card destacado (fundo azul).
 * @param string $separator Palavra que separa um app do seguinte; vazio omite.
 * @return string
 */
function cetech_planos_render_apps( $apps, $variant = 'tile', $featured = false, $separator = '' ) {
	if ( empty( $apps ) ) {
		return '';
	}

	$detailed = ( 'card' === $variant );
	$label    = trim( (string) $separator );

	/* Descarta o que nao renderiza (app inativo, sem logo e sem texto) antes
	   de montar a lista: assim a montagem sabe quem e o primeiro e o ultimo. */
	$rows = array();

	foreach ( $apps as $app ) {
		$inner = cetech_planos_render_app_inner( $app, $detailed );

		if ( '' === $inner ) {
			continue;
		}

		$rows[] = $inner;
	}

	if ( empty( $rows ) ) {
		return '';
	}

	$items = '';
	$total = count( $rows );
	$index = 0;

	/* Quantos logos cabem por linha. Precisa ser o mesmo numero que o CSS
	   usa, senao o "ou" fica numa posicao errada: e o PHP que decide onde
	   cada linha comeca e onde cada linha termina.
	   Sem separador (padrao) sao 3 apps por linha; com separador sao 2, com
	   a palavra no meio da linha. Vale para a listagem e para o modal.
	   O ultimo app sozinho (total impar) leva a classe --solo e o CSS o
	   centraliza. */
	$per_line = '' !== $label ? 2 : 3;

	if ( ! $detailed ) {
		$per_line = (int) apply_filters( 'cetech_planos_apps_per_line', $per_line );
	}

	$per_line = max( 1, $per_line );

	foreach ( $rows as $inner ) {
		$index++;

		/* O "ou" e um item da propria linha, entre um box e o seguinte, e
		   nao dentro do box: assim o box pode ter overflow:hidden (para o
		   logo nunca atravessar a moldura) sem cortar o conector.
		   Ele sai depois de um box quando existe outro logo na MESMA linha
		   logo depois. Fica de fora quando:
		   - o proximo cai na linha de baixo, para nao sobrar "ou" na ponta;
		   - e o ultimo box da lista, que apontaria para nada.
		   Com 2 por linha: OU so entre 1-2, 3-4, 5-6... Um total impar
		   deixa o ultimo icone sozinho na ultima linha, sem "ou". */
		$is_last   = ( $index === $total );
		$ends_line = ( 0 === $index % $per_line ) && ! $is_last;

		/* O ultimo box que abre uma linha fica SOZINHO nela (total impar).
		   A grade de 3 colunas -- icone, "ou", icone -- colocaria esse
		   box na coluna 1, grudado na borda esquerda. A classe deixa o CSS
		   saber disso e mandar o box ocupar a linha inteira, centralizado.
		   E o PHP que sabe: e ele que conta as linhas. */
		$class = 'cetech-planos__app';

		if ( $is_last && 0 === ( $index - 1 ) % $per_line ) {
			$class .= ' cetech-planos__app--solo';
		}

		$items .= '<li class="' . $class . '">' . $inner . '</li>';

		if ( ! $ends_line && ! $is_last && '' !== $label ) {
			$items .= '<li class="cetech-planos__app-or" aria-hidden="true"><span>' . esc_html( $label ) . '</span></li>';
		}
	}

	$list_class = 'cetech-planos__apps-list';

	if ( '' !== $label ) {
		$list_class .= ' cetech-planos__apps-list--choices';
	}

	$html = '<ul class="' . esc_attr( $list_class ) . '">' . $items . '</ul>';

	$class = 'cetech-planos__apps cetech-planos__apps--' . ( $detailed ? 'card' : 'tile' );
	if ( $featured ) {
		$class .= ' cetech-planos__apps--featured';
	}

	return '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
}

/**
 * O widget de WhatsApp da pagina esta disponivel para abrir o chat?
 *
 * Sem isso o botão ficaria sem acao: o widget so e impresso quando esta
 * ligado E tem numero configurado (ver cetech_wa_render).
 *
 * @return bool
 */
function cetech_planos_tem_whatsapp() {
	if ( ! function_exists( 'cetech_wa_is_enabled' ) || ! function_exists( 'cetech_wa_number' ) ) {
		return false;
	}

	return cetech_wa_is_enabled() && '' !== cetech_wa_number();
}

/**
 * Destino do botão de contratar de um plano.
 *
 * Padrão é o WhatsApp da própria página. Se o widget não estiver
 * disponível, cai para "link" para o botão nunca ficar sem ação.
 *
 * @param int $post_id ID do plano.
 * @return string "whatsapp" ou "link".
 */
function cetech_planos_cta_target( $post_id ) {
	$target = get_post_meta( $post_id, CETECH_PLANOS_CTA_TARGET_META, true );

	if ( ! in_array( $target, array( 'whatsapp', 'link' ), true ) ) {
		$target = 'whatsapp';
	}

	if ( 'whatsapp' === $target && ! cetech_planos_tem_whatsapp() ) {
		return 'link';
	}

	return $target;
}

/**
 * Botão de contratar.
 *
 * Com destino "whatsapp" renderiza um <button>, não um <a>: o clique é
 *interceptado pelo JS para abrir o chat da página. Um <a> com href
 * levaria o cliente para fora do site, que é justamente o que o cliente
 * pediu para evitar.
 *
 * @param string   $btn_text  Texto do botao.
 * @param string   $btn_link  Link do botao (usado so no destino "link").
 * @param string   $class     Classe CSS adicional.
 * @param int|null $plan_id   ID do plano, para pré-selecionar no chat.
 * @return string
 */
function cetech_planos_render_cta( $btn_text, $btn_link, $class = '', $plan_id = null ) {
	if ( '' === (string) $btn_text ) {
		return '';
	}

	$class = trim( 'cetech-planos__btn ' . $class );

	if ( cetech_planos_cta_target( $plan_id ) === 'whatsapp' ) {
		return '<button type="button" class="' . esc_attr( $class ) . ' cetech-planos__btn--whatsapp"'
			. ' data-cetech-planos-cta="whatsapp"'
			. ' data-cetech-planos-plano="' . esc_attr( (string) $plan_id ) . '">'
			. esc_html( $btn_text ) . '</button>';
	}

	$href     = '' !== (string) $btn_link ? $btn_link : '#';
	$external = 0 === strpos( $href, 'http' );
	$target   = $external ? ' target="_blank" rel="noopener noreferrer"' : '';

	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '"' . $target . '>' . esc_html( $btn_text ) . '</a>';
}

/* ------------------------------------------------------------
 * 10. Renderizacao do card
 * ------------------------------------------------------------ */
function cetech_planos_render_card( $post ) {
	$speed     = get_post_meta( $post->ID, '_cetech_plano_speed', true );
	$price_old = get_post_meta( $post->ID, '_cetech_plano_price_old', true );
	$price     = get_post_meta( $post->ID, '_cetech_plano_price', true );
	$period    = get_post_meta( $post->ID, '_cetech_plano_period', true );
	$badge     = get_post_meta( $post->ID, '_cetech_plano_badge', true );
	$desc      = get_post_meta( $post->ID, '_cetech_plano_desc', true );

	list( $speed_num, $speed_label ) = cetech_planos_split_speed( $speed );

	$featured = '' !== trim( (string) $badge );

	$card_class = 'cetech-planos__card';
	if ( $featured ) {
		$card_class .= ' cetech-planos__card--featured';
	}

	$html = '<div class="' . esc_attr( $card_class ) . '" data-cetech-card="' . esc_attr( (string) $post->ID ) . '">';

	if ( $featured ) {
		$html .= '<span class="cetech-planos__badge">' . esc_html( $badge ) . '</span>';
	}

	$html .= cetech_planos_render_head( $speed_num, $speed_label, $price_old, $price, $period );

	if ( '' !== (string) $desc ) {
		$html .= '<p class="cetech-planos__desc">' . esc_html( $desc ) . '</p>';
	}

	$apps      = cetech_planos_get_apps( $post );
	$separator = cetech_planos_get_separator( $post );

	/* Os beneficios ficam somente no modal de detalhes; o card mostra
	 * velocidade, preco, descricao e a vitrine de apps. */
	$html .= cetech_planos_render_apps( $apps, 'tile', $featured, $separator );

	/* Na listagem o botao abre o modal; o CTA de contratar existe apenas
	 * dentro do modal, levando o cliente a ver os detalhes antes. */
	$html .= '<button type="button" class="cetech-planos__btn cetech-planos__btn--details" data-cetech-modal-open aria-haspopup="dialog">'
		. esc_html__( 'Ver detalhes do plano', 'Divi' ) . '</button>';

	$html .= '</div>'; // .cetech-planos__card

	return $html;
}

/* ------------------------------------------------------------
 * 11. Renderizacao do conteudo do modal
 * ------------------------------------------------------------ */
function cetech_planos_render_modal_content( $post ) {
	$speed     = get_post_meta( $post->ID, '_cetech_plano_speed', true );
	$price_old = get_post_meta( $post->ID, '_cetech_plano_price_old', true );
	$price     = get_post_meta( $post->ID, '_cetech_plano_price', true );
	$period    = get_post_meta( $post->ID, '_cetech_plano_period', true );
	$badge     = get_post_meta( $post->ID, '_cetech_plano_badge', true );
	$desc      = get_post_meta( $post->ID, '_cetech_plano_desc', true );
	$benefits  = get_post_meta( $post->ID, '_cetech_plano_benefits', true );
	$btn_text  = get_post_meta( $post->ID, '_cetech_plano_btn_text', true );
	$btn_link  = get_post_meta( $post->ID, '_cetech_plano_btn_link', true );

	if ( ! is_array( $benefits ) ) {
		$benefits = array();
	}

	list( $speed_num, $speed_label ) = cetech_planos_split_speed( $speed );

	$title = trim( (string) $speed );
	if ( '' === $title ) {
		$title = get_the_title( $post->ID );
	}

	$html = '<div class="cetech-planos__modal-head" data-cetech-modal-title>';

	if ( '' !== trim( (string) $badge ) ) {
		$html .= '<span class="cetech-planos__badge cetech-planos__badge--static">' . esc_html( $badge ) . '</span>';
	}

	$html .= '<div class="cetech-planos__modal-headings">';
	$html .= '<h2 class="cetech-planos__modal-name">' . esc_html( $title ) . '</h2>';

	if ( '' !== (string) $desc ) {
		$html .= '<p class="cetech-planos__desc">' . esc_html( $desc ) . '</p>';
	}

	$html .= '</div>';
	$html .= cetech_planos_render_price( $price_old, $price, $period );
	$html .= '</div>'; // .cetech-planos__modal-head

	/* Os apps vem ANTES dos benefícios: o cliente primeiro ve o que pode
	 * escolher (Netflix, Disney+, Globoplay...) e so depois le o que o plano
	 * traz junto. */
	$separator  = cetech_planos_get_separator( $post );
	$apps_html  = cetech_planos_render_apps( cetech_planos_get_apps( $post ), 'card', false, $separator );
	if ( '' !== $apps_html ) {
		$html .= '<div class="cetech-planos__modal-section">';
		$html .= '<span class="cetech-planos__section-title">' . esc_html( cetech_planos_apps_section_title() ) . '</span>';
		$html .= $apps_html;
		$html .= '</div>';
	}

	$benefits_html = cetech_planos_render_benefits( $benefits );
	if ( '' !== $benefits_html ) {
		$html .= '<div class="cetech-planos__modal-section">';
		$html .= '<span class="cetech-planos__section-title">' . esc_html__( 'Benefícios inclusos', 'Divi' ) . '</span>';
		$html .= $benefits_html;
		$html .= '</div>';
	}

	$cta = cetech_planos_render_cta( $btn_text, $btn_link, 'cetech-planos__btn--modal', $post->ID );
	if ( '' !== $cta ) {
		$html .= '<div class="cetech-planos__modal-foot">' . $cta . '</div>';
	}

	return $html;
}

/**
 * Titulo da secao de apps no modal.
 *
 * Fala em "escolha" porque o cliente contrata UM app: todos os logos sao
 * alternativas, nao inclusoes. Filtravel para trocar o texto.
 *
 * @return string
 */
function cetech_planos_apps_section_title() {
	return (string) apply_filters( 'cetech_planos_apps_section_title', __( 'Apps disponíveis para escolha', 'Divi' ) );
}

/**
 * Divide a velocidade em número e rótulo.
 *
 * Ex.: "250 Mega" -> [ '250', 'Mega' ]; "Fibra 100" -> [ '100', 'Fibra' ].
 *
 * @param string $speed Texto da velocidade.
 * @return array{string,string}
 */
function cetech_planos_split_speed( $speed ) {
	$speed = trim( (string) $speed );

	if ( '' === $speed ) {
		return array( '', '' );
	}

	if ( preg_match( '/^(\d+(?:[.,]\d+)?)\s*(.*)$/u', $speed, $m ) ) {
		return array( $m[1], trim( $m[2] ) );
	}

	if ( preg_match( '/^(.*?)\s*(\d+(?:[.,]\d+)?)$/u', $speed, $m ) ) {
		return array( $m[2], trim( $m[1] ) );
	}

	return array( $speed, '' );
}
