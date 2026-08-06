<?php
/**
 * Enfileiramento (enqueue) de estilos e scripts do plugin.
 *
 * @package Setceb_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gerencia os assets do front-end e do admin.
 */
final class Setceb_Banner_Assets {

	/**
	 * Handles usados no front-end.
	 */
	private const CSS_HANDLE = 'setceb-banner';
	private const JS_HANDLE  = 'setceb-banner';
	private const SWIPER_CSS = 'setceb-banner-swiper';
	private const SWIPER_JS  = 'setceb-banner-swiper';
	private const ADMIN_CSS  = 'setceb-banner-admin';
	private const ADMIN_JS   = 'setceb-banner-admin';

	/**
	 * Registra os hooks do módulo.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
	}

	/**
	 * Enfileira o Swiper e os assets do plugin no front-end.
	 *
	 * @return void
	 */
	public static function enqueue_frontend() {
		wp_enqueue_style(
			self::SWIPER_CSS,
			SETCEB_BANNER_URL . '/assets/css/swiper-bundle.min.css',
			array(),
			SETCEB_BANNER_VERSION
		);

		wp_enqueue_style(
			self::CSS_HANDLE,
			SETCEB_BANNER_URL . '/assets/css/setceb-banner.css',
			array( self::SWIPER_CSS ),
			SETCEB_BANNER_VERSION
		);

		wp_enqueue_script(
			self::SWIPER_JS,
			SETCEB_BANNER_URL . '/assets/js/swiper-bundle.min.js',
			array(),
			SETCEB_BANNER_VERSION,
			true
		);

		wp_enqueue_script(
			self::JS_HANDLE,
			SETCEB_BANNER_URL . '/assets/js/setceb-banner.js',
			array( self::SWIPER_JS ),
			SETCEB_BANNER_VERSION,
			true
		);
	}

	/**
	 * Enfileira somente quando o shortcode está em uso ou o builder do Divi está ativo.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_frontend() {
		if ( self::is_divi_builder_active() || self::shortcode_in_use() ) {
			self::enqueue_frontend();
		}
	}

	/**
	 * Detecta o frontend builder do Divi para carregar os assets na prévia.
	 *
	 * @return bool
	 */
	private static function is_divi_builder_active() {
		if ( function_exists( 'is_et_fb' ) && is_et_fb() ) {
			return true;
		}

		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return true;
		}

		return false;
	}

	/**
	 * Verifica se o shortcode aparece no conteúdo atual ou em widgets.
	 *
	 * @return bool
	 */
	private static function shortcode_in_use() {
		$shortcode = 'setceb_banner';
		$queried   = get_queried_object();

		if ( $queried instanceof WP_Post && has_shortcode( $queried->post_content, $shortcode ) ) {
			return true;
		}

		foreach ( (array) $GLOBALS['wp_query']->posts as $post ) {
			if ( $post instanceof WP_Post && has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		$sidebars = wp_get_sidebars_widgets();

		if ( ! empty( $sidebars ) ) {
			$widgets = array();

			foreach ( $sidebars as $sidebar ) {
				if ( is_array( $sidebar ) ) {
					$widgets = array_merge( $widgets, $sidebar );
				}
			}

			foreach ( (array) $widgets as $widget_id ) {
				$widget_base = preg_replace( '/-[0-9]+$/', '', (string) $widget_id );
				$option      = get_option( 'widget_' . $widget_base );

				if ( is_array( $option ) ) {
					$serialized = wp_json_encode( $option );

					if ( is_string( $serialized ) && strpos( $serialized, $shortcode ) !== false ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Enfileira os assets do admin (editor de banner e configurações).
	 *
	 * @param string $hook_suffix Suflxo do hook atual.
	 * @return void
	 */
	public static function enqueue_admin( $hook_suffix ) {
		$post_type = Setceb_Banner_Post_Type::POST_TYPE;
		$screen    = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$is_banner_editor = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true )
			&& $post_type === $screen->post_type;
		$is_settings_page = 'setceb_banner_page_setceb-banner-settings' === $screen->id;
		$is_listing       = 'edit-' . $post_type === $screen->id;

		if ( $is_banner_editor ) {
			wp_enqueue_media();
		}

		if ( ! $is_banner_editor && ! $is_settings_page && ! $is_listing ) {
			return;
		}

		wp_enqueue_style(
			self::ADMIN_CSS,
			SETCEB_BANNER_URL . '/assets/css/setceb-banner-admin.css',
			array(),
			SETCEB_BANNER_VERSION
		);

		if ( $is_banner_editor ) {
			wp_enqueue_script(
				self::ADMIN_JS,
				SETCEB_BANNER_URL . '/assets/js/setceb-banner-admin.js',
				array( 'media-editor' ),
				SETCEB_BANNER_VERSION,
				true
			);
		}
	}
}
