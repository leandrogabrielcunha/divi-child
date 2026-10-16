<?php
/**
 * SETCEB - Modulo de Solicitacoes Juridicas
 *
 * Centraliza os atendimentos da Assessoria Juridica da entidade.
 *
 * O formulario Juridico da Area do Associado (page-associado.php), que
 * antes apenas enviava a mensagem por e-mail, passa a registrar a
 * solicitacao no painel (CPT setceb_solicitacao). Cada solicitacao guarda:
 * solicitante, status do atendimento, advogado responsavel, resposta do
 * juridico e o historico das interacoes.
 *
 * Perfis envolvidos:
 * - "advogado": equipe juridica. Ve apenas o menu de Solicitacoes
 *   Juridicas e a tela de perfil do proprio usuario; qualquer outro
 *   endereco do wp-admin encaminha para a listagem.
 * - Administradores: cadastram os advogados em Solicitacoes Juridicas >
 *   Advogados e acompanham os indicadores no Dashboard.
 *
 * Notificacoes por e-mail:
 * - nova solicitacao  -> advogado responsavel (ou todos os advogados)
 * - resposta/status   -> associado solicitante
 * - nova mensagem    -> advogado responsavel
 *
 * Carregado por functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug do post type (limite do WordPress: 20 caracteres).
 */
if ( ! defined( 'SETCEB_SOL_CPT' ) ) {
	define( 'SETCEB_SOL_CPT', 'setceb_solicitacao' );
}

/**
 * Base das capacidades proprias do post type. Separada do slug do post
 * type porque as capacidades nao aceitam o sufixo "s" de forma implicita.
 */
if ( ! defined( 'SETCEB_SOL_CAP' ) ) {
	define( 'SETCEB_SOL_CAP', 'setceb_sol' );
}

/**
 * Perfil da equipe juridica.
 */
if ( ! defined( 'SETCEB_SOL_ROLE' ) ) {
	define( 'SETCEB_SOL_ROLE', 'advogado' );
}

/**
 * Versao do conjunto de capacidades do modulo.
 *
 * Mantida para diagnostico em suporte: informa qual revisao de
 * capacidades o site deveria ter. A concessao em si nao depende dela.
 */
if ( ! defined( 'SETCEB_SOL_CAPS_VERSION' ) ) {
	define( 'SETCEB_SOL_CAPS_VERSION', '2' );
}

/* ------------------------------------------------------------
 * 1. Perfil "advogado"
 * ------------------------------------------------------------ */

/**
 * Capacidades concedidas a equipe juridica: somente leitura e edicao
 * das solicitacoes. Nao ha permissao para criar, publicar ou excluir.
 */
function setceb_sol_caps_advogado() {
	$cap = SETCEB_SOL_CAP;

	/*
	 * Apenas capacidades PRIMITIVAS. Com map_meta_cap ativo, o core
	 * traduz sozinho edit_post/read_post/delete_post para o post type.
	 * Conceder as meta capabilities (edit_setceb_sol, read_setceb_sol)
	 * aqui faz o WordPress emitir "_doing_it_wrong: sempre informe um
	 * post especifico" sempre que algo verifica a meta cap sem ID.
	 */
	return array(
		'read'                            => true,
		'edit_' . $cap . 's'              => true,
		'edit_others_' . $cap . 's'       => true,
		'edit_published_' . $cap . 's'    => true,
		'read_private_' . $cap . 's'      => true,
	);
}

/**
 * Capacidades que nao podem existir no perfil "advogado".
 *
 * Sao as meta capabilities do post type. Concedidas diretamente, elas
 * quebram a checagem do core em toda tela do painel.
 *
 * @return string[]
 */
function setceb_sol_caps_proibidas() {
	$cap = SETCEB_SOL_CAP;

	return array( 'read_' . $cap, 'edit_' . $cap, 'delete_' . $cap );
}

/**
 * Registra o perfil "advogado" na primeira carga do tema.
 */
function setceb_sol_register_advogado_role() {
	if ( ! get_role( SETCEB_SOL_ROLE ) ) {
		add_role( SETCEB_SOL_ROLE, 'Advogado', setceb_sol_caps_advogado() );
	}
}
add_action( 'init', 'setceb_sol_register_advogado_role', 5 );

/**
 * Garante que o perfil "advogado" tenha exatamente as capacidades do modulo.
 *
 * Roda a cada requisicao, de proposito: e idempotente e custa poucas
 * consultas a um objeto de papel que o WordPress ja mantem em cache.
 * Enquanto isto dependia de um numero de versao gravado em option, uma
 * instalacao que ja tivesse o valor antigo nunca recebia as capacidades
 * novas - e, sem "edit_setceb_sols", o proprio core nega a listagem.
 */
function setceb_sol_sync_advogado_caps() {
	$role = get_role( SETCEB_SOL_ROLE );

	if ( ! $role ) {
		return;
	}

	foreach ( setceb_sol_caps_advogado() as $cap => $granted ) {
		if ( ! $role->has_cap( $cap ) ) {
			$role->add_cap( $cap );
		}
	}

	// As meta capabilities nao podem existir no perfil: com map_meta_cap
	// ativo o core traduz edit_post/read_post/delete_post sozinho, e a
	// concessao direta faz o WordPress emitir "_doing_it_wrong" em toda
	// verificacao sem post especifico.
	foreach ( setceb_sol_caps_proibidas() as $cap ) {
		if ( $role->has_cap( $cap ) ) {
			$role->remove_cap( $cap );
		}
	}
}
add_action( 'init', 'setceb_sol_sync_advogado_caps', 6 );

/**
 * Verifica se o usuario pertence a equipe juridica.
 *
 * @param WP_User|int|null $user Usuario (padrao: atual).
 * @return bool
 */
function setceb_is_advogado( $user = null ) {
	if ( is_numeric( $user ) || 0 === $user ) {
		$user = get_userdata( (int) $user );
	}

	if ( ! $user ) {
		$user = wp_get_current_user();
	}

	if ( ! $user || ! $user->exists() ) {
		return false;
	}

	return in_array( SETCEB_SOL_ROLE, (array) $user->roles, true );
}

/**
 * O usuario atual e a equipe juridica (sem consideracao de admin)?
 *
 * @return bool
 */
function setceb_sol_usuario_restrito() {
	return setceb_is_advogado() && ! current_user_can( 'manage_options' );
}

/* ------------------------------------------------------------
 * 2. Status do atendimento
 * ------------------------------------------------------------ */

/**
 * Status possiveis de uma solicitacao.
 *
 * "ativo" indica atendimento em andamento (usado nos indicadores) e
 * "mensagens" indica que a conversa aceita novas mensagens. Sao coisas
 * diferentes: uma solicitacao "respondida" ja foi atendida, mas continua
 * aberta para o advogado responder de novo e para o associado
 * complementar. Apenas "encerrada" fecha a conversa.
 *
 * @return array slug => array( rotulo, icone, cor, ativo, mensagens )
 */
function setceb_sol_status() {
	$status = array(
		'aberta'               => array(
			'rotulo'      => 'Aberta',
			'rotulo_curto' => 'Aberta',
			'icone'     => 'dashicons-marker',
			'cor'       => '#1D5B9F',
			'ativo'     => true,
			'mensagens' => true,
		),
		'em_analise'           => array(
			'rotulo'      => 'Em analise',
			'rotulo_curto' => 'Analise',
			'icone'     => 'dashicons-hourglass',
			'cor'       => '#0d8199',
			'ativo'     => true,
			'mensagens' => true,
		),
		'aguardando_associado' => array(
			'rotulo'      => 'Aguardando associado',
			'rotulo_curto' => 'Aguardando',
			'icone'     => 'dashicons-backup',
			'cor'       => '#B8860B',
			'ativo'     => true,
			'mensagens' => true,
		),
		'respondida'           => array(
			'rotulo'      => 'Respondida',
			'rotulo_curto' => 'Respondida',
			'icone'     => 'dashicons-yes-alt',
			'cor'       => '#1E7D46',
			'ativo'     => false,
			'mensagens' => true,
		),
		'encerrada'            => array(
			'rotulo'      => 'Encerrada',
			'rotulo_curto' => 'Encerrada',
			'icone'     => 'dashicons-archive',
			'cor'       => '#64748B',
			'ativo'     => false,
			'mensagens' => false,
		),
	);

	return apply_filters( 'setceb_sol_status', $status );
}

/**
 * Categorias de assunto da solicitacao juridica.
 *
 * Fonte unica: o select do formulario e a validacao do servidor leem
 * daqui. A lista nao pode existir em dois lugares - quando existia, o
 * servidor aceitava qualquer valor no POST e a categoria gravada
 * podia ser um slug que o formulario nunca offeria.
 *
 * @return array<string,string> slug => rotulo exibido.
 */
function setceb_sol_categorias() {
	$categorias = array(
		'trabalhista' => 'Trabalhista',
		'tributario'  => 'Tributário',
		'contratual'  => 'Contratual',
		'previdencia' => 'INSS / Previdência',
		'civil'       => 'Cível',
		'outro'       => 'Outro',
	);

	return apply_filters( 'setceb_sol_categorias', $categorias );
}

/**
 * Uma categoria enviada no POST e valida?
 *
 * @param string $slug Valor recebido.
 * @return bool
 */
function setceb_sol_categoria_valida( $slug ) {
	$slug = (string) $slug;

	return '' !== $slug && array_key_exists( $slug, setceb_sol_categorias() );
}

/**
 * Status padrao de uma solicitacao recem-criada.
 *
 * @return string
 */
function setceb_sol_status_padrao() {
	return apply_filters( 'setceb_sol_status_padrao', 'aberta' );
}

/**
 * Dados de um status, com rotulo seguro para status desconhecido.
 *
 * @param string $slug Slug do status.
 * @return array
 */
function setceb_sol_status_info( $slug ) {
	$status = setceb_sol_status();
	$padrao = setceb_sol_status_padrao();

	if ( isset( $status[ $slug ] ) ) {
		return $status[ $slug ];
	}

	// Um filtro pode ter removido o status recebido; cai no padrao e,
	// em ultimo caso, no primeiro status disponivel.
	if ( isset( $status[ $padrao ] ) ) {
		return $status[ $padrao ];
	}

	return empty( $status ) ? array() : reset( $status );
}

/* ------------------------------------------------------------
 * 3. Custom Post Type
 * ------------------------------------------------------------ */

/**
 * Registra o post type das solicitacoes juridicas.
 */
function setceb_sol_register_cpt() {
	$rotulo = 'Solicitacoes Juridicas';

	register_post_type(
		SETCEB_SOL_CPT,
		array(
			'labels'          => array(
				'name'                  => $rotulo,
				'singular_name'         => 'Solicitacao Juridica',
				'menu_name'             => $rotulo,
				'name_admin_bar'        => 'Solicitacao Juridica',
				'add_new'               => 'Nova Solicitacao',
				'add_new_item'          => 'Registrar Solicitacao',
				'new_item'              => 'Nova Solicitacao',
				'edit_item'             => 'Atender Solicitacao',
				'view_item'             => 'Ver Solicitacao',
				'search_items'          => 'Buscar em ' . $rotulo,
				'not_found'             => 'Nenhuma solicitacao registrada.',
				'not_found_in_trash'    => 'Nenhuma solicitacao na lixeira.',
				'all_items'             => 'Todas as Solicitacoes',
				'featured_image'        => '',
				'items_list'            => 'Solicitacoes',
				'item_published'        => 'Solicitacao registrada.',
				'item_updated'          => 'Solicitacao atualizada.',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			// "feedback" e um dashicon real (balão de solicitações) e não
			// conflita com os icones das demais paginas da area do associado.
			'menu_icon'       => 'dashicons-feedback',
			'menu_position'   => 36,
			'capability_type' => SETCEB_SOL_CAP,
			'map_meta_cap'    => true,
			// As solicitacoes sao registradas exclusivamente pelos associados
			// na Area do Associado. Negar "create_posts" remove o item
			// "Registrar Solicitacao" do menu e o botao "Adicionar novo" da
			// listagem, sem afetar a criacao programatica de
			// setceb_sol_criar().
			'capabilities'    => array(
				'create_posts' => 'do_not_allow',
			),
			'supports'        => array( 'title' ),
			'has_archive'     => false,
			'hierarchical'    => false,
			'rewrite'         => false,
			'query_var'       => false,
			'show_in_rest'    => false,
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'setceb_sol_register_cpt' );

/**
 * Mantem a edicao no editor classico (metaboxes), onde o atendimento
 * aparece logo abaixo do titulo.
 *
 * @param bool   $use_block_editor Valor atual.
 * @param string $post_type        Tipo de post em edicao.
 * @return bool
 */
function setceb_sol_block_editor( $use_block_editor, $post_type ) {
	if ( SETCEB_SOL_CPT === $post_type ) {
		return false;
	}

	return $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'setceb_sol_block_editor', 10, 2 );

/**
 * URL da listagem de solicitacoes no painel.
 *
 * @return string
 */
function setceb_sol_admin_url() {
	return admin_url( 'edit.php?post_type=' . SETCEB_SOL_CPT );
}

/* ------------------------------------------------------------
 * 4. Leitura dos dados
 * ------------------------------------------------------------ */

/**
 * Dados de uma solicitacao, no formato consumido pelas telas.
 *
 * @param int $post_id ID da solicitacao.
 * @return array|null
 */
function setceb_sol_obter( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post || SETCEB_SOL_CPT !== $post->post_type ) {
		return null;
	}

	$status = (string) get_post_meta( $post->ID, '_setceb_sol_status', true );

	if ( '' === $status ) {
		$status = setceb_sol_status_padrao();
	}

	$info       = setceb_sol_status_info( $status );
	$advogado   = (int) get_post_meta( $post->ID, '_setceb_sol_advogado', true );
	$usuario    = $advogado ? get_userdata( $advogado ) : false;
	$atualizada = (string) get_post_meta( $post->ID, '_setceb_sol_atualizada_em', true );

	return array(
		'id'            => $post->ID,
		'protocolo'     => '#' . $post->ID,
		'titulo'        => get_the_title( $post ),
		'mensagem'      => $post->post_content,
		'status'        => $status,
		'status_rotulo'       => $info['rotulo'],
		'status_rotulo_curto' => isset( $info['rotulo_curto'] ) ? $info['rotulo_curto'] : $info['rotulo'],
		'status_cor'    => $info['cor'],
		'status_icone'  => $info['icone'],
		'ativo'         => ! empty( $info['ativo'] ),
		'mensagens'     => ! empty( $info['mensagens'] ),
		'nome'          => (string) get_post_meta( $post->ID, '_setceb_sol_nome', true ),
		'email'         => (string) get_post_meta( $post->ID, '_setceb_sol_email', true ),
		'telefone'      => (string) get_post_meta( $post->ID, '_setceb_sol_telefone', true ),
		'empresa'       => (string) get_post_meta( $post->ID, '_setceb_sol_empresa', true ),
		'usuario_id'    => (int) $post->post_author,
		'advogado_id'   => $advogado,
		'advogado'      => ( $usuario && $usuario->exists() ) ? $usuario->display_name : '',
		'data'          => $post->post_date,
		'atualizada'    => '' !== $atualizada ? $atualizada : $post->post_date,
		'historico'     => setceb_sol_historico( $post->ID ),
	);
}

/**
 * Formata uma data (formato mysql) no padrao do site.
 *
 * @param string $data Data em Y-m-d H:i:s.
 * @return string
 */
function setceb_sol_data( $data ) {
	if ( '' === (string) $data ) {
		return '';
	}

	/* Formato curto (dd/mm/aaaa hh:mm): o formato do WP ("F j, Y g:i a")
	   gera cabecalhos de 3-4 linhas na conversa, principalmente no mobile.
	   Usa gmdate porque a data e' local "naive" - a mesma convencao de
	   setceb_sol_tempo_relativo() - e date_i18n() somaria o offset 2x. */
	$timestamp = strtotime( (string) $data . ' UTC' );

	if ( false === $timestamp ) {
		return '';
	}

	return gmdate( 'd/m/Y H:i', $timestamp );
}

/**
 * Botao "Solicitacoes" que devolve o usuario a lista das solicitacoes.
 *
 * No mobile a conversa abre em tela cheia (modo detalhe); este botao e a
 * unica saida. E um link real (?sol_lista=1) para funcionar tambem sem JS.
 *
 * @param string $perfil_url URL da pagina de perfil do associado.
 * @return string
 */
function setceb_sol_botao_voltar( $perfil_url ) {
	$url = add_query_arg( 'sol_lista', 1, $perfil_url ) . '#juridico';

	return sprintf(
		'<a class="assoc-sol__voltar" href="%1$s" data-sol-voltar><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span class="assoc-sol__voltar-label">Solicitações</span></a>',
		esc_url( $url )
	);
}

/**
 * Botao compacto "Nova" dentro do cabecalho da conversa.
 *
 * Sem ele o associado preso em uma conversa nao tem como iniciar outra
 * no mobile, porque a coluna de solicitacoes fica oculta no modo detalhe.
 *
 * @param string $perfil_url URL da pagina de perfil do associado.
 * @return string
 */
function setceb_sol_botao_nova( $perfil_url ) {
	$url = add_query_arg( 'sol_nova', 1, $perfil_url ) . '#juridico';

	return sprintf(
		'<a class="assoc-sol__nova-mini" href="%1$s" data-sol-nova><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span class="assoc-sol__nova-mini-label">Nova</span></a>',
		esc_url( $url )
	);
}

/**
 * Lista as solicitacoes de um associado (ou todas, para a equipe juridica).
 *
 * @param array $args {
 *     @type int    $usuario_id Autor da solicitacao (0 = todos).
 *     @type string $status     Filtro de status (vazio = todos).
 *     @type int    $limite     Quantidade maxima (-1 = todas).
 * }
 * @return array[] Lista de solicitacoes (formato setceb_sol_obter).
 */
function setceb_sol_listar( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'usuario_id' => 0,
			'status'     => '',
			'limite'     => -1,
		)
	);

	$consulta = array(
		'post_type'              => SETCEB_SOL_CPT,
		'post_status'            => 'publish',
		'posts_per_page'         => (int) $args['limite'],
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'suppress_filters'       => false,
	);

	if ( (int) $args['usuario_id'] > 0 ) {
		$consulta['author'] = (int) $args['usuario_id'];
	}

	if ( '' !== $args['status'] ) {
		$consulta['meta_query'] = array(
			array(
				'key'   => '_setceb_sol_status',
				'value' => sanitize_key( $args['status'] ),
			),
		);
	}

	$itens = array();

	foreach ( get_posts( $consulta ) as $post ) {
		$solicitacao = setceb_sol_obter( $post->ID );

		if ( $solicitacao ) {
			$itens[] = $solicitacao;
		}
	}

	return $itens;
}

