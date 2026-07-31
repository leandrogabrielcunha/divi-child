<?php
/*================================================
#Load the Parent theme style.css file
================================================*/
function dt_enqueue_styles() {
	$parenthandle = 'divi-style'; 
	$theme = wp_get_theme();
	wp_enqueue_style( $parenthandle, get_template_directory_uri() . '/style.css', 
		array(),  // if the parent theme code has a dependency, copy it to here
		$theme->parent()->get('Version')
	);
	wp_enqueue_style( 'child-style', get_stylesheet_uri(),
		array( $parenthandle ),
		$theme->get('Version') 
	);
}
add_action( 'wp_enqueue_scripts', 'dt_enqueue_styles' );

/*================================================
#Load the translations from the child theme folder
================================================*/
function dt_translation() {
    // Hook into textdomain loading to handle hyphenated filenames
    add_filter('load_textdomain_mofile', function($mofile, $domain) {
        if ($domain === 'Divi') {
            $child_mofile = get_stylesheet_directory() . '/lang/theme/Divi-' . determine_locale() . '.mo';
            if (file_exists($child_mofile)) {
                return $child_mofile;
            }
        }
        if ($domain === 'et_builder') {
            $child_mofile = get_stylesheet_directory() . '/lang/builder/et_builder-' . determine_locale() . '.mo';
            if (file_exists($child_mofile)) {
                return $child_mofile;
            }
        }
        return $mofile;
    }, 10, 2);

    // Load the textdomains
    load_child_theme_textdomain('Divi', get_stylesheet_directory() . '/lang/theme/');
    load_child_theme_textdomain('et_builder', get_stylesheet_directory() . '/lang/builder/');
}

// Run after theme setup
add_action('after_setup_theme', 'dt_translation');


/*================================================
#SETCEB - Custom login screen (wp-login.php)
Toda a implementacao vive neste arquivo, usando apenas:
- login_enqueue_scripts (carrega a base de estilos)
- login_head (injeta o CSS)
- login_footer (fundo decorativo, sprite SVG e JS)
================================================*/
function setceb_login_assets() {
	$theme = wp_get_theme();
	wp_enqueue_style( 'setceb-login', get_stylesheet_uri(), array(), $theme->get( 'Version' ) );
}
add_action( 'login_enqueue_scripts', 'setceb_login_assets' );

function setceb_login_styles() {
	wp_add_inline_style( 'setceb-login', setceb_login_css() );
}
add_action( 'login_head', 'setceb_login_styles' );

function setceb_login_footer() {
	echo setceb_login_background_html();
	echo setceb_login_sprite_html();
	setceb_login_script();
}
add_action( 'login_footer', 'setceb_login_footer' );

/**
 * HTML do logo oficial. Usa o arquivo logo-cor-02.png da raiz do tema;
 * se o arquivo nao existir, exibe um placeholder SVG elegante.
 */
function setceb_login_logo_html() {
	$file = get_stylesheet_directory() . '/logo-cor-02.png';

	if ( file_exists( $file ) ) {
		$url = get_stylesheet_directory_uri() . '/logo-cor-02.png';
		return '<img class="setceb-logo" src="' . esc_url( $url ) . '" alt="SETCEB" width="265" height="62" />';
	}

	return '<svg class="setceb-logo" width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="SETCEB">'
		. '<defs><linearGradient id="setceb-logo-grad" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">'
		. '<stop stop-color="#123D73"/><stop offset="1" stop-color="#1FB7C9"/></linearGradient></defs>'
		. '<rect x="3" y="3" width="66" height="66" rx="20" fill="url(#setceb-logo-grad)"/>'
		. '<path d="M26 25C26 20 30.5 16.5 36 16.5C42.5 16.5 47 20.5 47 26C47 31 43.5 34.5 38 36C33.5 37.5 30 40.5 30 45L47 45" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<circle cx="30" cy="53" r="3.5" fill="#FFFFFF"/>'
		. '<circle cx="24" cy="19" r="2.5" fill="#1FB7C9"/></svg>';
}

/**
 * Fundo decorativo: curvas, circulos e pontos com transparencia
 * baixa para nao competir com o card.
 */
