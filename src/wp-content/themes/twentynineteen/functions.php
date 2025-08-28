<?php
/**
 * Twenty Nineteen functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Nineteen
 * @since Twenty Nineteen 1.0
 */

/**
 * Twenty Nineteen only works in WordPress 4.7 or later.
 */
if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
	require get_template_directory() . '/inc/back-compat.php';
	return;
}

if ( ! function_exists( 'twentynineteen_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 */
	function twentynineteen_setup() {

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );
		set_post_thumbnail_size( 1568, 9999 );

		// This theme uses wp_nav_menu() in two locations.
		register_nav_menus(
			array(
				'menu-1' => __( 'Primary', 'twentynineteen' ),
				'footer' => __( 'Footer Menu', 'twentynineteen' ),
				'social' => __( 'Social Links Menu', 'twentynineteen' ),
			)
		);

		/*
		 * Switch default core markup for search form, comment form, and comments
		 * to output valid HTML5.
		 */
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'script',
				'style',
				'navigation-widgets',
			)
		);

		/**
		 * Add support for core custom logo.
		 *
		 * @link https://codex.wordpress.org/Theme_Logo
		 */
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 190,
				'width'       => 190,
				'flex-width'  => false,
				'flex-height' => false,
			)
		);

		// Add theme support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );

		// Add support for full and wide align images.
		add_theme_support( 'align-wide' );

		// Add support for editor styles.
		add_theme_support( 'editor-styles' );

		// Enqueue editor styles.
		add_editor_style( 'style-editor.css' );

		// Add custom editor font sizes.
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name'      => __( 'Small', 'twentynineteen' ),
					'shortName' => __( 'S', 'twentynineteen' ),
					'size'      => 19.5,
					'slug'      => 'small',
				),
				array(
					'name'      => __( 'Normal', 'twentynineteen' ),
					'shortName' => __( 'M', 'twentynineteen' ),
					'size'      => 22,
					'slug'      => 'normal',
				),
				array(
					'name'      => __( 'Large', 'twentynineteen' ),
					'shortName' => __( 'L', 'twentynineteen' ),
					'size'      => 36.5,
					'slug'      => 'large',
				),
				array(
					'name'      => __( 'Huge', 'twentynineteen' ),
					'shortName' => __( 'XL', 'twentynineteen' ),
					'size'      => 49.5,
					'slug'      => 'huge',
				),
			)
		);

		// Editor color palette.
		add_theme_support(
			'editor-color-palette',
			array(
				array(
					'name'  => 'default' === get_theme_mod( 'primary_color', 'default' ) ? __( 'Blue', 'twentynineteen' ) : null,
					'slug'  => 'primary',
					'color' => twentynineteen_hsl_hex( 'default' === get_theme_mod( 'primary_color' ) ? 199 : get_theme_mod( 'primary_color_hue', 199 ), 100, 33 ),
				),
				array(
					'name'  => 'default' === get_theme_mod( 'primary_color', 'default' ) ? __( 'Dark Blue', 'twentynineteen' ) : null,
					'slug'  => 'secondary',
					'color' => twentynineteen_hsl_hex( 'default' === get_theme_mod( 'primary_color' ) ? 199 : get_theme_mod( 'primary_color_hue', 199 ), 100, 23 ),
				),
				array(
					'name'  => __( 'Dark Gray', 'twentynineteen' ),
					'slug'  => 'dark-gray',
					'color' => '#111',
				),
				array(
					'name'  => __( 'Light Gray', 'twentynineteen' ),
					'slug'  => 'light-gray',
					'color' => '#767676',
				),
				array(
					'name'  => __( 'White', 'twentynineteen' ),
					'slug'  => 'white',
					'color' => '#FFF',
				),
			)
		);

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Add support for custom line height.
		add_theme_support( 'custom-line-height' );
	}
endif;
add_action( 'after_setup_theme', 'twentynineteen_setup' );

