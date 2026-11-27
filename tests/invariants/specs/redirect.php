<?php
/**
 * Invariants of redirects, checked against how a browser reads a Location header.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'host-agrees-with-browser' )
		->describe( 'When wp_validate_redirect() lets a location through, a browser goes to the host it checked: the host parse_url() finds, or for a location without a host, the site itself.' )
		->include_test_callers()
		->after(
			'wp_validate_redirect',
			static function ( WP_Invariant_Call $call ) {
				$location = $call->return_value();
				if ( ! is_string( $location ) || '' === $location || $location === $call->arg( 'fallback_url' ) ) {
					return;
				}

				// wp_validate_redirect() checks the host parse_url() finds, against the hosts it allows.
				$parsed  = parse_url( $location ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- What wp_validate_redirect() uses.
				$checked = is_array( $parsed ) && isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : 'site.invalid';

				// A browser goes to an international domain name by its ASCII form.
				if ( preg_match( '/[^\x20-\x7e]/', $checked ) ) {
					$checked = function_exists( 'idn_to_ascii' ) ? idn_to_ascii( $checked ) : false;
					if ( false === $checked ) {
						return;
					}
				}

				// Any host on the site stands in for it: a location without a host stays on it.
				$host = WP_Invariant_Browser::url_host( $location, 'http://site.invalid/wp-admin/' );
				if ( null === $host ) {
					return;
				}

				$call->expect( $host === $checked, 'wp_validate_redirect( %s ) let %s through, checking the host %s, but a browser would go to %s.', $call->arg( 'location' ), $location, $checked, $host );
			}
		),
);
