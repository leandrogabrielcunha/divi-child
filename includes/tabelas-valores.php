<?php
/**
 * SETCEB - Tabelas de Valores (Custom Post Type + shortcode)
 *
 * O admin cadastra as tabelas de valores (servicos e valores para
 * associado) direto no painel WordPress, no menu "Tabelas de Valores".
 * Cada tabela possui: titulo (obrigatorio), subtitulo/descricao
 * (opcional), vigencia (opcional), linhas (servico, valor normal e
 * valor para associado) e observacao (opcional).
 *
 * Uso no conteudo:
 *   [tabela_valores id="123"]  - tabela especifica
 *   [tabela_valores]           - tabela publicada mais recente
 *
 * Arquitetura:
 * - CPT e metaboxes neste arquivo (inclui/admin)
 * - Repetidor de linhas e reordenacao em tabelas-valores.js (admin)
 * - CSS do front em style.css (.setceb-tabela-valores)
 * - Sem plugins externos e sem dependencias.
 *
 * As linhas ficam na meta _setceb_tabela_linhas (array serializado pelo
 * proprio WordPress), a observacao em _setceb_tabela_obs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug do post type (limite do WordPress: 20 caracteres).
 */
if ( ! defined( 'SETCEB_TABELA_VALORES_CPT' ) ) {
	define( 'SETCEB_TABELA_VALORES_CPT', 'tabelas-valores' );
}

/**
 * Cabecalho usado quando a linha nao tem valor para associado.
 */
if ( ! defined( 'SETCEB_TABELA_VALORES_VAZIO' ) ) {
	define( 'SETCEB_TABELA_VALORES_VAZIO', "\xE2\x80\x94" ); // —
}

/* ------------------------------------------------------------
 * Custom Post Type
 * ------------------------------------------------------------ */

/**
 * Registra o tipo de post "Tabelas de Valores".
 */
function setceb_tabela_valores_register() {
	$labels = array(
		'name'                  => 'Tabelas de Valores',
		'singular_name'         => 'Tabela de Valores',
		'menu_name'             => 'Tabelas de Valores',
		'add_new'               => 'Adicionar',
		'add_new_item'          => 'Adicionar Tabela de Valores',
		'new_item'              => 'Nova Tabela de Valores',
		'edit_item'             => 'Editar Tabela de Valores',
		'view_item'             => 'Ver Tabela de Valores',
		'search_items'          => 'Buscar em Tabelas de Valores',
		'not_found'             => 'Nenhuma tabela cadastrada.',
		'not_found_in_trash'    => 'Nenhuma tabela na lixeira.',
		'all_items'             => 'Todas as Tabelas',
	);

	register_post_type(
		SETCEB_TABELA_VALORES_CPT,
		array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-editor-table',
			'menu_position'   => 35,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title' ),
			'has_archive'     => false,
			'hierarchical'    => false,
			'rewrite'         => false,
			'query_var'       => false,
			'show_in_rest'    => false,
		)
	);
}
add_action( 'init', 'setceb_tabela_valores_register' );

/**
 * Mantém a edição no editor clássico (metaboxes), onde o repetidor
 * de linhas aparece logo abaixo do título.
 *
 * @param bool   $use_block_editor Valor atual.
 * @param string $post_type        Tipo de post em edição.
 * @return bool
 */
function setceb_tabela_valores_block_editor( $use_block_editor, $post_type ) {
	if ( SETCEB_TABELA_VALORES_CPT === $post_type ) {
		return false;
	}

	return $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'setceb_tabela_valores_block_editor', 10, 2 );

/* ------------------------------------------------------------
 * Formatacao de valores
 * ------------------------------------------------------------ */

/**
 * Converte o que o usuario digitou em numero.
 *
 * Aceita "281,00", "281.00", "R$ 1.234,56", "281" ou "1.234,56".
 *
 * @param string|float|int $valor Valor digitado.
 * @return float|null Numero ou null quando nao ha valor.
 */
