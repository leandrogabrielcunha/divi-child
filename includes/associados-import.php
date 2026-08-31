<?php
/**
 * SETCEB - Importacao de Associados
 *
 * Pagina admin em Ferramentas > Importar Associados.
 * Permite enviar um arquivo .csv (colunas NOME,E-MAIL,SENHA) para
 * cadastrar/atualizar usuarios com o perfil de associado, usando
 * a role "associado" registrada em includes/associado.php.
 *
 * Carregado por functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra o menu Ferramentas > Importar Associados.
 */
function setceb_associados_import_menu() {
	add_management_page(
		'Importar Associados',
		'Importar Associados',
		'manage_options',
		'setceb-importar-associados',
		'setceb_associados_import_page'
	);
}
add_action( 'admin_menu', 'setceb_associados_import_menu' );

/**
 * Le um arquivo .csv (enviado via upload ou caminho local) e devolve
 * um array de linhas associadas (nome, email, senha).
 *
 * Headers aceitos: NOME, E-MAIL, SENHA (ordem/index ou cabecalho).
 *
 * @param string $path Caminho do arquivo a ler.
 * @return array{linhas:array<int,array{nome:string,email:string,senha:string}>, erros:array<int,string>}
 */
function setceb_associados_import_parse( $path ) {
	$out = array(
		'linhas' => array(),
		'erros'  => array(),
	);

	if ( ! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
		$out['erros'][] = 'Arquivo nao encontrado ou sem permissao de leitura.';
		return $out;
	}

	$raw = file_get_contents( $path );
	if ( false === $raw ) {
		$out['erros'][] = 'Nao foi possivel ler o arquivo.';
		return $out;
	}

	// Normaliza BOM UTF-8 e quebras de linha.
	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$raw = str_replace( "\r\n", "\n", $raw );
	$raw = str_replace( "\r", "\n", $raw );

	$rows = array_map( 'trim', explode( "\n", $raw ) );
	$rows = array_values( array_filter( $rows, function ( $r ) {
		return '' !== $r;
	} ) );

	if ( empty( $rows ) ) {
		$out['erros'][] = 'O arquivo esta vazio.';
		return $out;
	}

	// Mapas de coluna pelo cabecalho (se presente), senao por posicao.
	$has_header = false;
	$col        = array( 'nome' => 0, 'email' => 1, 'senha' => 2 );

	$first = str_getcsv( $rows[0] );
	if ( is_array( $first ) && ! empty( $first ) && preg_match( '/(nome|email|e-?mail|senha)/i', implode( ' ', $first ) ) ) {
		$has_header  = true;
		$header_low  = array_map( function ( $h ) {
			return strtolower( preg_replace( '/[^a-z0-9]/', '', $h ) );
		}, $first );

		$find        = function ( $name ) use ( $header_low ) {
			$idx = array_search( $name, $header_low, true );
			return ( false === $idx ) ? null : $idx;
		};

		$map = array(
			'nome'  => $find( 'nome' ),
			'email' => $find( 'email' ),
			'senha' => $find( 'senha' ),
		);

		foreach ( array( 'nome', 'email', 'senha' ) as $k ) {
			if ( null === $map[ $k ] ) {
				$map[ $k ] = $col[ $k ];
			}
		}

		$col = $map;
	}

	$offset = $has_header ? 1 : 0;

	foreach ( array_slice( $rows, $offset ) as $i => $csvline ) {
		$idx   = $i + $offset + 1;
		$field = str_getcsv( $csvline );

		if ( ! is_array( $field ) || empty( $field ) ) {
			continue;
		}

		$nome  = isset( $field[ $col['nome'] ] ) ? trim( $field[ $col['nome'] ] ) : '';
		$email = isset( $field[ $col['email'] ] ) ? trim( $field[ $col['email'] ] ) : '';
		$senha = isset( $field[ $col['senha'] ] ) ? trim( $field[ $col['senha'] ] ) : '';

		if ( '' === $email ) {
			$out['erros'][] = "Linha {$idx}: sem e-mail, ignorada.";
			continue;
		}

		$out['linhas'][] = array(
			'nome'  => $nome,
			'email' => $email,
			'senha' => $senha,
		);
	}

	return $out;
}

/**
 * Renderiza a pagina admin (form + processamento).
 */
