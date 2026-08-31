<?php
/**
 * SETCEB - Formulario de Contato (Shortcode)
 *
 * Formulario de contato geral do site, disponibilizado via shortcode
 * [formulario_contato_setceb].
 *
 * Envio feito pelo proprio WordPress (wp_mail), reutilizando o SMTP
 * ja configurado na plataforma. Nenhum SMTP manual e configurado aqui.
 *
 * Arquitetura:
 * - Shortcode registrado em render_shortcode()
 * - Envio via admin-post.php (fallback) + AJAX (experiencia sem reload)
 * - CSS em style.css (.setceb-contact-form)
 * - JS em contato.js (mascaras, contador de caracteres, submit AJAX)
 *
 * Carregado em functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * Destinatario dos formularios de contato
 * ------------------------------------------------------------ */
function setceb_contato_recipient() {
	return apply_filters( 'setceb_contato_recipient', 'administrativo@setceb.com.br' );
}

/* ------------------------------------------------------------
 * Departamentos disponiveis no select
 * ------------------------------------------------------------ */
function setceb_contato_departamentos() {
	$departamentos = array(
		'acao-judicial-multas-nic' => 'Ação Judicial para Multas NIC',
	);

	return apply_filters( 'setceb_contato_departamentos', $departamentos );
}

/* ------------------------------------------------------------
 * Tipos de envio ("Quero enviar um(a)")
 * ------------------------------------------------------------ */
function setceb_contato_tipos() {
	$tipos = array(
		'duvida' => 'Dúvida',
	);

	return apply_filters( 'setceb_contato_tipos', $tipos );
}

/* ------------------------------------------------------------
 * Assets (carregados somente quando o shortcode estiver presente)
 * ------------------------------------------------------------ */

/**
 * Registra os assets do formulario.
 */
