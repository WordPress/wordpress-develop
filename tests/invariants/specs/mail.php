<?php
/**
 * Invariants of the email WordPress sends.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Whether a string has a line break, which would end a header and start another.
$breaks = static function ( $value ) {
	return is_string( $value ) && false !== strpbrk( $value, "\r\n" );
};

return array(
	WP_Invariant::define( 'no-header-injection' )
		->describe( 'What wp_mail() is given can add no header of its own: no address or subject has a line break, no header given as an array element has one, and no value the request sent with a line break in it appears in the headers. Each place in WordPress is reported once.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->before(
			'wp_mail',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $breaks ) {
				$caller = $call->caller();
				$site   = $caller['file'] . ':' . $caller['line'];
				if ( isset( $reported[ $site ] ) ) {
					return;
				}

				$problems = array();
				foreach ( WP_Invariant::strings_in( $call->arg( 'to' ) ) as $address ) {
					if ( $breaks( $address ) ) {
						$problems[] = sprintf( 'the address %s has a line break', var_export( $address, true ) );
					}
				}
				if ( $breaks( $call->arg( 'subject' ) ) ) {
					$problems[] = sprintf( 'the subject %s has a line break', var_export( $call->arg( 'subject' ), true ) );
				}

				$headers = $call->arg( 'headers' );
				if ( is_array( $headers ) ) {
					foreach ( WP_Invariant::strings_in( $headers ) as $header ) {
						if ( $breaks( rtrim( $header, "\r\n" ) ) ) {
							$problems[] = sprintf( 'the header %s has a line break', var_export( $header, true ) );
						}
					}
					$headers = implode( "\n", WP_Invariant::strings_in( $headers ) );
				}
				if ( is_string( $headers ) && '' !== $headers ) {
					foreach ( WP_Invariant_Request::values( "\r\n" ) as $value ) {
						if ( false !== strpos( $headers, $value ) ) {
							$problems[] = sprintf( 'the headers hold the request value %s, which has a line break', var_export( $value, true ) );
						}
					}
				}

				if ( array() !== $problems ) {
					$reported[ $site ] = true;
					$call->expect( false, 'wp_mail() was given headers it did not mean to send: %s.', implode( '; ', $problems ) );
				}
			}
		),
);
