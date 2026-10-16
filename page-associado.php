<?php
/**
 * Template Name: Perfil do Associado
 *
 * Area do associado, fora do painel do WordPress. E o destino do login
 * dos associados e concentra os servicos: emissao de boletos, planilhas,
 * relatorios, convencoes coletivas, simuladores, juridico e fale conosco.
 *
 * Layout em duas areas: barra lateral (perfil, modulos e atendimento) e
 * area principal (acesso rapido + paginas de cada modulo). O modulo
 * Juridico usa duas colunas: solicitacoes a esquerda e conversa a direita.
 *
 * Usa o fluxo padrao do Divi (get_header/get_footer) para exibir o
 * header e o footer globais. O header customizado e renderizado pelo
 * hook et_before_main_content (mesmo mecanismo das demais paginas).
 *
 * @package Divi_Child_SETCEB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Sinaliza para o header global (setceb-header.php) que esta e a pagina
   minimalista: esconde os links de navegacao, mantendo logo, busca e
   "Minha conta". */
$GLOBALS['setceb_header_minimal'] = true;

$is_entitled = setceb_is_associado() || current_user_can( 'manage_options' );
$home        = home_url( '/' );

add_filter(
	'body_class',
	function ( $classes ) use ( $is_entitled ) {
		$classes[] = 'setceb-perfil-associado';
		$classes[] = $is_entitled ? 'is-entitled' : 'not-entitled';
		return $classes;
	}
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		return $robots;
	}
);

get_header();

$user         = wp_get_current_user();
$categorias   = setceb_associado_categorias();
$anos         = setceb_associado_anos();
$ano_atual    = ! empty( $anos ) ? (string) $anos[0] : (string) gmdate( 'Y' );
$planilhas    = setceb_planilhas();
/* Categorias efetivamente presentes nas planilhas cadastradas. */
$categorias_planilhas = array();
foreach ( $planilhas as $plan_item ) {
	if ( ! empty( $plan_item['categoria'] ) ) {
		$cat_slug = $plan_item['categoria'];
		if ( ! isset( $categorias_planilhas[ $cat_slug ] ) ) {
			$categorias_planilhas[ $cat_slug ] = isset( $categorias[ $cat_slug ] ) ? $categorias[ $cat_slug ] : $cat_slug;
		}
	}
}
$relatorios   = setceb_relatorios();
$convencoes   = setceb_convencoes();
$outros_materiais = setceb_outros_mat();
$simuladores  = setceb_simuladores();
$recentes     = setceb_conteudos_recentes( 8 );
$boletos      = setceb_boletos();
$assuntos     = setceb_contato_assuntos();
$sol_categorias = setceb_sol_categorias();

$notice       = setceb_associado_form_notice();

/* Solicitações jurídicas (includes/juridico.php). O aviso do módulo
   complementa o aviso padrão dos formulários da área do associado. */
if ( ! $notice && function_exists( 'setceb_sol_notice' ) ) {
	$notice = setceb_sol_notice();
}

$minhas_solicitacoes = function_exists( 'setceb_sol_listar' ) ? setceb_sol_listar( array( 'usuario_id' => get_current_user_id() ) ) : array();
$sol_aberta          = isset( $_GET['sol_id'] ) ? absint( $_GET['sol_id'] ) : 0;
$sol_nova_aberta     = isset( $_GET['sol_nova'] ) && absint( $_GET['sol_nova'] ) > 0;

/* Painel visivel: aviso de formulario > solicitacao da URL > acesso rapido. */
$active_panel = 'visao-geral';

if ( $notice ) {
	$active_panel = $notice['forma'];
} elseif ( $sol_aberta || $sol_nova_aberta ) {
	$active_panel = 'juridico';
}

/* Modulos da area do associado: rotulo, icone (dashicon) e descricao.
   A ordem define a navegacao lateral, a grade de acesso rapido e o
   cabecalho de cada modulo. */
$modulos = array(
	'recentes'         => array(
		'rotulo' => 'Conteúdos recentes',
		'icone'  => 'dashicons-megaphone',
		'desc'   => 'Últimas publicações disponibilizadas para os associados.',
	),
	'planilhas'        => array(
		'rotulo' => 'Planilhas',
		'icone'  => 'dashicons-media-spreadsheet',
		'desc'   => 'Arquivos e ferramentas de apoio por categoria de transporte.',
	),
	'relatorios'       => array(
		'rotulo' => 'Relatórios',
		'icone'  => 'dashicons-chart-bar',
		'desc'   => 'Relatórios mensais por categoria de transporte.',
	),
	'convencoes'       => array(
		'rotulo' => 'Convenções coletivas',
		'icone'  => 'dashicons-media-document',
		'desc'   => 'Documentos oficiais das convenções coletivas da categoria.',
	),
	'outros-materiais' => array(
		'rotulo' => 'Outros materiais',
		'icone'  => 'dashicons-portfolio',
		'desc'   => 'Materiais complementares e documentos diversos.',
	),
	'simuladores'      => array(
		'rotulo' => 'Simuladores',
		'icone'  => 'dashicons-performance',
		'desc'   => 'Ferramentas online de cálculo e simulação.',
	),
	'juridico'         => array(
		'rotulo' => 'Jurídico',
		'icone'  => 'dashicons-book',
		'desc'   => 'Abra solicitações e converse com a equipe jurídica do SETCEB.',
	),
	'financeiro'       => array(
		'rotulo' => 'Financeiro',
		'icone'  => 'dashicons-money-alt',
		'desc'   => 'Geração de boletos e recursos financeiros do associado.',
	),
	'fale-conosco'     => array(
		'rotulo' => 'Fale conosco',
		'icone'  => 'dashicons-format-chat',
		'desc'   => 'Envie dúvidas, sugestões ou solicitações para o SETCEB.',
	),
);