/**
 * Listagem completa das solicitacoes para o painel do advogado.
 *
 * Diferente de setceb_sol_listar(), que carrega o objeto completo de
 * TODAS as solicitacoes, aqui a consulta e paginada: so os objetos da
 * pagina atual sao montados. Isso permite listar o historico inteiro
 * sem estourar memoria.
 *
 * @param array $args {
 *     @type string $status     Filtro de status (vazio = todos).
 *     @type string $busca      Texto livre (titulo, nome, e-mail) ou protocolo.
 *     @type int    $pagina     Pagina atual (1-based).
 *     @type int    $por_pagina Itens por pagina.
 * }
 * @return array {
 *     @type array[] $itens      Objetos de setceb_sol_obter() da pagina.
 *     @type int     $total      Total de solicitacoes que atendem ao filtro.
 *     @type int     $paginas    Quantidade total de paginas.
 *     @type int     $pagina     Pagina atual.
 *     @type int     $por_pagina Itens por pagina aplicados nesta consulta.
 * }
 */
function setceb_sol_listagem_admin( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'status'     => '',
			'busca'      => '',
			'pagina'     => 1,
			'por_pagina' => 25,
		)
	);

	$status     = sanitize_key( $args['status'] );
	$busca      = sanitize_text_field( $args['busca'] );
	$pagina     = max( 1, (int) $args['pagina'] );
	$por_pagina = max( 1, (int) $args['por_pagina'] );
	$protocolo  = ltrim( $busca, '#' );

	$consulta = array(
		'post_type'      => SETCEB_SOL_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => $por_pagina,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	/* Protocolo (#123) e o proprio ID do post. */
	if ( '' !== $protocolo && ctype_digit( $protocolo ) ) {
		$consulta['p'] = (int) $protocolo;
		$consulta['paged'] = 1;
		$pagina = 1;
	} else {
		$consulta['paged'] = $pagina;
	}

	$meta_query = array();

	if ( '' !== $status && isset( setceb_sol_status()[ $status ] ) ) {
		$meta_query[] = array(
			'key'   => '_setceb_sol_status',
			'value' => $status,
		);
	}

	if ( '' !== $busca && ! ctype_digit( $protocolo ) ) {
		$consulta['s'] = $busca;

		$por_texto = array(
			'relation' => 'OR',
			array(
				'key'     => '_setceb_sol_nome',
				'value'   => $busca,
				'compare' => 'LIKE',
			),
			array(
				'key'     => '_setceb_sol_email',
				'value'   => $busca,
				'compare' => 'LIKE',
			),
		);

		$meta_query[] = $por_texto;
	}

	if ( $meta_query ) {
		$consulta['meta_query'] = $meta_query;
	}

	$query = new WP_Query( $consulta );

	$itens = array();

	foreach ( $query->posts as $post ) {
		$solicitacao = setceb_sol_obter( $post->ID );

		if ( $solicitacao ) {
			$itens[] = $solicitacao;
		}
	}

	return array(
		'itens'      => $itens,
		'total'      => (int) $query->found_posts,
		'paginas'    => (int) $query->max_num_pages,
		'pagina'     => $pagina,
		'por_pagina' => $por_pagina,
	);
}

/**
 * Renderiza a conversa (historico) de uma solicitacao.
 *
 * Usado pela listagem do painel para exibir o conteudo sem sair da tela.
 * Nao e um formulario: o advogado acompanha, nao responde por aqui.
 *
 * @param int $post_id ID da solicitacao.
 * @return string HTML da conversa.
 */
function setceb_sol_render_conversa( $post_id ) {
	$historico = setceb_sol_historico( $post_id, false );

	if ( empty( $historico ) ) {
		return '<p class="setceb-sol-vazio">Nenhuma interacao registrada.</p>';
	}

	$html = '<ol class="setceb-sol-timeline setceb-sol-timeline--leitura">';

	foreach ( array_reverse( $historico ) as $registro ) {
		$html .= sprintf(
			'<li class="setceb-sol-timeline__item setceb-sol-timeline__item--%1$s">
				<span class="setceb-sol-timeline__marca" aria-hidden="true"><span class="dashicons %2$s"></span></span>
				<div class="setceb-sol-timeline__conteudo">
					<p class="setceb-sol-timeline__meta">
						<strong>%3$s</strong>
						<span class="setceb-sol-timeline__rotulo">%4$s</span>
						%5$s
					</p>
					%6$s
				</div>
			</li>',
			esc_attr( $registro['tipo'] ),
			esc_attr( $registro['icone'] ),
			esc_html( $registro['autor'] ),
			esc_html( $registro['rotulo'] ),
			'' !== $registro['data_txt']
				? '<time class="setceb-sol-timeline__quando">' . esc_html( $registro['data_txt'] ) . '</time>'
				: '',
			'' !== $registro['texto']
				? '<div class="setceb-sol-timeline__texto">' . nl2br( esc_html( $registro['texto'] ) ) . '</div>'
				: ''
		);
	}

	return $html . '</ol>';
}

/**
 * Devolve a conversa de uma solicitacao via AJAX.
 *
 * Leitura apenas: nao ha campo de resposta. Exige permissao de edicao
 * sobre a solicitacao, o mesmo que abre a tela de edicao.
 */
function setceb_sol_ajax_conversa() {
	$post_id = isset( $_POST['sol_id'] ) ? absint( $_POST['sol_id'] ) : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error(
			array( 'mensagem' => 'Sem permissao para ver esta solicitacao.' ),
			403
		);
	}

	check_ajax_referer( 'setceb_sol_conversa', 'nonce' );

	$post = get_post( $post_id );

	if ( ! $post || SETCEB_SOL_CPT !== $post->post_type ) {
		wp_send_json_error( array( 'mensagem' => 'Solicitacao nao encontrada.' ), 404 );
	}

	$solicitacao = setceb_sol_obter( $post_id );
	$contato     = array();

	foreach ( array( 'nome', 'email', 'telefone', 'empresa' ) as $campo ) {
		$contato[ $campo ] = $solicitacao[ $campo ];
	}

	wp_send_json_success(
		array(
			'html'      => setceb_sol_render_conversa( $post_id ),
			'protocolo' => $solicitacao['protocolo'],
			'titulo'    => $solicitacao['titulo'],
			'contato'   => $contato,
		)
	);
}
add_action( 'wp_ajax_setceb_sol_conversa', 'setceb_sol_ajax_conversa' );

/**
 * Relatorio com uma unica consulta, consumido pela listagem do admin e
 * pelo Dashboard. Devolve as linhas cruas (ID, data, status, advogado).
 *
 * @return array[]
 */
function setceb_sol_relatorio() {
	global $wpdb;

	$linhas = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, p.post_date, p.post_author,
				st.meta_value AS status,
				ad.meta_value AS advogado
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} ad ON ad.post_id = p.ID AND ad.meta_key = %s
			WHERE p.post_type = %s AND p.post_status = 'publish'",
			'_setceb_sol_status',
			'_setceb_sol_advogado',
			SETCEB_SOL_CPT
		),
		ARRAY_A
	);

	return is_array( $linhas ) ? $linhas : array();
}

/* ------------------------------------------------------------
 * 5. Historico das interacoes
 * ------------------------------------------------------------ */

/**
 * Rotulos de cada tipo de registro do historico.
 *
 * @return array tipo => array( rotulo, icone )
 */
function setceb_sol_historico_rotulos() {
	return apply_filters(
		'setceb_sol_historico_rotulos',
		array(
			'abertura' => array( 'rotulo' => 'Solicitacao registrada', 'icone' => 'dashicons-marker' ),
			'mensagem' => array( 'rotulo' => 'Mensagem do associado', 'icone' => 'dashicons-format-chat' ),
			'resposta' => array( 'rotulo' => 'Resposta do juridico', 'icone' => 'dashicons-email-alt' ),
			'status'   => array( 'rotulo' => 'Atualizacao de status', 'icone' => 'dashicons-update' ),
		)
	);
}

/**
 * Historico de uma solicitacao, ja no formato de exibicao.
 *
 * @param int  $post_id    ID da solicitacao.
 * @param bool $somente_publico Remove as anotacoes internas.
 * @return array[]
 */
function setceb_sol_historico( $post_id, $somente_publico = true ) {
	$bruto = get_post_meta( $post_id, '_setceb_sol_historico', true );

	if ( ! is_array( $bruto ) ) {
		return array();
	}

	$rotulos = setceb_sol_historico_rotulos();
	$itens   = array();

	foreach ( $bruto as $registro ) {
		if ( ! is_array( $registro ) || ! isset( $registro['tipo'] ) ) {
			continue;
		}

		// Registros internos ficam visiveis apenas para a equipe juridica.
		// O tipo "nota" tambem e coberto aqui porque anotacoes internas
		// gravadas antes da remocao do campo ja existem no banco.
		if ( $somente_publico && ( ! empty( $registro['interna'] ) || 'nota' === $registro['tipo'] ) ) {
			continue;
		}

		$tipo = isset( $rotulos[ $registro['tipo'] ] ) ? $registro['tipo'] : 'status';

		$itens[] = array(
			'tipo'     => $tipo,
			'rotulo'   => $rotulos[ $tipo ]['rotulo'],
			'icone'    => $rotulos[ $tipo ]['icone'],
			'texto'    => isset( $registro['texto'] ) ? (string) $registro['texto'] : '',
			'autor'    => isset( $registro['autor'] ) ? (string) $registro['autor'] : 'Sistema',
			'data'     => isset( $registro['data'] ) ? (string) $registro['data'] : '',
			'data_txt' => setceb_sol_data( isset( $registro['data'] ) ? $registro['data'] : '' ),
			'relativo' => setceb_sol_tempo_relativo( isset( $registro['data'] ) ? $registro['data'] : '' ),
		);
	}

	return $itens;
}

/**
 * Tempo decorrido desde uma data, em linguagem natural.
 *
 * @param string $data Data em Y-m-d H:i:s.
 * @return string
 */
function setceb_sol_tempo_relativo( $data ) {
	if ( '' === (string) $data ) {
		return '';
	}

	$timestamp = strtotime( (string) $data . ' UTC' );
	$agora      = strtotime( current_time( 'mysql' ) . ' UTC' );

	if ( ! $timestamp || ! $agora ) {
		return '';
	}

	$segundos = $agora - $timestamp;

	if ( $segundos < 0 ) {
		return '';
	}

	if ( $segundos < HOUR_IN_SECONDS ) {
		$minutos = max( 1, (int) round( $segundos / MINUTE_IN_SECONDS ) );

		return sprintf( 'ha %d %s', $minutos, 1 === $minutos ? 'minuto' : 'minutos' );
	}

	if ( $segundos < DAY_IN_SECONDS ) {
		$horas = (int) round( $segundos / HOUR_IN_SECONDS );

		return sprintf( 'ha %d %s', $horas, 1 === $horas ? 'hora' : 'horas' );
	}

	$dias = (int) floor( $segundos / DAY_IN_SECONDS );

	if ( $dias < 30 ) {
		return sprintf( 'ha %d %s', $dias, 1 === $dias ? 'dia' : 'dias' );
	}

	return sprintf( 'ha %d %s', (int) floor( $dias / 30 ), (int) floor( $dias / 30 ) > 1 ? 'meses' : 'mes' );
}

