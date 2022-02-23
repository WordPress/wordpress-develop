<?php
/**
 * Press This Display and Handler.
 *
 * @package WordPress
 * @subpackage Press_This
 */

const IFRAME_REQUEST = true;

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

/**
 * Loads the Press This application.
 *
 * If Press This is not installed, it offers to install it for users with sufficient capabilities.
 */
function wp_load_press_this() {
	/**
	 * Press This Plugin Slug
	 *
	 * The slug for the Press This plugin, e.g. `press-this`.
	 *
	 * @since 5.8.0
	 *
	 * @param string $slug The slug for the plugin used for Press This.
	 */
	$plugin_slug = apply_filters( 'press_this_plugin_slug', 'press-this' );
	/**
	 * Press This Plugin Filename
	 *
	 * The filename for the Press This plugin, e.g. `press-this/press-this-plugin.php`.
	 * This is the value used by WordPress to indicate the plugin is active. It should be the main plugin file for the
	 * Press This plugin.
	 *
	 * @since 5.8.0
	 *
	 * @param string $filename The file name value for the plugin used for Press This.
	 */
	$plugin_file = apply_filters( 'press_this_plugin_file', 'press-this/press-this-plugin.php' );

	if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( get_post_type_object( 'post' )->cap->create_posts ) ) {
		wp_die(
			__( 'Sorry, you are not allowed to create posts as this user.' ),
			__( 'You need a higher level of permission.' ),
			403
		);
	} elseif ( is_plugin_active( $plugin_file ) ) {
		/**
		 * Press This execution function
		 *
		 * The function name used to executing Press This. By default, this is the Core function `wp_execute_press_this`
		 * and assumes the default Press This plugin.
		 *
		 * @since 5.8.0
		 *
		 * @param callable $func The function used to execute Press This.
		 */
		$func = apply_filters( 'press_this_execution_func', 'wp_execute_press_this' );

		if ( is_callable( $func ) ) {
			call_user_func( $func );
		} else {
			wp_die(
				/* translators: %s is the name of a WordPress filter. */
				sprintf( __( 'The value passed to the %s filter must be a callable.' ), 'press_this_execution_func' ),
				500
			);
		}
	} elseif ( current_user_can( 'activate_plugins' ) ) {
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			$url    = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'activate',
						'plugin' => $plugin_file,
						'from'   => 'press-this',
					),
					admin_url( 'plugins.php' )
				),
				'activate-plugin_' . $plugin_file
			);
			$action = sprintf(
				'<a href="%1$s" aria-label="%2$s">%2$s</a>',
				esc_url( $url ),
				__( 'Activate Press This' )
			);
		} else {
			if ( is_main_site() ) {
				$url    = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'install-plugin',
							'plugin' => $plugin_slug,
							'from'   => 'press-this',
						),
						self_admin_url( 'update.php' )
					),
					'install-plugin_' . $plugin_slug
				);
				$action = sprintf(
					'<a href="%1$s" class="install-now" data-slug="%2$s" data-name="%2$s" aria-label="%3$s">%3$s</a>',
					esc_url( $url ),
					esc_attr( $plugin_slug ),
					__( 'Install Now' )
				);
			} else {
				$action = sprintf(
					/* translators: %s: URL to Press This bookmarklet on the main site. */
					__( 'Press This is not installed. Please install Press This from <a href="%s">the main site</a>.' ),
					get_admin_url( get_current_network_id(), 'press-this.php' )
				);
			}
		}
		wp_die(
			__( 'The Press This plugin is required.' ) . '<br />' . $action,
			__( 'Installation Required' ),
			200
		);
	} else {
		wp_die(
			__( 'Press This is not available. Please contact your site administrator.' ),
			__( 'Installation Required' ),
			200
		);
	}
}

/**
 * Executes the default Press This application.
 */
function wp_execute_press_this() {
	include WP_PLUGIN_DIR . '/press-this/class-wp-press-this-plugin.php';
	$wp_press_this = new WP_Press_This_Plugin();
	$wp_press_this->html();
}

wp_load_press_this();
