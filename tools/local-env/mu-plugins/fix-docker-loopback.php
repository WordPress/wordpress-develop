<?php
/**
 * Plugin Name: Fix Docker Loopback Requests
 * Description: Routes WordPress loopback HTTP requests (Site Health, wp_remote_get(home_url()), cron, etc.) to the Docker host gateway. Inside the php/cli containers "localhost" is the container's own loopback where nothing listens on the published port, so requests to home_url() fail with "cURL error 7: Could not connect to server". The docker-compose `extra_hosts: localhost:host-gateway` mapping is shadowed by Docker's default `127.0.0.1 localhost` entry, so this forces the gateway resolution at the cURL layer instead.
 *
 * This is a development-environment-only shim and should never ship to production.
 *
 * @package WordPress\Develop
 */

namespace WordPress\Develop;

// Only run in the local Docker dev environment.
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
