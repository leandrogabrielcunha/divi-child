<?php
/**
 * SETCEB - Ferramenta de Importacao de Planilhas
 *
 * Pagina admin em Ferramentas > Importar Planilhas.
 * Permite fazer upload de um .zip com as pastas de categorias
 * e importar automaticamente como posts do CPT setceb_planilha.
 *
 * Carregado por functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra o menu Ferramentas > Importar Planilhas.
 */
function setceb_importer_menu() {
	add_management_page(
		'Importar Planilhas',
		'Importar Planilhas',
		'manage_options',
		'setceb-importar-planilhas',
		'setceb_importer_page'
	);
}
add_action( 'admin_menu', 'setceb_importer_menu' );

/**
 * Diretorio temporario para extrair o .zip.
 */
function setceb_importer_tmp_dir() {
	return wp_upload_dir()['basedir'] . '/setceb-import-temp';
}

/**
 * Mapeamento pasta => slug da categoria.
 */
function setceb_importer_categoria_map() {
	return array(
		'CONTEINER'              => 'container',
		'FRACIONADA'             => 'fracionada',
		'FRIGORIFICADA'          => 'frigorificada',
		'GRANEIS E SOLIDOS'      => 'graos-e-solidos',
		'GRAOS'                  => 'graos-e-solidos',
		'GRÃOS'                  => 'graos-e-solidos',
		'INTERNACIONAL'          => 'internacional',
		'LIQUIDA'                => 'liquida',
		'LÍQUIDA'                => 'liquida',
		'LOTACAO'                => 'lotacao',
		'LOTAÇÃO'                => 'lotacao',
		'MAQUINAS E EQUIPAMENTOS' => 'maquinas-e-equipamentos',
		'MÁQUINAS E EQUIPAMENTOS' => 'maquinas-e-equipamentos',
		'INCTF'                  => 'graos-e-solidos',
	);
}

/**
 * Extrai o ano do nome do arquivo.
 */
