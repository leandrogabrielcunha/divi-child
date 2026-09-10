<?php
/**
 * CE Tech - Cobertura (mapa de cidades atendidas)
 *
 * Registra o CPT "Cidades atendidas", os campos de localizacao no editor
 * (UF, latitude, longitude e bairros com busca automatica de coordenadas)
 * e expoe o shortcode [cetech_cobertura] que renderiza um mapa Leaflet
 * do Brasil focado em Sao Paulo com pins animados nas cidades atendidas.
 *
 * Carregado em functions.php.
 *
 * @package CE Tech Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * Constantes
 * ------------------------------------------------------------ */
define( 'CETECH_CIDADE_CPT', 'cetech_cidade' );
define( 'CETECH_COBERTURA_CSS', 'cetech-cobertura' );
define( 'CETECH_COBERTURA_ADMIN_JS', 'cetech-cobertura-admin' );

/* Limites da projecao equirretangular do mapa SVG (SP + vizinhos). */
define( 'CETECH_MAP_LON_MIN', -58.8992 );
define( 'CETECH_MAP_LON_MAX', -39.1240 );
define( 'CETECH_MAP_LAT_MAX', -13.5004 );
define( 'CETECH_MAP_LAT_MIN', -27.4492 );
define( 'CETECH_MAP_WIDTH', 900.0 );

/* ------------------------------------------------------------
 * 1. Registro do CSS do front-end (mapa SVG)
 * ------------------------------------------------------------ */
function cetech_cobertura_register_assets() {
	wp_register_style(
		CETECH_COBERTURA_CSS,
		get_stylesheet_directory_uri() . '/assets/css/cetech-cobertura.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'cetech_cobertura_register_assets' );

/* ------------------------------------------------------------
 * 2. Lista de estados (UF => nome)
 * ------------------------------------------------------------ */
function cetech_cobertura_states() {
	return array(
		'AC' => 'Acre',
		'AL' => 'Alagoas',
		'AP' => 'Amapá',
		'AM' => 'Amazonas',
		'BA' => 'Bahia',
		'CE' => 'Ceará',
		'DF' => 'Distrito Federal',
		'ES' => 'Espírito Santo',
		'GO' => 'Goiás',
		'MA' => 'Maranhão',
		'MT' => 'Mato Grosso',
		'MS' => 'Mato Grosso do Sul',
		'MG' => 'Minas Gerais',
		'PA' => 'Pará',
		'PB' => 'Paraíba',
		'PR' => 'Paraná',
		'PE' => 'Pernambuco',
		'PI' => 'Piauí',
		'RJ' => 'Rio de Janeiro',
		'RN' => 'Rio Grande do Norte',
		'RS' => 'Rio Grande do Sul',
		'RO' => 'Rondônia',
		'RR' => 'Roraima',
		'SC' => 'Santa Catarina',
		'SP' => 'São Paulo',
		'SE' => 'Sergipe',
		'TO' => 'Tocantins',
	);
}

/* ------------------------------------------------------------
 * 3. Registro do Custom Post Type "Cidades atendidas"
 * ------------------------------------------------------------ */
function cetech_cobertura_register_post_type() {
	$labels = array(
		'name'               => _x( 'Cidades atendidas', 'post type general name', 'Divi' ),
		'singular_name'      => _x( 'Cidade', 'post type singular name', 'Divi' ),
		'menu_name'          => __( 'Cobertura', 'Divi' ),
		'add_new'            => __( 'Adicionar nova', 'Divi' ),
		'add_new_item'       => __( 'Adicionar nova cidade', 'Divi' ),
		'edit_item'          => __( 'Editar cidade', 'Divi' ),
		'new_item'           => __( 'Nova cidade', 'Divi' ),
		'view_item'          => __( 'Ver cidade', 'Divi' ),
		'search_items'       => __( 'Pesquisar cidades', 'Divi' ),
		'not_found'          => __( 'Nenhuma cidade cadastrada.', 'Divi' ),
		'not_found_in_trash' => __( 'Nenhuma cidade na lixeira.', 'Divi' ),
		'all_items'          => __( 'Todas as cidades', 'Divi' ),
	);

	register_post_type(
		CETECH_CIDADE_CPT,
		array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 8,
			'menu_icon'       => 'dashicons-location-alt',
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title' ),
			'rewrite'         => false,
			'show_in_rest'    => false,
			'hierarchical'    => false,
			'has_archive'     => false,
			'query_var'       => false,
		)
	);
}
add_action( 'init', 'cetech_cobertura_register_post_type' );

/* ------------------------------------------------------------
 * 4. Colunas da listagem
 * ------------------------------------------------------------ */
function cetech_cobertura_columns( $columns ) {
	$new_columns = array(
		'cb'               => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'            => isset( $columns['title'] ) ? $columns['title'] : __( 'Cidade', 'Divi' ),
		'cetech_uf'        => __( 'UF', 'Divi' ),
		'cetech_coords'    => __( 'Coordenadas', 'Divi' ),
	);

	return $new_columns;
}
add_filter( 'manage_' . CETECH_CIDADE_CPT . '_posts_columns', 'cetech_cobertura_columns' );

function cetech_cobertura_render_column( $column, $post_id ) {
	switch ( $column ) {
		case 'cetech_uf':
			echo esc_html( strtoupper( (string) get_post_meta( $post_id, '_cetech_cidade_uf', true ) ) );
			break;

		case 'cetech_coords':
			$lat = get_post_meta( $post_id, '_cetech_cidade_lat', true );
			$lng = get_post_meta( $post_id, '_cetech_cidade_lng', true );
			if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
				echo esc_html( number_format( (float) $lat, 4 ) . ', ' . number_format( (float) $lng, 4 ) );
			} else {
				esc_html_e( 'Sem coordenadas', 'Divi' );
			}
			break;
	}
}
add_action( 'manage_' . CETECH_CIDADE_CPT . '_posts_custom_column', 'cetech_cobertura_render_column', 10, 2 );