function setceb_login_background_html() {
	return '<div class="setceb-bg" aria-hidden="true">'
		. '<svg class="setceb-bg-curve setceb-bg-curve--a" viewBox="0 0 520 520" fill="none" preserveAspectRatio="none">'
		. '<path d="M-40 460 C140 380 170 220 30 40" stroke="rgba(255,255,255,0.10)" stroke-width="2.5" stroke-linecap="round"/>'
		. '<path d="M0 470 C180 370 190 200 60 70" stroke="rgba(255,255,255,0.06)" stroke-width="2" stroke-linecap="round"/>'
		. '<path d="M40 520 C210 430 240 250 120 150" stroke="rgba(255,255,255,0.05)" stroke-width="2" stroke-linecap="round"/>'
		. '<circle cx="190" cy="120" r="46" stroke="rgba(255,255,255,0.10)" stroke-width="2.5"/>'
		. '<circle cx="190" cy="120" r="9" fill="rgba(31,183,201,0.6)"/>'
		. '</svg>'
		. '<svg class="setceb-bg-curve setceb-bg-curve--b" viewBox="0 0 520 520" fill="none" preserveAspectRatio="none">'
		. '<path d="M480 40 C340 140 330 300 470 480" stroke="rgba(255,255,255,0.09)" stroke-width="2.5" stroke-linecap="round"/>'
		. '<path d="M460 20 C320 150 320 320 490 500" stroke="rgba(255,255,255,0.05)" stroke-width="2" stroke-linecap="round"/>'
		. '<circle cx="330" cy="120" r="34" stroke="rgba(31,183,201,0.3)" stroke-width="2.5"/>'
		. '<circle cx="330" cy="120" r="7" fill="rgba(255,255,255,0.6)"/>'
		. '</svg>'
		. '<span class="setceb-bg-ring setceb-bg-ring--a"></span>'
		. '<span class="setceb-bg-ring setceb-bg-ring--b"></span>'
		. '<span class="setceb-bg-dot setceb-bg-dot--a"></span>'
		. '<span class="setceb-bg-dot setceb-bg-dot--b"></span>'
		. '<span class="setceb-bg-dot setceb-bg-dot--c"></span>'
		. '<span class="setceb-bg-dot setceb-bg-dot--d"></span>'
		. '</div>';
}

/**
 * Sprite SVG com os icones usados pelo JavaScript.
 */
function setceb_login_sprite_html() {
	return '<svg class="setceb-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">'
		. '<symbol id="setceb-icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></symbol>'
		. '<symbol id="setceb-icon-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></symbol>'
		. '<symbol id="setceb-icon-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></symbol>'
		. '<symbol id="setceb-icon-eye-off" viewBox="0 0 24 24"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></symbol>'
		. '</svg>';
}

/**
 * JavaScript que reorganiza o HTML padrao do wp-login e monta o card.
 */