/**
 * Acrescenta um registro ao historico da solicitacao.
 *
 * @param int    $post_id  ID da solicitacao.
 * @param string $tipo     Tipo (ver setceb_sol_historico_rotulos).
 * @param string $texto    Conteudo do registro.
 * @param int    $autor_id Autor (0 = usuario atual).
 * @param bool   $interna  Registro visivel apenas para a equipe juridica.
 * @return array[] Historico atualizado.
 */
function setceb_sol_historico_add( $post_id, $tipo, $texto, $autor_id = 0, $interna = false ) {
	$bruto = get_post_meta( $post_id, '_setceb_sol_historico', true );

	if ( ! is_array( $bruto ) ) {
		$bruto = array();
	}

	$autor_id = (int) $autor_id;

	if ( ! $autor_id ) {
		$autor_id = get_current_user_id();
	}

	$autor = $autor_id ? get_userdata( $autor_id ) : false;

	$bruto[] = array(
		'data'     => current_time( 'mysql' ),
		'tipo'     => sanitize_key( $tipo ),
		'texto'    => (string) $texto,
		'autor'    => ( $autor && $autor->exists() ) ? $autor->display_name : 'Sistema',
		'autor_id' => $autor_id,
		'interna'  => (bool) $interna,
	);

	update_post_meta( $post_id, '_setceb_sol_historico', $bruto );
	update_post_meta( $post_id, '_setceb_sol_atualizada_em', current_time( 'mysql' ) );

	return $bruto;
}

/* ------------------------------------------------------------
 * 6. Metaboxes do atendimento
 * ------------------------------------------------------------ */

/**
 * Registra as metaboxes da solicitacao.
 */