function setceb_associados_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'form';

	// -------------------------------------------------------
	// ACAO: Importar (processa o upload do CSV)
	// -------------------------------------------------------
	if ( 'import' === $action && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'setceb_associados_import' ) ) {

		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Selecione um arquivo .csv para enviar.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-associados' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		$ext = strtolower( pathinfo( $_FILES['csv_file']['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext && 'txt' !== $ext ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Envie um arquivo .csv (ou .txt com colunas NOME,E-MAIL,SENHA).</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-associados' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		$parsed = setceb_associados_import_parse( $_FILES['csv_file']['tmp_name'] );

		if ( ! empty( $parsed['erros'] ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Problemas encontrados ao ler o arquivo:</p>';
			echo '<ul style="margin:8px 0 0 18px;list-style:disc;">';
			foreach ( $parsed['erros'] as $e ) {
				echo '<li>' . esc_html( $e ) . '</li>';
			}
			echo '</ul></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-associados' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		if ( empty( $parsed['linhas'] ) ) {
			echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhuma linha valida encontrada no arquivo.</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=setceb-importar-associados' ) ) . '" class="button">Voltar</a></p></div>';
			return;
		}

		set_time_limit( 300 );

		$criados    = 0;
		$atualizados = 0;
		$nao_alterados = 0;
		$sem_senha  = 0;
		$erros      = array();

		foreach ( $parsed['linhas'] as $row ) {
			$email  = sanitize_email( $row['email'] );
			$nome   = sanitize_text_field( $row['nome'] );
			$senha  = $row['senha'];

			if ( ! is_email( $email ) ) {
				$erros[] = $email . ' — e-mail invalido';
				continue;
			}

			$existing = get_user_by( 'email', $email );

			if ( $existing ) {
				$uid       = $existing->ID;
				$já_assoc  = setceb_is_associado( $existing );
				$novo_nome = $nome !== '' && $nome !== $existing->display_name;

				if ( $já_assoc && ! $novo_nome ) {
					$nao_alterados++;
					continue;
				}

				$upd = array( 'ID' => $existing->ID );

				if ( $novo_nome ) {
					$upd['display_name'] = $nome;
					$upd['first_name']   = $nome;
				}

				if ( $senha !== '' ) {
					$upd['user_pass'] = $senha;
				}

				$result = wp_update_user( $upd );

				if ( is_wp_error( $result ) ) {
					$erros[] = $email . ' — ' . $result->get_error_message();
					continue;
				}

				$existing->set_role( 'associado' );
				$atualizados++;
				$uid = $result;
			} else {
				if ( $senha === '' ) {
					$sem_senha++;
					$erros[] = $email . ' — sem senha (usuario nao criado)';
					continue;
				}

				$uarr  = array(
					'user_login'    => $email,
					'user_email'    => $email,
					'user_pass'     => $senha,
					'display_name'  => $nome,
					'first_name'    => $nome,
					'role'          => 'associado',
				);
				$result = wp_insert_user( $uarr );

				if ( is_wp_error( $result ) ) {
					// Nome de login ja usado por outro usuario com outro e-mail.
					if ( $result->get_error_code() === 'existing_user_login' ) {
						$existing = get_user_by( 'login', $email );
						if ( $existing ) {
							$existing->set_role( 'associado' );
							$upd2 = array( 'ID' => $existing->ID, 'user_email' => $email );
							if ( $senha !== '' ) {
								$upd2['user_pass'] = $senha;
							}
							$r2 = wp_update_user( $upd2 );
							if ( is_wp_error( $r2 ) ) {
								$erros[] = $email . ' — ' . $r2->get_error_message();
							} else {
								$atualizados++;
							}
							continue;
						}
					}
					$erros[] = $email . ' — ' . $result->get_error_message();
					continue;
				}

				$criados++;
				$uid = $result;
			}

			if ( isset( $uid ) && $uid && is_numeric( $uid ) ) {
				update_user_meta( (int) $uid, 'setceb_data_importacao', current_time( 'mysql' ) );
			}
		}

		?>
		<div class="wrap">
			<h1>Resultado da Importacao de Associados</h1>

			<div class="notice notice-success">
				<p>
					<strong><?php echo (int) $criados; ?></strong> associado(s) criado(s) &#183;
					<strong><?php echo (int) $atualizados; ?></strong> atualizado(s) &#183;
					<strong><?php echo (int) $nao_alterados; ?></strong> inalterado(s) &#183;
					<strong><?php echo (int) $sem_senha; ?></strong> sem senha (nao criados)
				</p>
			</div>

			<?php if ( $erros ) : ?>
				<div style="background:#fff;padding:15px;border:1px solid #ccc;border-radius:4px;font-family:monospace;font-size:12px;margin-bottom:15px;">
					<p><strong>Ocorrencias com erro:</strong></p>
					<pre><?php echo esc_html( implode( "\n", $erros ) ); ?></pre>
				</div>
			<?php endif; ?>

			<p><a href="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-associados' ) ); ?>" class="button">Nova Importacao</a></p>
		</div>
		<?php
		return;
	}

	// -------------------------------------------------------
	// ACAO: Form (padrao)
	// -------------------------------------------------------
	?>
	<div class="wrap">
		<h1>Importar Associados</h1>

		<p>Cadastra/atualiza usuarios com o perfil de <strong>associado</strong> a partir de um arquivo <code>.csv</code>.</p>

		<div class="card" style="max-width:640px;padding:20px;">
			<h2>Formato do arquivo</h2>
			<p>O arquivo deve ter as colunas abaixo (com ou sem cabeçalho):</p>
			<table class="widefat" style="max-width:360px;margin:10px 0;">
				<thead>
					<tr><th>NOME</th><th>E-MAIL</th><th>SENHA</th></tr>
				</thead>
				<tbody>
					<tr>
						<td>MARIA DA SILVA</td>
						<td>maria@exemplo.com</td>
						<td>123456</td>
					</tr>
				</tbody>
			</table>
			<ul style="list-style:disc;padding-left:20px;">
				<li>Se o e-mail já existir, o usuário é atualizado (nome, senha e perfil associado).</li>
				<li>E-mails duplicados ou inválidos são ignorados com aviso.</li>
				<li>Linhas sem senha, quando o usuário ainda não existe, não são criadas.</li>
			</ul>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'tools.php?page=setceb-importar-associados&action=import' ) ); ?>">
				<?php wp_nonce_field( 'setceb_associados_import' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="csv_file">Arquivo .csv</label></th>
						<td>
							<input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" required style="font-size:14px;">
							<p class="description">UTF-8 recomendado. Tamanho maximo: <?php echo ini_get( 'upload_max_filesize' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Importar Associados', 'primary large' ); ?>
			</form>
		</div>
	</div>
	<?php
}