/* ------------------------------------------------------------
 * 5. Metabox de localizacao
 * ------------------------------------------------------------ */
function cetech_cobertura_add_meta_box() {
	add_meta_box(
		'cetech_cobertura_fields',
		__( 'Localização no mapa', 'Divi' ),
		'cetech_cobertura_meta_box_render',
		CETECH_CIDADE_CPT,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'cetech_cobertura_add_meta_box' );

function cetech_cobertura_meta_box_render( $post ) {
	wp_nonce_field( 'cetech_cobertura_fields', 'cetech_cobertura_fields_nonce' );

	$states  = cetech_cobertura_states();
	$uf      = get_post_meta( $post->ID, '_cetech_cidade_uf', true );
	$lat     = get_post_meta( $post->ID, '_cetech_cidade_lat', true );
	$lng     = get_post_meta( $post->ID, '_cetech_cidade_lng', true );
	$bairros = get_post_meta( $post->ID, '_cetech_cidade_bairros', true );

	if ( '' === $uf ) {
		$uf = 'SP';
	}
	if ( ! is_array( $bairros ) ) {
		$bairros = array( '' );
	}
	$bairros = array_values( array_unique( array_filter( $bairros ) ) );
	if ( empty( $bairros ) ) {
		$bairros = array( '' );
	}
	?>
	<div class="cetech-cobertura-admin">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="cetech-cidade-uf"><?php esc_html_e( 'Estado (UF)', 'Divi' ); ?></label>
				</th>
				<td>
					<select name="_cetech_cidade_uf" id="cetech-cidade-uf">
						<?php foreach ( $states as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $uf, $code ); ?>><?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-cidade-lat"><?php esc_html_e( 'Latitude', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_cidade_lat" id="cetech-cidade-lat" value="<?php echo esc_attr( $lat ); ?>" placeholder="-23.5505" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-cidade-lng"><?php esc_html_e( 'Longitude', 'Divi' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="_cetech_cidade_lng" id="cetech-cidade-lng" value="<?php echo esc_attr( $lng ); ?>" placeholder="-46.6333" />
					<p class="description">
						<?php esc_html_e( 'Informe as coordenadas ou clique em "Buscar coordenadas" para preencher automaticamente pelo nome da cidade.', 'Divi' ); ?>
					</p>
					<p>
						<button type="button" class="button" id="cetech-cidade-geocode" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cetech_cobertura_geocode' ) ); ?>">
							<?php esc_html_e( 'Buscar coordenadas', 'Divi' ); ?>
						</button>
						<span id="cetech-cidade-geocode-feedback" class="description" style="display:none;"></span>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cetech-cidade-bairros"><?php esc_html_e( 'Bairros / regiões', 'Divi' ); ?></label>
				</th>
				<td>
					<?php foreach ( $bairros as $index => $bairro ) : ?>
						<input type="text" class="regular-text" name="_cetech_cidade_bairros[]" value="<?php echo esc_attr( $bairro ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Centro', 'Divi' ); ?>" style="margin-bottom:4px;" />
						<?php if ( $index < count( $bairros ) - 1 ) : ?>
							<br />
						<?php endif; ?>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Cada campo é um bairro/região. Aparecem no balão do pin no mapa.', 'Divi' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
	<?php
}

function cetech_cobertura_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['cetech_cobertura_fields_nonce'] ) || ! wp_verify_nonce( $_POST['cetech_cobertura_fields_nonce'], 'cetech_cobertura_fields' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$states = cetech_cobertura_states();

	$uf = isset( $_POST['_cetech_cidade_uf'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['_cetech_cidade_uf'] ) ) ) : 'SP';
	if ( ! isset( $states[ $uf ] ) ) {
		$uf = 'SP';
	}
	update_post_meta( $post_id, '_cetech_cidade_uf', $uf );

	$lat = isset( $_POST['_cetech_cidade_lat'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['_cetech_cidade_lat'] ) ) ) : 0;
	$lng = isset( $_POST['_cetech_cidade_lng'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['_cetech_cidade_lng'] ) ) ) : 0;

	if ( $lat >= -34 && $lat <= 6 && $lng >= -75 && $lng <= -27 ) {
		update_post_meta( $post_id, '_cetech_cidade_lat', $lat );
		update_post_meta( $post_id, '_cetech_cidade_lng', $lng );
	} else {
		delete_post_meta( $post_id, '_cetech_cidade_lat' );
		delete_post_meta( $post_id, '_cetech_cidade_lng' );
	}

	$bairros = array();
	if ( isset( $_POST['_cetech_cidade_bairros'] ) && is_array( $_POST['_cetech_cidade_bairros'] ) ) {
		foreach ( $_POST['_cetech_cidade_bairros'] as $bairro ) {
			$bairro = trim( sanitize_text_field( wp_unslash( $bairro ) ) );
			if ( '' !== $bairro ) {
				$bairros[] = $bairro;
			}
		}
	}
	$bairros = array_values( array_unique( $bairros ) );

	if ( ! empty( $bairros ) ) {
		update_post_meta( $post_id, '_cetech_cidade_bairros', $bairros );
	} else {
		delete_post_meta( $post_id, '_cetech_cidade_bairros' );
	}
}
add_action( 'save_post', 'cetech_cobertura_meta_box_save' );

