<?php
/**
 * CE Tech - Hero Section dinâmica (Home)
 *
 * Registra o Custom Post Type "Hero", os campos customizados no editor,
 * sementeia o primeiro Hero com o conteúdo padrao e renderiza a secao
 * hero na pagina inicial, suportando varios Heroes ativos em loop.
 *
 * Carregado em functions.php.
 *
 * @package CE Tech Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * 1. Assets (front-end)
 * ------------------------------------------------------------ */
function cetech_hero_enqueue_assets() {
	if ( ! is_front_page() ) {
		return;
	}

	$theme = wp_get_theme();

	wp_enqueue_style(
		'cetech-hero',
		get_stylesheet_uri(),
		array(),
		$theme->get( 'Version' )
	);

	wp_enqueue_script(
		'cetech-hero',
		get_stylesheet_directory_uri() . '/hero.js',
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'cetech_hero_enqueue_assets' );

/* ------------------------------------------------------------
 * 2. Registro do Custom Post Type "Hero"
 * ------------------------------------------------------------ */
function cetech_hero_register_post_type() {
	$labels = array(
		'name'               => _x( 'Hero', 'post type general name', 'Divi' ),
		'singular_name'      => _x( 'Hero', 'post type singular name', 'Divi' ),
		'menu_name'          => __( 'Hero', 'Divi' ),
		'add_new'            => __( 'Adicionar novo', 'Divi' ),
		'add_new_item'       => __( 'Adicionar novo Hero', 'Divi' ),
		'edit_item'          => __( 'Editar Hero', 'Divi' ),
		'new_item'           => __( 'Novo Hero', 'Divi' ),
		'view_item'          => __( 'Ver Hero', 'Divi' ),
		'search_items'       => __( 'Pesquisar Heroes', 'Divi' ),
		'not_found'          => __( 'Nenhum Hero encontrado.', 'Divi' ),
		'not_found_in_trash' => __( 'Nenhum Hero na lixeira.', 'Divi' ),
		'all_items'          => __( 'Todos os Heroes', 'Divi' ),
	);

	register_post_type(
		'cetech_hero',
		array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 6,
			'menu_icon'       => 'dashicons-cover-image',
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
add_action( 'init', 'cetech_hero_register_post_type' );

/* ------------------------------------------------------------
 * 3. Colunas da listagem do CPT
 * ------------------------------------------------------------ */
function cetech_hero_columns( $columns ) {
	$new_columns = array(
		'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'      => isset( $columns['title'] ) ? $columns['title'] : __( 'Título', 'Divi' ),
		'cetech_ord' => __( 'Ordem', 'Divi' ),
		'cetech_stt' => __( 'Status', 'Divi' ),
	);

	return $new_columns;
}
add_filter( 'manage_cetech_hero_posts_columns', 'cetech_hero_columns' );

function cetech_hero_render_column( $column, $post_id ) {
	switch ( $column ) {
		case 'cetech_ord':
			$order = get_post_field( 'menu_order', $post_id );
			echo esc_html( (string) absint( $order ) );
			break;

		case 'cetech_stt':
			$active = get_post_meta( $post_id, '_cetech_hero_active', true );
			if ( '1' === (string) $active ) {
				echo '<span style="color:#1a7f37;font-weight:600;">' . esc_html__( 'Ativo', 'Divi' ) . '</span>';
			} else {
				echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Inativo', 'Divi' ) . '</span>';
			}
			break;
	}
}
add_action( 'manage_cetech_hero_posts_custom_column', 'cetech_hero_render_column', 10, 2 );

function cetech_hero_sortable_columns( $columns ) {
	$columns['cetech_ord'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-cetech_hero_sortable_columns', 'cetech_hero_sortable_columns' );

/* ------------------------------------------------------------
 * 4. Metabox com os campos do Hero
 * ------------------------------------------------------------ */
function cetech_hero_add_meta_box() {
	add_meta_box(
		'cetech_hero_fields',
		__( 'Configurações do Hero', 'Divi' ),
		'cetech_hero_meta_box_render',
		'cetech_hero',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'cetech_hero_add_meta_box' );

function cetech_hero_meta_box_render( $post ) {
	wp_nonce_field( 'cetech_hero_fields', 'cetech_hero_fields_nonce' );

	$subtitle       = get_post_meta( $post->ID, '_cetech_hero_subtitle', true );
	$badge          = get_post_meta( $post->ID, '_cetech_hero_badge', true );
	$btn_primary    = get_post_meta( $post->ID, '_cetech_hero_btn_primary_text', true );
	$btn_primary_l  = get_post_meta( $post->ID, '_cetech_hero_btn_primary_link', true );
	$btn_secondary  = get_post_meta( $post->ID, '_cetech_hero_btn_secondary_text', true );
	$btn_secondary_l = get_post_meta( $post->ID, '_cetech_hero_btn_secondary_link', true );
	$image_id       = absint( get_post_meta( $post->ID, '_cetech_hero_image', true ) );
	$active         = get_post_meta( $post->ID, '_cetech_hero_active', true );
	$order          = absint( get_post_field( 'menu_order', $post->ID ) );

	if ( '' === $active ) {
		$active = '1';
	}
	?>
	<div class="cetech-hero-admin">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="cetech-hero-badge"><?php esc_html_e( 'Texto do selo', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_hero_badge" id="cetech-hero-badge" value="<?php echo esc_attr( $badge ); ?>" />
					<p class="description"><?php esc_html_e( 'Ex.: "Fibra Óptica 100%"', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-hero-subtitle"><?php esc_html_e( 'Subtítulo / Descrição', 'Divi' ); ?></label>
				</th>
				<td>
					<textarea class="large-text" rows="4" name="_cetech_hero_subtitle" id="cetech-hero-subtitle"><?php echo esc_textarea( $subtitle ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Descrição exibida abaixo do título principal.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-hero-btn-primary-text"><?php esc_html_e( 'Texto do botão principal', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_hero_btn_primary_text" id="cetech-hero-btn-primary-text" value="<?php echo esc_attr( $btn_primary ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-hero-btn-primary-link"><?php esc_html_e( 'Link do botão principal', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="url" class="regular-text" name="_cetech_hero_btn_primary_link" id="cetech-hero-btn-primary-link" value="<?php echo esc_url( $btn_primary_l ); ?>" placeholder="https://" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-hero-btn-secondary-text"><?php esc_html_e( 'Texto do botão secundário', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_hero_btn_secondary_text" id="cetech-hero-btn-secondary-text" value="<?php echo esc_attr( $btn_secondary ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-hero-btn-secondary-link"><?php esc_html_e( 'Link do botão secundário', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="url" class="regular-text" name="_cetech_hero_btn_secondary_link" id="cetech-hero-btn-secondary-link" value="<?php echo esc_url( $btn_secondary_l ); ?>" placeholder="https://" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-hero-image"><?php esc_html_e( 'Imagem / Ilustração do Hero', 'Divi' ); ?></label>
				</th>
				<td>
					<?php cetech_hero_render_image_field( '_cetech_hero_image', $image_id ); ?>
					<p class="description"><?php esc_html_e( 'Opcional. Se vazio, é exibida a ilustração padrão (círculo CeTech com conexões).', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-hero-order"><?php esc_html_e( 'Ordem de exibição', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="number" class="small-text" name="cetech_hero_order" id="cetech-hero-order" value="<?php echo esc_attr( (string) $order ); ?>" min="0" step="1" />
					<p class="description"><?php esc_html_e( 'Menor valor é exibido primeiro.', 'Divi' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'Divi' ); ?></th>
				<td>
					<label for="cetech-hero-active" style="margin-right:12px;">
						<input type="radio" name="_cetech_hero_active" id="cetech-hero-active" value="1" <?php checked( '1', $active ); ?> />
						<?php esc_html_e( 'Ativo', 'Divi' ); ?>
					</label>
					<label for="cetech-hero-inactive">
						<input type="radio" name="_cetech_hero_active" id="cetech-hero-inactive" value="0" <?php checked( '0', $active ); ?> />
						<?php esc_html_e( 'Inativo', 'Divi' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Somente Heroes ativos são exibidos na home.', 'Divi' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
	<?php
}

function cetech_hero_render_image_field( $meta_key, $image_id ) {
	$preview = '';
	if ( $image_id ) {
		$preview = wp_get_attachment_image( $image_id, 'medium', false, array( 'style' => 'max-width:240px;height:auto;border-radius:50%;display:block;' ) );
	}
	?>
	<div class="cetech-hero-image-field" data-field="cetech-hero-image">
		<input type="hidden" name="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" />
		<div class="cetech-hero-image-field__preview"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image escapa. ?></div>
		<p>
			<button type="button" class="button cetech-hero-image-field__add" data-media-title="<?php echo esc_attr__( 'Selecionar imagem', 'Divi' ); ?>"><?php esc_html_e( 'Selecionar imagem', 'Divi' ); ?></button>
			<button type="button" class="button cetech-hero-image-field__remove" <?php echo $image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remover', 'Divi' ); ?></button>
		</p>
	</div>
	<?php
}

function cetech_hero_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['cetech_hero_fields_nonce'] ) || ! wp_verify_nonce( $_POST['cetech_hero_fields_nonce'], 'cetech_hero_fields' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$text_keys = array(
		'_cetech_hero_subtitle',
		'_cetech_hero_badge',
		'_cetech_hero_btn_primary_text',
		'_cetech_hero_btn_secondary_text',
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

	foreach ( array( '_cetech_hero_btn_primary_link', '_cetech_hero_btn_secondary_link' ) as $key ) {
		$value = esc_url_raw( trim( isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '' ) );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	$image_id = isset( $_POST['_cetech_hero_image'] ) ? absint( $_POST['_cetech_hero_image'] ) : 0;

	if ( 0 === $image_id ) {
		delete_post_meta( $post_id, '_cetech_hero_image' );
	} else {
		update_post_meta( $post_id, '_cetech_hero_image', $image_id );
	}

	$active = '1' === ( isset( $_POST['_cetech_hero_active'] ) ? (string) $_POST['_cetech_hero_active'] : '' ) ? '1' : '0';
	update_post_meta( $post_id, '_cetech_hero_active', $active );

	$order = isset( $_POST['cetech_hero_order'] ) ? absint( $_POST['cetech_hero_order'] ) : 0;

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
add_action( 'save_post_cetech_hero', 'cetech_hero_meta_box_save' );

/* ------------------------------------------------------------
 * 5. Asset do admin (seletor de mídia)
 * ------------------------------------------------------------ */
function cetech_hero_admin_enqueue( $hook_suffix ) {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$is_editor = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true )
		&& 'cetech_hero' === $screen->post_type;

	if ( ! $is_editor ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'cetech-hero-admin',
		get_stylesheet_directory_uri() . '/hero-admin.js',
		array( 'media-editor' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'cetech_hero_admin_enqueue' );

/* ------------------------------------------------------------
 * 6. Sementeira do primeiro Hero (conteúdo padrão da referência)
 * ------------------------------------------------------------ */
function cetech_hero_seed() {
	if ( get_option( '_cetech_hero_seeded' ) ) {
		return;
	}

	$existing = get_posts(
		array(
			'post_type'      => 'cetech_hero',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $existing ) ) {
		update_option( '_cetech_hero_seeded', 1 );
		return;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'cetech_hero',
			'post_status'  => 'publish',
			'post_title'   => 'Hero padrão - Fibra Óptica 100%',
			'menu_order'   => 1,
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return;
	}

	update_post_meta( $post_id, '_cetech_hero_badge', 'Fibra Óptica 100%' );
	update_post_meta(
		$post_id,
		'_cetech_hero_subtitle',
		'Internet fibra óptica de alta velocidade com estabilidade, suporte especializado e instalação em até 48h.'
	);
	update_post_meta( $post_id, '_cetech_hero_btn_primary_text', 'Ver Planos' );
	update_post_meta( $post_id, '_cetech_hero_btn_primary_link', home_url( '/planos' ) );
	update_post_meta( $post_id, '_cetech_hero_btn_secondary_text', 'Falar no WhatsApp' );
	update_post_meta( $post_id, '_cetech_hero_btn_secondary_link', 'https://wa.me/5599999999999' );
	update_post_meta( $post_id, '_cetech_hero_active', '1' );

	update_option( '_cetech_hero_seeded', 1 );
}
add_action( 'init', 'cetech_hero_seed', 20 );

/* ------------------------------------------------------------
 * 7. Consulta dos Heroes ativos ordenados
 * ------------------------------------------------------------ */
function cetech_hero_get_items() {
	$query = new WP_Query(
		array(
			'post_type'           => 'cetech_hero',
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
					'key'   => '_cetech_hero_active',
					'value' => '1',
				),
			),
		)
	);

	return $query->posts;
}

/* ------------------------------------------------------------
 * 8. Renderização da seção Hero (só na home)
 * ------------------------------------------------------------ */
function cetech_hero_render() {
	if ( ! is_front_page() ) {
		return;
	}

	$heroes = cetech_hero_get_items();

	if ( empty( $heroes ) ) {
		return;
	}

	ob_start();
	?>
	<section class="cetech-hero" id="cetech-hero" role="region" aria-label="Destaque do início">
		<?php foreach ( $heroes as $index => $hero ) : ?>
			<?php $hero = cetech_hero_prepare( $hero ); ?>
			<div class="cetech-hero__slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-hero-index="<?php echo esc_attr( (string) $index ); ?>">
				<div class="cetech-hero__container">
					<div class="cetech-hero__text">
						<?php if ( $hero['badge'] ) : ?>
							<span class="cetech-hero__badge">
								<span class="cetech-hero__badge-dot" aria-hidden="true"></span>
								<?php echo esc_html( $hero['badge'] ); ?>
							</span>
						<?php endif; ?>

						<h1 class="cetech-hero__title">
							<?php echo wp_kses( $hero['title'], array( 'br' => array() ) ); ?>
						</h1>

						<?php if ( $hero['subtitle'] ) : ?>
							<p class="cetech-hero__subtitle"><?php echo esc_html( $hero['subtitle'] ); ?></p>
						<?php endif; ?>

						<div class="cetech-hero__actions">
							<?php if ( $hero['btn_primary_text'] ) : ?>
								<a class="cetech-hero__btn cetech-hero__btn--primary" href="<?php echo esc_url( $hero['btn_primary_link'] ); ?>">
									<?php echo esc_html( $hero['btn_primary_text'] ); ?>
								</a>
							<?php endif; ?>

							<?php if ( $hero['btn_secondary_text'] ) : ?>
								<a class="cetech-hero__btn cetech-hero__btn--secondary" href="<?php echo esc_url( $hero['btn_secondary_link'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $hero['btn_secondary_text'] ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>

					<div class="cetech-hero__visual">
						<?php
						if ( $hero['image_id'] ) {
							echo wp_get_attachment_image(
								$hero['image_id'],
								'large',
								false,
								array( 'class' => 'cetech-hero__image', 'alt' => $hero['raw_title'] )
							);
						} else {
							cetech_hero_render_illustration();
						}
						?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>

		<?php if ( count( $heroes ) > 1 ) : ?>
			<div class="cetech-hero__nav" role="tablist" aria-label="Navegar entre destaques">
				<?php foreach ( $heroes as $index => $hero ) : ?>
					<button
						type="button"
						class="cetech-hero__nav-dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( sprintf( 'Ir para o destaque %d', $index + 1 ) ); ?>"
						data-goto="<?php echo esc_attr( (string) $index ); ?>"
					></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	echo ob_get_clean();
}
add_action( 'et_before_main_content', 'cetech_hero_render', 5 );

/**
 * Prepara os dados de um Hero para renderização.
 *
 * O título principal vem do post_title, porém aceita quebras de linha
 * (#) para montar o título com <br>.
 *
 * @param WP_Post $post Objeto do Hero.
 * @return array
 */
function cetech_hero_prepare( $post ) {
	$title = get_the_title( $post );
	$title = str_replace( array( "\r\n", "\r", "\n" ), '#', $title );
	$title = str_replace( '#', '<br>', $title );

	return array(
		'title'            => $title,
		'raw_title'        => wp_strip_all_tags( get_the_title( $post ) ),
		'badge'            => get_post_meta( $post->ID, '_cetech_hero_badge', true ),
		'subtitle'         => get_post_meta( $post->ID, '_cetech_hero_subtitle', true ),
		'btn_primary_text' => get_post_meta( $post->ID, '_cetech_hero_btn_primary_text', true ),
		'btn_primary_link' => get_post_meta( $post->ID, '_cetech_hero_btn_primary_link', true ),
		'btn_secondary_text' => get_post_meta( $post->ID, '_cetech_hero_btn_secondary_text', true ),
		'btn_secondary_link' => get_post_meta( $post->ID, '_cetech_hero_btn_secondary_link', true ),
		'image_id'         => absint( get_post_meta( $post->ID, '_cetech_hero_image', true ) ),
	);
}

/**
 * Renderiza a ilustração padrão (círculo CeTech com conexões).
 *
 * Estrutura fiel à referência: círculo central "CeTech", anéis, pontos
 * de conexão e textos orbitais ao redor.
 *
 * @return void
 */
function cetech_hero_render_illustration() {
	?>
	<div class="cetech-hero__orbit" aria-hidden="true">
		<div class="cetech-hero__orbit-core">
			<span class="cetech-hero__orbit-logo">CeTech</span>
			<span class="cetech-hero__orbit-core-sub">Fibra &middot; 100%</span>
		</div>

		<div class="cetech-hero__orbit-ring cetech-hero__orbit-ring--1"></div>
		<div class="cetech-hero__orbit-ring cetech-hero__orbit-ring--2"></div>

		<span class="cetech-hero__node cetech-hero__node--a">
			<span class="cetech-hero__node-dot"></span>
			<span class="cetech-hero__node-text">Velocidade<br>Real</span>
		</span>
		<span class="cetech-hero__node cetech-hero__node--b">
			<span class="cetech-hero__node-dot"></span>
			<span class="cetech-hero__node-text">Estabilidade<br>Total</span>
		</span>
		<span class="cetech-hero__node cetech-hero__node--c">
			<span class="cetech-hero__node-dot"></span>
			<span class="cetech-hero__node-text">Suporte<br>Especializado</span>
		</span>
		<span class="cetech-hero__node cetech-hero__node--d">
			<span class="cetech-hero__node-dot"></span>
			<span class="cetech-hero__node-text">Instalação<br>em 48h</span>
		</span>
	</div>
	<?php
}
