<?php
/**
 * Administration notices API.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.1.0
 */

/**
 * Captures admin notices output for the current screen.
 *
 * @since 7.1.0
 *
 * @return array {
 *     @type string $html  The captured admin notices markup.
 *     @type int    $count The number of non-inline admin notices.
 * }
 */
function wp_capture_admin_notices() {
	global $wp_captured_admin_notices;

	if ( isset( $wp_captured_admin_notices ) ) {
		return $wp_captured_admin_notices;
	}

	ob_start();

	if ( is_network_admin() ) {
		/**
		 * Prints network admin screen notices.
		 *
		 * @since 3.1.0
		 */
		do_action( 'network_admin_notices' );
	} elseif ( is_user_admin() ) {
		/**
		 * Prints user admin screen notices.
		 *
		 * @since 3.1.0
		 */
		do_action( 'user_admin_notices' );
	} else {
		/**
		 * Prints admin screen notices.
		 *
		 * @since 3.1.0
		 */
		do_action( 'admin_notices' );
	}

	/**
	 * Prints generic admin screen notices.
	 *
	 * @since 3.1.0
	 */
	do_action( 'all_admin_notices' );

	$html = ob_get_clean();

	$wp_captured_admin_notices = array(
		'html'  => $html,
		'count' => wp_count_admin_notices_from_html( $html ),
	);

	return $wp_captured_admin_notices;
}

/**
 * Determines whether admin notices are present for the current screen.
 *
 * @since 7.1.0
 *
 * @return bool Whether admin notices are present.
 */
function wp_has_admin_notices() {
	$notices = wp_capture_admin_notices();

	return $notices['count'] > 0;
}

/**
 * Returns the number of admin notices for the current screen.
 *
 * @since 7.1.0
 *
 * @return int The number of admin notices.
 */
function wp_get_admin_notices_count() {
	$notices = wp_capture_admin_notices();

	return $notices['count'];
}

/**
 * Counts non-inline admin notices in the given HTML markup.
 *
 * @since 7.1.0
 *
 * @param string $html Admin notices HTML markup.
 * @return int The number of admin notices.
 */
function wp_count_admin_notices_from_html( $html ) {
	if ( '' === trim( $html ) ) {
		return 0;
	}

	$count = 0;

	if ( preg_match_all( '/<div\s[^>]*\bclass=["\'][^"\']*\b(?:notice|updated|error)\b[^"\']*["\'][^>]*>/i', $html, $matches ) ) {
		foreach ( $matches[0] as $opening_tag ) {
			if ( preg_match( '/\b(?:inline|below-h2)\b/', $opening_tag ) ) {
				continue;
			}

			++$count;
		}
	}

	return $count;
}

/**
 * Prepends the admin notices count to the admin page title.
 *
 * @since 7.1.0
 *
 * @param string $admin_title The page title, with extra context added.
 * @return string The filtered page title.
 */
function wp_prepend_admin_notices_count_to_admin_title( $admin_title ) {
	$count = wp_get_admin_notices_count();

	if ( $count < 1 ) {
		return $admin_title;
	}

	return sprintf(
		/* translators: 1: Number of admin notices, 2: Admin page title. */
		_n( '(%1$s notice) %2$s', '(%1$s notices) %2$s', $count ),
		number_format_i18n( $count ),
		$admin_title
	);
}

/**
 * Renders captured admin notices within an accessible landmark container.
 *
 * @since 7.1.0
 */
function wp_render_admin_notices() {
	$notices = wp_capture_admin_notices();

	if ( '' === trim( $notices['html'] ) ) {
		return;
	}

	printf(
		'<aside id="wp-admin-notices" class="wp-admin-notices" aria-label="%s">%s</aside>',
		esc_attr__( 'Administrative notices' ),
		$notices['html'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
