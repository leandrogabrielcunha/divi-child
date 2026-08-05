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
Modelo da branch main: estiliza a tela padrao do WordPress
somente com CSS, sem JS de layout, sem buffer e sem alterar o
core. O login.js aplica apenas placeholder e o texto do botao
(melhoria progressiva).
- CSS: style.css
- JS: login.js
- PHP: este arquivo
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
		'html{background-color:#eef2f7}'
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

/*================================================
#SETCEB - Regras de negocio do Associado
Role "associado", noticias restritas e area do
associado (perfil). Ver includes/associado.php
================================================*/
require_once get_stylesheet_directory() . '/includes/associado.php';

/*================================================
#SETCEB - Header global customizado
Estrutura do cabecalho e menu principal.
Ver includes/setceb-header.php
================================================*/
require_once get_stylesheet_directory() . '/includes/setceb-header.php';

/*================================================
#SETCEB - Banner / Carrossel (plugin embutido)
Plugin nativo do WordPress localizado em
setceb-banner/. Pode ser movido para
wp-content/plugins/ sem nenhuma alteracao.
A guarda evita carregamento duplicado caso o
plugin tambem esteja instalado via wp-content.
================================================*/
if ( ! function_exists( 'setceb_banner' ) ) {
	require_once get_stylesheet_directory() . '/setceb-banner/setceb-banner.php';
}
