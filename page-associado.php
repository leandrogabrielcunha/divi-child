<?php
/**
 * Template Name: Perfil do Associado
 *
 * Area do associado, fora do painel do WordPress. E o destino do login
 * dos associados e concentra os servicos: emissao de boletos, planilhas,
 * relatorios, convencoes coletivas, juridico e fale conosco.
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
$relatorios   = setceb_relatorios();
$convencoes   = setceb_convencoes();
$boletos      = setceb_boletos();
$assuntos     = setceb_contato_assuntos();
$notice       = setceb_associado_form_notice();
$active_panel = $notice ? $notice['forma'] : 'planilhas';

$atalhos = array(
	'planilhas'    => 'Planilhas',
	'relatorios'   => 'Relatórios',
	'convencoes'   => 'Convenções Coletivas',
	'juridico'     => 'Jurídico',
	'financeiro'   => 'Financeiro',
	'fale-conosco' => 'Fale Conosco',
);

$boleto_icons = array(
	'calendar' => 'dashicons-calendar-alt',
	'heart'    => 'dashicons-heart',
	'users'    => 'dashicons-groups',
);

$user_name  = trim( $user->first_name . ' ' . $user->last_name );
$user_name  = '' !== trim( $user_name ) ? $user_name : $user->display_name;
$user_email = $user->user_email;

/**
 * Painel ativo por padrao (classe/aria dos botoes e secoes).
 */
