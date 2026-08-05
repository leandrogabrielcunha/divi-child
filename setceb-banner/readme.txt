=== SETCEB Banner ===
Contributors: setceb
Tags: banner, carousel, slider, swiper
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Carrossel de banners gerenciado pelo painel do WordPress com Swiper.js.

== Description ==

Permite que qualquer administrador gerencie os banners pelo painel do WordPress, sem editar código.

* CPT "Banners" com imagens desktop/mobile, link, nova aba, texto alternativo (SEO), ordem e status.
* Página de configurações (velocidade, autoplay, loop, setas, bullets e alturas).
* Shortcode `[setceb_banner]` com parâmetros `autoplay`, `delay` e `height`.
* Swiper.js local (sem CDN), sem jQuery, responsivo, com swipe no mobile.

Para exibir, insira `[setceb_banner]` na Home ou em qualquer página.

== Installation ==

1. Copie a pasta `setceb-banner` para `wp-content/plugins/`.
2. Ative o plugin em "Plugins".
3. Acesse "Banners" e adicione os banners.
4. Configure em "Banners > Configurações".
5. Insira o shortcode `[setceb_banner]` na página desejada.

== Frequently Asked Questions ==

= Como sobrescrever as configurações pelo shortcode? =

`[setceb_banner autoplay="true" delay="5000" height="500"]`

= Onde ficam as configurações? =

Em "Banners > Configurações".

== Changelog ==

= 1.0.0 =
* Versão inicial.
