<?php
/**
 * Meta boxes do banner e persistência dos campos.
 *
 * @package Setceb_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gerencia os campos do banner.
 */
final class Setceb_Banner_Meta {

	/**
	 * Slug do metabox.
	 */
	private const META_BOX_ID = 'setceb_banner_fields';

	/**
	 * Chaves dos campos.
	 */
	public const KEY_IMAGE_DESKTOP = '_setceb_banner_image_desktop';
	public const KEY_IMAGE_MOBILE  = '_setceb_banner_image_mobile';
	public const KEY_LINK          = '_setceb_banner_link';
	public const KEY_TARGET        = '_setceb_banner_target_blank';
	public const KEY_ALT           = '_setceb_banner_alt';
	public const KEY_ACTIVE        = '_setceb_banner_active';

	/**
	 * Registra os hooks do módulo.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . Setceb_Banner_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Adiciona o metabox.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			self::META_BOX_ID,
			__( 'Configurações do banner', 'setceb-banner' ),
			array( __CLASS__, 'render_meta_box' ),
			Setceb_Banner_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Renderiza o metabox.
	 *
	 * @param WP_Post $post Post atual.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( self::META_BOX_ID, self::META_BOX_ID . '_nonce' );

		$image_desktop = absint( get_post_meta( $post->ID, self::KEY_IMAGE_DESKTOP, true ) );
		$image_mobile  = absint( get_post_meta( $post->ID, self::KEY_IMAGE_MOBILE, true ) );
		$link          = get_post_meta( $post->ID, self::KEY_LINK, true );
		$target        = get_post_meta( $post->ID, self::KEY_TARGET, true );
		$alt           = get_post_meta( $post->ID, self::KEY_ALT, true );
		$active        = get_post_meta( $post->ID, self::KEY_ACTIVE, true );
		$order         = absint( get_post_field( 'menu_order', $post->ID ) );

		if ( '' === $active ) {
			$active = '1';
		}
		?>
		<div class="setceb-banner-admin">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="setceb-image-desktop"><?php esc_html_e( 'Imagem Desktop', 'setceb-banner' ); ?>
							<span style="color:#b32d2e;">*</span>
						</label>
					</th>
					<td>
						<?php self::render_image_field( 'setceb-image-desktop', self::KEY_IMAGE_DESKTOP, $image_desktop ); ?>
						<p class="description"><?php esc_html_e( 'Imagem principal exibida em telas maiores (obrigatória).', 'setceb-banner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setceb-image-mobile"><?php esc_html_e( 'Imagem Mobile', 'setceb-banner' ); ?></label>
					</th>
					<td>
						<?php self::render_image_field( 'setceb-image-mobile', self::KEY_IMAGE_MOBILE, $image_mobile ); ?>
						<p class="description"><?php esc_html_e( 'Opcional. Se vazio, a imagem desktop é usada também no mobile.', 'setceb-banner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setceb-link"><?php esc_html_e( 'Link de destino', 'setceb-banner' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							class="regular-text"
							name="<?php echo esc_attr( self::KEY_LINK ); ?>"
							id="setceb-link"
							value="<?php echo esc_url( $link ); ?>"
							placeholder="https://"
						/>
						<p class="description"><?php esc_html_e( 'URL para onde o banner deve apontar. Deixe vazio para não ser clicável.', 'setceb-banner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Abrir em nova aba', 'setceb-banner' ); ?></th>
					<td>
						<label for="setceb-target">
							<input
								type="checkbox"
								name="<?php echo esc_attr( self::KEY_TARGET ); ?>"
								id="setceb-target"
								value="1"
								<?php checked( '1', $target ); ?>
							/>
							<?php esc_html_e( 'Abrir o link em uma nova aba do navegador.', 'setceb-banner' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setceb-alt"><?php esc_html_e( 'Texto alternativo (SEO)', 'setceb-banner' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( self::KEY_ALT ); ?>"
							id="setceb-alt"
							value="<?php echo esc_attr( $alt ); ?>"
							maxlength="255"
						/>
						<p class="description"><?php esc_html_e( 'Texto alternativo (alt) usado pela imagem para acessibilidade e SEO.', 'setceb-banner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setceb-order"><?php esc_html_e( 'Ordem de exibição', 'setceb-banner' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							class="small-text"
							name="setceb_banner_order"
							id="setceb-order"
							value="<?php echo esc_attr( (string) $order ); ?>"
							min="0"
							step="1"
						/>
						<p class="description"><?php esc_html_e( 'Menor valor é exibido primeiro. Use a coluna "Ordem" na listagem para ordenar.', 'setceb-banner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'setceb-banner' ); ?></th>
					<td>
						<label for="setceb-active" style="margin-right:12px;">
							<input
								type="radio"
								name="<?php echo esc_attr( self::KEY_ACTIVE ); ?>"
								id="setceb-active"
								value="1"
								<?php checked( '1', $active ); ?>
							/>
							<?php esc_html_e( 'Ativo', 'setceb-banner' ); ?>
						</label>
						<label for="setceb-inactive">
							<input
								type="radio"
								name="<?php echo esc_attr( self::KEY_ACTIVE ); ?>"
								id="setceb-inactive"
								value="0"
								<?php checked( '0', $active ); ?>
							/>
							<?php esc_html_e( 'Inativo', 'setceb-banner' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Somente banners ativos são exibidos no carrossel.', 'setceb-banner' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Renderiza o seletor de imagem com o Media Library do WordPress.
	 *
	 * @param string $id       ID do elemento.
	 * @param string $meta_key Chave do meta.
	 * @param int    $image_id ID da mídia selecionada.
	 * @return void
	 */
	private static function render_image_field( $id, $meta_key, $image_id ) {
		$preview = '';
		if ( $image_id ) {
			$preview = wp_get_attachment_image( $image_id, 'medium', false, array( 'style' => 'max-width:240px;height:auto;border-radius:8px;display:block;' ) );
		}
		?>
		<div class="setceb-image-field" data-field="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" />
			<div class="setceb-image-field__preview"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image escapa. ?></div>
			<p>
				<button type="button" class="button setceb-image-field__add" data-media-title="<?php echo esc_attr__( 'Selecionar imagem', 'setceb-banner' ); ?>">
					<?php esc_html_e( 'Selecionar imagem', 'setceb-banner' ); ?>
				</button>
				<button type="button" class="button setceb-image-field__remove" <?php echo $image_id ? '' : 'style="display:none;"'; ?>>
					<?php esc_html_e( 'Remover', 'setceb-banner' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Persiste os campos do banner com sanitização.
	 *
	 * @param int     $post_id ID do post.
	 * @param WP_Post $post    Objeto do post.
	 * @return void
	 */
	public static function save( $post_id, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Assinatura do hook save_post.
		$nonce_name  = self::META_BOX_ID . '_nonce';
		$nonce_value = isset( $_POST[ $nonce_name ] ) ? sanitize_key( wp_unslash( $_POST[ $nonce_name ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce_value, self::META_BOX_ID ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$keys = array(
			self::KEY_IMAGE_DESKTOP,
			self::KEY_IMAGE_MOBILE,
			self::KEY_LINK,
			self::KEY_TARGET,
			self::KEY_ALT,
			self::KEY_ACTIVE,
		);

		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizado individualmente no switch abaixo.
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

			switch ( $key ) {
				case self::KEY_IMAGE_DESKTOP:
				case self::KEY_IMAGE_MOBILE:
					$value = absint( $value );

					if ( 0 === $value ) {
						delete_post_meta( $post_id, $key );
					} else {
						update_post_meta( $post_id, $key, $value );
					}
					break;

				case self::KEY_LINK:
					$value = esc_url_raw( trim( (string) $value ) );

					if ( '' === $value ) {
						delete_post_meta( $post_id, $key );
					} else {
						update_post_meta( $post_id, $key, $value );
					}
					break;

				case self::KEY_TARGET:
				case self::KEY_ACTIVE:
					$value = '1' === (string) $value ? '1' : '0';
					update_post_meta( $post_id, $key, $value );
					break;

				case self::KEY_ALT:
					$value = sanitize_text_field( trim( (string) $value ) );

					if ( '' === $value ) {
						delete_post_meta( $post_id, $key );
					} else {
						update_post_meta( $post_id, $key, $value );
					}
					break;
			}
		}

		// Ordem de exibição armazenada no campo nativo menu_order.
		$order = isset( $_POST['setceb_banner_order'] ) ? absint( wp_unslash( $_POST['setceb_banner_order'] ) ) : 0;

		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Operação nativa do campo menu_order.
			$wpdb->posts,
			array( 'menu_order' => $order ),
			array( 'ID' => $post_id ),
			array( '%d' ),
			array( '%d' )
		);

		clean_post_cache( $post_id );
	}
}