if ( ! function_exists( 'wp_get_list_item_separator' ) ) :
	/**
	 * Retrieves the list item separator based on the locale.
	 *
	 * Added for backward compatibility to support pre-6.0.0 WordPress versions.
	 *
	 * @since 6.0.0
	 */
	function wp_get_list_item_separator() {
		/* translators: Used between list items, there is a space after the comma. */
		return __( ', ', 'twentynineteen' );
	}
endif;

/**
 * Registers widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function twentynineteen_widgets_init() {

	register_sidebar(
		array(
			'name'          => __( 'Footer', 'twentynineteen' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Add widgets here to appear in your footer.', 'twentynineteen' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'twentynineteen_widgets_init' );

/**
 * Replaces "[...]" (appended to automatically generated excerpts) with ... and
 * a 'Continue reading' link.
 *
 * @since Twenty Nineteen 2.0
 *
 * @param string $link Link to single post/page.
 * @return string 'Continue reading' link prepended with an ellipsis.
 */
function twentynineteen_excerpt_more( $link ) {
	if ( is_admin() ) {
		return $link;
	}

	$link = sprintf(
		'<p class="link-more"><a href="%1$s" class="more-link">%2$s</a></p>',
		esc_url( get_permalink( get_the_ID() ) ),
		/* translators: %s: Post title. Only visible to screen readers. */
		sprintf( __( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'twentynineteen' ), get_the_title( get_the_ID() ) )
	);
	return ' &hellip; ' . $link;
}
add_filter( 'excerpt_more', 'twentynineteen_excerpt_more' );

/**
 * Sets the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width Content width.
 */
function twentynineteen_content_width() {
	// This variable is intended to be overruled from themes.
	// Open WPCS issue: {@link https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards/issues/1043}.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$GLOBALS['content_width'] = apply_filters( 'twentynineteen_content_width', 640 );
}
add_action( 'after_setup_theme', 'twentynineteen_content_width', 0 );

/**
 * Enqueues scripts and styles.
 *
 * @since Twenty Nineteen 1.0
 */