function setceb_login_script() {
	$logo = wp_json_encode( setceb_login_logo_html() );
	?>
	<script>
		window.SetcebLogin = { logo: <?php echo $logo; // JSON ja escapado. ?> };

		(function () {
			'use strict';

			var ICONS = {
				user: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-user"/></svg>',
				lock: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-lock"/></svg>',
				eye: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-eye"/></svg>',
				eyeOff: '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-eye-off"/></svg>'
			};

			function whenReady(fn) {
				if (document.readyState !== 'loading') {
					fn();
				} else {
					document.addEventListener('DOMContentLoaded', fn);
				}
			}

			whenReady(function () {
				var login = document.getElementById('login');
				if (!login) {
					return;
				}

				var card = document.createElement('div');
				card.className = 'setceb-card';
				while (login.firstChild) {
					card.appendChild(login.firstChild);
				}
				login.appendChild(card);

				removeDefaultElements();
				buildBrand(card);
				decorateForm();
			});

			function removeDefaultElements() {
				var h1 = document.querySelector('#login h1');
				if (h1) {
					h1.remove();
				}
				var backtoblog = document.getElementById('backtoblog');
				if (backtoblog) {
					backtoblog.remove();
				}
				var language = document.querySelector('.language-switcher');
				if (language) {
					language.remove();
				}
			}

			function buildBrand(card) {
				var brand = document.createElement('div');
				brand.className = 'setceb-brand';
				brand.innerHTML = SetcebLogin.logo +
					'<h1 class="setceb-title">Fa\u00e7a login</h1>' +
					'<p class="setceb-subtitle">Para continuar, acesse sua conta.</p>' +
					'<span class="setceb-divider"></span>';
				card.insertBefore(brand, card.firstChild);
			}

			function decorateForm() {
				var form = document.getElementById('loginform');
				if (!form) {
					return;
				}

				decorateField('.login-username', 'user', 'Usu\u00e1rio', false);
				decorateField('.login-password', 'lock', 'Senha', true);
				decorateRemember();
				decorateSubmit();
				appendAccessNote(form);

				var link = document.querySelector('#nav a');
				if (link && document.body.classList.contains('login-action-login')) {
					link.textContent = 'Esqueceu sua senha?';
				}
			}

			function decorateField(selector, icon, labelText, withToggle) {
				var field = document.querySelector(selector);
				if (!field) {
					return;
				}

				field.classList.add('setceb-field', 'setceb-field--' + icon);

				var label = field.querySelector('label');
				if (label) {
					label.textContent = labelText;
				}

				var input = field.querySelector('input');
				if (!input) {
					return;
				}
				input.setAttribute('placeholder', labelText);

				var body = document.createElement('div');
				body.className = 'setceb-field-body';
				field.insertBefore(body, input);
				body.appendChild(input);
				body.insertAdjacentHTML('afterbegin', ICONS[icon]);

				if (withToggle) {
					var toggle = document.createElement('button');
					toggle.type = 'button';
					toggle.className = 'setceb-toggle-password';
					toggle.setAttribute('aria-label', 'Mostrar senha');
					toggle.innerHTML = ICONS.eye;
					toggle.addEventListener('click', function () {
						var visible = input.type === 'text';
						input.type = visible ? 'password' : 'text';
						toggle.innerHTML = visible ? ICONS.eye : ICONS.eyeOff;
						toggle.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
					});
					body.appendChild(toggle);
				}
			}

			function decorateRemember() {
				var wrap = document.querySelector('.forgetmenot, .login-remember');
				if (!wrap) {
					return;
				}

				wrap.classList.add('setceb-remember');

				var checkbox = wrap.querySelector('input[type="checkbox"]');
				if (!checkbox) {
					return;
				}

				var check = document.createElement('span');
				check.className = 'setceb-checkmark';
				checkbox.parentNode.insertBefore(check, checkbox.nextSibling);
			}

			function decorateSubmit() {
				var submit = document.getElementById('wp-submit');
				if (submit) {
					submit.value = 'Acessar';
					submit.classList.add('setceb-submit');
				}
				var row = submit ? submit.closest('p') : document.querySelector('#loginform .submit');
				if (row) {
					row.classList.add('setceb-submit-row');
				}
			}

			function appendAccessNote(form) {
				var note = document.createElement('p');
				note.className = 'setceb-note';
				note.innerHTML = ICONS.lock + '<span>Acesso exclusivo para usu\u00e1rios autorizados.</span>';
				form.appendChild(note);
			}
		})();
	</script>
	<?php
}

/**
 * CSS completo da tela de login, escopado em body.login.
 */
function setceb_login_css() {
	return <<<'CSS'
/* ---------- Base ---------- */
body.login {
	--setceb-blue: #123D73;
	--setceb-blue-light: #1D5B9F;
	--setceb-turquoise: #1FB7C9;
	--setceb-white: #FFFFFF;
	--setceb-gray: #F5F7FA;
	--setceb-ink: #0F172A;
	--setceb-muted: #64748B;
	--setceb-border: #DFE5EE;

	margin: 0;
	padding: 24px;
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	background-color: #0a2440;
	background-image:
		radial-gradient(1100px 720px at 10% -10%, rgba(31, 183, 201, 0.26), transparent 60%),
		radial-gradient(900px 640px at 100% 0%, rgba(29, 91, 159, 0.45), transparent 55%),
		radial-gradient(760px 680px at 88% 105%, rgba(31, 183, 201, 0.20), transparent 60%),
		radial-gradient(520px 420px at -5% 95%, rgba(18, 61, 115, 0.75), transparent 62%),
		repeating-linear-gradient(118deg, rgba(255, 255, 255, 0.035) 0, rgba(255, 255, 255, 0.035) 1px, transparent 1px, transparent 92px),
		linear-gradient(135deg, #0a2440 0%, #123D73 42%, #1D5B9F 100%);
	background-attachment: fixed;
}

body.login *,
body.login *::before,
body.login *::after {
	box-sizing: border-box;
}

/* ---------- Remocao de tudo que lembra o wp-login ---------- */
body.login h1:not(.setceb-title),
body.login .language-switcher,
body.login #backtoblog {
	display: none !important;
}

body.login #login {
	width: 460px;
	max-width: 100%;
	margin: 0;
	padding: 0 !important;
	position: relative;
	z-index: 2;
}

body.login form {
	margin: 0 !important;
	padding: 0 !important;
	background: transparent !important;
	border: 0 !important;
	box-shadow: none !important;
	overflow: visible !important;
}

/* ---------- Card ---------- */
body.login .setceb-card {
	background: var(--setceb-white);
	border-radius: 24px;
	padding: 52px 52px 44px;
	box-shadow:
		0 14px 44px rgba(6, 28, 60, 0.16),
		0 4px 16px rgba(6, 28, 60, 0.10);
	animation: setceb-card-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}

/* ---------- Marca ---------- */
body.login .setceb-brand {
	text-align: center;
	margin-bottom: 28px;
}

body.login .setceb-logo {
	display: block;
	max-width: 220px;
	width: auto;
	height: auto;
	margin: 0 auto 24px;
}

body.login .setceb-title {
	font-size: 30px;
	font-weight: 700;
	color: var(--setceb-blue);
	letter-spacing: -0.5px;
	margin: 0 0 8px !important;
}

body.login .setceb-subtitle {
	font-size: 15px;
	color: var(--setceb-muted);
	margin: 0 0 20px !important;
}

body.login .setceb-divider {
	display: block;
	width: 72px;
	height: 4px;
	margin: 0 auto;
	border-radius: 999px;
	background: linear-gradient(90deg, var(--setceb-blue), var(--setceb-turquoise));
}

/* ---------- Campos ---------- */
body.login .setceb-field {
	margin: 0 0 24px;
}

body.login .setceb-field > label {
	position: absolute;
	width: 1px;
	height: 1px;
	margin: -1px;
	padding: 0;
	overflow: hidden;
	clip: rect(0 0 0 0);
	white-space: nowrap;
	border: 0;
}

body.login .setceb-field-body {
	position: relative;
}

body.login input[type="text"],
body.login input[type="password"],
body.login input[type="email"] {
	background: var(--setceb-white) !important;
	border: 1.5px solid var(--setceb-border) !important;
	border-radius: 12px !important;
	font-size: 15px !important;
	box-shadow: none !important;
	padding: 14px 16px !important;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

body.login .setceb-field input[type="text"],
body.login .setceb-field input[type="password"] {
	width: 100% !important;
	height: 56px !important;
	line-height: 1 !important;
	padding: 14px 48px !important;
	color: var(--setceb-ink) !important;
	border-radius: 14px !important;
	margin: 0 !important;
	outline: none;
}

body.login .setceb-field input[type="password"] {
	padding-right: 52px !important;
}

body.login .setceb-field input:focus {
	border-color: var(--setceb-blue-light) !important;
	box-shadow: 0 0 0 4px rgba(29, 91, 159, 0.12) !important;
}

body.login .setceb-field input::placeholder {
	color: #9aa7b8;
	opacity: 1;
}

body.login .setceb-icon {
	width: 20px;
	height: 20px;
	fill: none;
	stroke: currentColor;
	stroke-width: 2;
	stroke-linecap: round;
	stroke-linejoin: round;
}

body.login .setceb-field .setceb-icon {
	position: absolute;
	left: 17px;
	top: 50%;
	transform: translateY(-50%);
	color: #9aa7b8;
	pointer-events: none;
	transition: color 0.2s ease;
}

body.login .setceb-field:focus-within .setceb-icon {
	color: var(--setceb-blue-light);
}

/* ---------- Mostrar senha ---------- */
body.login .setceb-toggle-password {
	position: absolute;
	right: 8px;
	top: 50%;
	transform: translateY(-50%);
	width: 42px;
	height: 42px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: transparent;
	border: 0;
	border-radius: 12px;
	cursor: pointer;
	color: #94a3b8;
	transition: background 0.2s ease, color 0.2s ease;
}

body.login .setceb-toggle-password:hover {
	background: var(--setceb-gray);
	color: var(--setceb-blue-light);
}

body.login .setceb-toggle-password .setceb-icon {
	position: static;
	transform: none;
}

/* ---------- Lembrar-me ---------- */
body.login .setceb-remember {
	display: flex;
	align-items: center;
	margin: 0 0 24px;
}

body.login .setceb-remember label {
	position: relative;
	display: inline-flex;
	align-items: center;
	gap: 10px;
	font-size: 14px;
	color: #475569;
	cursor: pointer;
}

body.login .setceb-remember input[type="checkbox"] {
	position: absolute;
	width: 1px;
	height: 1px;
	margin: 0;
	opacity: 0;
	clip: rect(0 0 0 0);
}

body.login .setceb-checkmark {
	flex-shrink: 0;
	width: 20px;
	height: 20px;
	border: 1.5px solid #cbd5e1;
	border-radius: 6px;
	background: #fff;
	position: relative;
	transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

body.login .setceb-remember input:checked + .setceb-checkmark {
	background: linear-gradient(135deg, var(--setceb-blue), var(--setceb-turquoise));
	border-color: transparent;
}

body.login .setceb-remember input:checked + .setceb-checkmark::after {
	content: "";
	position: absolute;
	left: 6px;
	top: 2px;
	width: 5px;
	height: 9px;
	border: solid #fff;
	border-width: 0 2px 2px 0;
	transform: rotate(45deg);
}

body.login .setceb-remember input:focus-visible + .setceb-checkmark {
	box-shadow: 0 0 0 3px rgba(29, 91, 159, 0.2);
}

/* ---------- Botao Acessar ---------- */
body.login .setceb-submit-row {
	margin: 0 0 24px;
}

body.login .setceb-submit {
	width: 100% !important;
	height: 56px !important;
	margin: 0 !important;
	padding: 0 !important;
	font-size: 16px !important;
	font-weight: 600 !important;
	letter-spacing: 0.2px;
	color: #fff !important;
	text-shadow: none !important;
	border: 0 !important;
	border-radius: 14px !important;
	background: linear-gradient(90deg, var(--setceb-blue) 0%, var(--setceb-turquoise) 100%) !important;
	box-shadow: 0 10px 22px rgba(18, 61, 115, 0.3) !important;
	cursor: pointer;
	transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}

body.login .setceb-submit:hover {
	transform: translateY(-2px);
	box-shadow: 0 16px 30px rgba(18, 61, 115, 0.38) !important;
	filter: brightness(1.05);
}

body.login .setceb-submit:active {
	transform: translateY(0);
	box-shadow: 0 8px 18px rgba(18, 61, 115, 0.28) !important;
}

body.login .setceb-submit:focus-visible {
	outline: 3px solid rgba(31, 183, 201, 0.45);
	outline-offset: 2px;
}

/* ---------- Esqueceu sua senha? ---------- */
body.login #nav {
	margin: 0 0 24px;
	padding: 0 !important;
	text-align: center;
}

body.login #nav a {
	color: var(--setceb-blue-light);
	font-size: 14px;
	font-weight: 500;
	text-decoration: none;
	transition: color 0.2s ease;
}

body.login #nav a:hover {
	color: var(--setceb-blue);
	text-decoration: underline;
}

