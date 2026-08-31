<?php
/**
 * SETCEB - Documentos do associado (Custom Post Types)
 *
 * O admin cadastra os conteudos da area do associado direto no
 * painel WordPress, nos menus:
 *
 * - Planilhas              (setceb_planilha)
 * - Relatorios             (setceb_relatorio)
 * - Convencoes Coletivas   (setceb_convencoes)
 * - Outros Materiais       (setceb_outros_materiais)
 *
 * Cada item possui: titulo, arquivo (URL da biblioteca de midia ou
 * link externo), categoria (taxonomia compartilhada, ja populada
 * com as categorias de transporte), ano e opcao de destaque.
 *
 * A area do associado le estes conteudos automaticamente atraves das
 * funcoes setceb_planilhas(), setceb_relatorios() e
 * setceb_convencoes() (includes/associado.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tipos de documento gerenciados no painel.
 *
 * @return string[]
 */
function setceb_documento_post_types() {
	return array( 'setceb_planilha', 'setceb_relatorio', 'setceb_convencoes', 'setceb_outros_materiais' );
}

/**
 * Registra a taxonomia e os tipos de documento.
 */
function setceb_documentos_register() {
	register_taxonomy(
		'setceb_cat_doc',
		setceb_documento_post_types(),
		array(
			'labels'            => array(
				'name'          => 'Categorias de Documentos',
				'singular_name' => 'Categoria de Documento',
				'menu_name'     => 'Categorias',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
			'show_in_rest'      => true,
		)
	);

	$tipos = array(
		'setceb_planilha'         => array( 'Planilhas', 'Planilha', 'dashicons-media-spreadsheet' ),
		'setceb_relatorio'        => array( 'Relatórios', 'Relatório', 'dashicons-chart-bar' ),
		'setceb_convencoes'       => array( 'Convenções Coletivas', 'Convenção Coletiva', 'dashicons-media-document' ),
		'setceb_outros_materiais' => array( 'Outros Materiais', 'Outro Material', 'dashicons-portfolio' ),
	);

	foreach ( $tipos as $slug => $info ) {
		register_post_type(
			$slug,
			array(
				'labels'       => array(
					'name'          => $info[0],
					'singular_name' => $info[1],
					'add_new_item'  => 'Adicionar ' . $info[1],
					'edit_item'     => 'Editar ' . $info[1],
					'new_item'      => 'Novo ' . $info[1],
					'search_items'  => 'Buscar em ' . $info[0],
					'not_found'     => 'Nenhum item cadastrado.',
				),
				'public'       => false,
				'show_ui'      => true,
				'menu_icon'    => $info[2],
				'supports'     => array( 'title' ),
				'has_archive'  => false,
				'rewrite'      => false,
				'show_in_rest' => true,
			)
		);
	}
}
add_action( 'init', 'setceb_documentos_register' );

/**
 * Popula a taxonomia com as categorias padrao do menu de filtros.
 */
function setceb_documentos_seed_categories() {
	if ( ! taxonomy_exists( 'setceb_cat_doc' ) ) {
		return;
	}

	static $done = false;

	if ( $done ) {
		return;
	}

	$done = true;

	foreach ( setceb_associado_categorias() as $slug => $label ) {
		if ( ! term_exists( $slug, 'setceb_cat_doc' ) ) {
			wp_insert_term( $label, 'setceb_cat_doc', array( 'slug' => $slug ) );
		}
	}
}
add_action( 'init', 'setceb_documentos_seed_categories', 20 );

/**
 * Consulta os documentos publicados de um tipo e devolve no formato
 * consumido pela area do associado.
 *
 * @param string $post_type Tipo de documento.
 * @return array[]
 */
function setceb_documentos_query( $post_type ) {
	$posts = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'posts_per_page'   => 100,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);

	$itens = array();

	foreach ( $posts as $post ) {
		$url = get_post_meta( $post->ID, '_setceb_doc_url', true );

		if ( '' === $url ) {
			continue;
		}

		$item = array(
			'titulo'    => get_the_title( $post ),
			'url'       => $url,
			'ano'       => (string) get_post_meta( $post->ID, '_setceb_doc_ano', true ),
			'descricao' => '',
		);

		$termos = wp_get_post_terms( $post->ID, 'setceb_cat_doc', array( 'fields' => 'slugs' ) );

		if ( ! is_wp_error( $termos ) && ! empty( $termos ) ) {
			$item['categoria'] = $termos[0];
		}

		if ( '1' === get_post_meta( $post->ID, '_setceb_doc_destaque', true ) ) {
			$item['destaque'] = true;
		}

		$itens[] = $item;
	}

	return $itens;
}

