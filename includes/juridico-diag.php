<?php
/**
 * Diagnostico do menu do modulo juridico.
 *
 * TEMPORARIO. Este arquivo existe para descobrir por que o item de topo
 * "Juridico" nao aparece para o administrador, e sai depois que a causa
 * for achada e corrigida.
 *
 * Por que instrumentalizar em vez de ler o codigo: o menu e montado em
 * tres passos que ficam em arquivos diferentes - wp-admin/menu.php cria o
 * item do post type, os add_submenu_page() do tema acrescentam o resto, e
 * o proprio menu.php filtra o que a conta nao alcanca. Tres rodadas de
 * correcao por leitura falharam; o unico jeito de parar de chutar e ver
 * o estado final na conta que falha.
 *
 * Uso (so para quem tem manage_options):
 *
 *   wp-admin/admin.php?page=setceb-sol-dashboard&setceb_diag=menu
 *
 * Mostra a arvore ja filtrada pelo core - o que o navegador recebeu, e
 * nao o que o tema pediu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liga o diagnostico quando o pedido traz setceb_diag=menu.
 */
function setceb_sol_diag_ligado() {
	if ( empty( $_GET['setceb_diag'] ) ) {
		return false;
	}

	return 'menu' === sanitize_key( wp_unslash( $_GET['setceb_diag'] ) );
}

/**
 * Monta o relatorio.
 *
 * @return string
 */
