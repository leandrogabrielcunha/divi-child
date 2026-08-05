<?php
/**
 * Cleanup completo ao excluir o plugin.
 *
 * @package Setceb_Banner
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove a opção de configurações.
delete_option( 'setceb_banner_settings' );

// Remove banners e os metas associados.
$banners = get_posts(
	array(
		'post_type'      => 'setceb_banner',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $banners as $banner_id ) {
	wp_delete_post( $banner_id, true );
}