function setceb_contato_register_assets() {
	$theme = wp_get_theme();

	wp_register_script(
		'setceb-contato',
		get_stylesheet_directory_uri() . '/contato.js',
		array(),
		$theme->get( 'Version' ),
		true
	);

	wp_localize_script(
		'setceb-contato',
		'SETCEB_CONTATO',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'setceb_contact_submit' ),
			'messages' => array(
				'success' => 'Mensagem enviada com sucesso! Em breve entraremos em contato.',
				'error'   => 'Não foi possível enviar sua mensagem. Tente novamente.',
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'setceb_contato_register_assets' );

/**
 * Detecta se o shortcode esta presente no conteudo atual e,
 * em caso positivo, enfileira os assets do formulario.
 *
 * Da suporte a paginas, posts, Gutenberg e blocos que renderizam
 * shortcodes. Alem disso, mantem o enfileiramento via shortcode
 * (que cobre os casos onde o conteudo nao passa no pre-parse).
 *
 * @param WP_Post $post Post atual (quando disponivel).
 */
function setceb_contato_maybe_enqueue( $post ) {
	if ( ! $post || ! isset( $post->post_content ) ) {
		return;
	}

	if ( has_shortcode( $post->post_content, 'formulario_contato_setceb' ) ) {
		setceb_contato_enqueue();
	}
}
add_action( 'the_post', 'setceb_contato_maybe_enqueue' );

/**
 * Enfileira de fato os assets do formulario.
 */
function setceb_contato_enqueue() {
	wp_enqueue_script( 'setceb-contato' );
	wp_enqueue_style( 'dashicons' );
}

/* ------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------ */

/**
 * Renderiza o formulario de contato.
 *
 * @param array|string $atts Atributos do shortcode.
 * @return string HTML do formulario.
 */
function setceb_contato_render_shortcode( $atts = array() ) {
	$atts = shortcode_atts( array(), $atts, 'formulario_contato_setceb' );

	// Enfileira os assets (seguro chamar aqui; o pre-parse tambem cobre).
	setceb_contato_enqueue();

	$departamentos = setceb_contato_departamentos();
	$tipos         = setceb_contato_tipos();

	ob_start();
	?>
	<div class="setceb-contact-form" data-setceb-contact>
		<form class="setceb-contact-form__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="setceb_contato">
			<?php wp_nonce_field( 'setceb_contact_submit', 'setceb_contato_nonce' ); ?>

			<!-- Honeypot -->
			<p class="setceb-contact-form__hp" aria-hidden="true">
				<label>Site<input type="text" name="setceb_website" tabindex="-1" autocomplete="off"></label>
			</p>

			<div class="setceb-contact-form__row setceb-contact-form__row--3">
				<div class="setceb-contact-form__field">
					<label for="setceb-cf-nome">Nome <span class="setceb-contact-form__req" aria-hidden="true">*</span></label>
					<input type="text" id="setceb-cf-nome" name="setceb_nome" required autocomplete="name" maxlength="120">
				</div>
				<div class="setceb-contact-form__field">
					<label for="setceb-cf-telefone">Telefone</label>
					<input type="tel" id="setceb-cf-telefone" name="setceb_telefone" autocomplete="tel" data-mask-phone placeholder="(00) 00000-0000">
				</div>
				<div class="setceb-contact-form__field">
					<label for="setceb-cf-email">Endereço de E-mail <span class="setceb-contact-form__req" aria-hidden="true">*</span></label>
					<input type="email" id="setceb-cf-email" name="setceb_email" required autocomplete="email" maxlength="120">
				</div>
			</div>

			<div class="setceb-contact-form__field">
				<label for="setceb-cf-rua">Nome da Rua</label>
				<input type="text" id="setceb-cf-rua" name="setceb_rua" autocomplete="street-address" maxlength="150">
			</div>

			<div class="setceb-contact-form__field">
				<label for="setceb-cf-complemento">Apartamento, suite, etc</label>
				<input type="text" id="setceb-cf-complemento" name="setceb_complemento" maxlength="150">
			</div>

			<div class="setceb-contact-form__row setceb-contact-form__row--2">
				<div class="setceb-contact-form__field">
					<label for="setceb-cf-cidade">Cidade</label>
					<input type="text" id="setceb-cf-cidade" name="setceb_cidade" autocomplete="address-level2" maxlength="100">
				</div>
				<div class="setceb-contact-form__field">
					<label for="setceb-cf-estado">Estado/Província</label>
					<input type="text" id="setceb-cf-estado" name="setceb_estado" autocomplete="address-level1" maxlength="60">
				</div>
			</div>

			<div class="setceb-contact-form__field">
				<label for="setceb-cf-cep">CEP / Código Postal</label>
				<input type="text" id="setceb-cf-cep" name="setceb_cep" autocomplete="postal-code" data-mask-cep placeholder="00000-000" maxlength="9">
			</div>

			<div class="setceb-contact-form__row setceb-contact-form__row--2">
				<div class="setceb-contact-form__field">
					<label for="setceb-cf-departamento">Departamento <span class="setceb-contact-form__req" aria-hidden="true">*</span></label>
					<select id="setceb-cf-departamento" name="setceb_departamento" required>
						<option value="">Selecione o departamento…</option>
						<?php foreach ( $departamentos as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="setceb-contact-form__field">
					<label for="setceb-cf-tipo">Quero enviar um(a) <span class="setceb-contact-form__req" aria-hidden="true">*</span></label>
					<select id="setceb-cf-tipo" name="setceb_tipo" required>
						<option value="">Selecione…</option>
						<?php foreach ( $tipos as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="setceb-contact-form__field">
					<span class="setceb-contact-form__label" id="setceb-cf-associado-label">Você é associado? <span class="setceb-contact-form__req" aria-hidden="true">*</span></span>
					<div class="setceb-contact-form__radios" role="radiogroup" aria-labelledby="setceb-cf-associado-label">
						<label class="setceb-contact-form__radio">
							<input type="radio" name="setceb_associado" value="sim" required>
							<span>Sim, sou associado</span>
						</label>
						<label class="setceb-contact-form__radio">
							<input type="radio" name="setceb_associado" value="nao">
							<span>Não, não sou associado</span>
						</label>
					</div>
				</div>
			</div>

			<div class="setceb-contact-form__field">
				<div class="setceb-contact-form__msg-top">
					<label for="setceb-cf-mensagem">Mensagem <span class="setceb-contact-form__req" aria-hidden="true">*</span></label>
					<span class="setceb-contact-form__counter" data-char-counter aria-live="polite">0 / 180</span>
				</div>
				<textarea id="setceb-cf-mensagem" name="setceb_mensagem" rows="5" required maxlength="180" data-char-input></textarea>
			</div>

			<div class="setceb-contact-form__errors" data-form-errors hidden role="alert"></div>
			<div class="setceb-contact-form__notice" data-form-notice hidden role="status"></div>

			<div class="setceb-contact-form__actions">
				<button type="submit" class="setceb-contact-form__submit" data-submit>
					<span class="setceb-contact-form__submit-label">Enviar</span>
				</button>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'formulario_contato_setceb', 'setceb_contato_render_shortcode' );

/* ------------------------------------------------------------
 * Captura de erro por campo (helper)
 * ------------------------------------------------------------ */

/**
 * Inteface: mensagens de erro por campo (chave => texto).
 * Usado tanto no fallback quanto no AJAX.
 */
function setceb_contato_new_errors() {
	return array();
}

/* ------------------------------------------------------------
 * Validacao dos campos
 * ------------------------------------------------------------ */

/**
 * Valida e sanitiza os dados do formulario.
 *
 * @param array $input Dados brutos ($_POST).
 * @return array{ data: array, errors: array }
 */
function setceb_contato_validate( $input ) {
	$errors = setceb_contato_new_errors();
	$data   = array();

	// Nome (obrigatorio)
	$data['nome'] = isset( $input['setceb_nome'] ) ? sanitize_text_field( wp_unslash( $input['setceb_nome'] ) ) : '';
	if ( '' === trim( $data['nome'] ) ) {
		$errors['nome'] = 'Informe seu nome.';
	}

	// Telefone (opcional)
	$data['telefone'] = isset( $input['setceb_telefone'] ) ? sanitize_text_field( wp_unslash( $input['setceb_telefone'] ) ) : '';

	// E-mail (obrigatorio)
	$data['email'] = isset( $input['setceb_email'] ) ? sanitize_email( wp_unslash( $input['setceb_email'] ) ) : '';
	if ( '' === $data['email'] ) {
		$errors['email'] = 'Informe seu e-mail.';
	} elseif ( ! is_email( $data['email'] ) ) {
		$errors['email'] = 'Informe um e-mail válido.';
	}

	// Endereco (opcionais)
	$data['rua']         = isset( $input['setceb_rua'] ) ? sanitize_text_field( wp_unslash( $input['setceb_rua'] ) ) : '';
	$data['complemento'] = isset( $input['setceb_complemento'] ) ? sanitize_text_field( wp_unslash( $input['setceb_complemento'] ) ) : '';
	$data['cidade']      = isset( $input['setceb_cidade'] ) ? sanitize_text_field( wp_unslash( $input['setceb_cidade'] ) ) : '';
	$data['estado']      = isset( $input['setceb_estado'] ) ? sanitize_text_field( wp_unslash( $input['setceb_estado'] ) ) : '';
	$data['cep']         = isset( $input['setceb_cep'] ) ? sanitize_text_field( wp_unslash( $input['setceb_cep'] ) ) : '';

	// Departamento (obrigatorio)
	$deptos = setceb_contato_departamentos();
	$data['departamento_slug'] = isset( $input['setceb_departamento'] ) ? sanitize_key( wp_unslash( $input['setceb_departamento'] ) ) : '';
	if ( ! isset( $deptos[ $data['departamento_slug'] ] ) ) {
		$errors['departamento'] = 'Selecione o departamento.';
	}

	// Tipo (obrigatorio)
	$tipos = setceb_contato_tipos();
	$data['tipo_slug'] = isset( $input['setceb_tipo'] ) ? sanitize_key( wp_unslash( $input['setceb_tipo'] ) ) : '';
	if ( ! isset( $tipos[ $data['tipo_slug'] ] ) ) {
		$errors['tipo'] = 'Selecione o tipo de envio.';
	}

	// Associado (obrigatorio)
	$data['associado'] = isset( $input['setceb_associado'] ) ? sanitize_key( wp_unslash( $input['setceb_associado'] ) ) : '';
	if ( ! in_array( $data['associado'], array( 'sim', 'nao' ), true ) ) {
		$errors['associado'] = 'Informe se você é associado.';
	}

	// Mensagem (obrigatorio, max 180)
	$data['mensagem'] = isset( $input['setceb_mensagem'] ) ? sanitize_textarea_field( wp_unslash( $input['setceb_mensagem'] ) ) : '';
	if ( '' === trim( $data['mensagem'] ) ) {
		$errors['mensagem'] = 'Escreva sua mensagem.';
	} elseif ( mb_strlen( $data['mensagem'] ) > 180 ) {
		$errors['mensagem'] = 'A mensagem deve ter no máximo 180 caracteres.';
	}

	return array(
		'data'   => $data,
		'errors' => $errors,
	);
}

/* ------------------------------------------------------------
 * Envio do e-mail
 * ------------------------------------------------------------ */

/**
 * Monta o corpo do e-mail a partir dos dados validados.
 *
 * @param array $data Dados validados.
 * @param array $rotulos Rotulos legiveis dos selects.
 * @return string Corpo do e-mail.
 */
function setceb_contato_build_body( $data, $rotulos ) {
	$rotulo_associado = ( 'sim' === $data['associado'] ) ? 'Sim, sou associado' : 'Não, não sou associado';

	$linhas = array(
		'Novo contato enviado pelo site.',
		'',
		'Nome: ' . $data['nome'],
		'Telefone: ' . ( '' !== $data['telefone'] ? $data['telefone'] : '-' ),
		'E-mail: ' . $data['email'],
		'Nome da Rua: ' . ( '' !== $data['rua'] ? $data['rua'] : '-' ),
		'Apartamento, suite, etc: ' . ( '' !== $data['complemento'] ? $data['complemento'] : '-' ),
		'Cidade: ' . ( '' !== $data['cidade'] ? $data['cidade'] : '-' ),
		'Estado/Província: ' . ( '' !== $data['estado'] ? $data['estado'] : '-' ),
		'CEP / Código Postal: ' . ( '' !== $data['cep'] ? $data['cep'] : '-' ),
		'Departamento: ' . $rotulos['departamento'],
		'Quero enviar um(a): ' . $rotulos['tipo'],
		'É associado: ' . $rotulo_associado,
		'',
		'Mensagem:',
		$data['mensagem'],
	);

	return implode( "\n", $linhas );
}

/**
 * Executa o processamento do formulario (compartilhado por AJAX e admin-post).
 *
 * @return array{ success: bool, errors: array|null }
 */
function setceb_contato_process() {
	$result = array(
		'success' => false,
		'errors'  => null,
	);

	// Verifica permissao de envio por IP (throttle simples).
	$throttle_key = 'setceb_contato_' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon' );

	if ( get_transient( $throttle_key ) ) {
		$result['errors'] = array(
			'form' => 'Aguarde alguns instantes antes de enviar uma nova mensagem.',
		);
		return $result;
	}

	// Honeypot: bots preencheram o campo escondido -> descarta como sucesso.
	if ( ! empty( $_POST['setceb_website'] ) ) {
		$result['success'] = true;
		return $result;
	}

	// Nonce.
	if ( ! isset( $_POST['setceb_contato_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['setceb_contato_nonce'] ) ), 'setceb_contact_submit' ) ) {
		$result['errors'] = array(
			'form' => 'A sessão expirou. Recarregue a página e tente novamente.',
		);
		return $result;
	}

	$valid = setceb_contato_validate( $_POST );

	if ( ! empty( $valid['errors'] ) ) {
		$result['errors'] = $valid['errors'];
		return $result;
	}

	$data  = $valid['data'];
	$deptos = setceb_contato_departamentos();
	$tipos  = setceb_contato_tipos();

	$rotulos = array(
		'departamento' => $deptos[ $data['departamento_slug'] ],
		'tipo'         => $tipos[ $data['tipo_slug'] ],
	);

	$body    = setceb_contato_build_body( $data, $rotulos );
	$subject = 'Novo contato pelo site - ' . $rotulos['departamento'];
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
	);

	// Reply-To com o e-mail do usuario (quando valido).
	if ( is_email( $data['email'] ) ) {
		$headers[] = 'Reply-To: ' . $data['nome'] . ' <' . $data['email'] . '>';
	}

	$enviado = wp_mail( setceb_contato_recipient(), $subject, $body, $headers );

	if ( ! $enviado ) {
		$result['errors'] = array(
			'form' => 'Não foi possível enviar sua mensagem. Tente novamente.',
		);
		return $result;
	}

	set_transient( $throttle_key, 1, 30 );
	$result['success'] = true;
	return $result;
}

/* ------------------------------------------------------------
 * Handler AJAX
 * ------------------------------------------------------------ */
function setceb_contato_ajax_handler() {
	$result = setceb_contato_process();

	wp_send_json( $result );
}
add_action( 'wp_ajax_setceb_contato', 'setceb_contato_ajax_handler' );
add_action( 'wp_ajax_nopriv_setceb_contato', 'setceb_contato_ajax_handler' );

/* ------------------------------------------------------------
 * Handler admin-post.php (fallback sem JS)
 * ------------------------------------------------------------ */
function setceb_contato_admin_post_handler() {
	$result = setceb_contato_process();

	$url = remove_query_arg( array( 'assoc_status', 'assoc_form' ), wp_get_referer() );

	if ( ! $url || false === strpos( $url, home_url() ) ) {
		$url = home_url( '/' );
	}

	if ( $result['success'] ) {
		$url = add_query_arg( 'setceb_contato_status', 'ok', $url );
	} elseif ( ! empty( $result['errors']['form'] ) ) {
		$url = add_query_arg( 'setceb_contato_status', 'erro', $url );
	}

	wp_safe_redirect( $url );
	exit;
}
add_action( 'admin_post_setceb_contato', 'setceb_contato_admin_post_handler' );
add_action( 'admin_post_nopriv_setceb_contato', 'setceb_contato_admin_post_handler' );
