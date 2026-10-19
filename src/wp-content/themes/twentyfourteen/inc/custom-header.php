<?php
/**
 * Implements Custom Header functionality for Twenty Fourteen
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */

/**
 * Sets up the WordPress core custom header settings.
 *
 * @since Twenty Fourteen 1.0
 *
 * @uses twentyfourteen_header_style()
 * @uses twentyfourteen_admin_header_style()
 * @uses twentyfourteen_admin_header_image()
 */
function twentyfourteen_custom_header_setup() {
	add_theme_support(
		'custom-header',
		/**
		 * Filters Twenty Fourteen custom-header support arguments.
		 *
		 * @since Twenty Fourteen 1.0
		 *
		 * @param array $args {
		 *     An array of custom-header support arguments.
		 *
		 *     @type bool   $header_text            Whether to display custom header text. Default false.
		 *     @type int    $width                  Width in pixels of the custom header image. Default 1260.
		 *     @type int    $height                 Height in pixels of the custom header image. Default 240.
		 *     @type bool   $flex_height            Whether to allow flexible-height header images. Default true.
		 *     @type string $admin_head_callback    Callback function used to style the image displayed in
		 *                                          the Appearance > Header screen.
		 *     @type string $admin_preview_callback Callback function used to create the custom header markup in
		 *                                          the Appearance > Header screen.
		 * }
		 */
		apply_filters(
			'twentyfourteen_custom_header_args',
			array(
				'default-text-color'     => 'fff',
				'width'                  => 1260,
				'height'                 => 240,
				'flex-height'            => true,
				'wp-head-callback'       => 'twentyfourteen_header_style',
				'admin-head-callback'    => 'twentyfourteen_admin_header_style',
				'admin-preview-callback' => 'twentyfourteen_admin_header_image',
			)
		)
	);
}
add_action( 'after_setup_theme', 'twentyfourteen_custom_header_setup' );

if ( ! function_exists( 'twentyfourteen_header_style' ) ) :
	/**
	 * Styles the header image and text displayed on the blog.
	 *
	 * @see twentyfourteen_custom_header_setup().
	 *
	 * @since Twenty Fourteen 4.7 Added `wp_print_inline_style_tag()` support.
	 */
	function twentyfourteen_header_style() {
		$text_color = get_header_textcolor();

		// If no custom color for text is set, let's bail.
		if ( display_header_text() && get_theme_support( 'custom-header', 'default-text-color' ) === $text_color ) {
			return;
		}

		// If we get this far, we have custom styles.
		$css = '';
		if ( ! display_header_text() ) {
			// The text has been hidden.
			$css = <<<CSS
			.site-title,
			.site-description {
				clip-path: inset(50%);
				position: absolute;
			}
CSS;
		} elseif ( get_theme_support( 'custom-header', 'default-text-color' ) !== $text_color ) {
			// The user has set a custom color for the text, so use that.
			// Validate inline since sanitize_hex_color_no_hash() is not available outside the Customizer before WordPress 4.6.
			$sanitized_text_color = preg_match( '/^([a-f0-9]{3}){1,2}$/i', $text_color ) ? $text_color : '';

			$css = <<<CSS
			.site-title a {
				color: #{$sanitized_text_color};
			}
CSS;
		}

		if ( function_exists( 'wp_print_inline_style_tag' ) ) {
			wp_print_inline_style_tag( $css, array( 'id' => 'twentyfourteen-header-css' ) );
		} else {
			echo "<style id=\"twentyfourteen-header-css\">\n{$css}\n</style>\n";
		}
	}
endif; // twentyfourteen_header_style()


if ( ! function_exists( 'twentyfourteen_admin_header_style' ) ) :
	/**
	 * Styles the header image displayed on the Appearance > Header screen.
	 *
	 * @see twentyfourteen_custom_header_setup()
	 *
	 * @since Twenty Fourteen 1.0
	 * @since Twenty Fourteen 4.7 Added `wp_print_inline_style_tag()` support.
	 */
	function twentyfourteen_admin_header_style() {
		$css = <<<CSS
		.appearance_page_custom-header #headimg {
			background-color: #000;
			border: none;
			max-width: 1260px;
			min-height: 48px;
		}
		#headimg h1 {
			font-family: Lato, sans-serif;
			font-size: 18px;
			line-height: 48px;
			margin: 0 0 0 30px;
		}
		.rtl #headimg h1  {
			margin: 0 30px 0 0;
		}
		#headimg h1 a {
			color: #fff;
			text-decoration: none;
		}
		#headimg img {
			vertical-align: middle;
		}
CSS;

		if ( function_exists( 'wp_print_inline_style_tag' ) ) {
			wp_print_inline_style_tag( $css, array( 'id' => 'twentyfourteen-admin-header-css' ) );
		} else {
			echo "<style id=\"twentyfourteen-admin-header-css\">\n{$css}\n</style>\n";
		}
	}
endif; // twentyfourteen_admin_header_style()

if ( ! function_exists( 'twentyfourteen_admin_header_image' ) ) :
	/**
	 * Creates the custom header image markup displayed on the Appearance > Header screen.
	 *
	 * @see twentyfourteen_custom_header_setup()
	 *
	 * @since Twenty Fourteen 1.0
	 */
	function twentyfourteen_admin_header_image() {
		?>
	<div id="headimg">
		<?php if ( get_header_image() ) : ?>
		<img src="<?php header_image(); ?>" alt="" />
		<?php endif; ?>
		<h1 class="displaying-header-text"><a id="name" style="<?php echo esc_attr( sprintf( 'color: #%s;', get_header_textcolor() ) ); ?>" onclick="return false;" href="<?php echo esc_url( home_url( '/' ) ); ?>" tabindex="-1"><?php bloginfo( 'name' ); ?></a></h1>
	</div>
		<?php
	}
endif; // twentyfourteen_admin_header_image()


if ( ! function_exists( 'twentyfourteen_header_image' ) ) :
	/**
	 * Creates the custom header image markup displayed.
	 *
	 * @see twentyfourteen_custom_header_setup()
	 *
	 * @since Twenty Fourteen 3.8
	 */
	function twentyfourteen_header_image() {
		$custom_header = get_custom_header();
		$attrs         = array(
			'alt'    => get_bloginfo( 'name', 'display' ),
			'height' => $custom_header->height,
			'width'  => $custom_header->width,
		);
		if ( function_exists( 'the_header_image_tag' ) ) {
			the_header_image_tag( $attrs );
			return;
		}
		?>
		<img src="<?php header_image(); ?>" width="<?php echo esc_attr( $attrs['width'] ); ?>" height="<?php echo esc_attr( $attrs['height'] ); ?>" alt="<?php echo esc_attr( $attrs['alt'] ); ?>" />
		<?php
	}
endif; // twentyfourteen_header_image()
