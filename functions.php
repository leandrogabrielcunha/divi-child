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
A estrutura da tela e gerada no servidor (buffer no login_init),
sem alterar o core. O login.js so cuida do botao mostrar senha.
CSS: style.css | JS: login.js | PHP: este arquivo
================================================*/
function setceb_login_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'setceb-login',
		get_stylesheet_uri(),
		array( 'login' ),
		$theme->get( 'Version' )
	);

	wp_enqueue_script(
		'setceb-login',
		get_stylesheet_directory_uri() . '/login.js',
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'login_enqueue_scripts', 'setceb_login_enqueue_assets' );

function setceb_login_head() {
	wp_add_inline_style(
		'setceb-login',
		'html{background-color:#0a2440}'
		. 'body.login h1:not(.setceb-title),'
		. 'body.login .language-switcher,'
		. 'body.login #backtoblog{display:none!important}'
	);
}
add_action( 'login_head', 'setceb_login_head' );

function setceb_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'setceb_login_header_url' );

function setceb_login_header_title() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'setceb_login_header_title' );

/* Captura a pagina do login para reescrever o formulario no servidor. */
function setceb_login_buffer_start() {
	ob_start( 'setceb_login_buffer_callback' );
}
add_action( 'login_init', 'setceb_login_buffer_start' );

function setceb_login_buffer_callback( $html ) {
	if ( false === strpos( $html, 'id="loginform"' ) ) {
		return $html;
	}
	return setceb_login_rewrite( $html );
}

function setceb_login_rewrite( $html ) {
	/* Move mensagens (erro/sucesso) para dentro do card. */
	$messages = '';
	$patterns = array(
		'/<div[^>]*id="login_error"[^>]*>.*?<\/div>/s',
		'/<div[^>]*class="[^"]*\b(message|success)\b[^"]*"[^>]*>.*?<\/div>/s',
	);
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $html, $m ) ) {
			$messages .= $m[0];
			$html      = str_replace( $m[0], '', $html );
		}
	}

	$card = '<div class="setceb-card">' . setceb_login_brand_html() . setceb_login_build_form( $messages ) . '</div>';

	$html = preg_replace( '/<form[^>]*id="loginform"[^>]*>.*?<\/form>/s', $card, $html, 1 );

	/* Remove elementos padrao (o CSS ja oculta, o DOM fica limpo). */
	$html = preg_replace( '/<h1[^>]*>.*?<\/h1>/s', '', $html, 1 );
	$html = preg_replace( '/<p[^>]*\b(id|class)="[^"]*backtoblog[^"]*"[^>]*>.*?<\/p>/s', '', $html, 1 );
	$html = preg_replace( '/<div[^>]*\bclass="[^"]*language-switcher[^"]*"[^>]*>.*?<\/div>/s', '', $html, 1 );

	return $html;
}

function setceb_login_brand_html() {
	$logo = get_stylesheet_directory_uri() . '/logo-cor-02.png';
	return '<div class="setceb-brand">'
		. '<img class="setceb-logo" src="' . esc_url( $logo ) . '" alt="SETCEB" width="265" height="62">'
		. '</div>';
}

function setceb_login_build_form( $messages = '' ) {
	$action   = esc_url( site_url( 'wp-login.php', 'login_post' ) );
	$redirect = isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : admin_url();
	$user     = isset( $_POST['log'] ) ? wp_unslash( $_POST['log'] ) : '';
	$lost_url = wp_lostpassword_url();

	$icon_user = '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-user"/></svg>';
	$icon_lock = '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-lock"/></svg>';
	$icon_eye  = '<svg class="setceb-icon" aria-hidden="true"><use href="#setceb-icon-eye"/></svg>';

	ob_start();
	?>
	<form name="loginform" id="loginform" action="<?php echo $action; ?>" method="post">
		<?php echo $messages; ?>

		<div class="setceb-field setceb-field--user">
			<label for="user_login">Usuário</label>
			<div class="setceb-field-body">
				<?php echo $icon_user; ?>
				<input type="text" name="log" id="user_login" class="input" value="<?php echo esc_attr( $user ); ?>" size="20" autocapitalize="off" autocomplete="username" placeholder="Usuário" required="required">
			</div>
		</div>

		<div class="setceb-field setceb-field--lock">
			<label for="user_pass">Senha</label>
			<div class="setceb-field-body">
				<?php echo $icon_lock; ?>
				<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" autocomplete="current-password" spellcheck="false" placeholder="Senha" required="required">
				<button type="button" class="setceb-toggle-password" aria-label="Mostrar senha">
					<?php echo $icon_eye; ?>
				</button>
			</div>
		</div>

		<div class="setceb-actions">
			<p class="submit setceb-submit-row">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large setceb-submit" value="Acessar">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
				<input type="hidden" name="testcookie" value="1">
			</p>
			<p id="nav" class="setceb-nav">
				<a href="<?php echo esc_url( $lost_url ); ?>">Recuperar senha</a>
			</p>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

function setceb_login_footer() {
	?>
	<!-- SETCEB: fundo decorativo (curvas, circulos e pontos) -->
	<div class="setceb-bg" aria-hidden="true">
		<svg class="setceb-bg-curve setceb-bg-curve--a" viewBox="0 0 520 520" fill="none" preserveAspectRatio="none">
			<path d="M-40 460 C140 380 170 220 30 40" stroke="rgba(255,255,255,0.16)" stroke-width="2.5" stroke-linecap="round"/>
			<path d="M0 470 C180 370 190 200 60 70" stroke="rgba(255,255,255,0.10)" stroke-width="2" stroke-linecap="round"/>
			<path d="M40 520 C210 430 240 250 120 150" stroke="rgba(255,255,255,0.07)" stroke-width="2" stroke-linecap="round"/>
			<circle cx="190" cy="120" r="46" stroke="rgba(255,255,255,0.16)" stroke-width="2.5"/>
			<circle cx="190" cy="120" r="9" fill="#1FB7C9"/>
		</svg>
		<svg class="setceb-bg-curve setceb-bg-curve--b" viewBox="0 0 520 520" fill="none" preserveAspectRatio="none">
			<path d="M480 40 C340 140 330 300 470 480" stroke="rgba(255,255,255,0.14)" stroke-width="2.5" stroke-linecap="round"/>
			<path d="M460 20 C320 150 320 320 490 500" stroke="rgba(255,255,255,0.09)" stroke-width="2" stroke-linecap="round"/>
			<circle cx="330" cy="120" r="34" stroke="rgba(31,183,201,0.4)" stroke-width="2.5"/>
			<circle cx="330" cy="120" r="7" fill="rgba(255,255,255,0.85)"/>
		</svg>
		<span class="setceb-bg-ring setceb-bg-ring--a"></span>
		<span class="setceb-bg-ring setceb-bg-ring--b"></span>
		<span class="setceb-bg-dot setceb-bg-dot--a"></span>
		<span class="setceb-bg-dot setceb-bg-dot--b"></span>
		<span class="setceb-bg-dot setceb-bg-dot--c"></span>
		<span class="setceb-bg-dot setceb-bg-dot--d"></span>
	</div>

	<!-- SETCEB: sprite SVG dos icones -->
	<svg class="setceb-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">
		<symbol id="setceb-icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></symbol>
		<symbol id="setceb-icon-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></symbol>
		<symbol id="setceb-icon-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></symbol>
		<symbol id="setceb-icon-eye-off" viewBox="0 0 24 24"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></symbol>
	</svg>
	<?php
}
add_action( 'login_footer', 'setceb_login_footer' );