function setceb_importer_extract_year( $filename ) {
	if ( preg_match( '/(\d{4})\s*\.pdf$/i', $filename, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/(\d{2})\s*\.pdf$/i', $filename, $m ) ) {
		$y = (int) $m[1];
		return (string) ( $y > 50 ? 1900 + $y : 2000 + $y );
	}
	return '';
}

/**
 * Titulo amigavel a partir do nome do arquivo.
 */
function setceb_importer_extract_title( $filename ) {
	$title = str_replace( '.pdf', '', basename( $filename ) );
	$title = str_replace( array( '_', '-' ), ' ', $title );
	return trim( preg_replace( '/\s+/', ' ', $title ) );
}

/**
 * Upload de arquivo para a Midia.
 */
function setceb_importer_upload( $src ) {
	if ( ! file_exists( $src ) ) {
		return false;
	}

	$fn     = basename( $src );
	$upload = wp_upload_dir();
	$dest   = $upload['path'] . '/' . $fn;

	if ( ! @copy( $src, $dest ) ) {
		return false;
	}

	$tipo = wp_check_filetype( $fn );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $tipo['type'],
			'post_title'     => sanitize_file_name( pathinfo( $fn, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		),
		$dest
	);

	if ( is_wp_error( $id ) || 0 === $id ) {
		return false;
	}

	$meta = wp_generate_attachment_metadata( $id, $dest );
	wp_update_attachment_metadata( $id, $meta );

	return $id;
}

/**
 * Encontra a pasta raiz que contem as pastas de categorias.
 *
 * O .zip pode chegar de varias formas:
 * - Direto: CONTEINER/, FRACIONADA/, ...
 * - Com wrapper: SETCEB/CONTEINER/, SETCEB/FRACIONADA/, ...
 * - Com wrapper aninhado e/ou nivel extra.
 *
 * Haus estamos procurando o diretorio em cujos subdiretorios estao
 * (ou as categorias mapeadas, ou pastas contendo PDFs). Descemos
 * recursivamente pelas pastas que NAO sao categorias.
 *
 * @param string $dir Diretorio a inspecionar.
 * @param array $cat_map Mapa pasta => slug.
 * @return string Caminho da raiz encontrada (ou $dir se nao achar).
 */
function setceb_importer_find_root( $dir, $cat_map ) {
	$entries = array_values( array_diff( scandir( $dir ), array( '.', '..', '.DS_Store', '__MACOSX' ) ) );

	// Sem entradas: retorna proprio dir.
	if ( empty( $entries ) ) {
		return $dir;
	}

	$dirs = array();

	foreach ( $entries as $entry ) {
		$full = $dir . '/' . $entry;

		if ( is_dir( $full ) ) {
			$dirs[] = array( 'name' => $entry, 'path' => $full );
		}
	}

	if ( empty( $dirs ) ) {
		return $dir;
	}

	// Se este nivel ja e o nivel de categorias (subpastas mapeadas ou com PDFs).
	foreach ( $dirs as $d ) {
		$upper = strtoupper( $d['name'] );

		if ( isset( $cat_map[ $upper ] ) ) {
			return $dir; // Este e o nivel raiz das categorias.
		}
	}

	// Se nenhuma subpasta e categoria, verifica se alguma contem PDFs diretamente.
	$has_pdf_child = false;

	foreach ( $dirs as $d ) {
		$pdfs = array_merge( glob( $d['path'] . '/*.pdf' ), glob( $d['path'] . '/*.PDF' ) );
		if ( ! empty( $pdfs ) ) {
			$has_pdf_child = true;
			break;
		}
	}

	if ( $has_pdf_child ) {
		return $dir;
	}

	// Se so existe uma pasta, desce nela (wrapper).
	if ( 1 === count( $dirs ) ) {
		return setceb_importer_find_root( $dirs[0]['path'], $cat_map );
	}

	// Multiplas pastas que nao sao categorias nem tem PDF: desce na primeira que seja wrapper
	// ou tiver subpastas mais internas. Tenta todas procurando categorias.
	foreach ( $dirs as $d ) {
		$inner = setceb_importer_find_root( $d['path'], $cat_map );

		// Verifica se o inner achou categorias (subpastas mapeadas).
		$inner_entries = array_values( array_diff( scandir( $inner ), array( '.', '..', '.DS_Store', '__MACOSX' ) ) );
		foreach ( $inner_entries as $ie ) {
			if ( isset( $cat_map[ strtoupper( $ie ) ] ) ) {
				return $inner;
			}
		}

		$inner_dirs = array_filter( $inner_entries, function ( $ie ) use ( $inner ) {
			return is_dir( $inner . '/' . $ie );
		} );

		if ( ! empty( $inner_dirs ) ) {
			return $inner;
		}
	}

	// fallback: diretorio original.
	return $dir;
}

/**
 * Processa o upload do .zip e escaneia as pastas.
 *
 * @param string $tmp_file Caminho do .zip temporario.
 * @return arrayDados do escaneamento.
 */
function setceb_importer_scan_zip( $tmp_file ) {
	$tmp_dir = setceb_importer_tmp_dir();

	// Limpa diretorio temporario anterior.
	if ( is_dir( $tmp_dir ) ) {
		rrmdir( $tmp_dir );
	}
	wp_mkdir_p( $tmp_dir );

	// Extrai o .zip.
	$zip = new ZipArchive();
	$res = $zip->open( $tmp_file );

	if ( true !== $res ) {
		return array( 'error' => 'Falha ao abrir o ZIP (erro #' . $res . ').' );
	}

	$zip->extractTo( $tmp_dir );
	$zip->close();

	// Encontra a pasta raiz (com suporte a wrapper e níveis extras).
	$cat_map = setceb_importer_categoria_map();
	$root    = setceb_importer_find_root( $tmp_dir, $cat_map );

	$pastas     = array();
	$total_files = 0;

	// Scaneia subdiretorios.
	$subentries = array_diff( scandir( $root ), array( '.', '..', '.DS_Store', '__MACOSX' ) );

	foreach ( $subentries as $entry ) {
		$full = $root . '/' . $entry;

		if ( ! is_dir( $full ) ) {
			continue;
		}

		$upper       = strtoupper( $entry );
		$slug_cat    = isset( $cat_map[ $upper ] ) ? $cat_map[ $upper ] : '';
		$term        = $slug_cat ? get_term_by( 'slug', $slug_cat, 'setceb_cat_doc' ) : false;
		$cat_label   = $term ? $term->name : ( $slug_cat ? $slug_cat : $entry );

		$pdfs = array_unique( array_merge(
			glob( $full . '/*.pdf' ),
			glob( $full . '/*.PDF' )
		) );

		$count = count( $pdfs );
		$total_files += $count;

		$pastas[] = array(
			'pasta'     => $entry,
			'slug'      => $slug_cat,
			'cat_label' => $cat_label,
			'count'     => $count,
			'path'      => $full,
			'mapeada'   => '' !== $slug_cat,
		);
	}

	return array(
		'root'        => $root,
		'pastas'      => $pastas,
		'total_files' => $total_files,
	);
}

/**
 * Renderiza a pagina admin.
 */
function setceb_importer_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'form';

	// -------------------------------------------------------
	// ACAO: Importar
	// -------------------------------------------------------
	if ( 'import' === $action && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_importar' ) ) {

		set_time_limit( 600 );
		ini_set( 'memory_limit', '512M' );

		$tmp_dir = setceb_importer_tmp_dir();

		if ( ! is_dir( $tmp_dir ) ) {
			echo '<div class="notice notice-error"><p>Diretorio temporario nao encontrado. Faca o upload novamente.</p></div>';
			return;
		}

		// Encontra a pasta raiz (mesma logica do scan).
		$cat_map = setceb_importer_categoria_map();
		$root    = setceb_importer_find_root( $tmp_dir, $cat_map );

		$total = 0;
		$erros = array();

		$subentries = array_diff( scandir( $root ), array( '.', '..', '.DS_Store', '__MACOSX' ) );

		echo '<div class="wrap"><h1>Importando Planilhas...</h1>';
		echo '<div id="import-log" style="background:#fff;padding:15px;border:1px solid #ccc;border-radius:4px;max-height:500px;overflow-y:auto;font-family:monospace;font-size:13px;">';

		foreach ( $subentries as $entry ) {
			$full = $root . '/' . $entry;
			if ( ! is_dir( $full ) ) {
				continue;
			}

			$upper    = strtoupper( $entry );
			$slug_cat = isset( $cat_map[ $upper ] ) ? $cat_map[ $upper ] : '';
			$term     = $slug_cat ? get_term_by( 'slug', $slug_cat, 'setceb_cat_doc' ) : false;
			$cname    = $term ? $term->name : $entry;

			$pdfs = array_unique( array_merge( glob( $full . '/*.pdf' ), glob( $full . '/*.PDF' ) ) );
			sort( $pdfs );

			echo '<p><strong>' . esc_html( $entry ) . ' → ' . esc_html( $cname ) . '</strong></p>';

			foreach ( $pdfs as $file ) {
				$fn  = basename( $file );
				$ano = setceb_importer_extract_year( $fn );
				$tit = setceb_importer_extract_title( $fn );
				$att = setceb_importer_upload( $file );

				if ( ! $att ) {
					$erros[] = $entry . '/' . $fn;
					echo '<p style="color:#e74c3c;margin:2px 0;">ERRO: ' . esc_html( $fn ) . '</p>';
					continue;
				}

				$url = wp_get_attachment_url( $att );
				$pid = wp_insert_post( array(
					'post_type'    => 'setceb_planilha',
					'post_title'   => $tit,
					'post_status'  => 'publish',
					'post_content' => '',
				) );

				if ( is_wp_error( $pid ) || 0 === $pid ) {
					$erros[] = $entry . '/' . $fn . ' (post)';
					wp_delete_attachment( $att, true );
					echo '<p style="color:#e74c3c;margin:2px 0;">ERRO POST: ' . esc_html( $fn ) . '</p>';
					continue;
				}

				update_post_meta( $pid, '_setceb_doc_url', $url );
				update_post_meta( $pid, '_setceb_doc_ano', $ano );

				if ( $term ) {
					wp_set_post_terms( $pid, array( $term->term_id ), 'setceb_cat_doc' );
				}

				$total++;
			}
		}

		echo '</div>';

		// Limpa o temporario.
		rrmdir( $tmp_dir );

		echo '<div class="notice notice-success"><p><strong>' . $total . ' planilhas</strong> importadas com sucesso.';

		if ( $erros ) {
			echo ' <span style="color:#e74c3c">(' . count( $erros ) . ' erros)</span>';
		}

		echo '</p></div>';

		if ( $erros ) {
			echo '<div style="background:#fff;padding:15px;border:1px solid #ccc;border-radius:4px;font-family:monospace;font-size:12px;margin-top:10px;">';
			echo '<p><strong>Arquivos com erro:</strong></p>';
			echo '<pre>' . esc_html( implode( "\n", $erros ) ) . '</pre>';
			echo '</div>';
		}

		echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Nova Importacao</a></p>';
		echo '</div>';
		return;
	}

	// -------------------------------------------------------
	// ACAO: Upload por categoria - Escanear (preview)
	// -------------------------------------------------------
	if ( 'cat-scan' === $action && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_cat_upload' ) ) {

		if ( empty( $_FILES['cat_zip']['tmp_name'] ) || ! is_uploaded_file( $_FILES['cat_zip']['tmp_name'] ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Selecione um arquivo .zip para enviar.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		$tmp_file = $_FILES['cat_zip']['tmp_name'];

		$cat_slug = isset( $_POST['cat_slug'] ) ? sanitize_key( $_POST['cat_slug'] ) : '';
		$term     = $cat_slug ? get_term_by( 'slug', $cat_slug, 'setceb_cat_doc' ) : false;

		if ( ! $term ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Selecione uma categoria valida.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		$ext = strtolower( pathinfo( $_FILES['cat_zip']['name'], PATHINFO_EXTENSION ) );
		if ( 'zip' !== $ext ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Envie um arquivo .zip</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		$scan = setceb_importer_scan_single_zip( $tmp_file );

		if ( isset( $scan['error'] ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $scan['error'] ) . '</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		if ( empty( $scan['pdfs'] ) ) {
			echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhum arquivo PDF encontrado no .zip.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		// Prepara a lista marcando duplicatas.
		$cat_scan = array(
			'root'     => $scan['root'],
			'cat_slug' => $term->slug,
			'cat_name' => $term->name,
			'term_id'  => $term->term_id,
			'files'    => array(),
		);

		$novos    = 0;
		$duplic   = 0;

		foreach ( $scan['pdfs'] as $file ) {
			$bn       = basename( $file );
			$is_dup   = setceb_importer_duplicate_check( $bn, $term->term_id );
			$cat_scan['files'][] = array(
				'path'   => $file,
				'name'   => $bn,
				'dup'    => $is_dup,
			);
			$is_dup ? $duplic++ : $novos++;
		}

		set_transient( 'setceb_importer_cat_scan', $cat_scan, 300 );

		?>
		<div class="wrap">
			<h1>Importar para Categoria — Confirmar</h1>

			<p>Destino: <strong><?php echo esc_html( $term->name ); ?></strong></p>
			<p>Total de PDFs: <strong><?php echo count( $cat_scan['files'] ); ?></strong>
				&#183; Novos: <strong style="color:#46b450"><?php echo $novos; ?></strong>
				&#183; Ja existentes (serao ignorados): <strong style="color:#dc3232"><?php echo $duplic; ?></strong></p>

			<table class="widefat striped" style="max-width:700px;">
				<thead>
					<tr>
						<th>Arquivo</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cat_scan['files'] as $f ) : ?>
						<tr>
							<td><?php echo esc_html( $f['name'] ); ?></td>
							<td>
								<?php if ( $f['dup'] ) : ?>
									<span style="color:#dc3232">Ja existe</span>
								<?php else : ?>
									<span style="color:#46b450">Novo</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $novos > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas&action=cat-import' ) ); ?>" style="margin-top:20px;" onsubmit="return confirm('Importar <?php echo $novos; ?> arquivo(s) para <?php echo esc_js( $term->name ); ?>?')">
					<?php wp_nonce_field( 'setceb_cat_import' ); ?>
					<?php submit_button( 'Importar ' . $novos . ' Novo(s)', 'primary large' ); ?>
				</form>
			<?php else : ?>
				<div class="notice notice-success" style="margin-top:20px;"><p>Nenhum arquivo novo. Todos os PDFs do .zip ja existem nesta categoria.</p></div>
			<?php endif; ?>

			<p><a href="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ); ?>">Cancelar e enviar outro arquivo</a></p>
		</div>
		<?php
		return;
	}

	// -------------------------------------------------------
	// ACAO: Upload por categoria - Importar
	// -------------------------------------------------------
	if ( 'cat-import' === $action && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_cat_import' ) ) {

		set_time_limit( 600 );
		ini_set( 'memory_limit', '512M' );

		$cat_scan = get_transient( 'setceb_importer_cat_scan' );

		if ( empty( $cat_scan ) || empty( $cat_scan['files'] ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Dados do arquivo expirados. Faca o upload novamente.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		$term = get_term_by( 'id', $cat_scan['term_id'], 'setceb_cat_doc' );

		if ( ! $term ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Categoria invalida.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		echo '<div class="wrap"><h1>Importando para ' . esc_html( $term->name ) . '...</h1>';
		echo '<div id="import-log" style="background:#fff;padding:15px;border:1px solid #ccc;border-radius:4px;max-height:500px;overflow-y:auto;font-family:monospace;font-size:13px;">';

		$total  = 0;
		$puladas = 0;
		$erros   = array();

		foreach ( $cat_scan['files'] as $f ) {
			$file = $f['path'];
			$bn   = $f['name'];

			if ( $f['dup'] || ! file_exists( $file ) ) {
				$puladas++;
				if ( $f['dup'] ) {
					echo '<p style="color:#999;margin:2px 0;">IGNORADO (ja existe): ' . esc_html( $bn ) . '</p>';
				}
				continue;
			}

			// Re-checa duplicidade (pode ter sido importado antes).
			if ( setceb_importer_duplicate_check( $bn, $term->term_id ) ) {
				$puladas++;
				echo '<p style="color:#999;margin:2px 0;">IGNORADO (ja existe): ' . esc_html( $bn ) . '</p>';
				continue;
			}

			$ano = setceb_importer_extract_year( $bn );
			$tit = setceb_importer_extract_title( $bn );
			$att = setceb_importer_upload( $file );

			if ( ! $att ) {
				$erros[] = $bn . ' (upload)';
				echo '<p style="color:#e74c3c;margin:2px 0;">ERRO: ' . esc_html( $bn ) . '</p>';
				continue;
			}

			$url = wp_get_attachment_url( $att );
			$pid = wp_insert_post( array(
				'post_type'    => 'setceb_planilha',
				'post_title'   => $tit,
				'post_status'  => 'publish',
				'post_content' => '',
			) );

			if ( is_wp_error( $pid ) || 0 === $pid ) {
				$erros[] = $bn . ' (post)';
				wp_delete_attachment( $att, true );
				echo '<p style="color:#e74c3c;margin:2px 0;">ERRO POST: ' . esc_html( $bn ) . '</p>';
				continue;
			}

			update_post_meta( $pid, '_setceb_doc_url', $url );
			update_post_meta( $pid, '_setceb_doc_ano', $ano );
			wp_set_post_terms( $pid, array( $term->term_id ), 'setceb_cat_doc' );

			$total++;
			echo '<p style="color:#46b450;margin:2px 0;">OK: ' . esc_html( $bn ) . '</p>';
		}

		echo '</div>';

		rrmdir( $cat_scan['root'] );
		delete_transient( 'setceb_importer_cat_scan' );

		echo '<div class="notice notice-success"><p><strong>' . $total . ' arquivo(s)</strong> importado(s) para ' . esc_html( $term->name ) . '. <span style="color:#999">(' . $puladas . ' ignorado(s) como duplicado)</span>';

		if ( $erros ) {
			echo ' <span style="color:#e74c3c">(' . count( $erros ) . ' erros)</span>';
		}

		echo '</p></div>';

		if ( $erros ) {
			echo '<div style="background:#fff;padding:15px;border:1px solid #ccc;border-radius:4px;font-family:monospace;font-size:12px;margin-top:10px;">';
			echo '<p><strong>Arquivos com erro:</strong></p>';
			echo '<pre>' . esc_html( implode( "\n", $erros ) ) . '</pre>';
			echo '</div>';
		}

		echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Nova Importacao</a></p>';
		echo '</div>';
		return;
	}

	// -------------------------------------------------------
	// ACAO: Escanear (preview)
	// -------------------------------------------------------
	if ( 'scan' === $action && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_upload' ) ) {

		if ( empty( $_FILES['zip_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['zip_file']['tmp_name'] ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Selecione um arquivo .zip para enviar.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		$tmp_file = $_FILES['zip_file']['tmp_name'];

		if ( 'application/zip' !== $_FILES['zip_file']['type'] && 'application/x-zip-compressed' !== $_FILES['zip_file']['type'] ) {
			$ext = strtolower( pathinfo( $_FILES['zip_file']['name'], PATHINFO_EXTENSION ) );
			if ( 'zip' !== $ext ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>Envie um arquivo .zip</p></div>';
				echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
				return;
			}
		}

		$scan = setceb_importer_scan_zip( $tmp_file );

		if ( isset( $scan['error'] ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $scan['error'] ) . '</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		// Salva o scan em transient para usar no proximo passo.
		set_transient( 'setceb_importer_scan', $scan, 300 );

		?>
		<div class="wrap">
			<h1>Importar Planilhas — Confirmar</h1>

			<p>Arquivo processado. Pastas encontradas:</p>

			<table class="widefat striped" style="max-width:700px;">
				<thead>
					<tr>
						<th>Pasta</th>
						<th>Categoria</th>
						<th style="text-align:right">Arquivos</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $scan['pastas'] as $p ) : ?>
						<tr>
							<td><?php echo esc_html( $p['pasta'] ); ?></td>
							<td>
								<?php if ( $p['slug'] ) : ?>
									<?php echo esc_html( $p['cat_label'] ); ?>
								<?php else : ?>
									<span style="color:#e74c3c">Nao mapeada</span>
								<?php endif; ?>
							</td>
							<td style="text-align:right"><?php echo $p['count']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td><strong>Total</strong></td>
						<td></td>
						<td style="text-align:right"><strong><?php echo $scan['total_files']; ?></strong></td>
					</tr>
				</tfoot>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas&action=import' ) ); ?>" style="margin-top:20px;" onsubmit="return confirm('Importar <?php echo $scan['total_files']; ?> planilhas?')">
				<?php wp_nonce_field( 'setceb_importar' ); ?>
				<?php submit_button( 'Importar ' . $scan['total_files'] . ' Planilhas', 'primary large' ); ?>
			</form>

			<p><a href="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas' ) ); ?>">Cancelar e enviar outro arquivo</a></p>
		</div>
		<?php
		return;
	}

	// -------------------------------------------------------
	// ACAO: Form (padrao)
	// -------------------------------------------------------
	?>
	<div class="wrap">
		<h1>Importar Planilhas</h1>

		<p>Ferramenta para importar planilhas PDF da area do associado.</p>

		<div class="card" style="max-width:600px;padding:20px;">
			<h2>Como usar</h2>
			<ol>
				<li>Coloque todas as pastas de categorias dentro de um arquivo <strong>.zip</strong></li>
				<li>Pastas esperadas: <code>CONTEINER</code>, <code>FRACIONADA</code>, <code>GRANEIS E SOLIDOS</code>, <code>GRAOS</code>, <code>INTERNACIONAL</code>, <code>LIQUIDA</code>, <code>LOTACAO</code>, <code>MAQUINAS E EQUIPAMENTOS</code>, <code>INCTF</code></li>
				<li>Envie o .zip abaixo</li>
				<li>Revise o preview e confirme a importacao</li>
			</ol>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas&action=scan' ) ); ?>">
				<?php wp_nonce_field( 'setceb_upload' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="zip_file">Arquivo .zip</label></th>
						<td>
							<input type="file" name="zip_file" id="zip_file" accept=".zip" required style="font-size:14px;">
							<p class="description">Tamanho maximo: <?php echo ini_get( 'upload_max_filesize' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Enviar e Visualizar', 'primary large' ); ?>
			</form>
		</div>

		<div class="card" style="max-width:600px;padding:20px;margin-top:15px;">
			<h2>Adicionar arquivos a uma categoria</h2>
			<p>Envie um <strong>.zip</strong> com PDFs e escolha a categoria de destino. Arquivos que ja existirem na categoria serao ignorados (sem duplicidade).</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-planilhas&action=cat-scan' ) ); ?>">
				<?php wp_nonce_field( 'setceb_cat_upload' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="cat_slug">Categoria</label></th>
						<td>
							<select name="cat_slug" id="cat_slug" required style="font-size:14px;min-width:220px;">
								<option value="">— Selecionar categoria —</option>
								<?php foreach ( setceb_importer_categorias_ui() as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cat_zip">Arquivo .zip</label></th>
						<td>
							<input type="file" name="cat_zip" id="cat_zip" accept=".zip" required style="font-size:14px;">
							<p class="description">Somente PDFs serao considerados (qualquer pasta dentro do .zip).</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Enviar e Visualizar', 'secondary large' ); ?>
			</form>
		</div>

		<div class="card" style="max-width:600px;padding:20px;margin-top:15px;">
			<h2>Mapeamento de Categorias</h2>
			<table class="widefat" style="margin-top:10px;">
				<thead>
					<tr><th>Pasta no .zip</th><th>Categoria WP</th></tr>
				</thead>
				<tbody>
					<tr><td>CONTEINER</td><td>Container</td></tr>
					<tr><td>FRACIONADA</td><td>Fracionada</td></tr>
					<tr><td>GRANEIS E SOLIDOS</td><td>Graos e Solidos</td></tr>
					<tr><td>GRAOS</td><td>Graos e Solidos</td></tr>
					<tr><td>INTERNACIONAL</td><td>Internacional</td></tr>
					<tr><td>LIQUIDA</td><td>Liquida</td></tr>
					<tr><td>LOTACAO</td><td>Lotacao</td></tr>
					<tr><td>MAQUINAS E EQUIPAMENTOS</td><td>Maquinas e Equipamentos</td></tr>
					<tr><td>INCTF</td><td>Graos e Solidos</td></tr>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}

/**
 * Retorna a lista de categorias disponiveis (slug => rotulo) para uso
 * no seletor de upload por categoria.
 *
 * @return array slug => rotulo
 */
function setceb_importer_categorias_ui() {
	$categorias = array();

	if ( taxonomy_exists( 'setceb_cat_doc' ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'setceb_cat_doc',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categorias[ $term->slug ] = $term->name;
			}
		}
	}

	// Fallback caso a taxonomia ainda nao tenha termos.
	if ( empty( $categorias ) && function_exists( 'setceb_associado_categorias' ) ) {
		$categorias = setceb_associado_categorias();
	}

	return $categorias;
}

/**
 * Verifica se ja existe um documento da categoria com o mesmo arquivo.
 *
 * O arquivo importado e copiado para a midia com o nome original; o
 * post guarda a URL em _setceb_doc_url. Comparamos pelo basename.
 *
 * @param string $basename Nome do arquivo (ex.: "2024.pdf").
 * @param int    $term_id  ID do termo da categoria.
 * @return bool True se ja existe.
 */
function setceb_importer_duplicate_check( $basename, $term_id ) {
	$basename = trim( $basename );

	if ( '' === $basename || ! $term_id ) {
		return false;
	}

	$q = new WP_Query(
		array(
			'post_type'      => 'setceb_planilha',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'suppress_filters' => false,
			'meta_query'     => array(
				array(
					'key'     => '_setceb_doc_url',
					'value'   => $basename,
					'compare' => 'LIKE',
				),
			),
			'tax_query'      => array(
				array(
					'taxonomy' => 'setceb_cat_doc',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		)
	);

	return $q->have_posts();
}

/**
 * Extrai recursivamente todos os PDFs de um diretorio (qualquer
 * profundidade), para o upload por categoria.
 *
 * @param string $dir Diretorio a varrer.
 * @return string[] Caminhos completos dos PDFs.
 */
function setceb_importer_find_pdfs_recursive( $dir ) {
	$found = array();

	if ( ! is_dir( $dir ) ) {
		return $found;
	}

	$items = array_diff( scandir( $dir ), array( '.', '..', '.DS_Store', '__MACOSX' ) );

	foreach ( $items as $item ) {
		$path = $dir . '/' . $item;

		if ( is_dir( $path ) ) {
			$found = array_merge( $found, setceb_importer_find_pdfs_recursive( $path ) );
		} elseif ( preg_match( '/\.pdf$/i', $item ) ) {
			$found[] = $path;
		}
	}

	return $found;
}

/**
 * Extrai um .zip e devolve todos os PDFs contidos nele (recursivo
 * e independente do nome das pastas). Usado pelo upload por categoria.
 *
 * @param string $tmp_file Caminho do .zip temporario.
 * @return array(array) list|false| Contem 'root', 'pdfs' e 'error'.
 */
function setceb_importer_scan_single_zip( $tmp_file ) {
	$tmp_dir = setceb_importer_tmp_dir();

	if ( is_dir( $tmp_dir ) ) {
		rrmdir( $tmp_dir );
	}
	wp_mkdir_p( $tmp_dir );

	$zip = new ZipArchive();
	$res = $zip->open( $tmp_file );

	if ( true !== $res ) {
		return array( 'error' => 'Falha ao abrir o ZIP (erro #' . $res . ').' );
	}

	$zip->extractTo( $tmp_dir );
	$zip->close();

	return array(
		'root' => $tmp_dir,
		'pdfs' => setceb_importer_find_pdfs_recursive( $tmp_dir ),
	);
}

/**
 * Remove diretorio recursivamente.
 */
function rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = array_diff( scandir( $dir ), array( '.', '..' ) );
	foreach ( $items as $item ) {
		$path = $dir . '/' . $item;
		is_dir( $path ) ? rrmdir( $path ) : unlink( $path );
	}
	rmdir( $dir );
}
