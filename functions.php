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
Reconstroi a tela de login sem tocar no core.
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

	wp_localize_script(
		'setceb-login',
		'SetcebLogin',
		array(
			'logo' => get_stylesheet_directory_uri() . '/logo-cor-02.png',
		)
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
