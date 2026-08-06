<?php
/**
 * Plugin Name:       SETCEB Banner
 * Description:       Carrossel de banners gerenciado pelo painel do WordPress. Registra o CPT "Banners", uma página de configurações e o shortcode [setceb_banner].
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            SETCEB
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       setceb-banner
 * Domain Path:       /languages
 *
 * @package Setceb_Banner
 */

defined( 'ABSPATH' ) || exit;

define( 'SETCEB_BANNER_VERSION', '1.1.0' );
define( 'SETCEB_BANNER_FILE', __FILE__ );
define( 'SETCEB_BANNER_DIR', plugin_dir_path( __FILE__ ) );

$setceb_banner_url = WP_CONTENT_URL . '/' . ltrim(
	str_replace(
		wp_normalize_path( WP_CONTENT_DIR ),
		'',
		wp_normalize_path( SETCEB_BANNER_DIR )
	),
	'/'
);
define( 'SETCEB_BANNER_URL', untrailingslashit( $setceb_banner_url ) );
unset( $setceb_banner_url );

require_once SETCEB_BANNER_DIR . 'includes/class-setceb-banner.php';

/**
 * Retorna a instância única do plugin.
 *
 * @return Setceb_Banner
 */
function setceb_banner() {
	return Setceb_Banner::instance();
}

setceb_banner();