/**
 * Metabox com arquivo, ano e destaque do documento.
 */
function setceb_documento_meta_box() {
	add_meta_box(
		'setceb_doc_arquivo',
		'Arquivo do documento',
		'setceb_documento_meta_box_render',
		setceb_documento_post_types(),
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'setceb_documento_meta_box' );

/**
 * Renderiza o metabox.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_documento_meta_box_render( $post ) {
	wp_nonce_field( 'setceb_doc_arquivo', 'setceb_doc_nonce' );

	$url      = get_post_meta( $post->ID, '_setceb_doc_url', true );
	$ano      = get_post_meta( $post->ID, '_setceb_doc_ano', true );
	$destaque = '1' === get_post_meta( $post->ID, '_setceb_doc_destaque', true );
	?>
	<p>
		<label for="setceb-doc-url"><strong>Arquivo (URL)</strong></label><br>
		<input type="text" id="setceb-doc-url" name="setceb_doc_url" value="<?php echo esc_attr( $url ); ?>" class="large-text" placeholder="https://...">
		<button type="button" class="button" id="setceb-doc-media">Selecionar da Biblioteca</button>
	</p>
	<p>
		<label for="setceb-doc-ano"><strong>Ano</strong> (usado no seletor de ano da área do associado)</label><br>
		<input type="number" id="setceb-doc-ano" name="setceb_doc_ano" value="<?php echo esc_attr( $ano ); ?>" min="2000" max="2100" class="small-text">
	</p>
	<p>
		<label>
			<input type="checkbox" name="setceb_doc_destaque" value="1" <?php checked( $destaque ); ?>>
			Destacar este documento
		</label>
	</p>
	<p class="description">
		Envie o arquivo (PDF etc.) para a <em>Biblioteca de Mídia</em> e clique em
		<strong>Selecionar da Biblioteca</strong>, ou cole um link externo.
		Itens sem URL não aparecem na área do associado.
	</p>
	<?php
}

/**
 * Salva os campos do metabox.
 *
 * @param int $post_id ID do post.
 */
function setceb_documento_meta_save( $post_id ) {
	if ( ! isset( $_POST['setceb_doc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['setceb_doc_nonce'] ) ), 'setceb_doc_arquivo' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$url = isset( $_POST['setceb_doc_url'] ) ? esc_url_raw( wp_unslash( $_POST['setceb_doc_url'] ) ) : '';

	if ( '' !== $url ) {
		update_post_meta( $post_id, '_setceb_doc_url', $url );
	} else {
		delete_post_meta( $post_id, '_setceb_doc_url' );
	}

	$ano = isset( $_POST['setceb_doc_ano'] ) ? absint( wp_unslash( $_POST['setceb_doc_ano'] ) ) : 0;

	if ( $ano > 0 ) {
		update_post_meta( $post_id, '_setceb_doc_ano', (string) $ano );
	} else {
		delete_post_meta( $post_id, '_setceb_doc_ano' );
	}

	if ( ! empty( $_POST['setceb_doc_destaque'] ) ) {
		update_post_meta( $post_id, '_setceb_doc_destaque', '1' );
	} else {
		delete_post_meta( $post_id, '_setceb_doc_destaque' );
	}
}
add_action( 'save_post', 'setceb_documento_meta_save' );

/**
 * Carrega o media picker na edicao dos documentos.
 *
 * @param string $hook Pagina atual do admin.
 */
function setceb_documento_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->post_type, setceb_documento_post_types(), true ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'setceb-doc-admin',
		get_stylesheet_directory_uri() . '/admin-documentos.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'setceb_documento_admin_assets' );
