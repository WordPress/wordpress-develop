<?php
/**
 * HTTPS migration functions.
 *
 * @package WordPress
 * @since 5.7.0
 */

/**
 * Checks whether WordPress should replace old HTTP URLs to the site with their HTTPS counterpart.
 *
 * If a WordPress site had its URL changed from HTTP to HTTPS, by default this will return `true`, causing WordPress to
 * add frontend filters to replace insecure site URLs that may be present in older database content. The
 * {@see 'wp_should_replace_insecure_home_url'} filter can be used to modify that behavior.
 *
 * @since 5.7.0
 *
 * @return bool True if insecure URLs should replaced, false otherwise.
 */
function wp_should_replace_insecure_home_url() {
	$should_replace_insecure_home_url = wp_is_using_https()
		// For automatic replacement, both 'home' and 'siteurl' need to not only use HTTPS, they also need to be using
		// the same domain.
		&& wp_parse_url( home_url(), PHP_URL_HOST ) === wp_parse_url( site_url(), PHP_URL_HOST )
		&& '0' === get_option( 'https_migrated' );

	/**
	 * Filters whether WordPress should replace old HTTP URLs to the site with their HTTPS counterpart.
	 *
	 * If a WordPress site had its URL changed from HTTP to HTTPS, by default this will return `true`. This filter can
	 * be used to disable that behavior, e.g. after having replaced URLs manually in the database.
	 *
	 * @since 5.7.0
	 *
	 * @param bool $should_replace_insecure_home_url Whether insecure HTTP URLs to the site should be replaced.
	 */
	return apply_filters( 'wp_should_replace_insecure_home_url', $should_replace_insecure_home_url );
}

/**
 * Replaces insecure HTTP URLs to the site in the given content, if configured to do so.
 *
 * This function replaces all occurrences of the HTTP version of the site's URL with its HTTPS counterpart, if
 * determined via {@see wp_should_replace_insecure_home_url()}.
 *
 * @since 5.7.0
 *
 * @param string $content Content to replace URLs in.
 * @return string Filtered content.
 */
function wp_replace_insecure_home_url( $content ) {
	if ( ! wp_should_replace_insecure_home_url() ) {
		return $content;
	}

	$https_url = home_url( '', 'https' );
	$http_url  = str_replace( 'https://', 'http://', $https_url );

	// Also replace potentially escaped URL.
	$escaped_https_url = str_replace( '/', '\/', $https_url );
	$escaped_http_url  = str_replace( '/', '\/', $http_url );

	return str_replace(
		array(
			$http_url,
			$escaped_http_url,
		),
		array(
			$https_url,
			$escaped_https_url,
		),
		$content
	);
}

/**
 * Updates the 'https_migrated' option if needed when the given URL has been updated from HTTP to HTTPS.
 *
 * If this is a fresh site, a migration will not be necessary, so the migration will be flagged as '1'. Otherwise, it
 * will be flagged as '0'.
 *
 * This is hooked into the {@see 'update_option_home'} action.
 *
 * @since 5.7.0
 * @access private
 *
 * @param mixed $old_url Previous value of the URL option.
 * @param mixed $new_url New value of the URL option.
 */
function wp_update_https_migrated( $old_url, $new_url ) {
	// Do nothing if WordPress is being installed.
	if ( wp_installing() ) {
		return;
	}

	// Delete/reset the option if the new URL is not the HTTPS version of the old URL.
	if ( untrailingslashit( (string) $old_url ) !== str_replace( 'https://', 'http://', untrailingslashit( (string) $new_url ) ) ) {
		delete_option( 'https_migrated' );
		return;
	}

	// If this is a fresh site, there is no content to migrate, so mark migration as completed.
	$https_migrated = get_option( 'fresh_site' ) ? '1' : '0';

	update_option( 'https_migrated', $https_migrated );
}
