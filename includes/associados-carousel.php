<?php
/**
 * SETCEB - Carrossel de conteudos exclusivos para associados.
 *
 * Shortcode: [setceb_carrossel_associados]
 * Exibe uma previa publica dos dez posts mais recentes marcados como
 * exclusivos. O post individual aplica o controle de acesso existente
 * em includes/associado.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra os assets do carrossel.
 */
function setceb_associados_carousel_register_assets() {
	$theme   = wp_get_theme();
	$version = $theme->get( 'Version' );

	wp_register_style(
		'setceb-associados-carousel',
		get_stylesheet_directory_uri() . '/associados-carousel.css',
		array(),
		$version
	);

	wp_register_script(
		'setceb-associados-carousel',
		get_stylesheet_directory_uri() . '/associados-carousel.js',
		array(),
		$version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'setceb_associados_carousel_register_assets', 5 );

/**
 * Confere se o shortcode aparece no conteudo carregado, em widgets ou no Divi Builder.
 *
 * @return bool
 */
function setceb_associados_carousel_is_present() {
	$shortcode = 'setceb_carrossel_associados';
	$queried   = get_queried_object();

	if ( $queried instanceof WP_Post && has_shortcode( $queried->post_content, $shortcode ) ) {
		return true;
	}

	global $wp_query;

	if ( $wp_query instanceof WP_Query ) {
		foreach ( (array) $wp_query->posts as $post ) {
			if ( $post instanceof WP_Post && has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}
	}

	if (
		( function_exists( 'is_et_fb' ) && is_et_fb() )
		|| ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() )
	) {
		return true;
	}

	foreach ( (array) wp_get_sidebars_widgets() as $sidebar ) {
		foreach ( (array) $sidebar as $widget_id ) {
			$widget_base = preg_replace( '/-[0-9]+$/', '', (string) $widget_id );
			$widget_data = get_option( 'widget_' . $widget_base );
			$serialized  = is_array( $widget_data ) ? wp_json_encode( $widget_data ) : '';

			if ( is_string( $serialized ) && false !== strpos( $serialized, $shortcode ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Enfileira os assets apenas em paginas que usam o shortcode.
 */
function setceb_associados_carousel_maybe_enqueue() {
	if ( setceb_associados_carousel_is_present() ) {
		setceb_associados_carousel_enqueue_assets();
	}
}
add_action( 'wp_enqueue_scripts', 'setceb_associados_carousel_maybe_enqueue', 20 );

/**
 * Enfileira CSS e JavaScript do carrossel.
 */
function setceb_associados_carousel_enqueue_assets() {
	wp_enqueue_style( 'setceb-associados-carousel' );
	wp_enqueue_script( 'setceb-associados-carousel' );
}

/**
 * Renderiza a previa publica dos dez posts exclusivos mais recentes.
 *
 * @param array|string $atts Atributos ignorados; o limite e sempre dez.
 * @return string HTML do carrossel.
 */
function setceb_associados_carousel_shortcode( $atts = array() ) {
	setceb_associados_carousel_enqueue_assets();

	$query      = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 10,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array(
				array(
					'key'     => '_setceb_associado_only',
					'value'   => '1',
					'compare' => '=',
				),
			),
		)
	);

	if ( ! $query->have_posts() ) {
		return '<div class="setceb-associados-carousel__empty"><p>Nenhum conteúdo exclusivo foi publicado recentemente.</p></div>';
	}

	$carousel_id = wp_unique_id( 'setceb-associados-carousel-' );
	$fallback    = get_stylesheet_directory_uri() . '/logo-cor-02.png';

	ob_start();
	?>
	<div id="<?php echo esc_attr( $carousel_id ); ?>" class="setceb-associados-carousel" data-setceb-associados-carousel>
		<div class="setceb-associados-carousel__viewport" data-carousel-viewport tabindex="0" aria-label="Conteúdos exclusivos para associados">
			<ul class="setceb-associados-carousel__track" data-carousel-track>
				<?php foreach ( $query->posts as $post ) : ?>
					<?php
					$categorias = get_the_category( $post->ID );
					$categoria  = ! empty( $categorias ) ? $categorias[0]->name : 'Associados';
					$titulo     = get_the_title( $post );
					$url        = get_permalink( $post );
					$imagem_id  = get_post_thumbnail_id( $post->ID );
					?>
					<li class="setceb-associados-carousel__slide">
						<article class="setceb-associados-carousel__card">
							<div class="setceb-associados-carousel__media<?php echo $imagem_id ? '' : ' setceb-associados-carousel__media--fallback'; ?>">
								<a class="setceb-associados-carousel__image-link" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
									<?php if ( $imagem_id ) : ?>
										<?php
										echo wp_get_attachment_image(
											$imagem_id,
											'large',
											false,
											array(
												'class'    => 'setceb-associados-carousel__image',
												'alt'      => $titulo,
												'loading'  => 'lazy',
												'decoding' => 'async',
											)
										);
										?>
									<?php else : ?>
										<img class="setceb-associados-carousel__image setceb-associados-carousel__image--fallback" src="<?php echo esc_url( $fallback ); ?>" alt="" loading="lazy" decoding="async">
									<?php endif; ?>
								</a>
								<span class="setceb-associados-carousel__category"><?php echo esc_html( $categoria ); ?></span>
							</div>
							<div class="setceb-associados-carousel__body">
								<h3 class="setceb-associados-carousel__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $titulo ); ?></a></h3>
								<time class="setceb-associados-carousel__date" datetime="<?php echo esc_attr( get_post_time( 'c', true, $post ) ); ?>">
									<?php echo esc_html( get_the_date( 'j F Y', $post ) ); ?>
								</time>
								<a class="setceb-associados-carousel__button" href="<?php echo esc_url( $url ); ?>">
									<span>Acessar conteúdo</span>
									<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M4 10h11M10 5l5 5-5 5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
								</a>
							</div>
						</article>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<nav class="setceb-associados-carousel__controls" aria-label="Navegar pelos conteúdos">
			<button class="setceb-associados-carousel__arrow" type="button" data-carousel-prev aria-label="Conteúdo anterior" disabled>
				<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M16 10H5m5 5-5-5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
			</button>
			<button class="setceb-associados-carousel__arrow" type="button" data-carousel-next aria-label="Próximo conteúdo" disabled>
				<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M4 10h11m-5-5 5 5-5 5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
			</button>
		</nav>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'setceb_carrossel_associados', 'setceb_associados_carousel_shortcode' );