body.login a:focus-visible {
	outline: 3px solid rgba(31, 183, 201, 0.45);
	outline-offset: 2px;
}

/* ---------- Nota de acesso ---------- */
body.login .setceb-note {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	margin: 0;
	padding: 0;
	font-size: 12.5px;
	color: #8a97a9;
}

body.login .setceb-note .setceb-icon {
	width: 13px;
	height: 13px;
	color: #94a3b8;
}

/* ---------- Erros e mensagens ---------- */
body.login #login_error,
body.login .message,
body.login .success {
	background: #fff !important;
	border: 0 !important;
	border-left: 4px solid #dc2626 !important;
	border-radius: 12px;
	box-shadow: 0 10px 24px rgba(6, 28, 60, 0.12) !important;
	color: #b91c1c;
	font-size: 13.5px;
	padding: 12px 14px !important;
	margin: 0 0 18px !important;
}

body.login .message,
body.login .success {
	border-left-color: var(--setceb-turquoise) !important;
	color: #334155;
}

/* ---------- Fundo decorativo ---------- */
body.login .setceb-bg {
	position: fixed;
	inset: 0;
	z-index: 0;
	overflow: hidden;
	pointer-events: none;
}

body.login .setceb-bg-curve {
	position: absolute;
	opacity: 0.7;
}

