<?php
/**
 * CE Tech - Planos (cards via shortcode)
 *
 * Registra o Custom Post Type "Planos", os campos customizados no editor
 * (incluindo a lista de beneficios dinamica), sementeia os planos iniciais
 * e expoe o shortcode [planos] que renderiza SOMENTE os cards dos planos
 * ativos, ordenados pela ordem cadastrada.
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

/* ------------------------------------------------------------
 * 1. Registro do CSS do front-end (isolado)
 * ------------------------------------------------------------ */
function cetech_planos_register_assets() {
	wp_register_style(
		CETECH_PLANOS_CSS_HANDLE,
		get_stylesheet_directory_uri() . '/assets/css/cetech-planos.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'cetech_planos_register_assets' );

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
		'cetech_ord'  => __( 'Ordem', 'Divi' ),
		'cetech_stt'  => __( 'Status', 'Divi' ),
	);

	return $new_columns;
}
add_filter( 'manage_' . CETECH_PLANOS_CPT . '_posts_columns', 'cetech_planos_columns' );

function cetech_planos_render_column( $column, $post_id ) {
	switch ( $column ) {
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
	$price_old    = get_post_meta( $post->ID, '_cetech_plano_price_old', true );
	$price        = get_post_meta( $post->ID, '_cetech_plano_price', true );
	$period       = get_post_meta( $post->ID, '_cetech_plano_period', true );
	$badge        = get_post_meta( $post->ID, '_cetech_plano_badge', true );
	$desc         = get_post_meta( $post->ID, '_cetech_plano_desc', true );
	$benefits     = get_post_meta( $post->ID, '_cetech_plano_benefits', true );
	$btn_text     = get_post_meta( $post->ID, '_cetech_plano_btn_text', true );
	$btn_link     = get_post_meta( $post->ID, '_cetech_plano_btn_link', true );
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

	wp_enqueue_style(
		'cetech-planos-admin',
		get_stylesheet_directory_uri() . '/assets/css/cetech-planos-admin.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'cetech-planos-admin',
		get_stylesheet_directory_uri() . '/assets/js/cetech-planos-admin.js',
		array( 'jquery' ),
		wp_get_theme()->get( 'Version' ),
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

/* ------------------------------------------------------------
 * 7. Consulta dos planos ativos ordenados
 * ------------------------------------------------------------ */
function cetech_planos_get_items() {
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
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CPT pequeno, busca simples por status ativo.
			'meta_query'          => array(
				array(
					'key'   => '_cetech_plano_active',
					'value' => '1',
				),
			),
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
		),
		$atts,
		'planos'
	);

	$plans = cetech_planos_get_items();

	if ( empty( $plans ) ) {
		return '';
	}

	wp_enqueue_style( CETECH_PLANOS_CSS_HANDLE );

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

	return $html;
}
add_shortcode( 'planos', 'cetech_planos_shortcode' );

/* ------------------------------------------------------------
 * 9. Renderização do card
 * ------------------------------------------------------------ */
function cetech_planos_render_card( $post ) {
	$speed       = get_post_meta( $post->ID, '_cetech_plano_speed', true );
	$price_old   = get_post_meta( $post->ID, '_cetech_plano_price_old', true );
	$price       = get_post_meta( $post->ID, '_cetech_plano_price', true );
	$period      = get_post_meta( $post->ID, '_cetech_plano_period', true );
	$badge       = get_post_meta( $post->ID, '_cetech_plano_badge', true );
	$desc        = get_post_meta( $post->ID, '_cetech_plano_desc', true );
	$benefits    = get_post_meta( $post->ID, '_cetech_plano_benefits', true );
	$btn_text    = get_post_meta( $post->ID, '_cetech_plano_btn_text', true );
	$btn_link    = get_post_meta( $post->ID, '_cetech_plano_btn_link', true );

	if ( ! is_array( $benefits ) ) {
		$benefits = array();
	}

	list( $speed_num, $speed_label ) = cetech_planos_split_speed( $speed );

	$featured = '' !== trim( (string) $badge );

	$card_class = 'cetech-planos__card';
	if ( $featured ) {
		$card_class .= ' cetech-planos__card--featured';
	}

	$html  = '<div class="' . esc_attr( $card_class ) . '">';

	if ( $featured ) {
		$html .= '<span class="cetech-planos__badge">' . esc_html( $badge ) . '</span>';
	}

	$html .= '<div class="cetech-planos__head">';

	if ( '' !== (string) $speed_num ) {
		$html .= '<div class="cetech-planos__speed"><span class="cetech-planos__speed-num">' . esc_html( $speed_num ) . '</span><span class="cetech-planos__speed-label">' . esc_html( $speed_label ) . '</span></div>';
	}

	$html .= '<div class="cetech-planos__vel">' . esc_html__( 'de velocidade', 'Divi' ) . '</div>';

	$html .= '<div class="cetech-planos__price">';

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

	$html .= '</div>'; // .cetech-planos__price
	$html .= '</div>'; // .cetech-planos__head

	if ( '' !== (string) $desc ) {
		$html .= '<p class="cetech-planos__desc">' . esc_html( $desc ) . '</p>';
	}

	if ( ! empty( $benefits ) ) {
		$html .= '<ul class="cetech-planos__benefits">';
		foreach ( $benefits as $benefit ) {
			$html .= '<li class="cetech-planos__benefit"><span class="cetech-planos__check" aria-hidden="true"><svg viewBox="0 0 12 12" width="11" height="11"><path d="M2 6l3 3 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>' . esc_html( $benefit ) . '</span></li>';
		}
		$html .= '</ul>';
	}

	if ( '' !== (string) $btn_text ) {
		$href      = '' !== (string) $btn_link ? $btn_link : '#';
		$external  = 0 === strpos( $href, 'http' );
		$target    = $external ? ' target="_blank" rel="noopener noreferrer"' : '';

		$html .= '<a class="cetech-planos__btn" href="' . esc_url( $href ) . '"' . $target . '>' . esc_html( $btn_text ) . '</a>';
	}

	$html .= '</div>'; // .cetech-planos__card

	return $html;
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