/* ------------------------------------------------------------
 * 6. Busca de coordenadas (AJAX admin, via Nominatim)
 * ------------------------------------------------------------ */
function cetech_cobertura_geocode() {
	check_ajax_referer( 'cetech_cobertura_geocode', 'nonce' );

	$city = isset( $_POST['cidade'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['cidade'] ) ) ) : '';
	$uf   = isset( $_POST['uf'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['uf'] ) ) ) : '';

	if ( '' === $city ) {
		wp_send_json_error( array( 'message' => __( 'Informe o nome da cidade.', 'Divi' ) ) );
	}

	$states = cetech_cobertura_states();
	$state  = isset( $states[ $uf ] ) ? $states[ $uf ] : 'São Paulo';

	$url = add_query_arg(
		array(
			'format'    => 'jsonv2',
			'limit'     => '1',
			'country'   => 'Brasil',
			'countrycodes' => 'br',
			'state'     => $state,
			'city'      => $city,
		),
		'https://nominatim.openstreetmap.org/search'
	);

	$response = wp_remote_get(
		$url,
		array(
			'user-agent' => 'CE Tech Site (contato@cetech.net.br)',
			'headers'    => array( 'Accept-Language' => 'pt-BR,pt;q=0.9' ),
			'timeout'    => 12,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		wp_send_json_error( array( 'message' => __( 'Não foi possível consultar o serviço de mapa.', 'Divi' ) ) );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body ) || ! isset( $body[0]['lat'], $body[0]['lon'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Cidade não encontrada. Preencha as coordenadas manualmente.', 'Divi' ) ) );
	}

	wp_send_json_success(
		array(
			'lat' => (float) $body[0]['lat'],
			'lng' => (float) $body[0]['lon'],
		)
	);
}
add_action( 'wp_ajax_cetech_cobertura_geocode', 'cetech_cobertura_geocode' );

function cetech_cobertura_admin_enqueue( $hook ) {
	global $post_type;
	$screen = get_current_screen();

	if ( ! $screen || CETECH_CIDADE_CPT !== $screen->post_type || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		return;
	}

	$theme = wp_get_theme();
	wp_enqueue_script(
		CETECH_COBERTURA_ADMIN_JS,
		get_stylesheet_directory_uri() . '/assets/js/cetech-cobertura-admin.js',
		array(),
		$theme->get( 'Version' ),
		true
	);

	wp_localize_script(
		CETECH_COBERTURA_ADMIN_JS,
		'cetechCoberturaAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cetech_cobertura_admin_enqueue' );

/* ------------------------------------------------------------
 * 7. Cidades atendidas (query publica com cache por request)
 * ------------------------------------------------------------ */
function cetech_cobertura_cities() {
	static $cities = null;

	if ( null !== $cities ) {
		return $cities;
	}

	$cities = array();

	$query = new WP_Query(
		array(
			'post_type'      => CETECH_CIDADE_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	if ( empty( $query->posts ) ) {
		return $cities;
	}

	foreach ( $query->posts as $post ) {
		$lat = get_post_meta( $post->ID, '_cetech_cidade_lat', true );
		$lng = get_post_meta( $post->ID, '_cetech_cidade_lng', true );

		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			continue;
		}

		$bairros = get_post_meta( $post->ID, '_cetech_cidade_bairros', true );
		if ( ! is_array( $bairros ) ) {
			$bairros = array();
		}

		$cities[] = array(
			'name'    => $post->post_title,
			'uf'      => strtoupper( (string) get_post_meta( $post->ID, '_cetech_cidade_uf', true ) ),
			'lat'     => (float) $lat,
			'lng'     => (float) $lng,
			'bairros' => array_values( array_filter( array_map( static function ( $b ) {
				return trim( (string) $b );
			}, $bairros ) ) ),
		);
	}

	return $cities;
}

/* ------------------------------------------------------------
 * 8. Mapa SVG (Brasil focado em Sao Paulo) + pins das cidades
 * ------------------------------------------------------------ */
function cetech_cobertura_pins_svg() {
	$cities = cetech_cobertura_cities();
	if ( empty( $cities ) ) {
		return '';
	}

	$width  = CETECH_MAP_WIDTH;
	$scale  = $width / ( CETECH_MAP_LON_MAX - CETECH_MAP_LON_MIN );
	$height = ( CETECH_MAP_LAT_MAX - CETECH_MAP_LAT_MIN ) * $scale;

	$out   = '';
	$index = 0;

	foreach ( $cities as $city ) {
		$px = ( $city['lng'] - CETECH_MAP_LON_MIN ) * $scale;
		$py = ( CETECH_MAP_LAT_MAX - $city['lat'] ) * $scale;

		if ( $px < 0 || $px > $width || $py < 0 || $py > $height ) {
			continue;
		}

		$title = $city['name'];
		if ( '' !== $city['uf'] ) {
			$title .= ' (' . $city['uf'] . ')';
		}
		if ( ! empty( $city['bairros'] ) ) {
			$title .= ' — ' . implode( ' · ', $city['bairros'] );
		}

		$out .= sprintf(
			'<g transform="translate(%1$s %2$s)"><g class="cetech-svg__pin" style="--i:%3$d">' .
			'<circle class="cetech-svg__pin-ring" r="9"/><circle class="cetech-svg__pin-dot" r="5.5"/>' .
			'<title>%4$s</title></g></g>',
			number_format( $px, 1, '.', '' ),
			number_format( $py, 1, '.', '' ),
			$index,
			esc_html( $title )
		);

		$index++;
	}

	return $out;
}

function cetech_cobertura_svg() {
	static $svg = null;

	if ( null !== $svg ) {
		return $svg;
	}

	$svg   = '';
	$file  = get_stylesheet_directory() . '/assets/maps/brasil.svg';
	$raw   = @file_get_contents( $file );

	if ( false !== $raw && false !== strpos( $raw, '</svg>' ) ) {
		$svg = str_replace( '</svg>', cetech_cobertura_pins_svg() . '</svg>', $raw );
	}

	return $svg;
}

/* ------------------------------------------------------------
 * 9. Shortcode [cetech_cobertura]
 * ------------------------------------------------------------ */
function cetech_cobertura_render() {
	static $enqueued = false;

	if ( ! $enqueued ) {
		wp_enqueue_style( CETECH_COBERTURA_CSS );
		$enqueued = true;
	}

	$cities = cetech_cobertura_cities();
	$svg    = cetech_cobertura_svg();

	ob_start();
	?>
	<div class="cetech-cobertura">
		<div class="cetech-cobertura__head">
			<h2 class="cetech-cobertura__title"><?php esc_html_e( 'Cidades atendidas', 'Divi' ); ?></h2>
			<?php if ( ! empty( $cities ) ) : ?>
				<span class="cetech-cobertura__count"><?php echo esc_html( count( $cities ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="cetech-cobertura__map-svg">
			<div class="cetech-cobertura__chip" aria-hidden="true">
				<strong>SP</strong>
				<span><?php echo esc_html( count( $cities ) ); ?> <?php esc_html_e( 'cidades atendidas', 'Divi' ); ?></span>
			</div>
			<?php if ( '' !== $svg ) : ?>
				<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interno do tema. ?>
			<?php elseif ( ! empty( $cities ) ) : ?>
				<ul class="cetech-cobertura__noscript">
					<?php foreach ( $cities as $cidade ) : ?>
						<li><?php echo esc_html( $cidade['name'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'cetech_cobertura', 'cetech_cobertura_render' );