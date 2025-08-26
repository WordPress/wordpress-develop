<?php
/**
 * Twenty Twenty-Four functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Four
 * @since Twenty Twenty-Four 1.0
 */

if ( ! function_exists( 'twentytwentyfour_setup' ) ) {
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 *
	 * @since Twenty Twenty-Four 1.0
	 *
	 * @return void
	 */
	function twentytwentyfour_setup() {
		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		// Set content-width.
		global $content_width;
		if ( ! isset( $content_width ) ) {
			$content_width = 1024;
		}

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );
		set_post_thumbnail_size( 1024, 0 );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

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

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );

		// Add support for editor styles.
		add_theme_support( 'editor-styles' );

		// Enqueue editor styles and fonts.
		add_editor_style(
			array(
				'./assets/css/style-shared.min.css',
			)
		);

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Remove the core block patterns.
		remove_theme_support( 'core-block-patterns' );
	}
}
add_action( 'after_setup_theme', 'twentytwentyfour_setup' );

/**
 * Enqueue style sheet.
 */
function twentytwentyfour_enqueue_style_sheet() {
	wp_enqueue_style(
		'twentytwentyfour-style',
		get_stylesheet_uri(),
		[],
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'twentytwentyfour_enqueue_style_sheet' );

/**
 * Modify user request action email data to include the plain text confirm key
 *
 * @param string $content    The email content.
 * @param array  $email_data The email data including the request object and confirm_url.
 * @return string The filtered email content.
 */
function fix_user_request_confirm_key( $content, $email_data ) {
    // Extract the confirm_key from the confirm_url
    if ( isset( $email_data['confirm_url'] ) ) {
        $url_parts = parse_url( $email_data['confirm_url'] );
        if ( isset( $url_parts['query'] ) ) {
            parse_str( $url_parts['query'], $query_params );
            if ( isset( $query_params['confirm_key'] ) ) {
                // Add the plain text confirm_key to the request object
                $email_data['request']->plain_confirm_key = $query_params['confirm_key'];
            }
        }
    }
    
    return $content;
}
add_filter( 'user_request_action_email_content', 'fix_user_request_confirm_key', 5, 2 );

/**
 * Register block styles.
 */
function twentytwentyfour_register_block_styles() {

	register_block_style(
		'core/details',
		array(
			'name'         => 'arrow-icon-details',
			'label'        => __( 'Arrow icon', 'twentytwentyfour' ),
			/*
			 * Styles for the custom Arrow icon style of the Details block
			 */
			'inline_style' => '
				.is-style-arrow-icon-details {
					padding-top: var(--wp--preset--spacing--10);
					padding-bottom: var(--wp--preset--spacing--10);
				}

				.is-style-arrow-icon-details summary {
					list-style-type: "\2193\00a0\00a0\00a0";
				}

				.is-style-arrow-icon-details[open]>summary {
					list-style-type: "\2192\00a0\00a0\00a0";
				}',
		)
	);
	register_block_style(
		'core/post-terms',
		array(
			'name'         => 'pill',
			'label'        => __( 'Pill', 'twentytwentyfour' ),
			/*
			 * Styles variation for post terms
			 * https://github.com/WordPress/gutenberg/issues/24956
			 */
			'inline_style' => '
				.is-style-pill a,
				.is-style-pill span:not([class], [data-rich-text-placeholder]) {
					display: inline-block;
					background-color: var(--wp--preset--color--base-2);
					padding: 0.375rem 0.875rem;
					border-radius: var(--wp--preset--spacing--20);
				}

				.is-style-pill a:hover {
					background-color: var(--wp--preset--color--contrast-3);
				}',
		)
	);
	register_block_style(
		'core/list',
		array(
			'name'         => 'checkmark-list',
			'label'        => __( 'Checkmark', 'twentytwentyfour' ),
			/*
			 * Styles for the custom checkmark lists
			 */
			'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
		)
	);
	register_block_style(
		'core/navigation-link',
		array(
			'name'         => 'outline',
			'label'        => __( 'Outline', 'twentytwentyfour' ),
			/*
			 * Styles for the custom outline navigation link style
			 */
			'inline_style' => '
				.is-style-outline .wp-block-navigation-item__content {
					padding: 0.5rem 1.25rem !important;
					border: 1px solid currentColor;
					border-radius: var(--wp--preset--spacing--20);
				}

				.is-style-outline .wp-block-navigation-item__content:hover {
					background-color: var(--wp--preset--color--contrast-3);
				}',
		)
	);

	register_block_style(
		'core/heading',
		array(
			'name'         => 'asterisk',
			'label'        => __( 'With asterisk', 'twentytwentyfour' ),
			'inline_style' => "
				.is-style-asterisk:before {
					content: '';
					width: 1.5rem;
					height: 3rem;
					background: var(--wp--preset--color--contrast-2, currentColor);
					clip-path: path('M11.93.684v8.039l5.633-5.633 1.216 1.23-5.66 5.66h8.04v1.737h-8.04l5.66 5.66-1.216 1.23-5.633-5.633v8.04H10.2v-8.04l-5.66 5.633-1.23-1.23 5.66-5.66H.93v-1.737h8.04l-5.66-5.66 1.23-1.23L10.2 8.723V.684h1.73Z');
					display: block;
				}
				",
		)
	);

	register_block_style(
		'core/quote',
		array(
			'name'         => 'plain',
			'label'        => __( 'Plain', 'twentytwentyfour' ),
			'inline_style' => "
				.is-style-plain {
					border-left: 0 !important;
				}
				.is-style-plain:before {
					content: '\201C';
					font-family: Georgia, serif;
					font-size: clamp(5rem, 10vw, 10rem);
					line-height: 0;
					display: block;
					margin-bottom: -0.5rem;
					color: var(--wp--preset--color--contrast-2, currentColor);
				}
				",
		)
	);

	register_block_style(
		'core/cover',
		array(
			'name'         => 'rounded-cover',
			'label'        => __( 'Rounded', 'twentytwentyfour' ),
			'inline_style' => "
				.is-style-rounded-cover {
					border-radius: 1rem;
					overflow: hidden;
				}
				",
		)
	);

	register_block_style(
		'core/button',
		array(
			'name'         => 'outline-2',
			'label'        => __( 'Outline alt', 'twentytwentyfour' ),
			'inline_style' => '
				.is-style-outline-2 .wp-element-button {
					border: 1px solid currentColor;
					padding: 0.4rem 1.4rem;
					background-color: transparent;
					color: currentColor;
				}

				.is-style-outline-2 .wp-element-button:hover {
					background-color: var(--wp--preset--color--contrast-3);
					color: currentColor;
				}',
		)
	);

	register_block_style(
		'core/button',
		array(
			'name'         => 'arrow-link',
			'label'        => __( 'Arrow Link', 'twentytwentyfour' ),
			'inline_style' => '
				.is-style-arrow-link .wp-element-button {
					background-color: transparent;
					border: 0;
					padding: 0;
					color: currentColor;
				}

				.is-style-arrow-link .wp-element-button::after {
					content: "\2197";
					padding-left: 0.5em;
				}

				.is-style-arrow-link .wp-element-button:hover {
					background-color: transparent;
					text-decoration: underline;
				}',
		)
	);
}
add_action( 'init', 'twentytwentyfour_register_block_styles' );