/* Cartao de acesso rapido: "Emitir boleto" abre o Financeiro, onde os
   links de emissao ficam. Cada modulo abre o proprio painel. */
$quick_cards = array_merge(
	array(
		'emitir-boleto' => array(
			'rotulo' => 'Emitir boleto',
			'icone'  => 'dashicons-tickets-alt',
			'desc'   => '2ª via e pagamentos',
			'painel' => 'financeiro',
		),
	),
	array_map(
		function ( $modulo, $chave ) {
			$modulo['painel'] = $chave;
			return $modulo;
		},
		$modulos,
		array_keys( $modulos )
	)
);

$boleto_icons = array(
	'calendar' => 'dashicons-calendar-alt',
	'heart'    => 'dashicons-heart',
	'users'    => 'dashicons-groups',
);

$user_name  = trim( $user->first_name . ' ' . $user->last_name );
$user_name  = '' !== trim( $user_name ) ? $user_name : $user->display_name;
$user_email = $user->user_email;
$perfil_url = setceb_associado_perfil_url();
$senha_url  = wp_lostpassword_url( $perfil_url );

/* Solicitacao aberta no painel do juridico (primeira da lista como padrao). */
$sol_ids   = array_map(
	function ( $sol_item ) {
		return (int) $sol_item['id'];
	},
	$minhas_solicitacoes
);
/* Entrada padrao do Juridico: a LISTA de solicitacoes. Nenhuma conversa
   e aberta sozinha - ela so abre quando o_assococentou nela (sol_id) ou
   pediu uma nova (sol_nova). Antes caia na primeira solicitacao da lista
   sem o usuario ter escolhido nada. */
$sol_lista_aberta = ! ( $sol_aberta > 0 || $sol_nova_aberta );

$sol_ativa = ( $sol_lista_aberta || ! in_array( (int) $sol_aberta, $sol_ids, true ) )
	? 0
	: (int) $sol_aberta;

$sol_total = count( $minhas_solicitacoes );

/* Formulario de nova solicitacao: so quando explicitamente pedido. Sem
   solicitacoes o usuario tambem cai na lista - que tem o botao "Nova" e
   o estado vazio explicando o que fazer. */
$sol_nova_ativa = ( ! $sol_lista_aberta ) && $sol_nova_aberta;

/**
 * Painel ativo por padrao (classe/aria dos botoes e secoes).
 */
function setceb_panel_active_attr( $panel, $active_panel, $attr ) {
	if ( 'class' === $attr ) {
		return $panel === $active_panel ? ' assoc-panel is-active' : ' assoc-panel';
	}

	return $panel === $active_panel ? 'true' : 'false';
}

/**
 * Cabecalho do modulo: retorno ao acesso rapido, icone, titulo e descricao.
 *
 * @param string $key     Chave do modulo.
 * @param array  $modulos Mapa de modulos da area do associado.
 */