function setceb_sol_meta_boxes() {
	// Coluna principal: a tela de atendimento, que e onde o advogado
	// trabalha e o que precisa de espaco.
	add_meta_box(
		'setceb_sol_atendimento',
		'Atendimento',
		'setceb_sol_meta_box_atendimento',
		SETCEB_SOL_CPT,
		'normal',
		'high'
	);

	// Coluna lateral: dados de quem solicitou e historico. Sao informacoes
	// de consulta, nao de trabalho, e ocupam bem o espaco vazio da direita.
	add_meta_box(
		'setceb_sol_solicitante',
		'Solicitante',
		'setceb_sol_meta_box_solicitante',
		SETCEB_SOL_CPT,
		'side',
		'high'
	);

	add_meta_box(
		'setceb_sol_historico',
		'Historico',
		'setceb_sol_meta_box_historico',
		SETCEB_SOL_CPT,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'setceb_sol_meta_boxes' );

/**
 * Box "Atendimento": status, responsavel, resposta e anotacoes.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_sol_meta_box_atendimento( $post ) {
	wp_nonce_field( 'setceb_sol_atendimento', 'setceb_sol_nonce' );

	$solicitacao = setceb_sol_obter( $post->ID );

	if ( ! $solicitacao ) {
		return;
	}

	$status   = $solicitacao['status'];
	// O responsavel nunca aparece vazio: sem atribuicao, responde o padrao.
	$advogado      = setceb_sol_advogado_responsavel( $post->ID );
	$advogado_nome = $advogado ? get_the_author_meta( 'display_name', $advogado ) : '';
	$info          = setceb_sol_status_info( $status );

	// O campo de resposta e um compositor e comeca sempre vazio: cada
	// texto salvo vira uma mensagem da conversa no historico. Respostas
	// novas sao permitidas sempre que a conversa esta aberta - inclusive
	// depois de uma resposta anterior.
	$placeholder = $solicitacao['mensagens']
		? 'Escreva a resposta para ' . $solicitacao['nome'] . '...'
		: 'Este atendimento esta fechado para novas respostas. Altere o status para responder.';
	?>
	<div class="setceb-sol-cabecalho" style="--setceb-sol-cor:<?php echo esc_attr( $info['cor'] ); ?>">
		<div class="setceb-sol-cabecalho__topo">
			<span class="setceb-sol-cabecalho__protocolo"><?php echo esc_html( $solicitacao['protocolo'] ); ?></span>
			<span class="setceb-sol-pill">
				<span class="dashicons <?php echo esc_attr( $info['icone'] ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $info['rotulo'] ); ?>
			</span>
		</div>
		<h2 class="setceb-sol-cabecalho__titulo"><?php echo esc_html( $solicitacao['titulo'] ); ?></h2>
		<ul class="setceb-sol-cabecalho__meta">
			<li>
				<span class="dashicons dashicons-clock" aria-hidden="true"></span>
				Aberta <?php echo esc_html( setceb_sol_tempo_relativo( $solicitacao['data'] ) ); ?>
				<span class="setceb-sol-cabecalho__exato"><?php echo esc_html( setceb_sol_data( $solicitacao['data'] ) ); ?></span>
			</li>
			<?php if ( '' !== $advogado_nome ) : ?>
				<li>
					<span class="dashicons dashicons-businessman" aria-hidden="true"></span>
					<?php echo esc_html( $advogado_nome ); ?>
				</li>
			<?php endif; ?>
			<li>
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				Atualizada <?php echo esc_html( setceb_sol_tempo_relativo( $solicitacao['atualizada'] ) ); ?>
				<span class="setceb-sol-cabecalho__exato"><?php echo esc_html( setceb_sol_data( $solicitacao['atualizada'] ) ); ?></span>
			</li>
		</ul>
	</div>

	<div class="setceb-sol-pergunta setceb-sol-pergunta--destaque">
		<span class="setceb-sol-pergunta__tag">
			<span class="dashicons dashicons-format-quote" aria-hidden="true"></span>
			Solicitacao de <?php echo esc_html( $solicitacao['nome'] ); ?>
		</span>
		<div class="setceb-sol-pergunta__texto"><?php echo nl2br( esc_html( $solicitacao['mensagem'] ) ); ?></div>
	</div>

	<div class="setceb-sol-campos">
		<div class="setceb-sol-bloco">
			<div class="setceb-sol-bloco__rotulo">
				<span class="setceb-sol-bloco__titulo">Status do atendimento</span>
				<span class="setceb-sol-bloco__dica">Clique no quadrado para alterar.</span>
			</div>

			<div class="setceb-sol-status" role="radiogroup" aria-label="Status do atendimento">
				<?php foreach ( setceb_sol_status() as $slug => $rotulo ) : ?>
					<label class="setceb-sol-status__item" style="--setceb-sol-cor:<?php echo esc_attr( $rotulo['cor'] ); ?>">
						<input
							type="radio"
							name="setceb_sol_status"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( $slug, $status ); ?>
						>
						<span class="dashicons <?php echo esc_attr( $rotulo['icone'] ); ?>" aria-hidden="true"></span>
						<span class="setceb-sol-status__texto"><?php echo esc_html( $rotulo['rotulo'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="setceb-sol-bloco setceb-sol-bloco--linha">
			<?php
			/*
			 * A equipe juridica nao escolhe o responsavel: ela recebe o que esta
			 * atribuido a ela. O campo fica com quem administra a distribuicao.
			 */
			?>
			<?php if ( ! setceb_sol_usuario_restrito() ) : ?>
				<div class="setceb-sol-bloco__campo">
					<label for="setceb-sol-advogado">
						<span class="setceb-sol-bloco__titulo">Advogado responsavel</span>
						<span class="setceb-sol-bloco__dica">Quem recebe os avisos desta solicitacao.</span>
					</label>
					<select id="setceb-sol-advogado" name="setceb_sol_advogado">
						<?php /* Com padrao definido nao existe "sem responsavel". */ ?>
						<?php if ( ! setceb_sol_advogado_padrao_id() ) : ?>
							<option value="0" <?php selected( 0, $advogado ); ?>>Nao atribuido</option>
						<?php endif; ?>
						<?php foreach ( setceb_sol_advogados() as $adv ) : ?>
							<option value="<?php echo (int) $adv->ID; ?>" <?php selected( $advogado, $adv->ID ); ?>><?php echo esc_html( $adv->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="setceb-sol-bloco__campo">
				<label for="setceb-sol-titulo">
					<span class="setceb-sol-bloco__titulo">Titulo interno</span>
					<span class="setceb-sol-bloco__dica">Aparece so na listagem do painel. O padrao e os primeiros caracteres da solicitacao.</span>
				</label>
				<input type="text" id="setceb-sol-titulo" name="post_title" value="<?php echo esc_attr( $post->post_title ); ?>">
			</div>
		</div>

		<div class="setceb-sol-bloco">
			<div class="setceb-sol-bloco__rotulo">
				<label for="setceb-sol-resposta" class="setceb-sol-bloco__titulo">Nova resposta</label>
			</div>

			<div class="setceb-sol-editor">
				<textarea
					id="setceb-sol-resposta"
					name="setceb_sol_resposta"
					rows="5"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					spellcheck="true"
					<?php echo $solicitacao['mensagens'] ? '' : 'readonly'; ?>
				></textarea>
			</div>
		</div>

		<div class="setceb-sol-acoes">
			<input type="submit" name="save" class="button button-primary button-hero" value="Salvar e enviar">
			<a class="button" href="<?php echo esc_url( setceb_sol_admin_url() ); ?>">Voltar para a lista</a>
		</div>
	</div>
	<?php
}

/**
 * Box "Solicitante": dados de quem abriu a solicitacao.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_sol_meta_box_solicitante( $post ) {
	$solicitacao = setceb_sol_obter( $post->ID );

	if ( ! $solicitacao ) {
		return;
	}
	?>
	<div class="setceb-sol-contato">
		<div class="setceb-sol-contato__pessoa">
			<span class="dashicons dashicons-businessman" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html( '' !== $solicitacao['nome'] ? $solicitacao['nome'] : 'Sem nome' ); ?></strong>
				<?php if ( '' !== $solicitacao['empresa'] ) : ?>
					<span><?php echo esc_html( $solicitacao['empresa'] ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<ul class="setceb-sol-contato__itens">
			<?php if ( is_email( $solicitacao['email'] ) ) : ?>
				<li>
					<span class="dashicons dashicons-email" aria-hidden="true"></span>
					<a href="mailto:<?php echo esc_attr( $solicitacao['email'] ); ?>"><?php echo esc_html( $solicitacao['email'] ); ?></a>
				</li>
			<?php endif; ?>

			<?php if ( '' !== $solicitacao['telefone'] ) : ?>
				<li>
					<span class="dashicons dashicons-phone" aria-hidden="true"></span>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $solicitacao['telefone'] ) ); ?>"><?php echo esc_html( $solicitacao['telefone'] ); ?></a>
				</li>
			<?php endif; ?>

			<?php if ( ! $solicitacao['usuario_id'] ) : ?>
				<li>
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					Conta de associado nao encontrada
				</li>
			<?php endif; ?>
		</ul>
	</div>
	<?php
}

/**
 * Box "Historico": linha do tempo das interacoes.
 *
 * @param WP_Post $post Post atual.
 */
function setceb_sol_meta_box_historico( $post ) {
	$historico = setceb_sol_historico( $post->ID, false );

	if ( empty( $historico ) ) {
		echo '<p class="description">Nenhuma interacao registrada.</p>';
		return;
	}
	?>
	<ol class="setceb-sol-timeline">
		<?php foreach ( array_reverse( $historico ) as $registro ) : ?>
			<li class="setceb-sol-timeline__item setceb-sol-timeline__item--<?php echo esc_attr( $registro['tipo'] ); ?>">
				<span class="setceb-sol-timeline__marca" aria-hidden="true">
					<span class="dashicons <?php echo esc_attr( $registro['icone'] ); ?>"></span>
				</span>

				<div class="setceb-sol-timeline__conteudo">
					<p class="setceb-sol-timeline__meta">
						<strong><?php echo esc_html( $registro['autor'] ); ?></strong>
						<span class="setceb-sol-timeline__rotulo"><?php echo esc_html( $registro['rotulo'] ); ?></span>
						<?php if ( '' !== $registro['relativo'] ) : ?>
							<time class="setceb-sol-timeline__quando"><?php echo esc_html( $registro['relativo'] ); ?></time>
						<?php endif; ?>
					</p>

					<?php if ( '' !== $registro['texto'] ) : ?>
						<div class="setceb-sol-timeline__texto"><?php echo nl2br( esc_html( $registro['texto'] ) ); ?></div>
					<?php endif; ?>

					<?php if ( '' !== $registro['data_txt'] ) : ?>
						<p class="setceb-sol-timeline__exato"><?php echo esc_html( $registro['data_txt'] ); ?></p>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
}

/**
 * A ultima resposta registrada e identica a que esta sendo enviada agora?
 *
 * Como a caixa limpa apos cada envio, um clique duplo no botao arrives
 * com o mesmo texto duas vezes. Aqui a segunda e descartada para nao
 * duplicar o registro no historico nem o e-mail ao associado.
 *
 * @param int    $post_id   ID da solicitacao.
 * @param string $resposta  Texto enviado.
 * @param int    $autor_id  Autor do envio.
 * @return bool
 */
function setceb_sol_resposta_repetida( $post_id, $resposta, $autor_id ) {
	$bruto = get_post_meta( $post_id, '_setceb_sol_historico', true );

	if ( ! is_array( $bruto ) || empty( $bruto ) ) {
		return false;
	}

	// Procura a resposta mais recente: depois dela ainda pode ter um
	// registro de status (o ajuste automatico para "Respondida"), entao
	// o ultimo item do historico nao e necessariamente a resposta.
	foreach ( array_reverse( $bruto ) as $registro ) {
		if ( ! is_array( $registro ) || 'resposta' !== ( isset( $registro['tipo'] ) ? $registro['tipo'] : '' ) ) {
			continue;
		}

		return isset( $registro['texto'], $registro['autor_id'] )
			&& $registro['texto'] === $resposta
			&& (int) $registro['autor_id'] === (int) $autor_id;
	}

	return false;
}

/**
 * Salva o atendimento (status, responsavel e resposta).
 *
 * @param int $post_id ID da solicitacao.
 */
function setceb_sol_atendimento_save( $post_id ) {
	if ( ! isset( $_POST['setceb_sol_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['setceb_sol_nonce'] ) ), 'setceb_sol_atendimento' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Forma idiomatica: quem verifica e o proprio post type.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$usuario_id = get_current_user_id();
	$antes      = setceb_sol_obter( $post_id );

	if ( ! $antes ) {
		return;
	}

	$status = isset( $_POST['setceb_sol_status'] ) ? sanitize_key( wp_unslash( $_POST['setceb_sol_status'] ) ) : $antes['status'];

	if ( ! isset( setceb_sol_status()[ $status ] ) ) {
		$status = $antes['status'];
	}

	// A equipe juridica nao envia este campo (ele nem aparece para ela):
	// sem o campo no POST, a atribuicao atual e mantida.
	$advogado = isset( $_POST['setceb_sol_advogado'] ) ? absint( wp_unslash( $_POST['setceb_sol_advogado'] ) ) : $antes['advogado_id'];
	$resposta = isset( $_POST['setceb_sol_resposta'] ) ? sanitize_textarea_field( wp_unslash( $_POST['setceb_sol_resposta'] ) ) : '';

	// ------------------------------------------------------------
	// Resposta do juridico
	//
	// O campo e um compositor, nao um rascunho: cada texto salvo vira um
	// registro no historico e a caixa volta vazia para a proxima resposta.
	// O historico e a conversa completa, entao nada se perde.
	// ------------------------------------------------------------
	if ( '' !== $resposta && ! setceb_sol_resposta_repetida( $post_id, $resposta, $usuario_id ) ) {
		setceb_sol_historico_add(
			$post_id,
			'resposta',
			$resposta,
			$usuario_id
		);

		// Uma resposta nova move a solicitacao para "Respondida", mas
		// nunca sobrepoe um encerramento escolhido pelo advogado.
		$selecionada = setceb_sol_status_info( $status );

		if ( $antes['ativo'] && ! empty( $selecionada['ativo'] ) && apply_filters( 'setceb_sol_auto_respondida', true, $post_id, $status ) ) {
			$novo = 'respondida';

			update_post_meta( $post_id, '_setceb_sol_status', $novo );
			$status = $novo;

			// A resposta enviada abaixo ja notifica o associado: sincroniza
			// a base para o bloco de status nao gerar um segundo registro
			// nem um segundo e-mail no mesmo salvamento.
			$antes['status'] = $novo;

			setceb_sol_historico_add(
				$post_id,
				'status',
				'Status alterado para ' . setceb_sol_status_info( $novo )['rotulo'] . ' (envio da resposta).',
				$usuario_id
			);
		}

		setceb_sol_email_associado( $post_id, $resposta );
	}

	// ------------------------------------------------------------
	// Advogado responsavel
	// ------------------------------------------------------------
	if ( $advogado !== $antes['advogado_id'] ) {
		update_post_meta( $post_id, '_setceb_sol_advogado', $advogado );

		$nome = $advogado ? get_the_author_meta( 'display_name', $advogado ) : 'nao atribuido';

		setceb_sol_historico_add(
			$post_id,
			'status',
			'Advogado responsavel: ' . $nome . '.',
			$usuario_id,
			true
		);
	}

	// ------------------------------------------------------------
	// Status
	// ------------------------------------------------------------
	if ( $status !== $antes['status'] ) {
		update_post_meta( $post_id, '_setceb_sol_status', $status );

		setceb_sol_historico_add(
			$post_id,
			'status',
			'Status alterado de ' . $antes['status_rotulo'] . ' para ' . setceb_sol_status_info( $status )['rotulo'] . '.',
			$usuario_id
		);

		// O associado e avisado de toda atualizacao de status relevante.
		if ( in_array( $status, array( 'respondida', 'encerrada', 'aguardando_associado' ), true ) ) {
			setceb_sol_email_associado_status( $post_id, $status );
		}
	} elseif ( '' === (string) get_post_meta( $post_id, '_setceb_sol_status', true ) ) {
		// Garante o status em registros antigos, criados antes do modulo.
		update_post_meta( $post_id, '_setceb_sol_status', $status );
	}
}
add_action( 'save_post', 'setceb_sol_atendimento_save' );

/* ------------------------------------------------------------
 * 7. Listagem no admin
 * ------------------------------------------------------------ */

/**
 * Colunas da listagem de solicitacoes.
 *
 * @param array $cols Colunas padrao.
 * @return array
 */
function setceb_sol_columns( $cols ) {
	$data = array();

	// Checkbox de selecao em massa.
	if ( isset( $cols['cb'] ) ) {
		$data['cb'] = $cols['cb'];
	}

	$data['title']       = 'Solicitacao';
	$data['status']      = 'Status';
	$data['solicitante'] = 'Solicitante';
	$data['advogado']    = 'Advogado';
	$data['data']        = isset( $cols['date'] ) ? $cols['date'] : 'Data';

	return $data;
}
add_filter( 'manage_' . SETCEB_SOL_CPT . '_posts_columns', 'setceb_sol_columns' );

/**
 * Conteudo das colunas customizadas.
 *
 * @param string $coluna  Coluna atual.
 * @param int    $post_id ID da solicitacao.
 */
function setceb_sol_column_content( $coluna, $post_id ) {
	$solicitacao = setceb_sol_obter( $post_id );

	if ( ! $solicitacao ) {
		return;
	}

	switch ( $coluna ) {
		case 'status':
			$info = setceb_sol_status_info( $solicitacao['status'] );
			printf(
				'<span class="setceb-sol-pill" style="--setceb-sol-cor:%1$s"><span class="dashicons %2$s" aria-hidden="true"></span>%3$s</span>',
				esc_attr( $info['cor'] ),
				esc_attr( $info['icone'] ),
				esc_html( $info['rotulo'] )
			);
			break;

		case 'solicitante':
			echo esc_html( '' !== $solicitacao['nome'] ? $solicitacao['nome'] : '—' );
			if ( '' !== $solicitacao['email'] ) {
				echo '<br><a href="mailto:' . esc_attr( $solicitacao['email'] ) . '">' . esc_html( $solicitacao['email'] ) . '</a>';
			}
			break;

		case 'advogado':
			$responsavel = setceb_sol_advogado_responsavel( $post_id );
			$nome        = $responsavel ? get_the_author_meta( 'display_name', $responsavel ) : '';

			echo esc_html( '' !== $nome ? $nome : '—' );
			break;
	}
}
add_action( 'manage_' . SETCEB_SOL_CPT . '_posts_custom_column', 'setceb_sol_column_content', 10, 2 );

/**
 * Contagem por status na listagem.
 *
 * Respeita o recorte de quem esta logado: o advogado conta o que esta
 * atribuido a ele mais o que ainda esta sem responsavel, exatamente como a
 * propria listagem. A administracao continua contando tudo.
 *
 * @return array slug => total
 */
function setceb_sol_contagem_status() {
	static $contagem = null;

	if ( null !== $contagem ) {
		return $contagem;
	}

	$restrito = setceb_sol_usuario_restrito();
	$eu       = get_current_user_id();
	$contagem = array();

	foreach ( setceb_sol_relatorio() as $linha ) {
		$status = '' !== (string) $linha['status'] ? (string) $linha['status'] : setceb_sol_status_padrao();

		if ( $restrito && (int) $linha['advogado'] && (int) $linha['advogado'] !== $eu ) {
			continue;
		}

		if ( ! isset( $contagem[ $status ] ) ) {
			$contagem[ $status ] = 0;
		}

		$contagem[ $status ]++;
	}

	return $contagem;
}

/**
 * Filtro por status no topo da listagem.
 *
 * Todas as solicitacoes usam post_status "publish", portanto as views
 * nativas (Todas / Publicadas) nao agregam informacao e sao removidas por
 * "setceb_sol_remover_views" em "views_edit-{$post_type}". O select fica em
 * "manage_posts_extra_tablenav" porque ali ele ja esta dentro do formulario
 * "posts-filter" da listagem, que carrega o post_type.
 *
 * A barra existe no topo e na base da tabela: o filtro aparece apenas no
 * topo. O select envia o proprio formulario ao trocar de valor - sem
 * JavaScript basta o botao "Filtrar".
 *
 * @param string $qual Topo ("top") ou base ("bottom") da barra.
 */
function setceb_sol_links_status( $qual ) {
	if ( 'top' !== $qual ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || SETCEB_SOL_CPT !== $screen->post_type ) {
		return;
	}

	if ( ! current_user_can( 'edit_setceb_sols' ) ) {
		return;
	}

	$atual    = isset( $_GET['status_sol'] ) ? sanitize_key( wp_unslash( $_GET['status_sol'] ) ) : '';
	$contagem = setceb_sol_contagem_status();
	$total    = 0;

	foreach ( $contagem as $numero ) {
		$total += (int) $numero;
	}
	?>
	<div class="setceb-sol-filtro">
		<label class="screen-reader-text" for="setceb-sol-status"><?php esc_html_e( 'Filtrar por status', 'default' ); ?></label>
		<select id="setceb-sol-status" name="status_sol" onchange="this.form.submit()">
			<option value="">Todas (<?php echo (int) $total; ?>)</option>
			<?php foreach ( setceb_sol_status() as $slug => $info ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $atual ); ?>>
					<?php echo esc_html( $info['rotulo'] . ' (' . ( isset( $contagem[ $slug ] ) ? (int) $contagem[ $slug ] : 0 ) . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'default' ); ?></button></noscript>
	</div>
	<?php
}
add_action( 'manage_posts_extra_tablenav', 'setceb_sol_links_status' );

/**
 * Remove as views nativas da listagem.
 *
 * Todas as solicitacoes usam post_status "publish", entao "Todas" e
 * "Publicadas" repetem o recorte que o filtro de status do atendimento ja
 * faz - e o core renderiza essa lista acima da tabela, separada do select.
 * A lixeira e mantida porque nao tem equivalente no filtro do modulo.
 *
 * @param array $views Links nativos da listagem.
 * @return array
 */
function setceb_sol_remover_views( $views ) {
	foreach ( array( 'all', 'publish', 'draft', 'pending', 'private', 'future', 'mine', 'sticky' ) as $slug ) {
		unset( $views[ $slug ] );
	}

	return $views;
}
add_filter( 'views_edit-' . SETCEB_SOL_CPT, 'setceb_sol_remover_views' );

/**
 * Aplica o filtro de status na listagem.
 *
 * @param WP_Query $consulta Consulta da listagem.
 */
function setceb_sol_filtro_status( $consulta ) {
	if ( ! is_admin() || ! $consulta->is_main_query() ) {
		return;
	}

	if ( SETCEB_SOL_CPT !== $consulta->get( 'post_type' ) ) {
		return;
	}

	if ( ! isset( $_GET['status_sol'] ) ) {
		return;
	}

	$status = sanitize_key( wp_unslash( $_GET['status_sol'] ) );

	if ( ! isset( setceb_sol_status()[ $status ] ) ) {
		return;
	}

	// Mescla com o que ja existir na consulta em vez de substituir.
	$meta_query = (array) $consulta->get( 'meta_query' );

	$meta_query[] = array(
		'key'   => '_setceb_sol_status',
		'value' => $status,
	);

	$consulta->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'setceb_sol_filtro_status' );

/**
 * Restringe a listagem ao que cabe a equipe juridica.
 *
 * O advogado responde pelo que esta atribuido a ele. Solicitacoes ainda sem
 * responsavel continuam aparecendo para todos os advogados, para nao deixarem
 * de ser atendidas enquanto a administracao nao distribuir.
 *
 * @param WP_Query $consulta Consulta da listagem.
 */
function setceb_sol_filtro_advogado( $consulta ) {
	if ( ! is_admin() || ! $consulta->is_main_query() ) {
		return;
	}

	if ( SETCEB_SOL_CPT !== $consulta->get( 'post_type' ) ) {
		return;
	}

	if ( ! setceb_sol_usuario_restrito() ) {
		return;
	}

	// Mescla com o que ja existir na consulta em vez de substituir.
	$meta_query = (array) $consulta->get( 'meta_query' );

	$meta_query[] = array(
		'relation' => 'OR',
		array(
			'key'     => '_setceb_sol_advogado',
			'value'   => array( '0', '' ),
			'compare' => 'IN',
		),
		array(
			'key'   => '_setceb_sol_advogado',
			'value' => (string) get_current_user_id(),
		),
		array(
			'key'     => '_setceb_sol_advogado',
			'compare' => 'NOT EXISTS',
		),
	);

	$consulta->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'setceb_sol_filtro_advogado' );

/**
 * Marca o post type na pagina de edicao (usado pelo CSS do admin).
 *
 * @param string $classes Classes do body.
 * @return string
 */
function setceb_sol_body_class( $classes ) {
	if ( get_current_screen() && SETCEB_SOL_CPT === get_current_screen()->post_type ) {
		$classes .= ' setceb-sol-admin ';
	}

	return $classes;
}
add_filter( 'admin_body_class', 'setceb_sol_body_class' );

/* ------------------------------------------------------------
 * 8. E-mails
 * ------------------------------------------------------------ */

/**
 * Usuarios cadastrados como advogado.
 *
 * @param array $args Argumentos extras para get_users().
 * @return WP_User[]
 */
function setceb_sol_advogados( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'role'   => SETCEB_SOL_ROLE,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => -1,
		)
	);

	return get_users( $args );
}

/**
 * Engajados para o recebimento das solicitacoes.
 *
 * @return int[] IDs de usuarios.
 */
function setceb_sol_advogados_padrao() {
	$destinatarios = array();

	foreach ( setceb_sol_advogados() as $advogado ) {
		$destinatarios[] = (int) $advogado->ID;
	}

	return apply_filters( 'setceb_sol_advogados_padrao', $destinatarios );
}

/**
 * Engajados de uma solicitacao: o responsavel ou, na ausencia, todos.
 *
 * @param int $post_id ID da solicitacao.
 * @return int[]
 */
function setceb_sol_advogados_da_solicitacao( $post_id ) {
	$responsavel = (int) get_post_meta( $post_id, '_setceb_sol_advogado', true );

	if ( $responsavel ) {
		return apply_filters( 'setceb_sol_advogados_da_solicitacao', array( $responsavel ), $post_id );
	}

	return apply_filters( 'setceb_sol_advogados_da_solicitacao', setceb_sol_advogados_padrao(), $post_id );
}

/**
 * Advogado marcado como responsavel padrao.
 *
 * @return int
 */
function setceb_sol_advogado_padrao_id() {
	return (int) get_option( 'setceb_sol_advogado_padrao', 0 );
}

/**
 * Advogado responsavel por uma solicitacao.
 *
 * Sem responsavel gravado, responde o advogado padrao do modulo: enquanto
 * houver padrao definido, nenhuma solicitacao fica sem dono.
 *
 * @param int $post_id ID da solicitacao.
 * @return int ID do usuario responsavel (0 quando nao ha nenhum).
 */
function setceb_sol_advogado_responsavel( $post_id ) {
	$responsavel = (int) get_post_meta( $post_id, '_setceb_sol_advogado', true );

	return $responsavel ? $responsavel : setceb_sol_advogado_padrao_id();
}

/**
 * Envia um e-mail formatado como texto.
 *
 * @param string|string[] $para       Destinatario(s).
 * @param string          $assunto    Assunto.
 * @param array           $linhas     Linhas do corpo.
 * @param string          $reply_to   E-mail de resposta (opcional).
 * @return bool
 */
function setceb_sol_email( $para, $assunto, $linhas, $reply_to = '' ) {
	$destinatarios = array_filter( array_map( 'sanitize_email', (array) $para ), 'is_email' );

	if ( empty( $destinatarios ) ) {
		return false;
	}

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	if ( $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}

	return wp_mail(
		$destinatarios,
		$assunto,
		implode( "\n", $linhas ),
		$headers
	);
}

/**
 * Assunto prefixado com o nome do portal.
 *
 * @param string $texto Texto do assunto.
 * @return string
 */
function setceb_sol_email_assunto( $texto ) {
	return sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $texto );
}

/**
 * Corpo do e-mail de aviso para a equipe juridica.
 *
 * O aviso nao carrega a solicitacao: nem identificacao do associado,
 * nem o texto da duvida, nem link de edicao. Ele diz o que houve e
 * aponta para o painel, onde o|time ja tem tudo em um lugar so. O
 * pedido foi explicito: "apenas falar que ele recebeu uma nova
 * solicitacao e apontar para o painel".
 *
 * @param string $frase O que aconteceu, em uma frase.
 * @return array Linhas do corpo.
 */
function setceb_sol_email_corpo_painel( $frase ) {
	return array(
		$frase,
		'',
		'Acompanhe pelo painel:',
		setceb_sol_admin_url(),
		'',
		'-- ',
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
	);
}

/**
 * Linhas de identification da solicitacao, comuns aos e-mails.
 *
 * @param array $solicitacao Solicitacao.
 * @return array
 */
function setceb_sol_email_identificacao( $solicitacao ) {
	return array(
		'Solicitacao: ' . $solicitacao['protocolo'] . ' - ' . $solicitacao['titulo'],
		'Solicitante: ' . $solicitacao['nome'],
		'E-mail: ' . $solicitacao['email'],
		'' !== $solicitacao['telefone'] ? 'Telefone: ' . $solicitacao['telefone'] : null,
		'' !== $solicitacao['empresa'] ? 'Empresa: ' . $solicitacao['empresa'] : null,
	);
}

/**
 * Remove as linhas nulas preservando as linhas em branco.
 *
 * As linhas vazias sao separadores propositais do corpo do e-mail e
 * por isso precisam sobreviver.
 *
 * @param array $linhas Linhas.
 * @return array
 */
function setceb_sol_linhas( $linhas ) {
	return array_values(
		array_filter(
			$linhas,
			static function ( $linha ) {
				return null !== $linha;
			}
		)
	);
}

/**
 * Notifica a equipe juridica sobre uma nova solicitacao.
 *
 * O corpo e propositalmente curto: uma frase e o link do painel. Sem
 * advogado cadastrado o aviso vai para o endereco administrativo, para
 * que nenhuma solicitacao fique sem retorno.
 *
 * @param int $post_id ID da solicitacao.
 */
function setceb_sol_email_nova_solicitacao( $post_id ) {
	$solicitacao = setceb_sol_obter( $post_id );

	if ( ! $solicitacao ) {
		return;
	}

	$emails = array();

	foreach ( setceb_sol_advogados_da_solicitacao( $post_id ) as $id ) {
		$usuario = get_userdata( (int) $id );

		if ( $usuario && is_email( $usuario->user_email ) ) {
			$emails[] = $usuario->user_email;
		}
	}

	if ( empty( $emails ) ) {
		$emails[] = setceb_form_recipient( 'juridico' );

		setceb_sol_historico_add(
			$post_id,
			'status',
			'[SETCEB] Nenhum advogado cadastrado. A notificacao foi enviada para o endereco administrativo.',
			0,
			true
		);
	}

	setceb_sol_email(
		$emails,
		setceb_sol_email_assunto( 'Nova solicitacao juridica ' . $solicitacao['protocolo'] ),
		setceb_sol_email_corpo_painel( 'Voce recebeu uma nova solicitacao juridica.' )
	);
}

/**
 * Notifica o associado sobre a resposta do juridico.
 *
 * @param int    $post_id ID da solicitacao.
 * @param string $resposta Texto da resposta.
 */
function setceb_sol_email_associado( $post_id, $resposta ) {
	$solicitacao = setceb_sol_obter( $post_id );

	if ( ! $solicitacao || ! is_email( $solicitacao['email'] ) ) {
		return;
	}

	$atendente = $solicitacao['advogado'] ? $solicitacao['advogado'] : 'Assessoria Juridica';

	$linhas = array_merge(
		array( 'Ola, ' . $solicitacao['nome'] . '!', '' ),
		setceb_sol_linhas( setceb_sol_email_identificacao( $solicitacao ) ),
		array(
			'',
			'Resposta de ' . $atendente . ':',
			$resposta,
			'',
			'O andamento completo esta disponivel na Area do Associado, em Juridico.',
			setceb_associado_perfil_url() . '#panel-juridico',
		)
	);

	setceb_sol_email(
		$solicitacao['email'],
		setceb_sol_email_assunto( 'Resposta da sua solicitacao juridica ' . $solicitacao['protocolo'] ),
		setceb_sol_linhas( $linhas )
	);
}

/**
 * Notifica o associado sobre a mudanca de status.
 *
 * @param int    $post_id ID da solicitacao.
 * @param string $status  Novo status.
 */
function setceb_sol_email_associado_status( $post_id, $status ) {
	$solicitacao = setceb_sol_obter( $post_id );

	if ( ! $solicitacao || ! is_email( $solicitacao['email'] ) ) {
		return;
	}

	$info    = setceb_sol_status_info( $status );
	$explica = array(
		'aguardando_associado' => 'O juridico precisa de mais alguma informacao sua. Voce pode responder direto pela Area do Associado.',
		'encerrada'            => 'Esta solicitacao foi encerrada. Se ainda for necessario, abra uma nova solicitacao.',
		'respondida'           => 'Sua solicitacao foi respondida. Consulte a resposta na Area do Associado.',
	);

	$linhas = array_merge(
		array( 'Ola, ' . $solicitacao['nome'] . '!', '' ),
		setceb_sol_linhas( setceb_sol_email_identificacao( $solicitacao ) ),
		array(
			'',
			'Status atual: ' . $info['rotulo'],
			isset( $explica[ $status ] ) ? $explica[ $status ] : '',
			'',
			'Consulta e responda em: ' . setceb_associado_perfil_url() . '#panel-juridico',
		)
	);

	setceb_sol_email(
		$solicitacao['email'],
		setceb_sol_email_assunto( 'Atualizacao da solicitacao juridica ' . $solicitacao['protocolo'] ),
		setceb_sol_linhas( $linhas )
	);
}

/**
 * Notifica o responsavel sobre uma nova interacao do associado.
 *
 * "Interacao" e a mensagem que o associado escreve no atendimento: e a
 * unica acao que ele tem sobre uma solicitacao ja aberta. O corpo
 * segue a mesma regra do aviso de solicitacao nova - uma frase e o
 * link do painel, sem o texto da mensagem nem os dados do associado.
 *
 * @param int    $post_id  ID da solicitacao.
 * @param string $mensagem Texto da mensagem (mantido no parametro para
 *                        o registro no historico e para compatibilidade
 *                        de quem chama).
 */
function setceb_sol_email_mensagem_associado( $post_id, $mensagem ) {
	$solicitacao = setceb_sol_obter( $post_id );

	if ( ! $solicitacao ) {
		return;
	}

	$emails = array();

	foreach ( setceb_sol_advogados_da_solicitacao( $post_id ) as $id ) {
		$usuario = get_userdata( (int) $id );

		if ( $usuario && is_email( $usuario->user_email ) ) {
			$emails[] = $usuario->user_email;
		}
	}

	if ( empty( $emails ) ) {
		$emails[] = setceb_form_recipient( 'juridico' );
	}

	setceb_sol_email(
		$emails,
		setceb_sol_email_assunto( 'Nova interacao na solicitacao ' . $solicitacao['protocolo'] ),
		setceb_sol_email_corpo_painel(
			'O associado fez uma nova interacao na solicitacao ' . $solicitacao['protocolo'] . '.'
		)
	);
}

/* ------------------------------------------------------------
 * 9. Area do Associado: formulario e acompanhamento
 * ------------------------------------------------------------ */

/**
 * O formulario juridico passa a registrar a solicitacao no painel em
 * vez de apenas enviar a mensagem por e-mail.
 */
function setceb_sol_assumir_formulario() {
	if ( function_exists( 'setceb_associado_process_juridico' ) ) {
		remove_action( 'admin_post_setceb_juridico', 'setceb_associado_process_juridico' );
		remove_action( 'admin_post_nopriv_setceb_juridico', 'setceb_associado_process_juridico' );
	}
}
add_action( 'init', 'setceb_sol_assumir_formulario' );

/**
 * Mensagens do formulario do modulo (abaixo de "Juridico").
 *
 * @return array|null
 */
function setceb_sol_notice() {
	if ( ! isset( $_GET['sol_status'] ) ) {
		return null;
	}

	$status = sanitize_key( wp_unslash( $_GET['sol_status'] ) );

	$mensagens = array(
		'ok'       => array(
			'tipo'  => 'sucesso',
			'texto' => 'Solicitacao registrada com sucesso. A equipe juridica foi notificada e voce pode acompanhar o atendimento pela Area do Associado.',
		),
		'resposta' => array(
			'tipo'  => 'sucesso',
			'texto' => 'Mensagem enviada. O juridico foi notificado e podera complementar a resposta.',
		),
		'encerrada' => array(
			'tipo'  => 'erro',
			'texto' => 'Esta solicitacao ja foi encerrada. Abra uma nova solicitacao para continuar o atendimento.',
		),
		'throttle' => array(
			'tipo'  => 'erro',
			'texto' => 'Aguarde alguns instantes antes de enviar uma nova mensagem.',
		),
		'campos'   => array(
			'tipo'  => 'erro',
			'texto' => 'Verifique os campos obrigatorios e tente novamente.',
		),
		'erro'     => array(
			'tipo'  => 'erro',
			'texto' => 'Nao foi possivel registrar a solicitacao agora. Tente novamente em alguns minutos.',
		),
	);

	if ( ! isset( $mensagens[ $status ] ) ) {
		return null;
	}

	return array(
		'forma' => 'juridico',
		'tipo'  => $mensagens[ $status ]['tipo'],
		'texto' => $mensagens[ $status ]['texto'],
	);
}

/**
 * Rotulo do formulario juridico quando uma solicitacao e registrada.
 *
 * @param array $mensagens Mensagens padrao.
 * @param string $forma     Contexto do formulario.
 * @return array
 */
function setceb_sol_notice_messages( $mensagens, $forma ) {
	if ( 'juridico' !== $forma ) {
		return $mensagens;
	}

	$mensagens['ok']['texto'] = 'Solicitacao registrada com sucesso. A equipe juridica foi notificada e voce pode acompanhar o atendimento por aqui.';

	return $mensagens;
}
add_filter( 'setceb_assoc_form_notice_messages', 'setceb_sol_notice_messages', 10, 2 );

/**
 * Destino do redirect apos o envio, preservando o painel "Juridico".
 *
 * @param string $status Status do formulario.
 * @param array  $extra  Argumentos adicionais na query string.
 */
function setceb_sol_redirecionar( $status, $extra = array() ) {
	$referer = wp_get_referer();

	if ( ! $referer || false === strpos( $referer, home_url() ) ) {
		$referer = setceb_associado_perfil_url();
	}

	$args_remover = array( 'assoc_status', 'assoc_form', 'sol_status', 'sol_id' );

	/* Quando o envio e bem-sucedido e abre uma solicitacao especifica, o
	   estado "nova solicitacao" nao deve continuar ativo. */
	if ( isset( $extra['sol_id'] ) && $extra['sol_id'] ) {
		$args_remover[] = 'sol_nova';
	}

	$referer = remove_query_arg( $args_remover, $referer );

	// Sem duplicar a ancora caso o referer ja a traga.
	$referer = strtok( $referer, '#' );

	$args = array( 'sol_status' => $status );

	if ( ! empty( $extra ) ) {
		$args = array_merge( $args, $extra );
	}

	wp_safe_redirect( add_query_arg( $args, $referer ) . '#panel-juridico' );
	exit;
}

/**
 * Registra uma nova solicitacao vinda do formulario Juridico.
 */
function setceb_sol_process_nova() {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( setceb_associado_perfil_url() ) );
		exit;
	}

	if ( ! setceb_is_associado() && ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( setceb_associado_perfil_url() );
		exit;
	}

	// Honeypot: bots que preenchem o campo escondido sao descartados.
	if ( ! empty( $_POST['setceb_website'] ) ) {
		setceb_sol_redirecionar( 'ok' );
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_form_juridico' ) ) {
		setceb_sol_redirecionar( 'erro' );
		return;
	}

	if ( get_transient( 'setceb_form_' . get_current_user_id() ) ) {
		setceb_sol_redirecionar( 'throttle' );
		return;
	}

	$usuario   = wp_get_current_user();
	$nome     = isset( $_POST['setceb_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_nome'] ) ) : '';
	$email    = isset( $_POST['setceb_email'] ) ? sanitize_email( wp_unslash( $_POST['setceb_email'] ) ) : '';
	$telefone = isset( $_POST['setceb_telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_telefone'] ) ) : '';
	$empresa  = isset( $_POST['setceb_empresa'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_empresa'] ) ) : '';
	$categoria = isset( $_POST['setceb_categoria'] ) ? sanitize_key( wp_unslash( $_POST['setceb_categoria'] ) ) : '';
	$mensagem = isset( $_POST['setceb_mensagem'] ) ? sanitize_textarea_field( wp_unslash( $_POST['setceb_mensagem'] ) ) : '';

	/* A categoria so passa se existir na lista. Antes bastava nao ser
	   vazia: um POST manual gravava um slug que o formulario nunca
	   offeria, e o painel mostrava um rotulo vazio. */
	if ( ! setceb_sol_categoria_valida( $categoria ) ) {
		$categoria = '';
	}

	if ( '' === $nome || '' === $mensagem || '' === $categoria || ! is_email( $email ) ) {
		setceb_sol_redirecionar( 'campos' );
		return;
	}

	$post_id = setceb_sol_criar(
		array(
			'nome'      => $nome,
			'email'     => $email,
			'telefone'  => $telefone,
			'empresa'   => $empresa,
			'categoria' => $categoria,
			'mensagem'  => $mensagem,
		),
		$usuario->ID
	);

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		setceb_sol_redirecionar( 'erro' );
		return;
	}

	set_transient( 'setceb_form_' . $usuario->ID, 1, 30 );

	setceb_sol_redirecionar( 'ok', array( 'sol_id' => (int) $post_id ) );
}
add_action( 'admin_post_setceb_juridico', 'setceb_sol_process_nova' );
add_action( 'admin_post_nopriv_setceb_juridico', 'setceb_sol_process_nova' );

