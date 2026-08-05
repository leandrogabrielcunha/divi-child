<?php
/**
 * Classe principal do plugin SETCEB Banner.
 *
 * Responsável por carregar as dependências e iniciar os módulos.
 *
 * @package Setceb_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe principal.
 */
final class Setceb_Banner {

	/**
	 * Instância única (singleton).
	 *
	 * @var Setceb_Banner|null
	 */
	private static $instance = null;

	/**
	 * Recupera a instância única.
	 *
	 * @return Setceb_Banner
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construtor privado: impede a instanciação direta.
	 */
	private function __construct() {
		$this->includes();

		if ( did_action( 'plugins_loaded' ) ) {
			$this->bootstrap();
		} else {
			add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
		}
	}

	/**
	 * Clona proibido.
	 */
	private function __clone() {}

	/**
	 * Deserialização proibida.
	 */
	public function __wakeup() {}

	/**
	 * Carrega os arquivos de cada módulo.
	 *
	 * @return void
	 */
	private function includes() {
		require_once SETCEB_BANNER_DIR . 'includes/class-setceb-banner-post-type.php';
		require_once SETCEB_BANNER_DIR . 'includes/class-setceb-banner-meta.php';
		require_once SETCEB_BANNER_DIR . 'includes/class-setceb-banner-settings.php';
		require_once SETCEB_BANNER_DIR . 'includes/class-setceb-banner-assets.php';
		require_once SETCEB_BANNER_DIR . 'includes/class-setceb-banner-shortcode.php';
	}

	/**
	 * Inicia os módulos do plugin.
	 *
	 * @return void
	 */
	public function bootstrap() {
		$this->load_textdomain();

		Setceb_Banner_Post_Type::register_hooks();
		Setceb_Banner_Meta::register_hooks();
		Setceb_Banner_Settings::register_hooks();
		Setceb_Banner_Assets::register_hooks();
		Setceb_Banner_Shortcode::register_hooks();
	}

	/**
	 * Carrega o domínio de texto para tradução.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		load_plugin_textdomain( 'setceb-banner', false, basename( SETCEB_BANNER_DIR ) . '/languages' );
	}
}
