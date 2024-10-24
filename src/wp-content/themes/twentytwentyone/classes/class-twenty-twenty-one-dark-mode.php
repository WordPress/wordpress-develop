<?php
/**
 * Dark Mode Class
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

/**
 * This class is in charge of Dark Mode.
 */
class Twenty_Twenty_One_Dark_Mode {

	/**
	 * Instantiates the object.
	 *
	 * @since Twenty Twenty-One 1.0
	 */
	public function __construct() {

		// Enqueue assets for the block-editor.
		add_action( 'enqueue_block_assets', array( $this, 'editor_custom_color_variables' ) );

		// Add styles for dark-mode.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add scripts for customizer controls.
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'customize_controls_enqueue_scripts' ) );

		// Add customizer controls.
		add_action( 'customize_register', array( $this, 'customizer_controls' ) );

		// Add HTML classes.
		add_filter( 'twentytwentyone_html_classes', array( $this, 'html_classes' ) );

		// Add classes to <body> in the dashboard.
		add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );

		// Add the switch on the frontend & customizer.
		add_action( 'wp_footer', array( $this, 'the_switch' ) );

		// Add the privacy policy content.
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Enqueues editor custom color variables & scripts.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @return void
	 */
	public function editor_custom_color_variables() {
		// See potential bug that this fixes in https://core.trac.wordpress.org/ticket/60111.
		if ( ! is_admin() ) {
			return;
		}

		if ( ! $this->switch_should_render() ) {
			return;
		}
		$background_color            = get_theme_mod( 'background_color', 'D1E4DD' );
		$should_respect_color_scheme = get_theme_mod( 'respect_user_color_preference', false );
		if ( $should_respect_color_scheme && Twenty_Twenty_One_Custom_Colors::get_relative_luminance_from_hex( $background_color ) > 127 ) {
			// Add Dark Mode variable overrides.
			wp_add_inline_style(
				'twenty-twenty-one-custom-color-overrides',
				'.is-dark-theme.is-dark-theme .editor-styles-wrapper { --global--color-background: var(--global--color-dark-gray); --global--color-primary: var(--global--color-light-gray); --global--color-secondary: var(--global--color-light-gray); --button--color-text: var(--global--color-background); --button--color-text-hover: var(--global--color-secondary); --button--color-text-active: var(--global--color-secondary); --button--color-background: var(--global--color-secondary); --button--color-background-active: var(--global--color-background); --global--color-border: #9ea1a7; --table--stripes-border-color: rgba(240, 240, 240, 0.15); --table--stripes-background-color: rgba(240, 240, 240, 0.15); }'
			);
		}
		wp_enqueue_script(
			'twentytwentyone-dark-mode-support-toggle',
			get_template_directory_uri() . '/assets/js/dark-mode-toggler.js',
			array(),
			'1.0.0',
			array( 'in_footer' => true )
		);

		wp_enqueue_script(
			'twentytwentyone-editor-dark-mode-support',
			get_template_directory_uri() . '/assets/js/editor-dark-mode-support.js',
			array( 'twentytwentyone-dark-mode-support-toggle' ),
			'1.0.0',
			array( 'in_footer' => true )
		);
	}

	/**
	 * Enqueues scripts and styles.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->switch_should_render() ) {
			return;
		}
		$url = get_template_directory_uri() . '/assets/css/style-dark-mode.css';
		if ( is_rtl() ) {
			$url = get_template_directory_uri() . '/assets/css/style-dark-mode-rtl.css';
		}
		wp_enqueue_style( 'tt1-dark-mode', $url, array( 'twenty-twenty-one-style' ), wp_get_theme()->get( 'Version' ) ); // @phpstan-ignore-line. Version is always a string.
	}

	/**
	 * Enqueues scripts for the customizer.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @return void
	 */
	public function customize_controls_enqueue_scripts() {
		if ( ! $this->switch_should_render() ) {
			return;
		}
		wp_enqueue_script(
			'twentytwentyone-customize-controls',
			get_template_directory_uri() . '/assets/js/customize.js',
			array( 'customize-base', 'customize-controls', 'underscore', 'jquery', 'twentytwentyone-customize-helpers' ),
			'1.0.0',
			array( 'in_footer' => true )
		);
	}

	/**
	 * Registers customizer options.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
	 * @return void
	 */
	public function customizer_controls( $wp_customize ) {

		$colors_section = $wp_customize->get_section( 'colors' );
		if ( is_object( $colors_section ) ) {
			$colors_section->title = __( 'Colors & Dark Mode', 'twentytwentyone' );
		}

		// Custom notice control.
		require_once get_theme_file_path( 'classes/class-twenty-twenty-one-customize-notice-control.php' ); // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound

		$wp_customize->add_setting(
			'respect_user_color_preference_notice',
			array(
				'capability'        => 'edit_theme_options',
				'default'           => '',
				'sanitize_callback' => '__return_empty_string',
			)
		);

		$wp_customize->add_control(
			new Twenty_Twenty_One_Customize_Notice_Control(
				$wp_customize,
				'respect_user_color_preference_notice',
				array(
					'section'         => 'colors',
					'priority'        => 100,
					'active_callback' => static function () {
						return 127 >= Twenty_Twenty_One_Custom_Colors::get_relative_luminance_from_hex( get_theme_mod( 'background_color', 'D1E4DD' ) );
					},
				)
			)
		);

		$wp_customize->add_setting(
			'respect_user_color_preference',
			array(
				'capability'        => 'edit_theme_options',
				'default'           => false,
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
			)
		);

		$description  = '<p>';
		$description .= sprintf(
			/* translators: %s: Twenty Twenty-One support article URL. */
			__( 'Dark Mode is a device setting. If a visitor to your site requests it, your site will be shown with a dark background and light text. <a href="%s">Learn more about Dark Mode.</a>', 'twentytwentyone' ),
			esc_url( __( 'https://wordpress.org/documentation/article/twenty-twenty-one/#dark-mode-support', 'twentytwentyone' ) )
		);
		$description .= '</p>';
		$description .= '<p>' . __( 'Dark Mode can also be turned on and off with a button that you can find in the bottom corner of the page.', 'twentytwentyone' ) . '</p>';

		$wp_customize->add_control(
			'respect_user_color_preference',
			array(
				'type'            => 'checkbox',
				'section'         => 'colors',
				'label'           => esc_html__( 'Dark Mode support', 'twentytwentyone' ),
				'priority'        => 110,
				'description'     => $description,
				'active_callback' => static function ( $value ) {
					return 127 < Twenty_Twenty_One_Custom_Colors::get_relative_luminance_from_hex( get_theme_mod( 'background_color', 'D1E4DD' ) );
				},
			)
		);

		// Add partial for background_color.
		$wp_customize->selective_refresh->add_partial(
			'background_color',
			array(
				'selector'            => '#dark-mode-toggler',
				'container_inclusive' => true,
				'render_callback'     => function () {
					$attrs = ( $this->switch_should_render() ) ? array() : array( 'style' => 'display:none;' );
					$this->the_html( $attrs );
				},
			)
		);
	}

	/**
	 * Calculates classes for the main <html> element.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @param string $classes The classes for <html> element.
	 * @return string
	 */
	public function html_classes( $classes ) {
		if ( ! $this->switch_should_render() ) {
			return $classes;
		}

		$background_color            = get_theme_mod( 'background_color', 'D1E4DD' );
		$should_respect_color_scheme = get_theme_mod( 'respect_user_color_preference', false );
		if ( $should_respect_color_scheme && 127 <= Twenty_Twenty_One_Custom_Colors::get_relative_luminance_from_hex( $background_color ) ) {
			return ( $classes ) ? ' respect-color-scheme-preference' : 'respect-color-scheme-preference';
		}

		return $classes;
	}

	/**
	 * Adds a class to the <body> element in the editor to accommodate dark-mode.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @global WP_Screen $current_screen WordPress current screen object.
	 *
	 * @param string $classes The admin body-classes.
	 * @return string
	 */
	public function admin_body_classes( $classes ) {
		if ( ! $this->switch_should_render() ) {
			return $classes;
		}

		global $current_screen;
		if ( empty( $current_screen ) ) {
			set_current_screen();
		}

		if ( $current_screen->is_block_editor() ) {
			$should_respect_color_scheme = get_theme_mod( 'respect_user_color_preference', false );
			$background_color            = get_theme_mod( 'background_color', 'D1E4DD' );

			if ( $should_respect_color_scheme && Twenty_Twenty_One_Custom_Colors::get_relative_luminance_from_hex( $background_color ) > 127 ) {
				$classes .= ' twentytwentyone-supports-dark-theme';
			}
		}

		return $classes;
	}

	/**
	 * Determines if we want to print the dark-mode switch or not.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @global bool $is_IE
	 *
	 * @return bool
	 */
	public function switch_should_render() {
		global $is_IE;
		return (
			get_theme_mod( 'respect_user_color_preference', false ) &&
			! $is_IE &&
			127 <= Twenty_Twenty_One_Custom_Colors::get_relative_luminance_from_hex( get_theme_mod( 'background_color', 'D1E4DD' ) )
		);
	}

	/**
	 * Adds night/day switch.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @return void
	 */
	public function the_switch() {
		if ( ! $this->switch_should_render() ) {
			return;
		}
		$this->the_html();
		$this->the_script();
	}

	/**
	 * Prints the dark-mode switch HTML.
	 *
	 * Inspired from https://codepen.io/aaroniker/pen/KGpXZo (MIT-licensed)
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @param array $attrs The attributes to add to our <button> element.
	 * @return void
	 */
	public function the_html( $attrs = array() ) {
		$defaults = array(
			'id'           => 'dark-mode-toggler',
			'class'        => 'fixed-bottom',
			'aria-pressed' => 'false',
		);

		// Extra attributes depending on whether or not the Interactivity API is being used.
		if ( function_exists( 'wp_register_script_module' ) ) {
			$defaults['data-wp-on--click']           = 'actions.toggleDarkMode';
			$defaults['data-wp-bind--aria-pressed']  = 'state.isDarkMode';
			$defaults['data-wp-class--hide']         = 'state.isDarkModeTogglerHidden';
			$defaults['data-wp-on-document--scroll'] = 'callbacks.refreshDarkModeToggler';
		} else {
			$defaults['onClick'] = 'toggleDarkMode()';
		}

		$attrs = wp_parse_args( $attrs, $defaults );
		echo '<button';
		foreach ( $attrs as $key => $val ) {
			echo ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
		}
		echo '>';
		printf(
			/* translators: %s: On/Off */
			esc_html__( 'Dark Mode: %s', 'twentytwentyone' ),
			'<span aria-hidden="true"></span>'
		);
		echo '</button>';
		?>
		<style>
			#dark-mode-toggler > span {
				margin-<?php echo is_rtl() ? 'right' : 'left'; ?>: 5px;
			}
			#dark-mode-toggler > span::before {
				content: '<?php esc_attr_e( 'Off', 'twentytwentyone' ); ?>';
			}
			#dark-mode-toggler[aria-pressed="true"] > span::before {
				content: '<?php esc_attr_e( 'On', 'twentytwentyone' ); ?>';
			}
			<?php if ( is_admin() || wp_is_json_request() ) : ?>
				.components-editor-notices__pinned ~ .edit-post-visual-editor #dark-mode-toggler {
					z-index: 20;
				}
				.is-dark-theme.is-dark-theme #dark-mode-toggler:not(:hover):not(:focus) {
					color: var(--global--color-primary);
				}
				@media only screen and (max-width: 782px) {
					#dark-mode-toggler {
						margin-top: 32px;
					}
				}
			<?php endif; ?>
		</style>

		<?php
	}

	/**
	 * Prints the dark-mode switch script.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @return void
	 */
	public function the_script() {
		// If the Interactivity API is being used, loading this JS code is not necessary.
		if ( function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		echo '<script>';
		include get_template_directory() . '/assets/js/dark-mode-toggler.js'; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude
		echo '</script>';
	}

	/**
	 * Adds information to the privacy policy.
	 *
	 * @since Twenty Twenty-One 1.0
	 *
	 * @return void
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p class="privacy-policy-tutorial">' . __( 'Twenty Twenty-One uses LocalStorage when Dark Mode support is enabled.', 'twentytwentyone' ) . '</p>'
				. '<strong class="privacy-policy-tutorial">' . __( 'Suggested text:', 'twentytwentyone' ) . '</strong> '
				. __( 'This website uses LocalStorage to save the setting when Dark Mode support is turned on or off.<br> LocalStorage is necessary for the setting to work and is only used when a user clicks on the Dark Mode button.<br> No data is saved in the database or transferred.', 'twentytwentyone' );
		wp_add_privacy_policy_content( __( 'Twenty Twenty-One', 'twentytwentyone' ), wp_kses_post( wpautop( $content, false ) ) );
	}
}