/**
 * Cria uma solicitacao e dispara o fluxo de notificacao.
 *
 * @param array $dados     Dados da solicitacao.
 * @param int   $usuario_id Autor (solicitante).
 * @return int|WP_Error
 */
function setceb_sol_criar( $dados, $usuario_id ) {
	$mensagem = isset( $dados['mensagem'] ) ? trim( (string) $dados['mensagem'] ) : '';

	if ( '' === $mensagem ) {
		return new WP_Error( 'setceb_sol_mensagem', 'A mensagem e obrigatoria.' );
	}

	$nome = isset( $dados['nome'] ) ? sanitize_text_field( $dados['nome'] ) : '';

	if ( '' === $nome ) {
		$nome = get_the_author_meta( 'display_name', (int) $usuario_id );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => SETCEB_SOL_CPT,
			'post_status'  => 'publish',
			'post_title'   => wp_html_excerpt( $mensagem, 80, '...' ),
			'post_content' => $mensagem,
			'post_author'  => (int) $usuario_id,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$email    = isset( $dados['email'] ) ? sanitize_email( $dados['email'] ) : '';
	$telefone = isset( $dados['telefone'] ) ? sanitize_text_field( $dados['telefone'] ) : '';
	$empresa  = isset( $dados['empresa'] ) ? sanitize_text_field( $dados['empresa'] ) : '';
	$categoria = isset( $dados['categoria'] ) ? sanitize_key( (string) $dados['categoria'] ) : '';

	update_post_meta( $post_id, '_setceb_sol_nome', $nome );
	update_post_meta( $post_id, '_setceb_sol_email', $email );
	update_post_meta( $post_id, '_setceb_sol_telefone', $telefone );
	update_post_meta( $post_id, '_setceb_sol_empresa', $empresa );
	update_post_meta( $post_id, '_setceb_sol_categoria', $categoria );
	update_post_meta( $post_id, '_setceb_sol_status', setceb_sol_status_padrao() );

	$advogado = setceb_sol_advogado_padrao_id();

	update_post_meta( $post_id, '_setceb_sol_advogado', $advogado );
	update_post_meta( $post_id, '_setceb_sol_atualizada_em', current_time( 'mysql' ) );

	setceb_sol_historico_add(
		$post_id,
		'abertura',
		'Solicitacao registrada pela Area do Associado.',
		$usuario_id
	);

	if ( $advogado ) {
		setceb_sol_historico_add(
			$post_id,
			'status',
			'Atribuida a ' . get_the_author_meta( 'display_name', $advogado ) . '.',
			$usuario_id,
			true
		);
	}

	setceb_sol_email_nova_solicitacao( $post_id );

	return (int) $post_id;
}

/**
 * Registra uma nova mensagem do associado em uma solicitacao aberta.
 */
function setceb_sol_process_mensagem() {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( setceb_associado_perfil_url() ) );
		exit;
	}

	$post_id = isset( $_POST['setceb_sol_id'] ) ? absint( wp_unslash( $_POST['setceb_sol_id'] ) ) : 0;

	if ( ! $post_id ) {
		setceb_sol_redirecionar( 'erro' );
		return;
	}

	$solicitacao = setceb_sol_obter( $post_id );

	if ( ! $solicitacao ) {
		setceb_sol_redirecionar( 'erro' );
		return;
	}

	// O associado so interage nas proprias solicitacoes.
	if ( ! current_user_can( 'manage_options' ) && (int) $solicitacao['usuario_id'] !== get_current_user_id() ) {
		setceb_sol_redirecionar( 'erro', array( 'sol_id' => $post_id ) );
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_juridico_mensagem_' . $post_id ) ) {
		setceb_sol_redirecionar( 'erro', array( 'sol_id' => $post_id ) );
		return;
	}

	if ( get_transient( 'setceb_form_' . get_current_user_id() ) ) {
		setceb_sol_redirecionar( 'throttle', array( 'sol_id' => $post_id ) );
		return;
	}

	if ( ! $solicitacao['mensagens'] ) {
		setceb_sol_redirecionar( 'encerrada', array( 'sol_id' => $post_id ) );
		return;
	}

	$mensagem = isset( $_POST['setceb_mensagem'] ) ? sanitize_textarea_field( wp_unslash( $_POST['setceb_mensagem'] ) ) : '';

	if ( '' === $mensagem ) {
		setceb_sol_redirecionar( 'campos', array( 'sol_id' => $post_id ) );
		return;
	}

	setceb_sol_historico_add( $post_id, 'mensagem', $mensagem, get_current_user_id() );

	// Uma nova mensagem do associado devolve a solicitacao para a fila.
	if ( in_array( $solicitacao['status'], array( 'respondida', 'aguardando_associado' ), true ) ) {
		update_post_meta( $post_id, '_setceb_sol_status', 'em_analise' );

		setceb_sol_historico_add(
			$post_id,
			'status',
			'Status alterado de ' . $solicitacao['status_rotulo'] . ' para ' . setceb_sol_status_info( 'em_analise' )['rotulo'] . '.',
			get_current_user_id()
		);
	}

	update_post_meta( $post_id, '_setceb_sol_atualizada_em', current_time( 'mysql' ) );

	setceb_sol_email_mensagem_associado( $post_id, $mensagem );

	set_transient( 'setceb_form_' . get_current_user_id(), 1, 30 );

	setceb_sol_redirecionar( 'resposta', array( 'sol_id' => $post_id ) );
}
add_action( 'admin_post_setceb_juridico_mensagem', 'setceb_sol_process_mensagem' );
add_action( 'admin_post_nopriv_setceb_juridico_mensagem', 'setceb_sol_process_mensagem' );

