<?php
/*================================================
#Load the Parent theme style.css file
================================================*/
function dt_enqueue_styles() {
	$parenthandle = 'divi-style';
	$theme = wp_get_theme();
	wp_enqueue_style( $parenthandle, get_template_directory_uri() . '/style.css',
		array(),
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

	load_child_theme_textdomain('Divi', get_stylesheet_directory() . '/lang/theme/');
	load_child_theme_textdomain('et_builder', get_stylesheet_directory() . '/lang/builder/');
}
add_action('after_setup_theme', 'dt_translation');


/*================================================
#CE Tech - Custom login screen (wp-login.php)
================================================*/
function cetech_login_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'cetech-login',
		get_stylesheet_uri(),
		array( 'login' ),
		$theme->get( 'Version' )
	);

	wp_enqueue_script(
		'cetech-login',
		get_stylesheet_directory_uri() . '/login.js',
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'login_enqueue_scripts', 'cetech_login_enqueue_assets' );

function cetech_login_head() {
	wp_add_inline_style(
		'cetech-login',
		'html{background-color:#eef2f7}'
	);
}
add_action( 'login_head', 'cetech_login_head' );

function cetech_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'cetech_login_header_url' );

function cetech_login_header_title() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'cetech_login_header_title' );

/*================================================
#CE Tech - Header global customizado
================================================*/
require_once get_stylesheet_directory() . '/includes/cetech-header.php';

/*================================================
#CE Tech - Hero section dinâmica (home)
================================================*/
require_once get_stylesheet_directory() . '/includes/cetech-hero.php';

/*================================================
#CE Tech - Planos (cards via shortcode [planos])
================================================*/
require_once get_stylesheet_directory() . '/includes/cetech-planos.php';