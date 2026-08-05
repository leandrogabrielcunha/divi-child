<?php
/**
 * Página de configurações do carrossel.
 *
 * @package Setceb_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gerencia as configurações globais do carrossel.
 */
final class Setceb_Banner_Settings {

	/**
	 * Slug da opção que guarda as configurações.
	 */
	public const OPTION_KEY = 'setceb_banner_settings';

	/**
	 * Slug da página de configurações.
	 */
	private const MENU_SLUG = 'setceb-banner-settings';

	/**
	 * Grupo de opções usado na Settings API.
	 */
	private const OPTION_GROUP = 'setceb_banner_settings_group';

	/**
	 * Valores padrão.
	 *
	 * @return array<string, int>
	 */
	public static function defaults() {
		return array(
			'autoplay'         => 1,
			'transition_speed' => 500,
			'autoplay_delay'   => 5000,
			'loop'             => 1,
			'show_arrows'      => 1,
			'show_bullets'     => 1,
			'desktop_height'   => 420,
			'mobile_height'    => 280,
		);
	}

	/**
	 * Recupera as configurações mescladas com os padrões.
	 *
	 * @return array<string, int>
	 */
	public static function get_settings() {
		$settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );

		return array_map( 'absint', $settings );
	}

	/**
	 * Registra os hooks do módulo.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Adiciona a página no submenu do CPT.
	 *
	 * @return void
	 */
	public static function add_submenu() {
		add_submenu_page(
			'edit.php?post_type=' . Setceb_Banner_Post_Type::POST_TYPE,
			__( 'Configurações do Banner', 'setceb-banner' ),
			__( 'Configurações', 'setceb-banner' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registra a opção, seção e campos na Settings API.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'setceb_banner_main',
			__( 'Comportamento do carrossel', 'setceb-banner' ),
			array( __CLASS__, 'render_section' ),
			self::MENU_SLUG
		);

		$fields = array(
			'autoplay'         => __( 'Autoplay', 'setceb-banner' ),
			'autoplay_delay'   => __( 'Tempo do autoplay (ms)', 'setceb-banner' ),
			'transition_speed' => __( 'Velocidade da transição (ms)', 'setceb-banner' ),
			'loop'             => __( 'Loop infinito', 'setceb-banner' ),
			'show_arrows'      => __( 'Setas laterais', 'setceb-banner' ),
			'show_bullets'     => __( 'Indicadores (bullets)', 'setceb-banner' ),
			'desktop_height'   => __( 'Altura do banner Desktop (px)', 'setceb-banner' ),
			'mobile_height'    => __( 'Altura do banner Mobile (px)', 'setceb-banner' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'setceb_banner_' . $key,
				$label,
				array( __CLASS__, 'render_field' ),
				self::MENU_SLUG,
				'setceb_banner_main',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Texto introdutório da seção.
	 *
	 * @return void
	 */
	public static function render_section() {
		echo '<p>' . esc_html__( 'Estas configurações valem para o shortcode [setceb_banner] usado sem parâmetros. Use os parâmetros do shortcode para sobrescrever autoplay, delay e altura.', 'setceb-banner' ) . '</p>';
	}

	/**
	 * Renderiza um campo de configuração.
	 *
	 * @param array $args Argumentos (key).
	 * @return void
	 */
	public static function render_field( $args ) {
		$key      = $args['key'];
		$settings = self::get_settings();
		$name     = self::OPTION_KEY . '[' . $key . ']';

		switch ( $key ) {
			case 'autoplay':
			case 'loop':
			case 'show_arrows':
			case 'show_bullets':
				?>
				<label for="setceb-<?php echo esc_attr( $key ); ?>">
					<input
						type="checkbox"
						name="<?php echo esc_attr( $name ); ?>"
						id="setceb-<?php echo esc_attr( $key ); ?>"
						value="1"
						<?php checked( '1', (string) $settings[ $key ] ); ?>
					/>
					<?php esc_html_e( 'Ativado', 'setceb-banner' ); ?>
				</label>
				<?php
				break;

			case 'transition_speed':
			case 'autoplay_delay':
				?>
				<input
					type="number"
					class="small-text"
					name="<?php echo esc_attr( $name ); ?>"
					id="setceb-<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>"
					min="<?php echo esc_attr( (string) self::field_min( $key ) ); ?>"
					max="<?php echo esc_attr( (string) self::field_max( $key ) ); ?>"
					step="100"
				/>
				<?php
				break;

			default:
				?>
				<input
					type="number"
					class="small-text"
					name="<?php echo esc_attr( $name ); ?>"
					id="setceb-<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>"
					min="<?php echo esc_attr( (string) self::field_min( $key ) ); ?>"
					max="<?php echo esc_attr( (string) self::field_max( $key ) ); ?>"
					step="10"
				/>
				<?php
		}
	}

	/**
	 * Mínimo de cada campo.
	 *
	 * @param string $key Chave do campo.
	 * @return int
	 */
	private static function field_min( $key ) {
		$min = array(
			'transition_speed' => 100,
			'autoplay_delay'   => 1000,
			'desktop_height'   => 150,
			'mobile_height'    => 100,
		);

		return isset( $min[ $key ] ) ? $min[ $key ] : 0;
	}

	/**
	 * Máximo de cada campo.
	 *
	 * @param string $key Chave do campo.
	 * @return int
	 */
	private static function field_max( $key ) {
		$max = array(
			'transition_speed' => 10000,
			'autoplay_delay'   => 60000,
			'desktop_height'   => 1200,
			'mobile_height'    => 1200,
		);

		return isset( $max[ $key ] ) ? $max[ $key ] : 9999;
	}

	/**
	 * Sanitiza as configurações antes de salvar.
	 *
	 * @param array $input Valores enviados.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		foreach ( $defaults as $key => $default ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : 0;

			switch ( $key ) {
				case 'autoplay':
				case 'loop':
				case 'show_arrows':
				case 'show_bullets':
					$output[ $key ] = '1' === (string) $value ? 1 : 0;
					break;

				case 'transition_speed':
				case 'autoplay_delay':
				case 'desktop_height':
				case 'mobile_height':
					$value = absint( $value );
					$min   = self::field_min( $key );
					$max   = self::field_max( $key );

					$output[ $key ] = max( $min, min( $max, $value ) );
					break;
			}
		}

		return $output;
	}

	/**
	 * Renderiza a página de configurações.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Configurações do Banner', 'setceb-banner' ); ?></h1>
			<p>
				<?php esc_html_e( 'Para exibir o carrossel, adicione o shortcode em qualquer página:', 'setceb-banner' ); ?>
				<code>[setceb_banner]</code>
			</p>
			<p>
				<?php esc_html_e( 'Com parâmetros opcionais:', 'setceb-banner' ); ?>
				<code>[setceb_banner autoplay="true" delay="5000" height="500"]</code>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