function setceb_tabela_valores_numero( $valor ) {
	if ( is_float( $valor ) || is_int( $valor ) ) {
		return (float) $valor;
	}

	$texto = trim( (string) $valor );

	if ( '' === $texto ) {
		return null;
	}

	// Remove tudo que nao for digito, ponto ou virgula.
	$texto = preg_replace( '/[^0-9.,]/', '', $texto );

	if ( null === $texto || '' === $texto ) {
		return null;
	}

	$virgula = strrpos( $texto, ',' );
	$ponto   = strrpos( $texto, '.' );

	if ( false !== $virgula && ( false === $ponto || $virgula > $ponto ) ) {
		// Virgula como separador decimal: 1.234,56.
		$texto = str_replace( '.', '', $texto );
		$texto = str_replace( ',', '.', $texto );
	} elseif ( false !== $ponto && false === $virgula ) {
		// Ponto como separador decimal: 1234.56.
		$texto = str_replace( ',', '', $texto );
	}

	return (float) $texto;
}

/**
 * Formata um valor no padrao brasileiro: R$ 281,00.
 *
 * @param string|float|int $valor Valor digitado.
 * @return string Valor formatado ou o marcador de linha vazia.
 */
function setceb_tabela_valores_formatar( $valor ) {
	$numero = setceb_tabela_valores_numero( $valor );

	if ( null === $numero ) {
		return SETCEB_TABELA_VALORES_VAZIO;
	}

	return 'R$ ' . number_format( $numero, 2, ',', '.' );
}

/* ------------------------------------------------------------
 * Leitura dos dados
 * ------------------------------------------------------------ */

/**
 * Devolve as linhas da tabela ja normalizadas.
 *
 * @param int $post_id ID da tabela.
 * @return array[] Lista de linhas com os campos servico, valor e valor_associado.
 */
function setceb_tabela_valores_linhas( $post_id ) {
	$bruto = get_post_meta( $post_id, '_setceb_tabela_linhas', true );

	if ( ! is_array( $bruto ) ) {
		return array();
	}

	$linhas = array();

	foreach ( $bruto as $linha ) {
		if ( ! is_array( $linha ) ) {
			continue;
		}

		$servico = isset( $linha['servico'] ) ? trim( (string) $linha['servico'] ) : '';
		$valor   = isset( $linha['valor'] ) ? trim( (string) $linha['valor'] ) : '';
		$assoc   = isset( $linha['valor_associado'] ) ? trim( (string) $linha['valor_associado'] ) : '';

		// Linha sem servico e sem valores nao entra na tabela.
		if ( '' === $servico && '' === $valor && '' === $assoc ) {
			continue;
		}

		$linhas[] = array(
			'servico'        => $servico,
			'valor'          => $valor,
			'valor_associado' => $assoc,
		);
	}

	return $linhas;
}

/**
 * Monta os dados completos de uma tabela para a view.
 *
 * @param int $post_id ID da tabela.
 * @return array|null Dados da tabela ou null se nao existir/nao publicada.
 */
function setceb_tabela_valores_dados( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post || SETCEB_TABELA_VALORES_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}

	return array(
		'id'        => $post->ID,
		'titulo'    => get_the_title( $post ),
		'subtitulo' => trim( (string) get_post_meta( $post->ID, '_setceb_tabela_subtitulo', true ) ),
		'vigencia'  => trim( (string) get_post_meta( $post->ID, '_setceb_tabela_vigencia', true ) ),
		'obs'       => trim( (string) get_post_meta( $post->ID, '_setceb_tabela_obs', true ) ),
		'linhas'    => setceb_tabela_valores_linhas( $post->ID ),
	);
}

/* ------------------------------------------------------------
 * Metabox - subtitulo e vigencia
 * ------------------------------------------------------------ */

/**
 * Registra os metaboxes da tabela.
 */
