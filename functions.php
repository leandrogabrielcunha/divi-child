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
#Customize the login screen
================================================*/
function custom_login_enqueue_styles() {
    echo '<style>
        body.login {
            background: linear-gradient(135deg, #0b2447, #19376d, #576cbc);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        body.login #login {
            width: 420px !important;
            padding: 0 !important;
        }

        /* Remove logo do WordPress */
        body.login h1 a {
            display: none !important;
        }

        /* Card central */
        .login form {
            background: #ffffff !important;
            border-radius: 14px !important;
            padding: 40px 40px !important;
            box-shadow: 0px 10px 30px rgba(0,0,0,0.2) !important;
        }

        /* Título */
        .login form::before {
            content: "Bem-vindo à Intranet";
            display: block;
            text-align: center;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #000;
        }

        /* Subtítulo */
        .login form::after {
            content: "Acesse comunicados da empresa, documentos, diretório de funcionários e ferramentas de colaboração.";
            display: block;
            text-align: center;
            font-size: 14px;
            color: #555;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        /* Inputs */
        .login form input[type=text],
        .login form input[type=password] {
            background: #eaf0fc !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 14px !important;
            font-size: 16px !important;
        }

        /* Botão */
        .login form input[type=submit] {
            background: linear-gradient(135deg, #0b2447, #081a33);
            border: none !important;
            border-radius: 6px !important;
            padding: 14px 20px !important;
            font-size: 16px !important;
            width: 100%;
            margin-top: 10px;
        }

        .login #nav a,
        .login #backtoblog a {
            color: #444 !important;
            font-size: 13px;
        }

        .login #nav {
            text-align: left !important;
            margin-top: -10px !important;
        }
    </style>';
}
add_action('login_head', 'custom_login_enqueue_styles');
