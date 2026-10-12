<?php
/**
 * Plugin Name: Fix Docker Loopback Requests
 * Plugin URI: https://core.trac.wordpress.org/ticket/65484
 * Description: Routes loopback HTTP requests (cron, Site Health, file editor checks) to the Docker host gateway. Copied from <code>tools/local-env/mu-plugins/fix-docker-loopback.php</code> when the environment starts, so make changes there, then run <code>npm run env:restart</code>.
 * Version: 1.0.0
 * Author: WordPress Core Team
 * Author URI: https://make.wordpress.org/core/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * Inside the php and cli containers, "localhost" is the container itself, where nothing listens on the published
 * web-server port. Loopback requests therefore fail with "cURL error 7: Could not connect to server". The
 * `localhost:host-gateway` entry in docker-compose's `extra_hosts` does not help, because 127.0.0.1 is still
 * resolved for "localhost". This shim pins requests for the site's own host to the gateway at the cURL layer.
 *
 * This is a development-environment-only shim and should never ship to production.
 *
 * @package WordPress\Develop
 */

namespace WordPress\Develop;

// Only run in the local dev environment.
if ( ! function_exists( 'wp_get_environment_type' ) || 'local' !== wp_get_environment_type() ) {
	return;
}

add_action( 'http_api_curl', __NAMESPACE__ . '\\resolve_loopback_to_host_gateway', 10, 3 );

/**
 * Pins loopback requests to the Docker host gateway via CURLOPT_RESOLVE.
 *
 * @param resource             $handle The cURL handle. A CurlHandle instance as of PHP 8.0.
 * @param array<string, mixed> $args   The HTTP request arguments.
 * @param string               $url    The request URL.
 */
function resolve_loopback_to_host_gateway( $handle, array $args, string $url ): void {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) ) {
		return;
	}

	// Only rewrite requests aimed at this site (loopback), not arbitrary outbound requests.
	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
	if ( $host !== $home_host && $host !== $site_host ) {
		return;
	}

	// host.docker.internal resolves to the host gateway, which reaches the published web-server port. Docker Desktop
	// provides this name automatically, while on Linux Docker Engine it comes from the docker-compose `extra_hosts`.
	$gateway = gethostbyname( 'host.docker.internal' );
	if ( 'host.docker.internal' === $gateway ) {
		return; // Gateway unavailable (e.g. not running under Docker).
	}

	$port = wp_parse_url( $url, PHP_URL_PORT );
	if ( ! $port ) {
		$port = ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) ? 443 : 80;
	}

	curl_setopt( $handle, CURLOPT_RESOLVE, array( "{$host}:{$port}:{$gateway}" ) );
}
