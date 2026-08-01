<?php
/**
 * Plugin Name: Fix Docker Loopback Requests
 * Description: Routes WordPress loopback HTTP requests (Site Health, wp_remote_get(home_url()), cron, etc.) to the Docker host gateway. Inside the php/cli containers "localhost" is the container's own loopback where nothing listens on the published port, so requests to home_url() fail with "cURL error 7: Could not connect to server". The docker-compose `extra_hosts: localhost:host-gateway` mapping is meant to address this, but it has no effect when cURL resolves "localhost" via glibc's getaddrinfo(), which special-cases that name to loopback and never sees the /etc/hosts gateway entry. This shim is therefore not always necessary -- on resolvers that do honor the mapping (e.g. a c-ares-based libcurl) loopback already works -- but it forces the gateway resolution at the cURL layer for the environments where it does not.
 *
 * This is a development-environment-only shim and should never ship to production.
 *
 * This file is copied from tools/local-env/mu-plugins/fix-docker-loopback.php, so ensure any changes are made there.
 * Restart the environment to apply the changes, as that file will be re-copied when the container starts.
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
	if ( $host !== $home_host ) {
		return;
	}

	// host.docker.internal resolves to the host gateway, which reaches the published web-server port.
	$gateway = gethostbyname( 'host.docker.internal' );
	if ( 'host.docker.internal' === $gateway ) {
		return; // Not running under Docker Desktop / gateway unavailable.
	}

	$port = wp_parse_url( $url, PHP_URL_PORT );
	if ( ! $port ) {
		$port = ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) ? 443 : 80;
	}

	curl_setopt( $handle, CURLOPT_RESOLVE, array( "{$host}:{$port}:{$gateway}" ) );
}