/* ------------------------------------------------------------
 * 10. Acesso da equipe juridica ao painel
 * ------------------------------------------------------------ */

/**
 * Telas do painel que a equipe juridica pode acessar.
 *
 * @return bool
 */
function setceb_sol_tela_permitida() {
	global $pagenow;

	// Usa as constantes do core em vez dos atalhos wp_doing_ajax(),
	// wp_doing_cron() e wp_doing_xmlrpc(): o ultimo so existe a partir do
	// WordPress 5.6 e o portal roda uma versao anterior.
	$ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;
	$cron   = defined( 'DOING_CRON' ) && DOING_CRON;
	$xmlrpc = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;

	// Ajax, cron e XML-RPC nao passam pelo fluxo de telas do admin.
	if ( $ajax || $cron || $xmlrpc ) {
		return true;
	}

	if ( 'admin-ajax.php' === $pagenow || 'admin-post.php' === $pagenow ) {
		return true;
	}

	// As subpaginas do modulo (Dashboard e Advogados) exigem manage_options.
	// Um link direto para elas precisa ser barrado aqui, e nao pelo
	// user_can_access_admin_page() do core, que responderia com um wp_die.
	if ( 'edit.php' === $pagenow ) {
		if ( ! empty( $_REQUEST['page'] ) ) {
			return false;
		}

		$tipo = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';

		return SETCEB_SOL_CPT === $tipo;
	}

	if ( 'post.php' === $pagenow ) {
		if ( ! empty( $_REQUEST['page'] ) ) {
			return false;
		}

		$tipo = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';

		if ( SETCEB_SOL_CPT === $tipo ) {
			return true;
		}

		$id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;

		if ( $id ) {
			$post = get_post( $id );

			return $post && SETCEB_SOL_CPT === $post->post_type;
		}

		return false;
	}

	// O proprio perfil do usuario: o link "Meu perfil" da barra de
	// administracao aponta para ca. Nao expoe dados de outros usuarios.
	if ( 'profile.php' === $pagenow ) {
		return true;
	}

	return false;
}

/**
 * Deixa no painel somente as entradas do modulo.
 *
 * Remover item a item nao basta. O core decide o acesso em
 * user_can_access_admin_page() percorrendo $menu e $submenu, e
 * remove_menu_page() apaga apenas $menu: a entrada "Todos os artigos" de
 * $submenu['edit.php'] continuava respondendo por current_user_can(
 * 'edit_posts') e negava a listagem do modulo. Reconstruir os dois
 * arrays com as unicas entradas da conta elimina a negativa pela raiz,
 * porque nada que o core compare deixa de ser acessivel.
 */
function setceb_sol_menu_minimo() {
	global $menu, $submenu, $parent_file, $typenow, $_wp_submenu_nopriv;

	if ( ! setceb_sol_usuario_restrito() ) {
		return;
	}

	$slug = 'edit.php?post_type=' . SETCEB_SOL_CPT;
	$obj  = get_post_type_object( SETCEB_SOL_CPT );

	if ( ! $obj || empty( $obj->cap->edit_posts ) ) {
		return;
	}

	$cap  = $obj->cap->edit_posts;
	$item = array(
		$obj->labels->menu_name,
		$cap,
		$slug,
		'',
		'menu-top menu-icon-' . SETCEB_SOL_CPT,
		'menu-' . SETCEB_SOL_CPT,
		$obj->menu_icon ? $obj->menu_icon : 'dashicons-feedback',
	);

	// Mantem a entrada como o core a montou (titulo, icone, posicao).
	foreach ( (array) $menu as $linha ) {
		if ( isset( $linha[2] ) && $slug === $linha[2] ) {
			$item = $linha;
			break;
		}
	}

	$menu    = array( 26 => $item );
	$submenu = array( $slug => array( 5 => array( $obj->labels->all_items, $cap, $slug ) ) );

	/*
	 * O core registra em $_wp_submenu_nopriv cada add_submenu_page()
	 * recusado por capacidade - inclusive as dos outros modulos do tema
	 * (o de documentos registra na prioridade 99, antes daqui). Essas
	 * entradas se referem a menus que nao existem mais para esta conta e
	 * sao consultadas por user_can_access_admin_page(); zerar elimina a
	 * ultima forma de o core negar uma tela do modulo.
	 */
	$_wp_submenu_nopriv = array();

	/*
	 * O item so aparece destacado quando $parent_file aponta para ele.
	 * Como o submenu fica com uma unica entrada, o proprio core remove
	 * $submenu[$slug] e nenhum $parent_file e definido sozinho.
	 */
	if ( SETCEB_SOL_CPT === $typenow || $slug === $parent_file ) {
		$parent_file = $slug;
	}
}
add_action( 'admin_menu', 'setceb_sol_menu_minimo', 9999 );


/**
 * Redireciona a equipe juridica quando o acesso a uma tela e negado.
 *
 * O core checa user_can_access_admin_page() dentro de wp-admin/menu.php,
 * que roda ANTES de admin_init. Um link direto para uma subpagina do
 * modulo (Dashboard, Advogados) chega la e resultaria em
 * "Sorry, you are not allowed to access this page.".
 *
 * Este hook dispara imediatamente antes do wp_die do core, que e
 * exatamente o ponto de interceptacao necessario.
 */
function setceb_sol_acesso_negado() {
	if ( ! setceb_sol_usuario_restrito() ) {
		return;
	}

	// Nunca redireciona para a tela que ja esta sendo pedida.
	if ( setceb_sol_e_a_listagem() ) {
		return;
	}

	wp_safe_redirect( setceb_sol_admin_url() );
	exit;
}
add_action( 'admin_page_access_denied', 'setceb_sol_acesso_negado' );

/**
 * Bloqueia as telas fora do modulo.
 */
function setceb_sol_bloquear_telas() {
	if ( ! setceb_sol_usuario_restrito() ) {
		return;
	}

	if ( setceb_sol_tela_permitida() ) {
		return;
	}

	/*
	 * Nao existe tela de erro: qualquer endereco fora do modulo termina
	 * na listagem, inclusive o Painel (index.php), que e o destino padrao
	 * de quem entra no wp-admin. Redirecionar para a listagem e seguro
	 * porque ela e sempre liberada, e a trava abaixo impede repetir a
	 * troca caso a requisicao ja seja a propria listagem.
	 */
	if ( setceb_sol_e_a_listagem() ) {
		return;
	}

	wp_safe_redirect( setceb_sol_admin_url() );
	exit;
}
add_action( 'admin_init', 'setceb_sol_bloquear_telas' );

/**
 * A requisicao atual ja e a listagem do modulo?
 *
 * Trava anti-laco do redirecionamento aplicado ao Painel.
 *
 * @return bool
 */
function setceb_sol_e_a_listagem() {
	global $pagenow;

	return 'edit.php' === $pagenow
		&& empty( $_GET['page'] )
		&& isset( $_GET['post_type'] )
		&& SETCEB_SOL_CPT === sanitize_key( wp_unslash( $_GET['post_type'] ) );
}

/**
 * Ao entrar, o advogado vai direto para as solicitacoes.
 *
 * @param string           $redirect_to         Destino original.
 * @param string           $requested_redirect_to Destino solicitado.
 * @param WP_User|WP_Error $user                Usuario autenticado.
 * @return string
 */
function setceb_sol_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! is_a( $user, 'WP_User' ) || ! setceb_is_advogado( $user ) ) {
		return $redirect_to;
	}

	return setceb_sol_admin_url();
}
add_filter( 'login_redirect', 'setceb_sol_login_redirect', 20, 3 );

/**
 * Esconde a barra de administracao da equipe juridica no site.
 *
 * @param bool $mostrar Valor atual.
 * @return bool
 */
function setceb_sol_hide_admin_bar( $mostrar ) {
	if ( is_user_logged_in() && setceb_sol_usuario_restrito() ) {
		return false;
	}

	return $mostrar;
}
add_filter( 'show_admin_bar', 'setceb_sol_hide_admin_bar', 20 );

/**
 * Limpa a barra do admin para a equipe juridica.
 *
 * @param WP_Admin_Bar $barra Barra de administracao.
 */
function setceb_sol_limpar_admin_bar( $barra ) {
	if ( ! is_admin() || ! setceb_sol_usuario_restrito() ) {
		return;
	}

	foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates', 'site-name' ) as $node ) {
		$barra->remove_node( $node );
	}

	/*
	 * Os nos "my-account" e "user-info" ja apontam para profile.php
	 * (get_edit_profile_url() usa a propria conta), e o modulo libera
	 * essa tela. Nao ha o que corrigir aqui.
	 */
}
add_action( 'admin_bar_menu', 'setceb_sol_limpar_admin_bar', 999 );

/**
 * Remove os avisos de atualizacao do core para a equipe juridica.
 */