function twentynineteen_scripts() {
	wp_enqueue_style( 'twentynineteen-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );

	wp_style_add_data( 'twentynineteen-style', 'rtl', 'replace' );

	if ( has_nav_menu( 'menu-1' ) ) {
		wp_enqueue_script(
			'twentynineteen-priority-menu',
			get_theme_file_uri( '/js/priority-menu.js' ),
			array(),
			'20200129',
			array(
				'in_footer' => false, // Because involves header.
				'strategy'  => 'defer',
			)
		);
		wp_enqueue_script(
			'twentynineteen-touch-navigation',
			get_theme_file_uri( '/js/touch-keyboard-navigation.js' ),
			array(),
			'20230621',
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	wp_enqueue_style( 'twentynineteen-print-style', get_template_directory_uri() . '/print.css', array(), wp_get_theme()->get( 'Version' ), 'print' );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'twentynineteen_scripts' );

/**
 * Fixes skip link focus in IE11.
 *
 * This does not enqueue the script because it is tiny and because it is only for IE11,
 * thus it does not warrant having an entire dedicated blocking script being loaded.
 *
 * @since Twenty Nineteen 1.0
 * @deprecated Twenty Nineteen 2.6 Removed from wp_print_footer_scripts action.
 *
 * @link https://git.io/vWdr2
 */
function twentynineteen_skip_link_focus_fix() {
	// The following is minified via `terser --compress --mangle -- js/skip-link-focus-fix.js`.
	wp_print_inline_script_tag(
		'/(trident|msie)/i.test(navigator.userAgent) && document.getElementById && window.addEventListener && window.addEventListener("hashchange", function() {
			var t, e = location.hash.substring(1);
			/^[A-z0-9_-]+$/.test(e) && (t = document.getElementById(e)) && (/^(?:a|select|input|button|textarea)$/i.test(t.tagName) || (t.tabIndex = -1), t.focus());
		}, false);'
	);
}

/**
 * Enqueues supplemental block editor styles.
 */
function twentynineteen_editor_customizer_styles() {

	wp_enqueue_style( 'twentynineteen-editor-customizer-styles', get_theme_file_uri( '/style-editor-customizer.css' ), false, '2.1', 'all' );

	if ( 'custom' === get_theme_mod( 'primary_color' ) ) {
		// Include color patterns.
		require_once get_parent_theme_file_path( '/inc/color-patterns.php' );
		wp_add_inline_style( 'twentynineteen-editor-customizer-styles', twentynineteen_custom_colors_css() );
	}
}
add_action( 'enqueue_block_editor_assets', 'twentynineteen_editor_customizer_styles' );

/**
 * Displays custom color CSS in customizer and on frontend.
 */
function twentynineteen_colors_css_wrap() {

	// Only include custom colors in customizer or frontend.
	if ( ( ! is_customize_preview() && 'default' === get_theme_mod( 'primary_color', 'default' ) ) || is_admin() ) {
		return;
	}

	require_once get_parent_theme_file_path( '/inc/color-patterns.php' );

	$primary_color = 199;
	if ( 'default' !== get_theme_mod( 'primary_color', 'default' ) ) {
		$primary_color = get_theme_mod( 'primary_color_hue', 199 );
	}
	?>

	<style type="text/css" id="custom-theme-colors" <?php echo is_customize_preview() ? 'data-hue="' . absint( $primary_color ) . '"' : ''; ?>>
		<?php echo twentynineteen_custom_colors_css(); ?>
	</style>
	<?php
}
add_action( 'wp_head', 'twentynineteen_colors_css_wrap' );

/**
 * SVG Icons class.
 */
require get_template_directory() . '/classes/class-twentynineteen-svg-icons.php';

/**
 * Custom Comment Walker template.
 */
require get_template_directory() . '/classes/class-twentynineteen-walker-comment.php';

/**
 * Common theme functions.
 */
require get_template_directory() . '/inc/helper-functions.php';

/**
 * SVG Icons related functions.
 */
require get_template_directory() . '/inc/icon-functions.php';

/**
 * Enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Custom template tags for the theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Registers block patterns and pattern categories.
 *
 * @since Twenty Nineteen 3.0
 */
function twentynineteen_register_block_patterns() {
	require get_template_directory() . '/inc/block-patterns.php';
}

add_action( 'init', 'twentynineteen_register_block_patterns' );

if ( ! function_exists( 'wp_sanitize_script_attributes' ) ) {
	/**
	 * Sanitizes an attributes array into an attributes string to be placed inside a `<script>` tag.
	 *
	 * Automatically injects type attribute if needed.
	 * Used by {@see wp_get_script_tag()} and {@see wp_get_inline_script_tag()}.
	 *
	 * @since 5.7.0
	 *
	 * @param array $attributes Key-value pairs representing `<script>` tag attributes.
	 * @return string String made of sanitized `<script>` tag attributes.
	 */
	function wp_sanitize_script_attributes( $attributes ) {
		$html5_script_support = ! is_admin() && ! current_theme_supports( 'html5', 'script' );
		$attributes_string    = '';

		/*
		 * If HTML5 script tag is supported, only the attribute name is added
		 * to $attributes_string for entries with a boolean value, and that are true.
		 */
		foreach ( $attributes as $attribute_name => $attribute_value ) {
			if ( is_bool( $attribute_value ) ) {
				if ( $attribute_value ) {
					$attributes_string .= $html5_script_support ? sprintf( ' %1$s="%2$s"', esc_attr( $attribute_name ), esc_attr( $attribute_name ) ) : ' ' . esc_attr( $attribute_name );
				}
			} else {
				$attributes_string .= sprintf( ' %1$s="%2$s"', esc_attr( $attribute_name ), esc_attr( $attribute_value ) );
			}
		}

		return $attributes_string;
	}
}

if ( ! function_exists( 'wp_get_inline_script_tag' ) ) {
	/**
	 * Constructs an inline script tag.
	 *
	 * It is possible to inject attributes in the `<script>` tag via the {@see 'wp_inline_script_attributes'} filter.
	 * Automatically injects type attribute if needed.
	 *
	 * Added for backward compatibility to support pre-5.7.0 WordPress versions.
	 *
	 * @since 5.7.0
	 *
	 * @param string $data       Data for script tag: JavaScript, importmap, speculationrules, etc.
	 * @param array  $attributes Optional. Key-value pairs representing `<script>` tag attributes.
	 * @return string String containing inline JavaScript code wrapped around `<script>` tag.
	 */
	function wp_get_inline_script_tag( $data, $attributes = array() ) {
		$is_html5 = current_theme_supports( 'html5', 'script' ) || is_admin();
		if ( ! isset( $attributes['type'] ) && ! $is_html5 ) {
			// Keep the type attribute as the first for legacy reasons (it has always been this way in core).
			$attributes = array_merge(
				array( 'type' => 'text/javascript' ),
				$attributes
			);
		}

		/*
		 * XHTML extracts the contents of the SCRIPT element and then the XML parser
		 * decodes character references and other syntax elements. This can lead to
		 * misinterpretation of the script contents or invalid XHTML documents.
		 *
		 * Wrapping the contents in a CDATA section instructs the XML parser not to
		 * transform the contents of the SCRIPT element before passing them to the
		 * JavaScript engine.
		 *
		 * Example:
		 *
		 *     <script>console.log('&hellip;');</script>
		 *
		 *     In an HTML document this would print "&hellip;" to the console,
		 *     but in an XHTML document it would print "…" to the console.
		 *
		 *     <script>console.log('An image is <img> in HTML');</script>
		 *
		 *     In an HTML document this would print "An image is <img> in HTML",
		 *     but it's an invalid XHTML document because it interprets the `<img>`
		 *     as an empty tag missing its closing `/`.
		 *
		 * @see https://www.w3.org/TR/xhtml1/#h-4.8
		 */
		if (
			! $is_html5 &&
			(
				! isset( $attributes['type'] ) ||
				'module' === $attributes['type'] ||
				str_contains( $attributes['type'], 'javascript' ) ||
				str_contains( $attributes['type'], 'ecmascript' ) ||
				str_contains( $attributes['type'], 'jscript' ) ||
				str_contains( $attributes['type'], 'livescript' )
			)
		) {
			/*
			 * If the string `]]>` exists within the JavaScript it would break
			 * out of any wrapping CDATA section added here, so to start, it's
			 * necessary to escape that sequence which requires splitting the
			 * content into two CDATA sections wherever it's found.
			 *
			 * Note: it's only necessary to escape the closing `]]>` because
			 * an additional `<![CDATA[` leaves the contents unchanged.
			 */
			$data = str_replace( ']]>', ']]]]><![CDATA[>', $data );

			// Wrap the entire escaped script inside a CDATA section.
			$data = sprintf( "/* <![CDATA[ */\n%s\n/* ]]> */", $data );
		}

		$data = "\n" . trim( $data, "\n\r " ) . "\n";

		/** This filter is documented in wp-includes/script-loader.php */
		$attributes = apply_filters( 'wp_inline_script_attributes', $attributes, $data );

		return sprintf( "<script%s>%s</script>\n", wp_sanitize_script_attributes( $attributes ), $data );
	}
}

if ( ! function_exists( 'wp_print_inline_script_tag' ) ) {
	/**
	 * Prints an inline script tag.
	 *
	 * It is possible to inject attributes in the `<script>` tag via the {@see 'wp_inline_script_attributes'} filter.
	 * Automatically injects type attribute if needed.
	 *
	 * @since 5.7.0
	 *
	 * @param string $data       Data for script tag: JavaScript, importmap, speculationrules, etc.
	 * @param array  $attributes Optional. Key-value pairs representing `<script>` tag attributes.
	 */
	function wp_print_inline_script_tag( $data, $attributes = array() ) {
		echo wp_get_inline_script_tag( $data, $attributes );
	}
}