function setceb_modulo_cabecalho( $key, $modulos, $year_filter = false ) {
	global $anos, $ano_atual;
	if ( ! isset( $modulos[ $key ] ) ) {
		return;
	}

	$modulo = $modulos[ $key ];
	?>
	<header class="assoc-mod">
		<button type="button" class="assoc-mod__back" data-open-panel="visao-geral">
			<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			<span class="assoc-mod__back-full">Voltar ao acesso rápido</span>
			<span class="assoc-mod__back-short">Voltar</span>
		</button>
		<div class="assoc-mod__main">
			<span class="assoc-mod__icon"><span class="dashicons <?php echo esc_attr( $modulo['icone'] ); ?>" aria-hidden="true"></span></span>
			<div class="assoc-mod__body">
				<h2 class="assoc-mod__title"><?php echo esc_html( $modulo['rotulo'] ); ?></h2>
				<p class="assoc-mod__desc"><?php echo esc_html( $modulo['desc'] ); ?></p>
			</div>
			<?php if ( $year_filter ) : ?>
			<div class="assoc-mod__year">
				<div class="assoc-year">
					<button type="button" class="assoc-year__btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Selecionar ano de referência">
						<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						<span class="assoc-year__current"><?php echo esc_html( $ano_atual ); ?></span>
						<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<ul class="assoc-year__list" role="listbox" aria-label="Anos disponíveis" hidden>
						<?php foreach ( $anos as $ano ) : ?>
							<li class="assoc-year__option<?php echo (string) $ano === $ano_atual ? ' is-selected' : ''; ?>" role="option" tabindex="-1" data-ano="<?php echo esc_attr( $ano ); ?>" aria-selected="<?php echo (string) $ano === $ano_atual ? 'true' : 'false'; ?>"><?php echo esc_html( $ano ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</header>
	<?php
}
?>
<div id="main-content">
	<main class="setceb-perfil">

		<h1 class="assoc-sr-only">Área do Associado</h1>

		<div class="assoc-container">

			<?php if ( ! is_user_logged_in() ) : ?>

				<div class="setceb-perfil__card">
					<div class="setceb-perfil__state">
						<span class="setceb-perfil__icon"><span class="dashicons dashicons-lock" aria-hidden="true"></span></span>
						<h2>Área do Associado</h2>
						<p>Entre com sua conta para acessar a emissão de boletos e os conteúdos exclusivos para associados.</p>
						<a class="setceb-perfil__btn" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Entrar</a>
					</div>
				</div>

			<?php elseif ( ! $is_entitled ) : ?>

				<div class="setceb-perfil__card">
					<div class="setceb-perfil__state">
						<span class="setceb-perfil__icon"><span class="dashicons dashicons-lock" aria-hidden="true"></span></span>
						<h2>Acesso restrito</h2>
						<p>Sua conta não possui o perfil de associado. Entre em contato com a associação para regularizar seu acesso.</p>
						<a class="setceb-perfil__btn setceb-perfil__btn--ghost" href="<?php echo esc_url( $home ); ?>">Voltar ao site</a>
					</div>
				</div>

			<?php else : ?>

				<div class="assoc-shell">

					<aside class="assoc-sidebar">


						<!-- Navegacao dos modulos -->
						<nav class="assoc-nav" aria-label="Módulos da Área do Associado">
							<details class="assoc-nav__details" open>
								<summary class="assoc-nav__toggle">
									<span class="dashicons dashicons-menu" aria-hidden="true"></span>
									<span class="assoc-nav__toggle-label">Módulos</span>
									<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
								</summary>

								<div class="assoc-nav__list" role="tablist" aria-orientation="vertical" aria-label="Módulos da Área do Associado">
									<button type="button" class="assoc-nav__item" id="tab-visao-geral" role="tab" data-panel="visao-geral" aria-controls="panel-visao-geral" aria-selected="<?php echo esc_attr( setceb_panel_active_attr( 'visao-geral', $active_panel, 'aria' ) ); ?>" tabindex="<?php echo 'visao-geral' === $active_panel ? '0' : '-1'; ?>">
										<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
										<span class="assoc-nav__label">Visão geral</span>
									</button>
									<?php foreach ( $modulos as $key => $modulo ) : ?>
										<button type="button" class="assoc-nav__item" id="tab-<?php echo esc_attr( $key ); ?>" role="tab" data-panel="<?php echo esc_attr( $key ); ?>" aria-controls="panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo esc_attr( setceb_panel_active_attr( $key, $active_panel, 'aria' ) ); ?>" tabindex="<?php echo $key === $active_panel ? '0' : '-1'; ?>">
											<span class="dashicons <?php echo esc_attr( $modulo['icone'] ); ?>" aria-hidden="true"></span>
											<span class="assoc-nav__label"><?php echo esc_html( $modulo['rotulo'] ); ?></span>
										</button>
									<?php endforeach; ?>
								</div>
							</details>
						</nav>

					</aside>

					<div class="assoc-main">

						<noscript><style>.assoc-panel{display:block!important}</style></noscript>

						<div class="assoc-panels">

							<!-- VISAO GERAL -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'visao-geral', $active_panel, 'class' ) ) ); ?>" id="panel-visao-geral" role="tabpanel" aria-labelledby="tab-visao-geral" tabindex="0">
								<div class="assoc-quick">
									<?php $quick_primeiro = true; ?>
									<?php foreach ( $quick_cards as $quick_key => $quick ) : ?>
										<button type="button" class="assoc-quick__card<?php echo $quick_primeiro ? ' assoc-quick__card--pri' : ''; ?>" data-open-panel="<?php echo esc_attr( $quick['painel'] ); ?>">
											<span class="assoc-quick__icon"><span class="dashicons <?php echo esc_attr( $quick['icone'] ); ?>" aria-hidden="true"></span></span>
											<span class="assoc-quick__title"><?php echo esc_html( $quick['rotulo'] ); ?></span>
											<span class="assoc-quick__desc"><?php echo esc_html( $quick['desc'] ); ?></span>
										</button>
										<?php $quick_primeiro = false; ?>
									<?php endforeach; ?>
											<a class="assoc-quick__card assoc-quick__card--seg" href="<?php echo esc_url( $senha_url ); ?>">
												<span class="assoc-quick__icon"><span class="dashicons dashicons-lock" aria-hidden="true"></span></span>
												<span class="assoc-quick__title">Segurança da conta</span>
												<span class="assoc-quick__desc">Alterar a sua senha</span>
											</a>
								</div>

							</section>

							<!-- RECENTES -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'recentes', $active_panel, 'class' ) ) ); ?>" id="panel-recentes" role="tabpanel" aria-labelledby="tab-recentes" tabindex="0">
								<?php setceb_modulo_cabecalho( 'recentes', $modulos ); ?>

								<?php if ( ! empty( $recentes ) ) : ?>
									<ul class="assoc-list">
										<?php foreach ( $recentes as $item ) : ?>
											<li class="assoc-list__item">
												<span class="assoc-list__icon"><span class="dashicons <?php echo esc_attr( $item['icone'] ); ?>" aria-hidden="true"></span></span>
												<div class="assoc-list__info">
													<h3 class="assoc-list__title"><?php echo esc_html( $item['titulo'] ); ?></h3>
													<?php
													$rec_meta = array( $item['tipo_label'] );

													if ( '' !== $item['categoria'] ) {
														$rec_meta[] = $item['categoria'];
													}

													if ( '' !== $item['ano'] ) {
														$rec_meta[] = $item['ano'];
													}
													?>
													<span class="assoc-list__meta"><?php echo esc_html( implode( ' · ', $rec_meta ) ); ?></span>
												</div>
												<a class="assoc-list__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir
													<span class="dashicons dashicons-external" aria-hidden="true"></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<div class="assoc-empty">
										<span class="dashicons dashicons-megaphone assoc-empty__icon" aria-hidden="true"></span>
										<p>Nenhum conteúdo publicado no momento.</p>
									</div>
								<?php endif; ?>
							</section>

							<!-- PLANILHAS -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'planilhas', $active_panel, 'class' ) ) ); ?>" id="panel-planilhas" role="tabpanel" aria-labelledby="tab-planilhas" tabindex="0">
								<?php setceb_modulo_cabecalho( 'planilhas', $modulos, true ); ?>

								<?php if ( ! empty( $planilhas ) ) : ?>
									<div class="assoc-filters" role="group" aria-label="Filtrar planilhas por categoria">
										<label class="assoc-cat-select" for="assoc-cat-filter">
											<span class="assoc-cat-select__label">Categoria</span>
											<select id="assoc-cat-filter" name="assoc_cat_filter" data-categoria-select>
												<?php foreach ( $categorias_planilhas as $cat_slug => $cat_label ) : ?>
													<option value="<?php echo esc_attr( $cat_slug ); ?>"<?php echo $cat_slug === array_key_first( $categorias_planilhas ) ? ' selected' : ''; ?>><?php echo esc_html( $cat_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									</div>

									<ul class="assoc-list" data-planilhas-list data-per-page="10">
										<?php foreach ( $planilhas as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) ) {
												continue;
											}

											$plan_meta = array();

											if ( ! empty( $item['categoria'] ) ) {
												$plan_meta[] = isset( $categorias[ $item['categoria'] ] ) ? $categorias[ $item['categoria'] ] : $item['categoria'];
											}

											if ( ! empty( $item['ano'] ) ) {
												$plan_meta[] = $item['ano'];
											}
											?>
											<li class="assoc-list__item"<?php echo ! empty( $item['categoria'] ) ? ' data-categoria="' . esc_attr( $item['categoria'] ) . '"' : ''; ?><?php echo ! empty( $item['ano'] ) ? ' data-ano="' . esc_attr( $item['ano'] ) . '"' : ''; ?>>
												<span class="assoc-list__icon"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span></span>
												<div class="assoc-list__info">
													<h3 class="assoc-list__title"><?php echo esc_html( $item['titulo'] ); ?></h3>
													<?php if ( ! empty( $plan_meta ) ) : ?>
														<span class="assoc-list__meta"><?php echo esc_html( implode( ' · ', $plan_meta ) ); ?></span>
													<?php endif; ?>
												</div>
												<a class="assoc-list__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir
													<span class="dashicons dashicons-download" aria-hidden="true"></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>

									<nav class="assoc-pagination" data-planilhas-pagination aria-label="Paginação de planilhas" hidden></nav>

									<div class="assoc-empty" data-planilhas-empty hidden>
										<span class="dashicons dashicons-media-spreadsheet assoc-empty__icon" aria-hidden="true"></span>
										<p data-planilhas-empty-text>Nenhuma planilha disponível para o filtro selecionado.</p>
									</div>
								<?php else : ?>
									<div class="assoc-empty">
										<span class="dashicons dashicons-media-spreadsheet assoc-empty__icon" aria-hidden="true"></span>
										<p>Nenhuma planilha disponível no momento.</p>
									</div>
								<?php endif; ?>
							</section>

							<!-- RELATORIOS -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'relatorios', $active_panel, 'class' ) ) ); ?>" id="panel-relatorios" role="tabpanel" aria-labelledby="tab-relatorios" tabindex="0">
								<?php setceb_modulo_cabecalho( 'relatorios', $modulos, true ); ?>

								<?php if ( ! empty( $relatorios ) ) : ?>
									<ul class="assoc-list">
										<?php foreach ( $relatorios as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) ) {
												continue;
											}

											$doc_meta = array();

											if ( ! empty( $item['categoria'] ) ) {
												$doc_meta[] = isset( $categorias[ $item['categoria'] ] ) ? $categorias[ $item['categoria'] ] : $item['categoria'];
											}

											if ( ! empty( $item['ano'] ) ) {
												$doc_meta[] = $item['ano'];
											}
											?>
											<li class="assoc-list__item<?php echo ! empty( $item['destaque'] ) ? ' assoc-list__item--destaque' : ''; ?>"<?php echo ! empty( $item['categoria'] ) ? ' data-categoria="' . esc_attr( $item['categoria'] ) . '"' : ''; ?><?php echo ! empty( $item['ano'] ) ? ' data-ano="' . esc_attr( $item['ano'] ) . '"' : ''; ?>>
												<?php if ( ! empty( $item['destaque'] ) ) : ?>
													<span class="assoc-list__tag">Destaque</span>
												<?php endif; ?>
												<span class="assoc-list__icon"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span></span>
												<div class="assoc-list__info">
													<h3 class="assoc-list__title"><?php echo esc_html( $item['titulo'] ); ?></h3>
													<?php if ( ! empty( $doc_meta ) ) : ?>
														<span class="assoc-list__meta"><?php echo esc_html( implode( ' · ', $doc_meta ) ); ?></span>
													<?php endif; ?>
												</div>
												<a class="assoc-list__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir
													<span class="dashicons dashicons-external" aria-hidden="true"></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
									<div class="assoc-empty" data-relatorios-empty hidden>
										<span class="dashicons dashicons-chart-bar assoc-empty__icon" aria-hidden="true"></span>
										<p data-empty-text>Nenhum relatório disponível para o período selecionado.</p>
									</div>
								<?php else : ?>
									<div class="assoc-empty">
										<span class="dashicons dashicons-chart-bar assoc-empty__icon" aria-hidden="true"></span>
										<p>Nenhum relatório disponível para o período selecionado.</p>
									</div>
								<?php endif; ?>
							</section>

							<!-- CONVENCOES COLETIVAS -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'convencoes', $active_panel, 'class' ) ) ); ?>" id="panel-convencoes" role="tabpanel" aria-labelledby="tab-convencoes" tabindex="0">
								<?php setceb_modulo_cabecalho( 'convencoes', $modulos ); ?>

								<?php if ( ! empty( $convencoes ) ) : ?>
									<ul class="assoc-list">
										<?php foreach ( $convencoes as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) ) {
												continue;
											}
											?>
											<li class="assoc-list__item">
												<span class="assoc-list__icon"><span class="dashicons dashicons-media-document" aria-hidden="true"></span></span>
												<div class="assoc-list__info">
													<h3 class="assoc-list__title"><?php echo esc_html( $item['titulo'] ); ?></h3>
													<?php if ( ! empty( $item['descricao'] ) ) : ?>
														<span class="assoc-list__meta"><?php echo esc_html( $item['descricao'] ); ?></span>
													<?php endif; ?>
												</div>
												<a class="assoc-list__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir
													<span class="dashicons dashicons-external" aria-hidden="true"></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<div class="assoc-empty">
										<span class="dashicons dashicons-media-document assoc-empty__icon" aria-hidden="true"></span>
										<p>Nenhuma convenção coletiva publicada no momento.</p>
									</div>
								<?php endif; ?>
							</section>

							<!-- OUTROS MATERIAIS -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'outros-materiais', $active_panel, 'class' ) ) ); ?>" id="panel-outros-materiais" role="tabpanel" aria-labelledby="tab-outros-materiais" tabindex="0">
								<?php setceb_modulo_cabecalho( 'outros-materiais', $modulos ); ?>

								<?php if ( ! empty( $outros_materiais ) ) : ?>
									<ul class="assoc-list">
										<?php foreach ( $outros_materiais as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) ) {
												continue;
											}

											$om_meta = array();

											if ( ! empty( $item['categoria'] ) ) {
												$om_meta[] = isset( $categorias[ $item['categoria'] ] ) ? $categorias[ $item['categoria'] ] : $item['categoria'];
											}

											if ( ! empty( $item['ano'] ) ) {
												$om_meta[] = $item['ano'];
											}
											?>
											<li class="assoc-list__item">
												<span class="assoc-list__icon"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span></span>
												<div class="assoc-list__info">
													<h3 class="assoc-list__title"><?php echo esc_html( $item['titulo'] ); ?></h3>
													<?php if ( ! empty( $om_meta ) ) : ?>
														<span class="assoc-list__meta"><?php echo esc_html( implode( ' · ', $om_meta ) ); ?></span>
													<?php endif; ?>
												</div>
												<a class="assoc-list__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir
													<span class="dashicons dashicons-download" aria-hidden="true"></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<div class="assoc-empty">
										<span class="dashicons dashicons-portfolio assoc-empty__icon" aria-hidden="true"></span>
										<p>Nenhum material disponível no momento.</p>
									</div>
								<?php endif; ?>
							</section>

							<!-- SIMULADORES -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'simuladores', $active_panel, 'class' ) ) ); ?>" id="panel-simuladores" role="tabpanel" aria-labelledby="tab-simuladores" tabindex="0">
								<?php setceb_modulo_cabecalho( 'simuladores', $modulos ); ?>

								<?php if ( ! empty( $simuladores ) ) : ?>
									<ul class="assoc-list">
										<?php foreach ( $simuladores as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) ) {
												continue;
											}

											$sim_meta = array();

											if ( ! empty( $item['descricao'] ) ) {
												$sim_meta[] = $item['descricao'];
											}

											if ( ! empty( $item['ano'] ) ) {
												$sim_meta[] = $item['ano'];
											}
											?>
											<li class="assoc-list__item">
												<span class="assoc-list__icon"><span class="dashicons dashicons-performance" aria-hidden="true"></span></span>
												<div class="assoc-list__info">
													<h3 class="assoc-list__title"><?php echo esc_html( $item['titulo'] ); ?></h3>
													<?php if ( ! empty( $sim_meta ) ) : ?>
														<span class="assoc-list__meta"><?php echo esc_html( implode( ' · ', $sim_meta ) ); ?></span>
													<?php endif; ?>
												</div>
												<a class="assoc-list__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir
													<span class="dashicons dashicons-external" aria-hidden="true"></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<div class="assoc-empty">
										<span class="dashicons dashicons-performance assoc-empty__icon" aria-hidden="true"></span>
										<p>Nenhum simulador disponível no momento.</p>
									</div>
								<?php endif; ?>
							</section>

							<!-- JURIDICO -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'juridico', $active_panel, 'class' ) ) ); ?>" id="panel-juridico" role="tabpanel" aria-labelledby="tab-juridico" tabindex="0">
								<?php setceb_modulo_cabecalho( 'juridico', $modulos ); ?>

								<?php if ( $notice && 'juridico' === $notice['forma'] ) : ?>
									<div class="assoc-notice assoc-notice--<?php echo esc_attr( $notice['tipo'] ); ?>" role="<?php echo 'sucesso' === $notice['tipo'] ? 'status' : 'alert'; ?>">
										<span class="dashicons <?php echo 'sucesso' === $notice['tipo'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
										<p><?php echo esc_html( $notice['texto'] ); ?></p>
									</div>
								<?php endif; ?>

								<div class="assoc-sol<?php echo $sol_lista_aberta ? ' is-list' : ''; ?>">

									<aside class="assoc-sol__side">
										<div class="assoc-sol__side-head">
											<h3 class="assoc-sol__side-title">Solicitações</h3>
											<span class="assoc-sol__count"><?php echo esc_html( $sol_total . ' ' . ( 1 === $sol_total ? 'protocolo' : 'protocolos' ) ); ?></span>
										</div>

										<a class="assoc-sol__nova-btn<?php echo $sol_nova_ativa ? ' is-active' : ''; ?>"
											id="sol-nova-btn"
											href="<?php echo esc_url( add_query_arg( 'sol_nova', 1, $perfil_url ) . '#panel-juridico' ); ?>"
											data-sol-nova
											aria-current="<?php echo $sol_nova_ativa ? 'true' : 'false'; ?>">
											<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
											<span class="assoc-sol__nova-label">Nova solicitação</span>
										</a>

										<?php if ( empty( $minhas_solicitacoes ) ) : ?>
											<div class="assoc-sol__sem-conversas">
												<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
												<p>Voce ainda nao tem nenhuma solicitacao juridica.</p>
												<p class="assoc-sol__sem-conversas-dica">Use o botao <strong>Nova solicitacao</strong> acima para abrir um atendimento com a Assessoria Juridica.</p>
											</div>
										<?php else : ?>
											<ul class="assoc-sol__list">
												<?php foreach ( $minhas_solicitacoes as $sol ) : ?>
													<?php $sol_esta_ativa = ( (int) $sol['id'] === $sol_ativa ) && ! $sol_nova_ativa; ?>
													<li>
														<a class="assoc-sol__link<?php echo $sol_esta_ativa ? ' is-active' : ''; ?>"
															id="sol-tab-<?php echo (int) $sol['id']; ?>"
															href="<?php echo esc_url( add_query_arg( 'sol_id', (int) $sol['id'], $perfil_url ) . '#panel-juridico' ); ?>"
															data-sol-link="<?php echo (int) $sol['id']; ?>"
															aria-current="<?php echo $sol_esta_ativa ? 'true' : 'false'; ?>">
															<span class="assoc-sol__link-icon" style="--setceb-sol-cor:<?php echo esc_attr( $sol['status_cor'] ); ?>">
																<span class="dashicons <?php echo esc_attr( $sol['status_icone'] ); ?>" aria-hidden="true"></span>
															</span>
															<span class="assoc-sol__link-main">
																<span class="assoc-sol__link-title"><?php echo esc_html( $sol['titulo'] ); ?></span>
																<span class="assoc-sol__link-foot">
																	<span class="assoc-sol__link-meta">
																		Protocolo <?php echo esc_html( $sol['protocolo'] ); ?>
																		<?php if ( '' !== setceb_sol_tempo_relativo( $sol['atualizada'] ) ) : ?>
																			<span class="assoc-sol__link-tempo">&middot; atualizada <?php echo esc_html( setceb_sol_tempo_relativo( $sol['atualizada'] ) ); ?></span>
																		<?php endif; ?>
																	</span>
																	<?php /* O badge fica NA LINHA DO PROTOCOLO, e nao como terceira
																		   coluna nem em linha propria: como coluna ele rouba a
																		   largura do titulo (medido: 135px, titulo em duas
																		   linhas) e o cartao nao encolhe. Aqui ele divide
																		   altura com o protocolo e some da conta. */ ?>
																	<span class="assoc-badge" style="--setceb-sol-cor:<?php echo esc_attr( $sol['status_cor'] ); ?>">
																		<span class="dashicons <?php echo esc_attr( $sol['status_icone'] ); ?>" aria-hidden="true"></span>
																		<span class="assoc-badge__full"><?php echo esc_html( $sol['status_rotulo'] ); ?></span>
																		<span class="assoc-badge__curto"><?php echo esc_html( $sol['status_rotulo_curto'] ); ?></span>
																	</span>
																</span>
															</span>
														</a>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</aside>

									<div class="assoc-sol__panes" aria-live="polite">

										<!-- Nova solicitacao: o formulario abre na coluna da conversa -->
										<article class="assoc-sol__pane assoc-sol__pane--nova" id="sol-pane-nova" data-sol-pane="nova" aria-labelledby="sol-nova-btn"<?php echo $sol_nova_ativa ? '' : ' hidden'; ?>>
											<header class="assoc-sol__pane-head">
												<?php echo setceb_sol_botao_voltar( $perfil_url ); ?>
											</header>

