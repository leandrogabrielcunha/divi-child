<?php
/**
 * Template Name: Perfil do Associado
 *
 * Area do associado, fora do painel do WordPress. E o destino do login
 * dos associados e traz os atalhos de emissao de boletos.
 *
 * @package Divi_Child_SETCEB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_entitled = setceb_is_associado() || current_user_can( 'manage_options' );
$home        = home_url( '/' );
$page_title  = $is_entitled ? 'Perfil do Associado' : 'Acesso restrito';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $page_title ); ?> &lsaquo; <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="setceb-perfil-associado <?php echo $is_entitled ? 'is-entitled' : 'not-entitled'; ?>">

<?php echo setceb_header_markup(); ?>

<main class="setceb-perfil">
	<div class="setceb-perfil__card">
		<?php if ( ! is_user_logged_in() ) : ?>

			<div class="setceb-perfil__state">
				<span class="setceb-perfil__icon" aria-hidden="true"><?php echo setceb_svg_icon( 'lock' ); ?></span>
				<h1>Area do Associado</h1>
				<p>Entre com sua conta para acessar a emissao de boletos e os conteudos exclusivos para associados.</p>
				<a class="setceb-perfil__btn" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Entrar</a>
			</div>

		<?php elseif ( ! setceb_is_associado() && ! current_user_can( 'manage_options' ) ) : ?>

			<div class="setceb-perfil__state">
				<span class="setceb-perfil__icon" aria-hidden="true"><?php echo setceb_svg_icon( 'lock' ); ?></span>
				<h1>Acesso restrito</h1>
				<p>Sua conta nao possui o perfil de associado. Entre em contato com a associacao para regularizar seu acesso.</p>
				<a class="setceb-perfil__btn setceb-perfil__btn--ghost" href="<?php echo esc_url( $home ); ?>">Voltar ao site</a>
			</div>

		<?php else : ?>

			<h2 class="setceb-perfil__section-title">Geração de boletos</h2>
			<div class="setceb-perfil__boletos">
				<?php foreach ( setceb_boletos() as $boleto ) : ?>
					<a class="setceb-perfil__boleto"
						href="<?php echo esc_url( $boleto['url'] ); ?>"
						target="_blank" rel="noopener noreferrer">
						<span class="setceb-perfil__boleto-icon" aria-hidden="true"><?php echo setceb_svg_icon( $boleto['icon'] ); ?></span>
						<span class="setceb-perfil__boleto-label"><?php echo esc_html( $boleto['label'] ); ?></span>
						<span class="setceb-perfil__boleto-cta">Emitir boleto</span>
					</a>
				<?php endforeach; ?>
			</div>

		<?php endif; ?>
	</div>
</main>

<?php wp_footer(); ?>
</body>
</html>