body.login .setceb-bg-curve--a {
	left: -70px;
	top: -50px;
	width: 520px;
	height: 520px;
	animation: setceb-float-a 16s ease-in-out infinite;
}

body.login .setceb-bg-curve--b {
	right: -90px;
	bottom: -70px;
	width: 560px;
	height: 560px;
	animation: setceb-float-b 20s ease-in-out infinite;
}

body.login .setceb-bg-ring {
	position: absolute;
	border-radius: 50%;
	border: 1.5px solid rgba(255, 255, 255, 0.10);
}

body.login .setceb-bg-ring--a {
	width: 190px;
	height: 190px;
	right: 14%;
	top: 12%;
	animation: setceb-float-b 22s ease-in-out infinite;
}

body.login .setceb-bg-ring--b {
	width: 54px;
	height: 54px;
	left: 10%;
	bottom: 18%;
	border-color: rgba(31, 183, 201, 0.35);
	animation: setceb-float-a 14s ease-in-out infinite;
}

body.login .setceb-bg-dot {
	position: absolute;
	border-radius: 50%;
}

body.login .setceb-bg-dot--a {
	width: 10px;
	height: 10px;
	background: rgba(31, 183, 201, 0.7);
	left: 15%;
	top: 26%;
}

body.login .setceb-bg-dot--b {
	width: 6px;
	height: 6px;
	background: rgba(255, 255, 255, 0.5);
	right: 23%;
	top: 34%;
}