<?php /* Formulario unico, sem passos.

	   O assunto e os dados foram numero de passos por um tempo, com um
	   "Continuar" que so existia com JS. Sem JS os dois passos apareciam
	   juntos e o botao nao fazia nada - e o JS nao carregava em parte dos
	   ambiente. Um formulario so nao depende de script para existir: o
	   servidor valida, o navegador mostra os campos obrigatorios, e o
	   espaco em tela e o de quatro campos. */ ?>
<noscript>
	<style>
		/* A troca lista <-> conversa e feita pela classe is-list, que vem
		   do PHP. Sem JS nao ha como trocar a classe, entao a conversa
		   ficaria oculta para sempre e o associado nao conseguiria nem ler
		   o que escreveu, nem responder. O painel ativo e o unico sem o
		   atributo hidden, entao mostrar o bloco inteiro mostra so ele. */
		.assoc-sol.is-list > .assoc-sol__panes { display: block !important; }
	</style>
</noscript>
<form class="assoc-form assoc-form--nova" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-assoc-form>
														<input type="hidden" name="action" value="setceb_juridico">
														<?php wp_nonce_field( 'setceb_form_juridico' ); ?>

														<p class="assoc-hp" aria-hidden="true">
															<label>Site<input type="text" name="setceb_website" tabindex="-1" autocomplete="off"></label>
														</p>

														<input type="hidden" name="setceb_nome" value="<?php echo esc_attr( $user_name ); ?>">
														<input type="hidden" name="setceb_email" value="<?php echo esc_attr( $user_email ); ?>">

														<?php /* A lista vem de setceb_sol_categorias(): e a mesma
														   funcao que o servidor usa para validar, entao um
														   array solto no template nao pode divergir do que o
														   servidor aceita. */ ?>
														<div class="assoc-form__grid">
															<p class="assoc-form__field assoc-form__field--largo">
																<label for="assoc-jur-categoria">Assunto <span aria-hidden="true">*</span></label>
																<select id="assoc-jur-categoria" name="setceb_categoria" required>
																	<option value="">Selecione um assunto</option>
																	<?php foreach ( $sol_categorias as $sol_cat_slug => $sol_cat_rotulo ) : ?>
																		<option value="<?php echo esc_attr( $sol_cat_slug ); ?>"><?php echo esc_html( $sol_cat_rotulo ); ?></option>
																	<?php endforeach; ?>
																</select>
															</p>

															<?php /* Telefone e empresa lado a lado quando ha largura.
															   No celular viram uma coluna, como antes. */ ?>
															<p class="assoc-form__field">
																<label for="assoc-jur-fone">Telefone</label>
																<input type="tel" id="assoc-jur-fone" name="setceb_telefone" autocomplete="tel" inputmode="tel" data-mask-phone maxlength="18" placeholder="(00) 00000-0000">
															</p>
															<p class="assoc-form__field">
																<label for="assoc-jur-empresa">Empresa</label>
																<input type="text" id="assoc-jur-empresa" name="setceb_empresa" autocomplete="organization">
															</p>