function setceb_sol_diag_relatorio() {
	global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $_registered_pages, $parent_file, $pagenow;

	if ( ! defined( 'SETCEB_SOL_CPT' ) ) {
		return "SETCEB_SOL_CPT nao esta definido.\n";
	}

	$slug = 'edit.php?post_type=' . SETCEB_SOL_CPT;
	$obj  = get_post_type_object( SETCEB_SOL_CPT );

	$e = '';

	// ---------------------------------------------------------------
	// Quem sou eu
	// ---------------------------------------------------------------
	$usuario = wp_get_current_user();

	$e .= "== CONTA ==\n";
	$e .= 'user_login:    ' . $usuario->user_login . "\n";
	$e .= 'roles:         ' . implode( ', ', (array) $usuario->roles ) . "\n";
	$e .= 'e advogado:    ' . ( setceb_is_advogado( $usuario ) ? 'SIM' : 'nao' ) . "\n";
	$e .= 'restrito:      ' . ( function_exists( 'setceb_sol_usuario_restrito' ) && setceb_sol_usuario_restrito() ? 'SIM' : 'nao' ) . "\n";
	$e .= 'manage_options:' . ( current_user_can( 'manage_options' ) ? 'SIM' : 'nao' ) . "\n";
	$e .= 'pagenow:       ' . $pagenow . "\n";
	$e .= 'parent_file:   ' . ( isset( $parent_file ) ? $parent_file : '(vazio)' ) . "\n";
	$e .= 'versao do tema: ' . wp_get_theme()->get( 'Version' ) . "\n";
	$e .= "\n";
	$e .= "A versao importa: se ela estiver atras, o arquivo que produziu o\n"
		. "problema nao e o que voce esta olhando. O css e o js levam ?ver=,\n"
		. "entao eles trocam junto - mas o PHP precisa ser reenviado.\n\n";

	// ---------------------------------------------------------------
	// O post type esta registrado?
	// ---------------------------------------------------------------
	$e .= "== POST TYPE ==\n";
	if ( ! $obj ) {
		$e .= "NAO ESTA REGISTRADO. A funcao register_post_type nao rodou,\n"
			. "ou rodou com outro nome. O item de menu do WordPress e criado\n"
			. "a partir daqui - sem isso o menu nao existe por construcao.\n\n";
	} else {
		$e .= 'name:             ' . $obj->name . "\n";
		$e .= 'labels->menu_name:' . $obj->labels->menu_name . "\n";
		$e .= 'labels->all_items:' . $obj->labels->all_items . "\n";
		$e .= 'show_ui:          ' . ( $obj->show_ui ? 'SIM' : 'nao' ) . "\n";
		$e .= 'show_in_menu:     ' . ( $obj->show_in_menu ? 'SIM' : var_export( $obj->show_in_menu, true ) ) . "\n";
		$e .= 'menu_position:    ' . $obj->menu_position . "\n";
		$e .= 'cap->edit_posts:  ' . $obj->cap->edit_posts . "\n";
		$e .= 'a conta tem essa cap? ' . ( current_user_can( $obj->cap->edit_posts ) ? 'SIM' : 'NAO' ) . "\n";
		$e .= "as tres primeiras linhas abaixo explicam o resto:\n";
		$e .= "  show_in_menu precisa ser verdadeiro para o core criar o item.\n";
		$e .= "  a conta precisa ter a cap de edit_posts, senao o core remove.\n\n";
	}

	// ---------------------------------------------------------------
	// O item de topo sobreviveu?
	// ---------------------------------------------------------------
	$e .= "== ITEM DE TOPO em \$menu ==\n";

	$achou = false;
	foreach ( (array) $menu as $posicao => $item ) {
		if ( ! is_array( $item ) || empty( $item[2] ) ) {
			continue;
		}
		if ( $slug === $item[2] ) {
			$achou = true;
			$e .= "  posicao {$posicao}\n";
			$e .= "  [0] titulo:   {$item[0]}\n";
			$e .= "  [1] cap:      {$item[1]}\n";
			$e .= "  [2] slug:     {$item[2]}\n";
		}
	}
	if ( ! $achou ) {
		$e .= "  NAO ESTA EM \$menu.\n";
		$e .= "  O core remove o item quando a conta nao tem a capacidade\n";
		$e .= "  (user_can_access_admin_page), ou quando outro menu usou a\n";
		$e .= "  mesma menu_position - o core indexa \$menu por posicao, e\n";
		$e .= "  quem registra depois sobrescreve quem estava antes.\n";
	}
	$e .= "\n";

	// ---------------------------------------------------------------
	// Os vizinhos na mesma posicao
	// ---------------------------------------------------------------
	$e .= "== \$menu inteiro (indice 2 = slug) ==\n";
	foreach ( (array) $menu as $posicao => $item ) {
		if ( ! is_array( $item ) || empty( $item[2] ) ) {
			continue;
		}
		$marcado = ( $slug === $item[2] ) ? ' <<<' : '';
		$e .= sprintf( "  %-4s %-28s %s%s\n", $posicao, $item[0], $item[2], $marcado );
	}
	$e .= "\n";

	// ---------------------------------------------------------------
	// Submenu
	// ---------------------------------------------------------------
	$e .= "== \$submenu['{$slug}'] ==\n";
	if ( isset( $submenu[ $slug ] ) ) {
		foreach ( $submenu[ $slug ] as $chave => $item ) {
			$e .= sprintf( "  [%s] %-16s -> %s\n", $chave, $item[0], isset( $item[2] ) ? $item[2] : '?' );
		}
	} else {
		$e .= "  NAO EXISTE. Sem submenu, o item de topo vira link direto.\n";
	}
	$e .= "\n";

	// ---------------------------------------------------------------
	// As listas de bloqueio do core
	// ---------------------------------------------------------------
	$e .= "== BLOQUEIOS ==\n";
	$e .= '$_wp_menu_nopriv[' . $slug . ']:    '
		. ( isset( $_wp_menu_nopriv[ $slug ] ) ? $_wp_menu_nopriv[ $slug ] : '(vazio)' ) . "\n";
	$e .= '$_wp_submenu_nopriv: ';
	if ( empty( $_wp_submenu_nopriv ) ) {
		$e .= "(vazio)\n";
	} else {
		$e .= "\n";
		foreach ( $_wp_submenu_nopriv as $filho => $pais ) {
			$e .= '  ' . $filho . ' => ' . implode( ', ', (array) $pais ) . "\n";
		}
	}
	$e .= "\n";

	// ---------------------------------------------------------------
	// parent_file: e o que decide qual item fica destacado
	// ---------------------------------------------------------------
	$e .= "== DESTAQUE ==\n";
	$e .= 'parent_file: ' . ( isset( $parent_file ) ? $parent_file : '(vazio)' ) . "\n";
	$e .= ( isset( $parent_file ) && $slug === $parent_file )
		? "  igual a slug do modulo: o item de topo fica destacado.\n"
		: "  diferente do slug do modulo: o item pode nao ficar destacado.\n";
	$e .= "\n";

	return $e;
}

/**
 * Imprime o relatorio no rodape de qualquer tela do admin.
 */
function setceb_sol_diag_imprime() {
	if ( ! setceb_sol_diag_ligado() ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$texto = setceb_sol_diag_relatorio();

	echo '<div style="position:fixed;inset:0;z-index:99999;background:#fff;overflow:auto;padding:16px">';
	echo '<pre style="white-space:pre-wrap;font:12px/1.5 monospace;margin:0 0 12px">' . esc_html( $texto ) . '</pre>';
	echo '<a href="' . esc_url( remove_query_arg( 'setceb_diag' ) ) . '">Fechar</a>';
	echo '</div>';
}
add_action( 'admin_footer', 'setceb_sol_diag_imprime', 99999 );

/**
 * Avisa no topo do admin que o diagnostico esta ligado.
 */
function setceb_sol_diag_aviso() {
	if ( ! setceb_sol_diag_ligado() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>'
		. '<strong>Diagnostico do menu ligado.</strong> O relatorio aparece no rodape de qualquer tela. '
		. 'Para desligar, tire <code>&amp;setceb_diag=menu</code> da URL.'
		. '</p></div>';
}
add_action( 'admin_notices', 'setceb_sol_diag_aviso', 99999 );