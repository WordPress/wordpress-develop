<?php
/**
 * Theme previews using the Site Editor for block themes.
 *
 * @package WordPress
 */

/**
 * Filters the blog option to return the path for the previewed theme.
 *
 * @since 6.3.0
 *
 * @param string $current_stylesheet The current theme's stylesheet or template path.
 * @return string The previewed theme's stylesheet or template path.
 */
function wp_get_theme_preview_path( $current_stylesheet = null ) {
	if ( ! current_user_can( 'switch_themes' ) ) {
		return $current_stylesheet;
	}

	$preview_stylesheet = ! empty( $_GET['wp_theme_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['wp_theme_preview'] ) ) : null;
	$wp_theme           = wp_get_theme( $preview_stylesheet );
	if ( ! is_wp_error( $wp_theme->errors() ) ) {
		if ( current_filter() === 'template' ) {
			$theme_path = $wp_theme->get_template();
		} else {
			$theme_path = $wp_theme->get_stylesheet();
		}

		return sanitize_text_field( $theme_path );
	}

	return $current_stylesheet;
}

/**
 * Adds a middleware to `apiFetch` to set the theme for the preview.
 * This adds a `wp_theme_preview` URL parameter to API requests from the Site Editor, so they also respond as if the theme is set to the value of the parameter.
 *
 * @since 6.3.0
 */
function wp_attach_theme_preview_middleware() {
	// Don't allow non-admins to preview themes.
	if ( ! current_user_can( 'switch_themes' ) ) {
		return;
	}

	wp_add_inline_script(
		'wp-api-fetch',
		sprintf(
			'wp.apiFetch.use( wp.apiFetch.createThemePreviewMiddleware( %s ) );',
			wp_json_encode( sanitize_text_field( wp_unslash( $_GET['wp_theme_preview'] ) ) )
		),
		'after'
	);
}

/**
 * Set a JavaScript constant for theme activation.
 *
 * Sets the JavaScript global WP_BLOCK_THEME_ACTIVATE_NONCE containing the nonce
 * required to activate a theme. For use within the site editor.
 *
 * @see https://github.com/WordPress/gutenberg/pull/41836
 *
 * @since 6.3.0
 * @access private
 */
function wp_block_theme_activate_nonce() {
	$nonce_handle = 'switch-theme_' . wp_get_theme_preview_path();
	?>
	<script type="text/javascript">
		window.WP_BLOCK_THEME_ACTIVATE_NONCE = <?php echo wp_json_encode( wp_create_nonce( $nonce_handle ) ); ?>;
	</script>
	<?php
}

/**
 * Add `_doing_it_wrong()` notice if the theme is requested before `pluggable.php` is loaded.
 *
 * @since 6.3.2
 * @param string $current The current theme's stylesheet or template path.
 * @return string Return the current theme's stylesheet or template path as is.
 */
function wp_theme_preview_maybe_doing_it_wrong( $current ) {
	if ( ! empty( $_GET['wp_theme_preview'] ) && ! function_exists( 'wp_get_current_user' ) ) {
		if ( current_filter() === 'template' ) {
			$function_name = 'get_template()';
		} else {
			$function_name = 'get_stylesheet()';
		}

		_doing_it_wrong(
			$function_name,
			sprintf(
				/* translators: translators: Developer debugging message. 1: PHP function name */
				__( 'Calling %s before pluggable.php has loaded can result in getting the current theme instead of the previewed theme. Please ensure that you are using these functions after the plugins_loaded action has fired if this is not intended.' ),
				$function_name
			),
			'6.3.2'
		);
	}
	return $current;
}
add_filter( 'stylesheet', 'wp_theme_preview_maybe_doing_it_wrong' );
add_filter( 'template', 'wp_theme_preview_maybe_doing_it_wrong' );

/**
 * Add filters and actions to enable Block Theme Previews in the Site Editor.
 *
 * The filters and actions should be added after `pluggable.php` is included as they may
 * trigger code that uses `current_user_can()` which requires functionality from `pluggable.php`.
 *
 * @since 6.3.2
 */
function wp_initialize_theme_preview_hooks() {
	if ( ! empty( $_GET['wp_theme_preview'] ) ) {
		add_filter( 'stylesheet', 'wp_get_theme_preview_path' );
		add_filter( 'template', 'wp_get_theme_preview_path' );
		add_action( 'init', 'wp_attach_theme_preview_middleware' );
		add_action( 'admin_head', 'wp_block_theme_activate_nonce' );
	}
}