function setceb_tabela_valores_meta_boxes() {
	add_meta_box(
		'setceb_tabela_info',
		'Subtítulo e vigência',
		'setceb_tabela_valores_meta_box_info',
		SETCEB_TABELA_VALORES_CPT,
		'side',
		'default'
	);

	add_meta_box(
		'setceb_tabela_linhas',
		'Linhas da tabela',
		'setceb_tabela_valores_meta_box_linhas',
		SETCEB_TABELA_VALORES_CPT,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'setceb_tabela_valores_meta_boxes' );

/**
 * Renderiza o metabox de subtitulo e vigencia.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_tabela_valores_meta_box_info( $post ) {
	wp_nonce_field( 'setceb_tabela_valores', 'setceb_tabela_nonce' );

	$subtitulo = (string) get_post_meta( $post->ID, '_setceb_tabela_subtitulo', true );
	$vigencia  = (string) get_post_meta( $post->ID, '_setceb_tabela_vigencia', true );
	?>
	<p>
		<label for="setceb-tabela-subtitulo"><strong>Subtítulo / descrição</strong> (opcional)</label><br>
		<textarea
			id="setceb-tabela-subtitulo"
			name="setceb_tabela_subtitulo"
			rows="3"
			class="widefat"
			placeholder="Ex.: Valores das taxas de serviços cobrados pelo SETCEB."
		><?php echo esc_textarea( $subtitulo ); ?></textarea>
	</p>
	<p>
		<label for="setceb-tabela-vigencia"><strong>Vigência</strong> (opcional)</label><br>
		<input
			type="text"
			id="setceb-tabela-vigencia"
			name="setceb_tabela_vigencia"
			class="widefat"
			value="<?php echo esc_attr( $vigencia ); ?>"
			placeholder="Vigência a partir de 01/02/2026"
		>
	</p>
	<p class="description">
		O <strong>título do post</strong> (no topo da tela) é o nome da tabela.<br>
		Para exibir no site, insira o shortcode
		<code>[tabela_valores id="ID_DESTA_TABELA"]</code> em uma página
		(ou bloco de texto/parágrafo do Divi). Sem <code>id</code>, mostra a
		tabela publicada mais recente.
	</p>
	<?php
}

/* ------------------------------------------------------------
 * Metabox - linhas e observacao
 * ------------------------------------------------------------ */

/**
 * Renderiza o metabox com as linhas e a observacao.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_tabela_valores_meta_box_linhas( $post ) {
	wp_nonce_field( 'setceb_tabela_valores', 'setceb_tabela_nonce' );

	$linhas = setceb_tabela_valores_linhas( $post->ID );
	$obs    = (string) get_post_meta( $post->ID, '_setceb_tabela_obs', true );

	if ( empty( $linhas ) ) {
		// Sempre comeca com uma linha em branco para facilitar o cadastro.
		$linhas = array( array( 'servico' => '', 'valor' => '', 'valor_associado' => '' ) );
	}
	?>
	<p class="description" style="margin:0 0 10px;">
		Monte a tabela com uma linha por serviço. O valor pode ser digitado como
		<strong>281,00</strong>, <strong>R$ 281,00</strong> ou <strong>281</strong>.
		Sem valor para associado, deixe o campo vazio ou use o travessão <strong>—</strong>.
	</p>

	<div class="setceb-tabela-rep" id="setceb-tabela-rep">
		<div class="setceb-tabela-rep__cabecalho" aria-hidden="true">
			<span class="setceb-tabela-rep__grip"></span>
			<span class="setceb-tabela-rep__col setceb-tabela-rep__col--servico">Serviço</span>
			<span class="setceb-tabela-rep__col setceb-tabela-rep__col--valor">Valor normal (R$)</span>
			<span class="setceb-tabela-rep__col setceb-tabela-rep__col--valor">Valor para associado (R$)</span>
			<span class="setceb-tabela-rep__col setceb-tabela-rep__col--acoes">Ordenar</span>
		</div>

		<div class="setceb-tabela-rep__lista" id="setceb-tabela-rep-lista">
			<?php foreach ( $linhas as $i => $linha ) : ?>
				<?php setceb_tabela_valores_render_linha( $linha, $i ); ?>
			<?php endforeach; ?>
		</div>
	</div>

	<p style="margin:12px 0 0;">
		<button type="button" class="button button-secondary" data-setceb-tabela-add>+ Adicionar linha</button>
	</p>

	<p>
		<label for="setceb-tabela-obs"><strong>Observação</strong> (opcional, aparece abaixo da tabela)</label><br>
		<textarea
			id="setceb-tabela-obs"
			name="setceb_tabela_obs"
			rows="3"
			class="widefat"
			placeholder="Ex.: Valores vigentes a partir de 01/02/2026."
		><?php echo esc_textarea( $obs ); ?></textarea>
	</p>

	<script type="text/template" id="setceb-tabela-linha-modelo">
		<?php
		setceb_tabela_valores_render_linha(
			array(
				'servico'         => '',
				'valor'           => '',
				'valor_associado' => '',
			),
			'__indice__'
		);
		?>
	</script>
	<?php
}

/**
 * Renderiza uma linha do repetidor.
 *
 * @param array    $linha  Linha com servico, valor e valor_associado.
 * @param int|string $indice Indice da linha no nome dos campos.
 */
function setceb_tabela_valores_render_linha( $linha, $indice ) {
	$servico = isset( $linha['servico'] ) ? $linha['servico'] : '';
	$valor   = isset( $linha['valor'] ) ? $linha['valor'] : '';
	$assoc   = isset( $linha['valor_associado'] ) ? $linha['valor_associado'] : '';
	$posicao = is_numeric( $indice ) ? (string) ( (int) $indice + 1 ) : '';
	?>
	<div class="setceb-tabela-rep__linha" data-setceb-tabela-linha>
		<span class="setceb-tabela-rep__grip" data-setceb-tabela-grip draggable="true" title="Arraste para reordenar" aria-hidden="true">&#8942;&#8942;</span>
		<span class="setceb-tabela-rep__col setceb-tabela-rep__col--servico">
			<label class="screen-reader-text" for="setceb-tabela-servico-<?php echo esc_attr( $indice ); ?>">Serviço</label>
			<input
				type="text"
				id="setceb-tabela-servico-<?php echo esc_attr( $indice ); ?>"
				class="setceb-tabela-rep__input"
				data-setceb-tabela-campo="servico"
				name="setceb_tabela_linhas[<?php echo esc_attr( $indice ); ?>][servico]"
				value="<?php echo esc_attr( $servico ); ?>"
				placeholder="Ex.: Reativação de Transportador"
				autocomplete="off"
			>
		</span>
		<span class="setceb-tabela-rep__col setceb-tabela-rep__col--valor" data-rotulo="Valor normal (R$)">
			<label class="screen-reader-text" for="setceb-tabela-valor-<?php echo esc_attr( $indice ); ?>">Valor normal (R$)</label>
			<input
				type="text"
				id="setceb-tabela-valor-<?php echo esc_attr( $indice ); ?>"
				class="setceb-tabela-rep__input"
				data-setceb-tabela-campo="valor"
				name="setceb_tabela_linhas[<?php echo esc_attr( $indice ); ?>][valor]"
				value="<?php echo esc_attr( $valor ); ?>"
				placeholder="281,00"
				inputmode="decimal"
				autocomplete="off"
			>
		</span>
		<span class="setceb-tabela-rep__col setceb-tabela-rep__col--valor" data-rotulo="Valor para associado (R$)">
			<label class="screen-reader-text" for="setceb-tabela-associado-<?php echo esc_attr( $indice ); ?>">Valor para associado (R$)</label>
			<input
				type="text"
				id="setceb-tabela-associado-<?php echo esc_attr( $indice ); ?>"
				class="setceb-tabela-rep__input"
				data-setceb-tabela-campo="valor_associado"
				name="setceb_tabela_linhas[<?php echo esc_attr( $indice ); ?>][valor_associado]"
				value="<?php echo esc_attr( $assoc ); ?>"
				placeholder="130,50 ou —"
				inputmode="decimal"
				autocomplete="off"
			>
		</span>
		<span class="setceb-tabela-rep__col setceb-tabela-rep__col--acoes">
			<span class="setceb-tabela-rep__ordem" data-setceb-tabela-ordem aria-hidden="true"><?php echo esc_html( $posicao ); ?></span>
			<button
				type="button"
				class="button button-small"
				data-setceb-tabela-move="-1"
				title="Mover linha para cima"
				aria-label="Mover linha para cima"
			>&uarr;</button>
			<button
				type="button"
				class="button button-small"
				data-setceb-tabela-move="1"
				title="Mover linha para baixo"
				aria-label="Mover linha para baixo"
			>&darr;</button>
			<button
				type="button"
				class="button button-small setceb-tabela-rep__remover"
				data-setceb-tabela-remove
				title="Remover linha"
				aria-label="Remover linha"
			>&times;</button>
		</span>
	</div>
	<?php
}

/* ------------------------------------------------------------
 * Salvamento
 * ------------------------------------------------------------ */

/**
 * Salva subtitulo, vigencia, linhas e observacao.
 *
 * @param int $post_id ID do post.
 */
function setceb_tabela_valores_save( $post_id ) {
	if ( ! isset( $_POST['setceb_tabela_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['setceb_tabela_nonce'] ) ), 'setceb_tabela_valores' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( SETCEB_TABELA_VALORES_CPT !== get_post_type( $post_id ) ) {
		return;
	}

	$texto = array(
		'_setceb_tabela_subtitulo' => 'setceb_tabela_subtitulo',
		'_setceb_tabela_vigencia'  => 'setceb_tabela_vigencia',
		'_setceb_tabela_obs'       => 'setceb_tabela_obs',
	);

	foreach ( $texto as $meta_key => $field ) {
		$valor = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';

		if ( '' !== $valor ) {
			update_post_meta( $post_id, $meta_key, $valor );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	$linhas = array();

	if ( isset( $_POST['setceb_tabela_linhas'] ) && is_array( $_POST['setceb_tabela_linhas'] ) ) {
		foreach ( wp_unslash( $_POST['setceb_tabela_linhas'] ) as $linha ) {
			if ( ! is_array( $linha ) ) {
				continue;
			}

			$servico = isset( $linha['servico'] ) ? sanitize_text_field( $linha['servico'] ) : '';
			$valor   = isset( $linha['valor'] ) ? sanitize_text_field( $linha['valor'] ) : '';
			$assoc   = isset( $linha['valor_associado'] ) ? sanitize_text_field( $linha['valor_associado'] ) : '';

			if ( '' === trim( $servico ) && '' === trim( $valor ) && '' === trim( $assoc ) ) {
				continue;
			}

			$linhas[] = array(
				'servico'         => trim( $servico ),
				'valor'           => trim( $valor ),
				'valor_associado' => trim( $assoc ),
			);
		}
	}

	if ( ! empty( $linhas ) ) {
		update_post_meta( $post_id, '_setceb_tabela_linhas', $linhas );
	} else {
		delete_post_meta( $post_id, '_setceb_tabela_linhas' );
	}
}
add_action( 'save_post', 'setceb_tabela_valores_save' );

/* ------------------------------------------------------------
 * Assets do admin
 * ------------------------------------------------------------ */

/**
 * Carrega o JS do repetidor apenas na edicao das tabelas.
 *
 * @param string $hook Pagina atual do admin.
 */
function setceb_tabela_valores_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || SETCEB_TABELA_VALORES_CPT !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script(
		'setceb-tabela-valores',
		get_stylesheet_directory_uri() . '/tabelas-valores.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	// Estilo do repetidor (somente admin).
	wp_enqueue_style(
		'setceb-tabela-valores-admin',
		get_stylesheet_directory_uri() . '/tabelas-valores-admin.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'admin_enqueue_scripts', 'setceb_tabela_valores_admin_assets' );

/* ------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------ */

/**
 * Renderiza a tabela de valores.
 *
 * @param array|string $atts Atributos do shortcode (id).
 * @return string HTML da tabela.
 */
function setceb_tabela_valores_shortcode( $atts = array() ) {
	$atts = shortcode_atts( array( 'id' => '' ), $atts, 'tabela_valores' );

	$dados = null;
	$id = trim( (string) $atts['id'] );

	if ( '' !== $id ) {
		// Id informado: renderiza somente essa tabela.
		$id_num = absint( $id );
		$dados  = ( $id_num > 0 ) ? setceb_tabela_valores_dados( $id_num ) : null;
	} else {
		// Sem id: usa a tabela publicada mais recente com linhas.
		$recentes = get_posts(
			array(
				'post_type'        => SETCEB_TABELA_VALORES_CPT,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		if ( ! empty( $recentes ) ) {
			$dados = setceb_tabela_valores_dados( $recentes[0]->ID );
		}
	}

	if ( ! $dados ) {
		return '<p class="setceb-tabela-valores__vazio">Tabela de valores não encontrada ou não publicada.</p>';
	}

	if ( empty( $dados['linhas'] ) ) {
		return '<p class="setceb-tabela-valores__vazio">Esta tabela de valores ainda não possui linhas cadastradas.</p>';
	}

	ob_start();
	?>
	<div class="setceb-tabela-valores" id="setceb-tabela-valores-<?php echo esc_attr( (string) $dados['id'] ); ?>">
		<?php if ( '' !== $dados['titulo'] ) : ?>
			<header class="setceb-tabela-valores__cabecalho">
				<h2 class="setceb-tabela-valores__titulo"><?php echo esc_html( $dados['titulo'] ); ?></h2>
				<?php if ( '' !== $dados['vigencia'] ) : ?>
					<p class="setceb-tabela-valores__vigencia"><?php echo esc_html( $dados['vigencia'] ); ?></p>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php if ( '' !== $dados['subtitulo'] ) : ?>
			<p class="setceb-tabela-valores__subtitulo"><?php echo esc_html( $dados['subtitulo'] ); ?></p>
		<?php endif; ?>

		<div class="setceb-tabela-valores__box">
			<table class="setceb-tabela-valores__tabela">
				<thead>
					<tr>
						<th scope="col" class="setceb-tabela-valores__col-servico">Serviço</th>
						<th scope="col" class="setceb-tabela-valores__col-valor">Valor normal</th>
						<th scope="col" class="setceb-tabela-valores__col-valor">Valor para associado</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $dados['linhas'] as $i => $linha ) : ?>
						<tr class="setceb-tabela-valores__linha<?php echo ( 0 === ( (int) $i % 2 ) ) ? ' setceb-tabela-valores__linha--alt' : ''; ?>">
							<td class="setceb-tabela-valores__col-servico" data-label="Serviço">
								<span class="setceb-tabela-valores__servico"><?php echo esc_html( $linha['servico'] ); ?></span>
							</td>
							<td class="setceb-tabela-valores__col-valor" data-label="Valor normal">
								<?php echo esc_html( setceb_tabela_valores_formatar( $linha['valor'] ) ); ?>
							</td>
							<td class="setceb-tabela-valores__col-valor setceb-tabela-valores__col-associado" data-label="Valor para associado">
								<?php echo esc_html( setceb_tabela_valores_formatar( $linha['valor_associado'] ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( '' !== $dados['obs'] ) : ?>
			<p class="setceb-tabela-valores__obs"><strong>Observação:</strong> <?php echo esc_html( $dados['obs'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tabela_valores', 'setceb_tabela_valores_shortcode' );