<p class="assoc-form__field assoc-form__field--largo">
																<label for="assoc-jur-msg">Descreva sua dúvida <span aria-hidden="true">*</span></label>
																<textarea id="assoc-jur-msg" name="setceb_mensagem" rows="4" required placeholder="Explique o que aconteceu. Se possível, informe datas e os documentos envolvidos."></textarea>
															</p>
														</div>

														<div class="assoc-form__actions">
															<button type="submit" class="assoc-btn assoc-btn--full">
																<span class="assoc-btn__label">Registrar solicitação</span>
															</button>
																													</div>
												</form>
										</article>

										<?php foreach ( $minhas_solicitacoes as $sol ) : ?>
											<?php $sol_esta_ativa = ( (int) $sol['id'] === $sol_ativa ) && ! $sol_nova_ativa; ?>
											<article class="assoc-sol__pane" id="sol-pane-<?php echo (int) $sol['id']; ?>" data-sol-pane="<?php echo (int) $sol['id']; ?>" aria-labelledby="sol-tab-<?php echo (int) $sol['id']; ?>"<?php echo $sol_esta_ativa ? '' : ' hidden'; ?>>
													<header class="assoc-sol__pane-head">
														<?php echo setceb_sol_botao_voltar( $perfil_url ); ?>
													</header>

													<div class="assoc-thread">
														<div class="assoc-thread__msg assoc-thread__msg--assoc">
															<div class="assoc-thread__who">
																<strong><?php echo esc_html( '' !== $sol['nome'] ? $sol['nome'] : $user_name ); ?></strong>
																<span class="assoc-thread__role">Associado &middot; <?php echo esc_html( setceb_sol_data( $sol['data'] ) ); ?></span>
															</div>
															<div class="assoc-thread__bubble">
																<p><?php echo nl2br( esc_html( $sol['mensagem'] ) ); ?></p>
															</div>
														</div>

														<?php foreach ( $sol['historico'] as $registro ) : ?>
															<?php if ( 'resposta' === $registro['tipo'] || 'mensagem' === $registro['tipo'] ) : ?>
																<?php $registro_assoc = ( 'mensagem' === $registro['tipo'] ); ?>
																<div class="assoc-thread__msg assoc-thread__msg--<?php echo $registro_assoc ? 'assoc' : 'equipe'; ?>">
																	<div class="assoc-thread__who">
																		<strong><?php echo esc_html( $registro['autor'] ); ?></strong>
																		<span class="assoc-thread__role">
																			<?php echo $registro_assoc ? 'Associado' : 'Assessoria Jurídica'; ?>
																			<?php if ( '' !== $registro['data_txt'] ) : ?>
																				&middot; <?php echo esc_html( $registro['data_txt'] ); ?>
																			<?php endif; ?>
																		</span>
																	</div>
																	<div class="assoc-thread__bubble">
																		<p><?php echo nl2br( esc_html( $registro['texto'] ) ); ?></p>
																	</div>
																</div>
															<?php else : ?>
																<p class="assoc-thread__event">
																	<span class="dashicons <?php echo esc_attr( $registro['icone'] ); ?>" aria-hidden="true"></span>
																	<?php echo esc_html( $registro['rotulo'] ); ?>
																	<?php if ( '' !== $registro['data_txt'] ) : ?>
																		&middot; <?php echo esc_html( $registro['data_txt'] ); ?>
																	<?php endif; ?>
																</p>
															<?php endif; ?>
														<?php endforeach; ?>
													</div>

													<?php if ( $sol['mensagens'] ) : ?>
														<form class="assoc-form assoc-form--resposta" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-assoc-form>
															<input type="hidden" name="action" value="setceb_juridico_mensagem">
															<input type="hidden" name="setceb_sol_id" value="<?php echo (int) $sol['id']; ?>">
															<?php wp_nonce_field( 'setceb_juridico_mensagem_' . (int) $sol['id'] ); ?>

															<p class="assoc-hp" aria-hidden="true">
																<label>Site<input type="text" name="setceb_website" tabindex="-1" autocomplete="off"></label>
															</p>

															<p class="assoc-form__field">
																<label for="assoc-sol-msg-<?php echo (int) $sol['id']; ?>">Responder à solicitação <?php echo esc_html( $sol['protocolo'] ); ?> <span aria-hidden="true">*</span></label>
																<textarea id="assoc-sol-msg-<?php echo (int) $sol['id']; ?>" name="setceb_mensagem" rows="3" required placeholder="Escreva sua resposta para a Assessoria Jurídica…"></textarea>
															</p>

															<button type="submit" class="assoc-btn">
																<span class="assoc-btn__label">Enviar mensagem</span>
																<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
															</button>
														</form>
													<?php else : ?>
														<p class="assoc-sol__encerrada">
															<span class="dashicons dashicons-lock" aria-hidden="true"></span>
															Este atendimento está <?php echo esc_html( mb_strtolower( $sol['status_rotulo'] ) ); ?>. Abra uma nova solicitação se precisar de mais um atendimento.
														</p>
													<?php endif; ?>
												</article>
											<?php endforeach; ?>
										</div>

								</div>
							</section>

							<!-- FINANCEIRO -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'financeiro', $active_panel, 'class' ) ) ); ?>" id="panel-financeiro" role="tabpanel" aria-labelledby="tab-financeiro" tabindex="0">
								<?php setceb_modulo_cabecalho( 'financeiro', $modulos ); ?>

								<div class="setceb-perfil__boletos">
									<?php foreach ( $boletos as $boleto ) : ?>
										<a class="setceb-perfil__boleto"
											href="<?php echo esc_url( $boleto['url'] ); ?>"
											target="_blank" rel="noopener noreferrer">
											<span class="setceb-perfil__boleto-icon"><span class="dashicons <?php echo esc_attr( isset( $boleto_icons[ $boleto['icon'] ] ) ? $boleto_icons[ $boleto['icon'] ] : 'dashicons-media-document' ); ?>" aria-hidden="true"></span></span>
											<span class="setceb-perfil__boleto-label"><?php echo esc_html( $boleto['label'] ); ?></span>
											<span class="setceb-perfil__boleto-cta">Emitir boleto</span>
										</a>
									<?php endforeach; ?>
								</div>
							</section>

							<!-- FALE CONOSCO -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'fale-conosco', $active_panel, 'class' ) ) ); ?>" id="panel-fale-conosco" role="tabpanel" aria-labelledby="tab-fale-conosco" tabindex="0">
								<?php setceb_modulo_cabecalho( 'fale-conosco', $modulos ); ?>

								<?php if ( $notice && 'fale-conosco' === $notice['forma'] ) : ?>
									<div class="assoc-notice assoc-notice--<?php echo esc_attr( $notice['tipo'] ); ?>" role="<?php echo 'sucesso' === $notice['tipo'] ? 'status' : 'alert'; ?>">
										<span class="dashicons <?php echo 'sucesso' === $notice['tipo'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
										<p><?php echo esc_html( $notice['texto'] ); ?></p>
									</div>
								<?php endif; ?>

								<form class="assoc-form assoc-form--card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-assoc-form>
									<input type="hidden" name="action" value="setceb_fale_conosco">
									<?php wp_nonce_field( 'setceb_form_fale-conosco' ); ?>

									<p class="assoc-hp" aria-hidden="true">
										<label>Site<input type="text" name="setceb_website" tabindex="-1" autocomplete="off"></label>
									</p>

									<p class="assoc-form__field">
										<label for="assoc-fal-assunto">Assunto <span aria-hidden="true">*</span></label>
										<select id="assoc-fal-assunto" name="setceb_assunto" required>
											<option value="">Selecione o assunto…</option>
											<?php foreach ( $assuntos as $assunto_slug => $assunto_label ) : ?>
												<option value="<?php echo esc_attr( $assunto_slug ); ?>"><?php echo esc_html( $assunto_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</p>

									<div class="assoc-form__row">
										<p class="assoc-form__field">
											<label for="assoc-fal-nome">Nome completo <span aria-hidden="true">*</span></label>
											<input type="text" id="assoc-fal-nome" name="setceb_nome" value="<?php echo esc_attr( $user_name ); ?>" required autocomplete="name">
										</p>
										<p class="assoc-form__field">
											<label for="assoc-fal-email">E-mail <span aria-hidden="true">*</span></label>
											<input type="email" id="assoc-fal-email" name="setceb_email" value="<?php echo esc_attr( $user_email ); ?>" required autocomplete="email">
										</p>
									</div>

									<p class="assoc-form__field">
										<label for="assoc-fal-msg">Sua mensagem <span aria-hidden="true">*</span></label>
										<textarea id="assoc-fal-msg" name="setceb_mensagem" rows="5" required placeholder="Escreva sua dúvida, sugestão ou solicitação..."></textarea>
									</p>

									<button type="submit" class="assoc-btn">
										<span class="assoc-btn__label">Enviar mensagem</span>
									</button>
									<p class="assoc-form__note">Campos com <span aria-hidden="true">*</span> são obrigatórios.</p>
								</form>
							</section>

						</div><!-- /.assoc-panels -->

					</div><!-- /.assoc-main -->

				</div><!-- /.assoc-shell -->

			<?php endif; ?>
		</div>
	</main>
</div>

<?php get_footer(); ?>