body.login .setceb-bg-dot--c {
	width: 14px;
	height: 14px;
	border: 2px solid rgba(255, 255, 255, 0.25);
	right: 10%;
	bottom: 24%;
}

body.login .setceb-bg-dot--d {
	width: 5px;
	height: 5px;
	background: rgba(31, 183, 201, 0.7);
	left: 27%;
	bottom: 13%;
}

/* ---------- Animacoes ---------- */
@keyframes setceb-card-in {
	from {
		opacity: 0;
		transform: translateY(18px) scale(0.985);
	}
	to {
		opacity: 1;
		transform: none;
	}
}

@keyframes setceb-float-a {
	0%, 100% { transform: translate3d(0, 0, 0); }
	50% { transform: translate3d(0, -16px, 0); }
}

@keyframes setceb-float-b {
	0%, 100% { transform: translate3d(0, 0, 0); }
	50% { transform: translate3d(0, 14px, 0); }
}

/* ---------- Responsivo ---------- */
@media (max-width: 600px) {
	body.login {
		padding: 16px;
	}

	body.login #login {
		width: 92%;
		max-width: 460px;
	}

	body.login .setceb-card {
		padding: 34px 22px 30px;
		border-radius: 20px;
	}

	body.login .setceb-logo {
		max-width: 170px;
	}

	body.login .setceb-title {
		font-size: 23px;
	}

	body.login .setceb-field input[type="text"],
	body.login .setceb-field input[type="password"],
	body.login .setceb-submit {
		height: 54px !important;
	}
}
CSS;
}