function setceb_sol_remover_avisos() {
	if ( ! setceb_sol_usuario_restrito() ) {
		return;
	}

	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
	remove_action( 'admin_notices', 'site_admin_notice', 4 );
}
add_action( 'admin_init', 'setceb_sol_remover_avisos' );

/* ------------------------------------------------------------
 * 11. Cadastro de advogados
 * ------------------------------------------------------------ */

/**
 * Cadastra (ou atualiza) um advogado da equipe juridica.
 *
 * Funcao de uso programmatico, tambem usada pela pagina
 * "Solicitacoes Juridicas > Advogados".
 *
 * @param array $dados {
 *     @type string $nome     Nome completo (obrigatorio).
 *     @type string $email    E-mail, tambem login (obrigatorio).
 *     @type string $senha    Senha (obrigatoria na criacao).
 *     @type string $telefone Telefone (opcional).
 *     @type string $oab      Registro na OAB (opcional).
 * }
 * @return int|WP_Error ID do usuario criado/atualizado.
 */
function setceb_cadastrar_advogado( $dados = array() ) {
	$dados = wp_parse_args(
		$dados,
		array(
			'nome'     => '',
			'email'    => '',
			'senha'    => '',
			'telefone' => '',
			'oab'      => '',
		)
	);

	$nome  = sanitize_text_field( $dados['nome'] );
	$email = sanitize_email( $dados['email'] );

	if ( '' === $nome ) {
		return new WP_Error( 'setceb_advogado_nome', 'Informe o nome do advogado.' );
	}

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'setceb_advogado_email', 'Informe um e-mail valido para o advogado.' );
	}

	$existente = get_user_by( 'email', $email );

	if ( $existente ) {
		$user_id = (int) $existente->ID;

		$atualiza = array(
			'ID'           => $user_id,
			'display_name' => $nome,
			'first_name'   => $nome,
		);

		if ( '' !== (string) $dados['senha'] ) {
			$atualiza['user_pass'] = (string) $dados['senha'];
		}

		$resultado = wp_update_user( $atualiza );

		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}
	} else {
		if ( strlen( (string) $dados['senha'] ) < 8 ) {
			return new WP_Error( 'setceb_advogado_senha', 'Defina uma senha com pelo menos 8 caracteres.' );
		}

		$login = $email;

		if ( username_exists( $login ) ) {
			$login = sanitize_user( str_replace( '@', '.', $email ), true );
			$base  = $login;
			$i     = 1;

			while ( username_exists( $login ) ) {
				++$i;
				$login = $base . $i;
			}
		}

		$resultado = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => (string) $dados['senha'],
				'display_name' => $nome,
				'first_name'   => $nome,
				'role'         => SETCEB_SOL_ROLE,
			)
		);

		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}

		$user_id = (int) $resultado;
	}

	$user = get_userdata( $user_id );

	// add_role preserva os demais perfis do usuario: reaproveitar um e-mail
	// ja existente nunca deve rebaixar um administrador ou um associado.
	// Quem tem "manage_options" e administrador e permanece como tal.
	if ( $user && ! user_can( $user, 'manage_options' ) && ! setceb_is_advogado( $user ) ) {
		$user->add_role( SETCEB_SOL_ROLE );
	}

	if ( '' !== $dados['telefone'] ) {
		update_user_meta( $user_id, 'setceb_telefone', sanitize_text_field( $dados['telefone'] ) );
	}

	if ( '' !== $dados['oab'] ) {
		update_user_meta( $user_id, 'setceb_oab', sanitize_text_field( $dados['oab'] ) );
	}

	/**
	 * Dispara depois que o advogado foi cadastrado ou atualizado.
	 *
	 * @param int   $user_id ID do usuario.
	 * @param array $dados   Dados informados (sem a senha).
	 */
	do_action(
		'setceb_advogado_cadastrado',
		$user_id,
		array(
			'nome'     => $nome,
			'email'    => $email,
			'telefone' => isset( $dados['telefone'] ) ? sanitize_text_field( $dados['telefone'] ) : '',
			'oab'      => isset( $dados['oab'] ) ? sanitize_text_field( $dados['oab'] ) : '',
		)
	);

	return $user_id;
}

/**
 * Remove o perfil de advogado de um usuario (sem excluir a conta).
 *
 * @param int $user_id ID do usuario.
 * @return bool
 */
function setceb_sol_remover_advogado( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user || ! setceb_is_advogado( $user ) ) {
		return false;
	}

	if ( user_can( $user, 'manage_options' ) ) {
		return false;
	}

	// remove_role tira o perfil e as capacidades derivadas dele; a conta
	// do usuario e preservada.
	$user->remove_role( SETCEB_SOL_ROLE );

	if ( (int) get_option( 'setceb_sol_advogado_padrao', 0 ) === (int) $user_id ) {
		delete_option( 'setceb_sol_advogado_padrao' );
	}

	return true;
}

/* ------------------------------------------------------------
 * 12. Menu do modulo
 * ------------------------------------------------------------ */

/**
 * Paginas administrativas proprias do modulo.
 *
 * Ambas exigem "manage_options": a equipe juridica ve apenas a
 * listagem e o atendimento das solicitacoes.
 */
function setceb_sol_menu() {
	$listagem = 'edit.php?post_type=' . SETCEB_SOL_CPT;

	add_submenu_page(
		$listagem,
		'Dashboard Juridico',
		'Dashboard',
		'manage_options',
		'setceb-sol-dashboard',
		'setceb_sol_pagina_dashboard'
	);

	add_submenu_page(
		$listagem,
		'Advogados',
		'Advogados',
		'manage_options',
		'setceb-sol-advogados',
		'setceb_sol_pagina_advogados'
	);
}
add_action( 'admin_menu', 'setceb_sol_menu', 20 );

/**
 * Bloqueia a tela de criacao de solicitacoes.
 *
 * O item de menu e o botao "Adicionar novo" ja somem porque o post type
 * declara create_posts como "do_not_allow". Esta guarda cobre os acessos
 * diretos pela URL (post-new.php), inclusive por administradores.
 */
function setceb_sol_bloquear_criacao() {
	global $pagenow;

	if ( 'post-new.php' !== $pagenow ) {
		return;
	}

	$tipo = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';

	if ( SETCEB_SOL_CPT !== $tipo ) {
		return;
	}

	// Nao existe tela de erro: o acesso termina na listagem do modulo.
	wp_safe_redirect( setceb_sol_admin_url() );
	exit;
}
add_action( 'admin_init', 'setceb_sol_bloquear_criacao', 5 );

/**
 * Garante que o menu de nivel superior do modulo exista.
 *
 * Assim como os demais CPTs da area do associado, outro plugin pode
 * remover ou sobrescrever o item; se isso acontecer, recriamos o menu
 * e os submenus padrao do post type.
 *
 * O slug do menu e sempre relativo ("edit.php?post_type=..."), que e
 * como o WordPress registra os menus de post type.
 */
function setceb_sol_garantir_menu() {
	global $menu;

	$slug = 'edit.php?post_type=' . SETCEB_SOL_CPT;

	foreach ( (array) $menu as $item ) {
		if ( ! empty( $item[2] ) && $slug === $item[2] ) {
			return;
		}
	}

	$obj = get_post_type_object( SETCEB_SOL_CPT );

	if ( ! $obj || ! current_user_can( $obj->cap->edit_posts ) ) {
		return;
	}

	add_menu_page(
		$obj->labels->name,
		$obj->labels->menu_name,
		$obj->cap->edit_posts,
		$slug,
		'',
		$obj->menu_icon ? $obj->menu_icon : 'dashicons-admin-post',
		$obj->menu_position
	);

	add_submenu_page( $slug, $obj->labels->all_items, $obj->labels->all_items, $obj->cap->edit_posts, $slug );
}
add_action( 'admin_menu', 'setceb_sol_garantir_menu', 100 );

/* ------------------------------------------------------------
 * 13. Dashboard
 * ------------------------------------------------------------ */

/**
 * Indicadores do atendimento juridico.
 *
 * @return array
 */
function setceb_sol_indicadores() {
	$linhas     = setceb_sol_relatorio();
	$por_status = array();
	$por_adv    = array();
	$recentes   = 0;
	$mes        = 0;
	$solicitantes = array();
	$abertas     = 0;

	$hoje       = current_time( 'timestamp' );
	$desde_30   = gmdate( 'Y-m-d H:i:s', $hoje - ( 30 * DAY_IN_SECONDS ) );
	$ini_mes    = current_time( 'Y-m-01 00:00:00' );
	$padrao     = setceb_sol_advogado_padrao_id();

	foreach ( $linhas as $linha ) {
		$status   = '' !== (string) $linha['status'] ? (string) $linha['status'] : setceb_sol_status_padrao();
		$advogado = (int) $linha['advogado'];
		$data     = (string) $linha['post_date'];

		// Mesmo criterio da tela de atendimento: sem responsavel gravado,
		// a solicitacao pertence ao advogado padrao.
		if ( ! $advogado ) {
			$advogado = $padrao;
		}

		if ( ! isset( $por_status[ $status ] ) ) {
			$por_status[ $status ] = 0;
		}

		$por_status[ $status ]++;

		$chave = $advogado ? (string) $advogado : '0';

		if ( ! isset( $por_adv[ $chave ] ) ) {
			$por_adv[ $chave ] = array(
				'id'          => $advogado,
				'total'       => 0,
				'abertas'     => 0,
				'respondidas' => 0,
				'ultima'      => '',
			);
		}

		$por_adv[ $chave ]['total']++;

		if ( setceb_sol_status_info( $status )['ativo'] ) {
			$por_adv[ $chave ]['abertas']++;
			++$abertas;
		}

		if ( 'respondida' === $status ) {
			$por_adv[ $chave ]['respondidas']++;
		}

		if ( '' === $por_adv[ $chave ]['ultima'] || $data > $por_adv[ $chave ]['ultima'] ) {
			$por_adv[ $chave ]['ultima'] = $data;
		}

		if ( $data >= $desde_30 ) {
			++$recentes;
		}

		if ( $data >= $ini_mes ) {
			++$mes;
		}

		$solicitantes[ (int) $linha['post_author'] ] = true;
	}

	$nomes = array();

	foreach ( setceb_sol_advogados() as $advogado ) {
		$nomes[ (string) $advogado->ID ] = $advogado->display_name;
	}

	foreach ( $por_adv as $chave => $dados ) {
		$por_adv[ $chave ]['nome'] = isset( $nomes[ $chave ] ) ? $nomes[ $chave ] : 'Sem responsavel';
	}

	uasort(
		$por_adv,
		static function ( $a, $b ) {
			return $b['total'] <=> $a['total'];
		}
	);

	return array(
		'total'        => count( $linhas ),
		'abertas'      => $abertas,
		'por_status'   => $por_status,
		'por_advogado' => $por_adv,
		'recentes'     => $recentes,
		'mes'          => $mes,
		'solicitantes' => count( $solicitantes ),
	);
}

/**
 * Renderiza o Dashboard Juridico.
 */