function setceb_panel_active_attr( $panel, $active_panel, $attr ) {
	if ( 'class' === $attr ) {
		return $panel === $active_panel ? ' assoc-panel is-active' : ' assoc-panel';
	}

	return $panel === $active_panel ? 'true' : 'false';
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

				<div class="assoc-layout">

					<!-- Menu lateral de categorias -->
					<aside class="assoc-side">
						<button type="button" class="assoc-side__toggle" aria-expanded="false" aria-controls="assoc-cats">
							<span class="dashicons dashicons-menu-alt3" aria-hidden="true"></span>
							Categorias
							<span class="dashicons dashicons-arrow-down-alt2 assoc-side__chevron" aria-hidden="true"></span>
						</button>
						<nav id="assoc-cats" class="assoc-cats" aria-label="Categorias de documentos">
							<ul class="assoc-cats__list">
								<?php foreach ( $categorias as $cat_slug => $cat_label ) : ?>
									<li>
										<button type="button" class="assoc-cats__item" data-categoria="<?php echo esc_attr( $cat_slug ); ?>" aria-pressed="false"><?php echo esc_html( $cat_label ); ?></button>
									</li>
								<?php endforeach; ?>
							</ul>
						</nav>
					</aside>

					<div class="assoc-main">

						<!-- Seletor de ano + emissao de boletos -->
						<div class="assoc-toolbar">
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

							<button type="button" class="assoc-btn assoc-btn--boletos" data-open-panel="financeiro">
								Emissão de Boletos
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</button>
						</div>

						<!-- Atalhos -->
						<div class="assoc-shortcuts" role="tablist" aria-label="Atalhos da área do associado">
							<?php foreach ( $atalhos as $key => $label ) : ?>
								<button type="button" class="assoc-tab" id="tab-<?php echo esc_attr( $key ); ?>" role="tab" data-panel="<?php echo esc_attr( $key ); ?>" aria-controls="panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo esc_attr( setceb_panel_active_attr( $key, $active_panel, 'aria' ) ); ?>" tabindex="<?php echo $key === $active_panel ? '0' : '-1'; ?>"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>

						<noscript><style>.assoc-panel{display:block!important}</style></noscript>

						<div class="assoc-panels">

							<!-- PLANILHAS -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'planilhas', $active_panel, 'class' ) ) ); ?>" id="panel-planilhas" role="tabpanel" aria-labelledby="tab-planilhas" tabindex="0">
								<h2 class="assoc-panel__title">Planilhas</h2>
								<p class="assoc-panel__intro">Arquivos e ferramentas de apoio disponibilizados pela entidade.</p>

								<?php if ( ! empty( $planilhas ) ) : ?>
									<div class="assoc-docs">
										<?php foreach ( $planilhas as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) ) {
												continue;
											}
											?>
											<article class="assoc-doc"<?php echo isset( $item['ano'] ) ? ' data-ano="' . esc_attr( $item['ano'] ) . '"' : ''; ?>>
												<span class="dashicons dashicons-media-spreadsheet assoc-doc__icon" aria-hidden="true"></span>
												<h3 class="assoc-doc__titulo"><?php echo esc_html( $item['titulo'] ); ?></h3>
												<?php if ( ! empty( $item['descricao'] ) ) : ?>
													<p class="assoc-doc__meta"><?php echo esc_html( $item['descricao'] ); ?></p>
												<?php endif; ?>
												<a class="assoc-doc__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir documento
													<span class="dashicons dashicons-download" aria-hidden="true"></span>
												</a>
											</article>
										<?php endforeach; ?>
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
								<h2 class="assoc-panel__title">Relatórios</h2>
								<p class="assoc-panel__intro">Relatórios mensais por categoria de transporte. Use as categorias do menu lateral e o seletor de ano para filtrar.</p>

								<div class="assoc-filter" data-filter hidden>
									<span>Categoria: <strong data-filter-label></strong></span>
									<button type="button" class="assoc-filter__clear" data-filter-clear>
										<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
										Remover filtro
									</button>
								</div>

								<?php if ( ! empty( $relatorios ) ) : ?>
									<div class="assoc-docs">
										<?php foreach ( $relatorios as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) || empty( $item['categoria'] ) || empty( $item['ano'] ) ) {
												continue;
											}
											$doc_categoria = isset( $categorias[ $item['categoria'] ] ) ? $categorias[ $item['categoria'] ] : $item['categoria'];
											?>
											<article class="assoc-doc<?php echo ! empty( $item['destaque'] ) ? ' assoc-doc--destaque' : ''; ?>" data-categoria="<?php echo esc_attr( $item['categoria'] ); ?>" data-ano="<?php echo esc_attr( $item['ano'] ); ?>">
												<?php if ( ! empty( $item['destaque'] ) ) : ?>
													<span class="assoc-doc__tag">Destaque</span>
												<?php endif; ?>
												<span class="dashicons dashicons-chart-bar assoc-doc__icon" aria-hidden="true"></span>
												<h3 class="assoc-doc__titulo"><?php echo esc_html( $item['titulo'] ); ?></h3>
												<p class="assoc-doc__meta"><?php echo esc_html( $doc_categoria . ' · ' . $item['ano'] ); ?></p>
												<a class="assoc-doc__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir documento
													<span class="dashicons dashicons-external" aria-hidden="true"></span>
												</a>
											</article>
										<?php endforeach; ?>
									</div>
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
								<h2 class="assoc-panel__title">Convenções Coletivas</h2>
								<p class="assoc-panel__intro">Documentos oficiais das convenções coletivas da categoria.</p>

								<?php if ( ! empty( $convencoes ) ) : ?>
									<div class="assoc-docs">
										<?php foreach ( $convencoes as $item ) : ?>
											<?php
											if ( empty( $item['titulo'] ) || empty( $item['url'] ) ) {
												continue;
											}
											?>
											<article class="assoc-doc"<?php echo isset( $item['ano'] ) ? ' data-ano="' . esc_attr( $item['ano'] ) . '"' : ''; ?>>
												<span class="dashicons dashicons-media-document assoc-doc__icon" aria-hidden="true"></span>
												<h3 class="assoc-doc__titulo"><?php echo esc_html( $item['titulo'] ); ?></h3>
												<?php if ( ! empty( $item['descricao'] ) ) : ?>
													<p class="assoc-doc__meta"><?php echo esc_html( $item['descricao'] ); ?></p>
												<?php endif; ?>
												<a class="assoc-doc__link" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													Abrir documento
													<span class="dashicons dashicons-external" aria-hidden="true"></span>
												</a>
											</article>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div class="assoc-empty">
										<span class="dashicons dashicons-media-document assoc-empty__icon" aria-hidden="true"></span>
										<p>Nenhuma convenção coletiva publicada no momento.</p>
									</div>
								<?php endif; ?>
							</section>

							<!-- JURIDICO -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'juridico', $active_panel, 'class' ) ) ); ?>" id="panel-juridico" role="tabpanel" aria-labelledby="tab-juridico" tabindex="0">
								<h2 class="assoc-panel__title">Jurídico</h2>

								<div class="assoc-callout">
									<span class="dashicons dashicons-book assoc-callout__icon" aria-hidden="true"></span>
									<div>
										<h3>Assessoria Jurídica</h3>
										<p>Envie sua dúvida para o assessor jurídico da entidade. A resposta será encaminhada para o e-mail informado abaixo.</p>
									</div>
								</div>

								<?php if ( $notice && 'juridico' === $notice['forma'] ) : ?>
									<div class="assoc-notice assoc-notice--<?php echo esc_attr( $notice['tipo'] ); ?>" role="<?php echo 'sucesso' === $notice['tipo'] ? 'status' : 'alert'; ?>">
										<span class="dashicons <?php echo 'sucesso' === $notice['tipo'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
										<p><?php echo esc_html( $notice['texto'] ); ?></p>
									</div>
								<?php endif; ?>

								<form class="assoc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-assoc-form>
									<input type="hidden" name="action" value="setceb_juridico">
									<?php wp_nonce_field( 'setceb_form_juridico' ); ?>

									<p class="assoc-hp" aria-hidden="true">
										<label>Site<input type="text" name="setceb_website" tabindex="-1" autocomplete="off"></label>
									</p>

									<div class="assoc-form__row">
										<p class="assoc-form__field">
											<label for="assoc-jur-nome">Nome completo <span aria-hidden="true">*</span></label>
											<input type="text" id="assoc-jur-nome" name="setceb_nome" value="<?php echo esc_attr( $user_name ); ?>" required autocomplete="name">
										</p>
										<p class="assoc-form__field">
											<label for="assoc-jur-email">E-mail <span aria-hidden="true">*</span></label>
											<input type="email" id="assoc-jur-email" name="setceb_email" value="<?php echo esc_attr( $user_email ); ?>" required autocomplete="email">
										</p>
									</div>

									<div class="assoc-form__row">
										<p class="assoc-form__field">
											<label for="assoc-jur-fone">Telefone</label>
											<input type="tel" id="assoc-jur-fone" name="setceb_telefone" autocomplete="tel">
										</p>
										<p class="assoc-form__field">
											<label for="assoc-jur-empresa">Empresa</label>
											<input type="text" id="assoc-jur-empresa" name="setceb_empresa" autocomplete="organization">
										</p>
									</div>

									<p class="assoc-form__field">
										<label for="assoc-jur-msg">Sua dúvida <span aria-hidden="true">*</span></label>
										<textarea id="assoc-jur-msg" name="setceb_mensagem" rows="5" required placeholder="Descreva sua dúvida para o assessor jurídico..."></textarea>
									</p>

									<button type="submit" class="assoc-btn">
										<span class="assoc-btn__label">Enviar dúvida ao jurídico</span>
									</button>
									<p class="assoc-form__note">Campos com <span aria-hidden="true">*</span> são obrigatórios.</p>
								</form>
							</section>

							<!-- FINANCEIRO -->
							<section class="<?php echo esc_attr( trim( setceb_panel_active_attr( 'financeiro', $active_panel, 'class' ) ) ); ?>" id="panel-financeiro" role="tabpanel" aria-labelledby="tab-financeiro" tabindex="0">
								<h2 class="assoc-panel__title">Financeiro</h2>
								<p class="assoc-panel__intro">Geração de boletos e recursos financeiros do associado.</p>

								<h3 class="assoc-panel__subtitle">Geração de boletos</h3>
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
								<h2 class="assoc-panel__title">Fale Conosco</h2>

								<div class="assoc-callout">
									<span class="dashicons dashicons-email-alt assoc-callout__icon" aria-hidden="true"></span>
									<div>
										<h3>Como podemos ajudar?</h3>
										<p>Escolha o assunto, informe sua dúvida e envie sua mensagem. Nossa equipe responderá o mais breve possível.</p>
									</div>
								</div>

								<?php if ( $notice && 'fale-conosco' === $notice['forma'] ) : ?>
									<div class="assoc-notice assoc-notice--<?php echo esc_attr( $notice['tipo'] ); ?>" role="<?php echo 'sucesso' === $notice['tipo'] ? 'status' : 'alert'; ?>">
										<span class="dashicons <?php echo 'sucesso' === $notice['tipo'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
										<p><?php echo esc_html( $notice['texto'] ); ?></p>
									</div>
								<?php endif; ?>

								<form class="assoc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-assoc-form>
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
				</div><!-- /.assoc-layout -->

			<?php endif; ?>
		</div>
	</main>
</div>

<?php get_footer(); ?>
