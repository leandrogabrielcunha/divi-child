<?php
/**
 * Shortcode [setceb_banner] e renderização do carrossel.
 *
 * @package Setceb_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderiza o carrossel de banners via shortcode.
 */
final class Setceb_Banner_Shortcode {

	/**
	 * Registra os hooks do módulo.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_shortcode( 'setceb_banner', array( __CLASS__, 'render' ) );
	}

	/**
	 * Renderiza o carrossel.
	 *
	 * @param array|string $atts Atributos do shortcode.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'autoplay' => '',
				'delay'    => 0,
				'height'   => 0,
			),
			$atts,
			'setceb_banner'
		);

		$settings = Setceb_Banner_Settings::get_settings();

		$banners = self::get_banners();

		if ( empty( $banners ) ) {
			return '';
		}

		$config = self::resolve_config( $atts, $settings, count( $banners ) );

		Setceb_Banner_Assets::enqueue_frontend();

		$style = sprintf(
			'--setceb-banner-h-desktop:%1$dpx;--setceb-banner-h-mobile:%2$dpx;',
			absint( $config['height_desktop'] ),
			absint( $config['height_mobile'] )
		);

		$html  = '<div class="setceb-banner swiper" style="' . esc_attr( $style ) . '" data-setceb-banner="' . esc_attr( (string) wp_json_encode( $config['swiper'] ) ) . '">';
		$html .= '<div class="swiper-wrapper">';

		foreach ( $banners as $banner ) {
			$html .= self::render_slide( $banner );
		}

		$html .= '</div>';

		if ( $config['swiper']['arrows'] ) {
			$html .= '<div class="setceb-banner__nav" aria-hidden="true">';
			$html .= '<div class="swiper-button-prev" role="button" tabindex="0"></div>';
			$html .= '<div class="swiper-button-next" role="button" tabindex="0"></div>';
			$html .= '</div>';
		}

		if ( $config['swiper']['bullets'] ) {
			$html .= '<div class="swiper-pagination"></div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Consulta os banners ativos ordenados.
	 *
	 * @return WP_Post[]
	 */
	private static function get_banners() {
		$query = new WP_Query(
			array(
				'post_type'           => Setceb_Banner_Post_Type::POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'orderby'             => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CPT pequeno, busca simples por status ativo.
				'meta_query'          => array(
					array(
						'key'   => Setceb_Banner_Meta::KEY_ACTIVE,
						'value' => '1',
					),
				),
			)
		);

		return $query->posts;
	}

	/**
	 * Resolve a configuração final mesclando shortcode e opções.
	 *
	 * @param array           $atts     Atributos do shortcode.
	 * @param array<int, int> $settings Configurações globais.
	 * @param int             $count    Total de banners.
	 * @return array
	 */
	private static function resolve_config( $atts, $settings, $count ) {
		$autoplay = absint( $settings['autoplay'] );

		if ( self::is_bool_string( $atts['autoplay'] ) ) {
			$autoplay = self::parse_bool_string( $atts['autoplay'] );
		}

		$delay = absint( $atts['delay'] ) > 0 ? absint( $atts['delay'] ) : absint( $settings['autoplay_delay'] );
		$loop  = absint( $settings['loop'] );

		if ( $count < 2 ) {
			$loop     = 0;
			$autoplay = 0;
		}

		return array(
			'height_desktop' => absint( $atts['height'] ) > 0 ? absint( $atts['height'] ) : absint( $settings['desktop_height'] ),
			'height_mobile'  => absint( $settings['mobile_height'] ),
			'swiper'         => array(
				'speed'    => absint( $settings['transition_speed'] ),
				'delay'    => $delay,
				'loop'     => $loop,
				'autoplay' => $autoplay,
				'arrows'   => absint( $settings['show_arrows'] ),
				'bullets'  => absint( $settings['show_bullets'] ),
			),
		);
	}

	/**
	 * Renderiza um slide.
	 *
	 * @param WP_Post $post Banner.
	 * @return string
	 */
	private static function render_slide( $post ) {
		$image_desktop = absint( get_post_meta( $post->ID, Setceb_Banner_Meta::KEY_IMAGE_DESKTOP, true ) );
		$image_mobile  = absint( get_post_meta( $post->ID, Setceb_Banner_Meta::KEY_IMAGE_MOBILE, true ) );
		$link          = get_post_meta( $post->ID, Setceb_Banner_Meta::KEY_LINK, true );
		$target        = get_post_meta( $post->ID, Setceb_Banner_Meta::KEY_TARGET, true );
		$alt           = get_post_meta( $post->ID, Setceb_Banner_Meta::KEY_ALT, true );

		if ( '' === (string) $alt ) {
			$alt = get_the_title( $post );
		}

		$slide_class = 'setceb-banner-slide swiper-slide';

		if ( ! $image_mobile ) {
			$slide_class .= ' setceb-banner-slide--no-mobile';
		}

		$inner = '';

		if ( $image_desktop ) {
			$inner .= wp_get_attachment_image(
				$image_desktop,
				'full',
				false,
				array(
					'class' => 'setceb-banner-image setceb-banner-image--desktop',
					'alt'   => $alt,
				)
			);
		}

		if ( $image_mobile ) {
			$inner .= wp_get_attachment_image(
				$image_mobile,
				'full',
				false,
				array(
					'class' => 'setceb-banner-image setceb-banner-image--mobile',
					'alt'   => $alt,
				)
			);
		}

		if ( '' !== (string) $link ) {
			$open_new    = '1' === (string) $target;
			$target_attr = $open_new ? '_blank' : '_self';
			$rel         = $open_new ? 'noopener noreferrer' : 'noopener';

			$inner = '<a class="setceb-banner-link" href="' . esc_url( $link ) . '" target="' . esc_attr( $target_attr ) . '" rel="' . esc_attr( $rel ) . '">' . $inner . '</a>';
		}

		return '<div class="' . esc_attr( $slide_class ) . '">' . $inner . '</div>';
	}

	/**
	 * Verifica se o valor parece um boolean textual.
	 *
	 * @param mixed $value Valor.
	 * @return bool
	 */
	private static function is_bool_string( $value ) {
		return in_array(
			strtolower( (string) $value ),
			array( 'true', 'false', '1', '0', 'yes', 'no', 'on', 'off' ),
			true
		);
	}

	/**
	 * Converte um boolean textual em 1 ou 0.
	 *
	 * @param mixed $value Valor.
	 * @return int
	 */
	private static function parse_bool_string( $value ) {
		return in_array( strtolower( (string) $value ), array( 'true', '1', 'yes', 'on' ), true ) ? 1 : 0;
	}
}