function setceb_sol_pagina_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$dados        = setceb_sol_indicadores();
	$status       = setceb_sol_status();
	$total_status = max( 1, (int) $dados['total'] );

	/* Filtros da listagem completa (nonce dispensado: apenas leitura). */
	$sol_filtro_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$sol_filtro_busca  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$sol_filtro_pagina = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

	if ( '' !== $sol_filtro_status && ! isset( $status[ $sol_filtro_status ] ) ) {
		$sol_filtro_status = '';
	}

	$sol_lista = setceb_sol_listagem_admin(
		array(
			'status'     => $sol_filtro_status,
			'busca'      => $sol_filtro_busca,
			'pagina'     => $sol_filtro_pagina,
			'por_pagina' => 25,
		)
	);
	?>
	<div class="wrap setceb-sol-wrap">
		<h1 class="wp-heading-inline">Solicitacoes Juridicas</h1>
		<hr class="wp-header-end">

		<p class="setceb-sol-subtitulo">
			Acompanhamento dos atendimentos da Assessoria Juridica.
		</p>

		<div class="setceb-sol-cards">
			<div class="setceb-sol-card setceb-sol-card--destaque">
				<span class="setceb-sol-card__numero"><?php echo (int) $dados['total']; ?></span>
				<span class="setceb-sol-card__rotulo">Solicitacoes registradas</span>
			</div>
			<div class="setceb-sol-card">
				<span class="setceb-sol-card__numero"><?php echo (int) $dados['abertas']; ?></span>
				<span class="setceb-sol-card__rotulo">Em aberto</span>
			</div>
			<div class="setceb-sol-card">
				<span class="setceb-sol-card__numero"><?php echo (int) $dados['mes']; ?></span>
				<span class="setceb-sol-card__rotulo">No mes atual</span>
			</div>
			<div class="setceb-sol-card">
				<span class="setceb-sol-card__numero"><?php echo (int) $dados['recentes']; ?></span>
				<span class="setceb-sol-card__rotulo">Ultimos 30 dias</span>
			</div>
			<div class="setceb-sol-card">
				<span class="setceb-sol-card__numero"><?php echo (int) $dados['solicitantes']; ?></span>
				<span class="setceb-sol-card__rotulo">Associados atendidos</span>
			</div>
		</div>

		<div class="setceb-sol-colunas">
			<div class="setceb-sol-box">
				<h2>Distribuicao por status</h2>
				<ul class="setceb-sol-barras">
					<?php foreach ( $status as $slug => $info ) : ?>
						<?php
						$total = isset( $dados['por_status'][ $slug ] ) ? (int) $dados['por_status'][ $slug ] : 0;
						$valor = $total ? max( 3, (int) round( ( $total / $total_status ) * 100 ) ) : 0;
						?>
						<li class="setceb-sol-barras__item">
							<a href="<?php echo esc_url( add_query_arg( 'status_sol', $slug, setceb_sol_admin_url() ) ); ?>">
								<span class="setceb-sol-barras__rotulo">
									<span class="dashicons <?php echo esc_attr( $info['icone'] ); ?>" aria-hidden="true"></span>
									<?php echo esc_html( $info['rotulo'] ); ?>
								</span>
								<span class="setceb-sol-barras__trilho">
									<span class="setceb-sol-barras__valor" style="width:<?php echo (int) $valor; ?>%;background:<?php echo esc_attr( $info['cor'] ); ?>"></span>
								</span>
								<span class="setceb-sol-barras__numero"><?php echo (int) $total; ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="setceb-sol-box">
				<h2>Atendimentos por advogado</h2>
				<?php if ( empty( $dados['por_advogado'] ) ) : ?>
					<p class="description">Nenhuma solicitacao registrada.</p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Advogado</th>
								<th class="setceb-sol-num">Total</th>
								<th class="setceb-sol-num">Em aberto</th>
								<th class="setceb-sol-num">Respondidas</th>
								<th>Ultima</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $dados['por_advogado'] as $linha ) : ?>
								<tr>
									<td>
										<?php if ( $linha['id'] ) : ?>
											<a href="<?php echo esc_url( get_edit_user_link( $linha['id'] ) ); ?>"><?php echo esc_html( $linha['nome'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $linha['nome'] ); ?>
										<?php endif; ?>
									</td>
									<td class="setceb-sol-num"><?php echo (int) $linha['total']; ?></td>
									<td class="setceb-sol-num"><?php echo (int) $linha['abertas']; ?></td>
									<td class="setceb-sol-num"><?php echo (int) $linha['respondidas']; ?></td>
									<td><?php echo esc_html( setceb_sol_data( $linha['ultima'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

<div class="setceb-sol-box setceb-sol-box--lista">
			<h2>Todas as solicitacoes</h2>

			<form class="setceb-sol-filtros" method="get">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( SETCEB_SOL_CPT ); ?>">
				<input type="hidden" name="page" value="setceb-sol-dashboard">

				<label class="setceb-sol-filtros__campo">
					<span>Status</span>
					<select name="status">
						<option value="">Todos os status</option>
						<?php foreach ( $status as $slug_status => $info_status ) : ?>
							<option value="<?php echo esc_attr( $slug_status ); ?>" <?php selected( $sol_filtro_status, $slug_status ); ?>>
								<?php echo esc_html( $info_status['rotulo'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="setceb-sol-filtros__campo setceb-sol-filtros__campo--busca">
					<span>Buscar</span>
					<input type="search" name="s" value="<?php echo esc_attr( $sol_filtro_busca ); ?>" placeholder="Protocolo, titulo, nome ou e-mail">
				</label>

				<button type="submit" class="button">Filtrar</button>

				<?php if ( '' !== $sol_filtro_status || '' !== $sol_filtro_busca ) : ?>
					<a class="button-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SETCEB_SOL_CPT . '&page=setceb-sol-dashboard' ) ); ?>">Limpar</a>
				<?php endif; ?>
			</form>

			<p class="setceb-sol-contagem">
				<?php
				printf(
					'%d solicitacao%s encontrada%s',
					(int) $sol_lista['total'],
					1 === (int) $sol_lista['total'] ? '' : 's',
					(int) $sol_lista['total'] > (int) $sol_lista['por_pagina'] ? 's' : ''
				);
				?>
				<?php if ( $sol_lista['paginas'] > 1 ) : ?>
					&middot; pagina <?php echo (int) $sol_lista['pagina']; ?> de <?php echo (int) $sol_lista['paginas']; ?>
				<?php endif; ?>
			</p>

			<?php if ( empty( $sol_lista['itens'] ) ) : ?>
				<p class="description">Nenhuma solicitacao encontrada com esses filtros.</p>
			<?php else : ?>
				<table class="widefat striped setceb-sol-lista" data-nonce="<?php echo esc_attr( wp_create_nonce( 'setceb_sol_conversa' ) ); ?>">
					<thead>
						<tr>
							<th class="setceb-sol-col-ver">Conversa</th>
							<th>Protocolo</th>
							<th>Solicitante</th>
							<th>Status</th>
							<th>Advogado</th>
							<th>Aberta em</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sol_lista['itens'] as $sol ) : ?>
							<tr class="setceb-sol-lista__linha" data-sol-id="<?php echo (int) $sol['id']; ?>">
								<td class="setceb-sol-col-ver">
									<button type="button" class="button button-small setceb-sol-ver" data-sol-ver="<?php echo (int) $sol['id']; ?>" aria-expanded="false" aria-controls="setceb-sol-conversa-<?php echo (int) $sol['id']; ?>">
										Ver conversa
									</button>
								</td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $sol['id'] ) ); ?>">
										<strong><?php echo esc_html( $sol['protocolo'] ); ?></strong>
									</a>
									<br><span class="description"><?php echo esc_html( $sol['titulo'] ); ?></span>
								</td>
								<td><?php echo esc_html( '' !== $sol['nome'] ? $sol['nome'] : '—' ); ?></td>
								<td>
									<span class="setceb-sol-pill" style="--setceb-sol-cor:<?php echo esc_attr( $sol['status_cor'] ); ?>">
										<span class="dashicons <?php echo esc_attr( $sol['status_icone'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $sol['status_rotulo'] ); ?>
									</span>
								</td>
								<td><?php echo esc_html( '' !== $sol['advogado'] ? $sol['advogado'] : '—' ); ?></td>
								<td><?php echo esc_html( setceb_sol_data( $sol['data'] ) ); ?></td>
							</tr>
							<tr class="setceb-sol-lista__conversa" id="setceb-sol-conversa-<?php echo (int) $sol['id']; ?>" data-sol-painel="<?php echo (int) $sol['id']; ?>" hidden>
								<td colspan="6">
									<div class="setceb-sol-conversa" data-sol-conversa="<?php echo (int) $sol['id']; ?>">
										<p class="setceb-sol-vazio">Carregando conversa…</p>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $sol_lista['paginas'] > 1 ) : ?>
					<div class="setceb-sol-paginacao">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									/* Mantem os filtros ao trocar de pagina.
									   add_query_arg escapa '%#%' para
									   '%25%23%25'; usa-se o padrao
									   numerico do proprio WordPress. */
									'base'      => str_replace(
										'999999999',
										'%#%',
										esc_url(
											add_query_arg(
												array(
													'paged'  => 999999999,
													'status' => $sol_filtro_status,
													's'      => $sol_filtro_busca,
												),
												admin_url( 'edit.php?post_type=' . SETCEB_SOL_CPT . '&page=setceb-sol-dashboard' )
											)
										)
									),
									'format'    => '',
									'current'   => (int) $sol_lista['pagina'],
									'total'     => (int) $sol_lista['paginas'],
									'type'      => 'plain',
									'prev_text' => '&laquo; Anteriores',
									'next_text' => 'Proximas &raquo;',
								)
							)
						);
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/* ------------------------------------------------------------
 * 14. Pagina de cadastro de advogados
 * ------------------------------------------------------------ */

/**
 * URL da pagina de advogados.
 *
 * @param array $args Argumentos adicionais.
 * @return string
 */
function setceb_sol_advogados_url( $args = array() ) {
	return add_query_arg(
		array_merge( array( 'post_type' => SETCEB_SOL_CPT, 'page' => 'setceb-sol-advogados' ), $args ),
		admin_url( 'edit.php' )
	);
}

/**
 * Renderiza a pagina de cadastro/gestao de advogados.
 */
function setceb_sol_pagina_advogados() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$avisos = array();

	// ------------------------------------------------------------
	// Acoes
	// ------------------------------------------------------------
	if ( isset( $_POST['setceb_sol_acao'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_sol_advogados' ) ) {
		$acao = sanitize_key( wp_unslash( $_POST['setceb_sol_acao'] ) );

		if ( 'salvar' === $acao ) {
			$resultado = setceb_cadastrar_advogado(
				array(
					'nome'     => isset( $_POST['setceb_sol_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_sol_nome'] ) ) : '',
					'email'    => isset( $_POST['setceb_sol_email'] ) ? sanitize_email( wp_unslash( $_POST['setceb_sol_email'] ) ) : '',
					// A senha nao passa por sanitize_text_field: isso
					// corromperia caracteres especiais. Ela nunca e
					// exibida, apenas armazenada com hash pelo WordPress.
					'senha'    => isset( $_POST['setceb_sol_senha'] ) && is_scalar( $_POST['setceb_sol_senha'] ) ? (string) wp_unslash( $_POST['setceb_sol_senha'] ) : '',
					'telefone' => isset( $_POST['setceb_sol_telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_sol_telefone'] ) ) : '',
					'oab'      => isset( $_POST['setceb_sol_oab'] ) ? sanitize_text_field( wp_unslash( $_POST['setceb_sol_oab'] ) ) : '',
				)
			);

			if ( is_wp_error( $resultado ) ) {
				$avisos[] = array( 'erro', $resultado->get_error_message() );
			} else {
				$avisos[] = array( 'ok', 'Advogado cadastrado com sucesso. Ele ja pode entrar no painel e ver apenas as solicitacoes juridicas.' );
			}
		}

		if ( 'padrao' === $acao ) {
			$user_id = isset( $_POST['setceb_sol_user'] ) ? absint( wp_unslash( $_POST['setceb_sol_user'] ) ) : 0;

			if ( setceb_is_advogado( $user_id ) ) {
				update_option( 'setceb_sol_advogado_padrao', $user_id );
				$avisos[] = array( 'ok', 'Advogado definido como responsavel padrao. As novas solicitacoes passam a ser atribuidas a ele.' );
			}
		}

		if ( 'remover' === $acao ) {
			$user_id = isset( $_POST['setceb_sol_user'] ) ? absint( wp_unslash( $_POST['setceb_sol_user'] ) ) : 0;

			if ( setceb_sol_remover_advogado( $user_id ) ) {
				$avisos[] = array( 'ok', 'Perfil de advogado removido do usuario.' );
			} else {
				$avisos[] = array( 'erro', 'Nao foi possivel remover o perfil deste usuario.' );
			}
		}
	}

	$advogados = setceb_sol_advogados();
	$padrao_id = setceb_sol_advogado_padrao_id();
	?>
	<div class="wrap setceb-sol-wrap">
		<h1>Advogados</h1>

		<p class="setceb-sol-subtitulo">
			Cadastre a equipe juridica. O perfil <strong>Advogado</strong> da acesso
			exclusivo ao menu de Solicitacoes Juridicas no painel.
		</p>

		<?php foreach ( $avisos as $aviso ) : ?>
			<div class="notice notice-<?php echo 'ok' === $aviso[0] ? 'success' : 'error'; ?> is-dismissible">
				<p><?php echo esc_html( $aviso[1] ); ?></p>
			</div>
		<?php endforeach; ?>

		<div class="setceb-sol-colunas">
			<div class="setceb-sol-box">
				<h2>Novo advogado</h2>
				<form method="post" action="<?php echo esc_url( setceb_sol_advogados_url() ); ?>">
					<?php wp_nonce_field( 'setceb_sol_advogados' ); ?>
					<input type="hidden" name="setceb_sol_acao" value="salvar">

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="setceb-adv-nome">Nome completo</label></th>
								<td><input type="text" id="setceb-adv-nome" name="setceb_sol_nome" class="regular-text" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="setceb-adv-email">E-mail (login)</label></th>
								<td>
									<input type="email" id="setceb-adv-email" name="setceb_sol_email" class="regular-text" required>
									<p class="description">Se ja existir um usuario com este e-mail, ele e convertido em advogado.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="setceb-adv-senha">Senha</label></th>
								<td>
									<input type="text" id="setceb-adv-senha" name="setceb_sol_senha" class="regular-text" autocomplete="new-password">
									<p class="description">Minimo de 8 caracteres. Deixe em branco para manter a senha atual.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="setceb-adv-oab">OAB</label></th>
								<td><input type="text" id="setceb-adv-oab" name="setceb_sol_oab" class="regular-text" placeholder="OAB/SP 123.456"></td>
							</tr>
							<tr>
								<th scope="row"><label for="setceb-adv-telefone">Telefone</label></th>
								<td><input type="text" id="setceb-adv-telefone" name="setceb_sol_telefone" class="regular-text"></td>
							</tr>
						</tbody>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">Cadastrar advogado</button>
					</p>
				</form>
			</div>

			<div class="setceb-sol-box">
				<h2>Equipe juridica cadastrada</h2>
				<?php if ( empty( $advogados ) ) : ?>
					<p class="description">Nenhum advogado cadastrado. As solicitacoes serao notificadas no endereco administrativo.</p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Advogado</th>
								<th>Contato</th>
								<th>Acoes</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $advogados as $advogado ) : ?>
								<?php
								$telefone = (string) get_user_meta( $advogado->ID, 'setceb_telefone', true );
								$oab      = (string) get_user_meta( $advogado->ID, 'setceb_oab', true );
								$eh_padrao = (int) $advogado->ID === $padrao_id;
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $advogado->display_name ); ?></strong>
										<?php if ( $eh_padrao ) : ?>
											<span class="setceb-sol-tag">Responsavel padrao</span>
										<?php endif; ?>
										<?php if ( '' !== $oab ) : ?>
											<br><span class="description"><?php echo esc_html( $oab ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<a href="mailto:<?php echo esc_attr( $advogado->user_email ); ?>"><?php echo esc_html( $advogado->user_email ); ?></a>
										<?php if ( '' !== $telefone ) : ?>
											<br><span class="description"><?php echo esc_html( $telefone ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! $eh_padrao ) : ?>
											<form method="post" action="<?php echo esc_url( setceb_sol_advogados_url() ); ?>" class="setceb-sol-inline-form">
												<?php wp_nonce_field( 'setceb_sol_advogados' ); ?>
												<input type="hidden" name="setceb_sol_acao" value="padrao">
												<input type="hidden" name="setceb_sol_user" value="<?php echo (int) $advogado->ID; ?>">
												<button type="submit" class="button button-small">Tornar padrao</button>
											</form>
										<?php endif; ?>
										<form method="post" action="<?php echo esc_url( setceb_sol_advogados_url() ); ?>" class="setceb-sol-inline-form">
											<?php wp_nonce_field( 'setceb_sol_advogados' ); ?>
											<input type="hidden" name="setceb_sol_acao" value="remover">
											<input type="hidden" name="setceb_sol_user" value="<?php echo (int) $advogado->ID; ?>">
											<button type="submit" class="button button-small setceb-sol-perigo">Remover perfil</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/* ------------------------------------------------------------
 * 15. Estilos do admin
 * ------------------------------------------------------------ */

/**
 * Carrega o CSS do modulo nas telas do painel.
 *
 * @param string $hook Pagina atual do admin.
 */
function setceb_sol_admin_assets( $hook ) {
	$screen = get_current_screen();
	$pagina = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	$nossas_telas = ( $screen && SETCEB_SOL_CPT === $screen->post_type )
		|| 0 === strpos( (string) $hook, 'setceb-sol' )
		|| 0 === strpos( $pagina, 'setceb-sol' );

	if ( ! $nossas_telas ) {
		return;
	}

	$versao = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'setceb-sol-admin',
		get_stylesheet_directory_uri() . '/juridico-admin.css',
		array(),
		$versao
	);

	/* O "Ver conversa" so existe na listagem do dashboard. */
	if ( $pagina === 'setceb-sol-dashboard' ) {
		wp_enqueue_script(
			'setceb-sol-admin',
			get_stylesheet_directory_uri() . '/juridico-admin.js',
			array(),
			$versao,
			true
		);

		wp_localize_script(
			'setceb-sol-admin',
			'setcebSolAdmin',
			array(
				'nonce' => wp_create_nonce( 'setceb_sol_conversa' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'setceb_sol_admin_assets' );
