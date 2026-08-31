<?php
/**
 * SETCEB - Eventos (Custom Post Type)
 *
 * O admin cadastra os eventos no painel WordPress, no menu "Eventos".
 * Cada evento possui: titulo, descricao (conteudo editor), data do
 * evento e imagem em destaque (thumbnail). Quando nao ha imagem,
 * o shortcode exibe um fallback com icon de calendario (sem gradiente).
 *
 * Uso:
 *   [setceb_eventos]            - todos os eventos
 *
 * O shortcode exibe os eventos em lista/cards com filtro por ano e mes
 * baseado na data de cada evento.
 *
 * A data fica registrada no meta _setceb_evento_data no formato Y-m-d.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra o tipo de post "Evento".
 */
function setceb_eventos_register() {
	register_post_type(
		'setceb_evento',
		array(
			'labels'       => array(
				'name'          => 'Eventos',
				'singular_name' => 'Evento',
				'add_new_item'  => 'Adicionar Evento',
				'edit_item'     => 'Editar Evento',
				'new_item'      => 'Novo Evento',
				'view_item'     => 'Ver Evento',
				'search_items'  => 'Buscar em Eventos',
				'not_found'     => 'Nenhum evento cadastrado.',
			),
			'public'       => true,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'eventos' ),
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'setceb_eventos_register' );

/**
 * Consulta os eventos publicados.
 *
 * @return WP_Post[] Eventos ordenados pela data do evento (mais proximos primeiro).
 */
function setceb_eventos_query() {
	$posts = get_posts(
		array(
			'post_type'        => 'setceb_evento',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'meta_value',
			'meta_key'         => '_setceb_evento_data',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);

	return $posts;
}

/**
 * Retorna a data de um evento.
 *
 * @param int $post_id ID do evento.
 * @return array Array com timestamp, data bruta e partes.
 */
function setceb_evento_data( $post_id ) {
	$raw = get_post_meta( $post_id, '_setceb_evento_data', true );

	if ( ! $raw || false === strtotime( $raw ) ) {
		return null;
	}

	$time = strtotime( $raw );

	return array(
		'raw'  => $raw,
		'ts'   => $time,
		'year' => (int) gmdate( 'Y', $time ),
		'month'=> (int) gmdate( 'n', $time ),
	);
}

/* ------------------------------------------------------------
 * Metabox - data do evento
 * ------------------------------------------------------------ */

/**
 * Registra o metabox de data.
 */
function setceb_evento_meta_box() {
	add_meta_box(
		'setceb_evento_data',
		'Data do evento',
		'setceb_evento_meta_box_render',
		'setceb_evento',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'setceb_evento_meta_box' );

/**
 * Renderiza o metabox de data.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_evento_meta_box_render( $post ) {
	wp_nonce_field( 'setceb_evento_data', 'setceb_evento_nonce' );

	$data = get_post_meta( $post->ID, '_setceb_evento_data', true );
	?>
	<p>
		<label for="setceb-evento-data"><strong>Data do evento</strong></label><br>
		<input type="date" id="setceb-evento-data" name="setceb_evento_data" value="<?php echo esc_attr( $data ); ?>">
	</p>
	<p class="description">A data exibida no card do evento e usada no filtro por ano/mês.</p>
	<?php
}

/**
 * Salva o campo do metabox.
 *
 * @param int $post_id ID do post.
 */
function setceb_evento_meta_save( $post_id ) {
	if ( ! isset( $_POST['setceb_evento_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['setceb_evento_nonce'] ) ), 'setceb_evento_data' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['setceb_evento_data'] ) ) {
		return;
	}

	$raw = sanitize_text_field( wp_unslash( $_POST['setceb_evento_data'] ) );

	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
		update_post_meta( $post_id, '_setceb_evento_data', $raw );
	} else {
		delete_post_meta( $post_id, '_setceb_evento_data' );
	}
}
add_action( 'save_post', 'setceb_evento_meta_save' );

/* ------------------------------------------------------------
 * Assets
 * ------------------------------------------------------------ */

function setceb_eventos_register_assets() {
	$theme = wp_get_theme();

	wp_register_script(
		'setceb-eventos',
		get_stylesheet_directory_uri() . '/eventos.js',
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'setceb_eventos_register_assets' );

/**
 * Detecta o shortcode no conteudo e enfileira os assets.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_eventos_maybe_enqueue( $post ) {
	if ( ! $post || ! isset( $post->post_content ) ) {
		return;
	}

	if ( has_shortcode( $post->post_content, 'setceb_eventos' ) ) {
		setceb_eventos_enqueue();
	}
}
add_action( 'the_post', 'setceb_eventos_maybe_enqueue' );

/**
 * Enfileira de fato os assets dos eventos.
 */
function setceb_eventos_enqueue() {
	wp_enqueue_script( 'setceb-eventos' );
	wp_enqueue_style( 'dashicons' );
}

/* ------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------ */

/**
 * Helper para preservar acentos na ordenacao de meses.
 *
 * @return string[] Meses 1..12 em portugues.
 */
function setceb_eventos_meses() {
	return array(
		1  => 'Janeiro',
		2  => 'Fevereiro',
		3  => 'Março',
		4  => 'Abril',
		5  => 'Maio',
		6  => 'Junho',
		7  => 'Julho',
		8  => 'Agosto',
		9  => 'Setembro',
		10 => 'Outubro',
		11 => 'Novembro',
		12 => 'Dezembro',
	);
}

/**
 * Renderiza a listagem de eventos.
 *
 * @param array|string $atts Atributos do shortcode.
 * @return string HTML da listagem.
 */
function setceb_eventos_render_shortcode( $atts = array() ) {
	$atts = shortcode_atts( array(), $atts, 'setceb_eventos' );

	// Enfileira os assets (seguro chamar aqui).
	setceb_eventos_enqueue();

	$eventos = setceb_eventos_query();

	// Constroi os dados de cada evento ja formatados para a view.
	$itens      = array();
	$anos_lista = array();

	foreach ( $eventos as $evento ) {
		$dados = setceb_evento_data( $evento->ID );

		if ( ! $dados ) {
			continue;
		}

		$itens[] = array(
			'id'          => $evento->ID,
			'titulo'      => get_the_title( $evento ),
			'descricao'   => wp_strip_all_tags( has_excerpt( $evento ) ? get_the_excerpt( $evento ) : get_the_content( null, false, $evento ) ),
			'link'        => get_permalink( $evento ),
			'data'        => $dados,
			'data_label'  => setceb_evento_data_label( $dados['ts'] ),
			'imagem'      => get_the_post_thumbnail( $evento, 'medium_large', array( 'class' => 'setceb-eventos__img', 'loading' => 'lazy' ) ),
		);

		$anos_lista[ $dados['year'] ] = $dados['year'];
	}

	krsort( $anos_lista ); // Anos mais recentes primeiro.

	// Se nao ha eventos, ordena por data e renderiza vazio.
	ob_start();
	?>
	<div class="setceb-eventos" data-setceb-eventos>
		<?php if ( empty( $itens ) ) : ?>
			<p class="setceb-eventos__empty">Nenhum evento cadastrado ainda.</p>
		<?php else : ?>

			<form class="setceb-eventos__filtro" method="get" role="search" aria-label="Filtrar eventos">
				<div class="setceb-eventos__filtro-field">
					<label for="setceb-eventos-ano">Ano</label>
					<select id="setceb-eventos-ano" data-setceb-eventos-ano>
						<option value="">Todos os anos</option>
						<?php foreach ( $anos_lista as $ano ) : ?>
							<option value="<?php echo esc_attr( (string) $ano ); ?>"><?php echo esc_html( (string) $ano ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="setceb-eventos__filtro-field">
					<label for="setceb-eventos-mes">Mês</label>
					<select id="setceb-eventos-mes" data-setceb-eventos-mes disabled>
						<option value="">Todos os meses</option>
					</select>
				</div>
			</form>

			<ul class="setceb-eventos__lista" data-setceb-eventos-lista>
				<?php foreach ( $itens as $item ) : ?>
					<?php setceb_eventos_render_card( $item ); ?>
				<?php endforeach; ?>
			</ul>

			<p class="setceb-eventos__empty-result" hidden>Nenhum evento encontrado para o filtro selecionado.</p>

		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'setceb_eventos', 'setceb_eventos_render_shortcode' );

/**
 * Formata a data do evento para exibicao.
 *
 * Ex.: 15 de Março de 2026
 *
 * @param int $ts Timestamp.
 * @return string
 */
function setceb_evento_data_label( $ts ) {
	$meses = setceb_eventos_meses();

	return sprintf(
		'%d de %s de %d',
		(int) gmdate( 'j', $ts ),
		$meses[ (int) gmdate( 'n', $ts ) ],
		(int) gmdate( 'Y', $ts )
	);
}

/**
 * Renderiza um card de evento.
 *
 * @param array $item Dados do evento.
 */
function setceb_eventos_render_card( $item ) {
	$data_attr = '';
	if ( $item['data'] ) {
		$data_attr = sprintf(
			' data-ano="%d" data-mes="%d"',
			$item['data']['year'],
			$item['data']['month']
		);
	}
	?>
	<li class="setceb-eventos__item"<?php echo $data_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<a class="setceb-eventos__card" href="<?php echo esc_url( $item['link'] ); ?>">
			<div class="setceb-eventos__media">
				<?php if ( $item['imagem'] ) : ?>
					<?php echo $item['imagem']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<div class="setceb-eventos__fallback" aria-hidden="true">
						<span class="dashicons dashicons-calendar-alt"></span>
					</div>
				<?php endif; ?>
			</div>
			<div class="setceb-eventos__body">
				<time class="setceb-eventos__data" datetime="<?php echo esc_attr( $item['data']['raw'] ); ?>"><?php echo esc_html( $item['data_label'] ); ?></time>
				<h3 class="setceb-eventos__titulo"><?php echo esc_html( $item['titulo'] ); ?></h3>
				<?php if ( '' !== $item['descricao'] ) : ?>
					<p class="setceb-eventos__descricao"><?php echo esc_html( $item['descricao'] ); ?></p>
				<?php endif; ?>
			</div>
		</a>
	</li>
	<?php
}
