<?php
/**
 * Registro do Custom Post Type "Banners" e colunas do admin.
 *
 * @package Setceb_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gerencia o CPT de banners.
 */
final class Setceb_Banner_Post_Type {

	/**
	 * Slug do post type.
	 */
	public const POST_TYPE = 'setceb_banner';

	/**
	 * Registra os hooks do módulo.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );

		$post_type = self::POST_TYPE;

		add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( "manage_edit-{$post_type}_sortable_columns", array( __CLASS__, 'sortable_columns' ) );
	}

	/**
	 * Registra o post type.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => _x( 'Banners', 'post type general name', 'setceb-banner' ),
			'singular_name'      => _x( 'Banner', 'post type singular name', 'setceb-banner' ),
			'menu_name'          => __( 'Banners', 'setceb-banner' ),
			'add_new'            => __( 'Adicionar novo', 'setceb-banner' ),
			'add_new_item'       => __( 'Adicionar novo banner', 'setceb-banner' ),
			'edit_item'          => __( 'Editar banner', 'setceb-banner' ),
			'new_item'           => __( 'Novo banner', 'setceb-banner' ),
			'view_item'          => __( 'Ver banner', 'setceb-banner' ),
			'search_items'       => __( 'Pesquisar banners', 'setceb-banner' ),
			'not_found'          => __( 'Nenhum banner encontrado.', 'setceb-banner' ),
			'not_found_in_trash' => __( 'Nenhum banner na lixeira.', 'setceb-banner' ),
			'all_items'          => __( 'Todos os banners', 'setceb-banner' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 5,
				'menu_icon'       => 'dashicons-images-alt2',
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

	/**
	 * Colunas da listagem.
	 *
	 * @param array $columns Colunas padrão.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new_columns = array(
			'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'setceb_img' => __( 'Imagem', 'setceb-banner' ),
			'title'      => isset( $columns['title'] ) ? $columns['title'] : __( 'Título', 'setceb-banner' ),
			'setceb_ord' => __( 'Ordem', 'setceb-banner' ),
			'setceb_stt' => __( 'Status', 'setceb-banner' ),
		);

		return $new_columns;
	}

	/**
	 * Conteúdo das colunas personalizadas.
	 *
	 * @param string $column  Nome da coluna.
	 * @param int    $post_id ID do post.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'setceb_img':
				$image_id = get_post_meta( $post_id, '_setceb_banner_image_desktop', true );
				if ( $image_id ) {
					echo wp_get_attachment_image(
						absint( $image_id ),
						array( 80, 45 ),
						false,
						array( 'style' => 'width:80px;height:45px;object-fit:cover;border-radius:6px;display:block;' )
					);
				} else {
					echo '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>';
				}
				break;

			case 'setceb_ord':
				$order = get_post_field( 'menu_order', $post_id );
				echo esc_html( (string) absint( $order ) );
				break;

			case 'setceb_stt':
				$active = get_post_meta( $post_id, '_setceb_banner_active', true );

				if ( '1' === (string) $active ) {
					echo '<span style="color:#1a7f37;font-weight:600;">' . esc_html__( 'Ativo', 'setceb-banner' ) . '</span>';
				} else {
					echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Inativo', 'setceb-banner' ) . '</span>';
				}
				break;
		}
	}

	/**
	 * Colunas ordenáveis.
	 *
	 * @param array $columns Colunas ordenáveis.
	 * @return array
	 */
	public static function sortable_columns( $columns ) {
		$columns['setceb_ord'] = 'menu_order';

		return $columns;
	}
}
