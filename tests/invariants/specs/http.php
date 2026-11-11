<?php
/**
 * Invariants of the URLs WordPress lets its HTTP API request, checked against how a client reads them.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Every ASCII byte, to tell an international domain name from an ASCII one without PCRE.
$ascii = implode( '', array_map( 'chr', range( 0, 127 ) ) );

return array(
	WP_Invariant::define( 'validated-urls-stay-external' )
		->describe( 'When wp_http_validate_url() accepts a URL, an HTTP client goes to the host it checked, as a WHATWG URL parser reads the URL, and that host is not a loopback, private, link-local, carrier-grade NAT or unspecified address, in any IPv4 or IPv6 form, unless it is the site\'s own host or the http_request_host_is_external filter allows it.' )
		->include_test_callers()
		->after(
			'wp_http_validate_url',
			static function ( WP_Invariant_Call $call ) use ( $ascii ) {
				$url = $call->return_value();
				if ( ! is_string( $url ) || '' === $url ) {
					return;
				}

				$client = Uri\WhatWg\Url::parse( $url );
				if ( null === $client ) {
					return;
				}
				$host = trim( strtolower( (string) $client->getAsciiHost() ), '[].' );

				// What wp_http_validate_url() checked.
				$parsed  = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- What wp_http_validate_url() uses.
				$checked = is_array( $parsed ) && isset( $parsed['host'] ) ? trim( strtolower( $parsed['host'] ), '.' ) : '';
				if ( strlen( $checked ) !== strspn( $checked, $ascii ) ) {
					// A client goes to an international domain name by its ASCII form.
					$checked = function_exists( 'idn_to_ascii' ) ? idn_to_ascii( $checked ) : false;
					if ( false === $checked ) {
						return;
					}
				}

				// wp_http_validate_url() resolves a host such as 0x7f.1 with gethostbyname(), which reads IPv4 addresses as a WHATWG URL parser does.
				$same = $host === $checked || ( filter_var( $host, FILTER_VALIDATE_IP ) && '' !== $checked && gethostbyname( $checked ) === $host );
				if ( ! $call->expect( $same, 'wp_http_validate_url( %s ) checked the host %s, but an HTTP client would go to %s.', $call->arg( 'url' ), $checked, $host ) ) {
					return;
				}

				$internal = filter_var( $host, FILTER_VALIDATE_IP ) ? WP_Invariant_Security::internal_address( $host ) : null;
				if ( null === $internal ) {
					return;
				}

				$home = parse_url( (string) get_option( 'home' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- What wp_http_validate_url() uses.
				if ( is_array( $home ) && isset( $home['host'] ) && strtolower( $home['host'] ) === $checked ) {
					return;
				}

				/** This filter is documented in wp-includes/http.php */
				$call->expect( (bool) apply_filters( 'http_request_host_is_external', false, $host, $url ), 'wp_http_validate_url( %s ) accepted a URL whose host %s is a %s address.', $call->arg( 'url' ), $host, $internal );
			}
		),
);
