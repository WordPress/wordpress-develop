<?php
/**
 * Invariants of authentication.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// The users get_user_by( 'login', ... ) found, by the depth of the call.
$logins = WP_Invariant::shared( 'auth/logins' );

return array(
	WP_Invariant::define( 'valid-cookie-is-its-user' )
		->describe( 'When wp_validate_auth_cookie() returns a user ID, it is the ID of the user it looked up by the cookie\'s username.' )
		->include_test_callers()
		->after(
			'get_user_by',
			static function ( WP_Invariant_Call $call ) use ( $logins ) {
				if ( 'login' === $call->arg( 'field' ) ) {
					$logins->found[ $call->depth() ] = array( $call->arg( 'value' ), $call->return_value() );
				}
			}
		)
		->before(
			'wp_validate_auth_cookie',
			static function ( WP_Invariant_Call $call ) use ( $logins ) {
				unset( $logins->found[ $call->depth() + 1 ] );
			}
		)
		->after(
			'wp_validate_auth_cookie',
			static function ( WP_Invariant_Call $call ) use ( $logins ) {
				$user_id = $call->return_value();
				if ( false === $user_id ) {
					return;
				}

				$depth = $call->depth() + 1;
				$found = isset( $logins->found[ $depth ] ) ? $logins->found[ $depth ] : null;
				unset( $logins->found[ $depth ] );
				if ( ! $call->expect( is_int( $user_id ) && $user_id > 0 && null !== $found, 'wp_validate_auth_cookie() returned %s without looking up a user.', $user_id ) ) {
					return;
				}

				list( $login, $user ) = $found;
				$cookie               = wp_parse_auth_cookie( $call->arg( 'cookie' ), $call->arg( 'scheme' ) );
				$call->expect(
					is_object( $user ) && $user->ID === $user_id && is_array( $cookie ) && $cookie['username'] === $login,
					'wp_validate_auth_cookie() returned the user %d, but looked up %s for the cookie of %s.',
					$user_id,
					$login,
					is_array( $cookie ) ? $cookie['username'] : $cookie
				);
			}
		),

	WP_Invariant::define( 'authenticate-result' )
		->describe( 'wp_authenticate() returns a WP_User with an ID above 0, or a WP_Error: never null or false.' )
		->include_test_callers()
		->after(
			'wp_authenticate',
			static function ( WP_Invariant_Call $call ) {
				$user = $call->return_value();
				$call->expect(
					( $user instanceof WP_User && $user->ID > 0 ) || $user instanceof WP_Error,
					'wp_authenticate() returned %s.',
					$user
				);
			}
		),

	WP_Invariant::define( 'password-hash-not-plaintext' )
		->describe( 'wp_hash_password() returns a hash starting with $, which does not contain the password, or * for a password over 4,096 bytes.' )
		->include_test_callers()
		->after(
			'wp_hash_password',
			static function ( WP_Invariant_Call $call ) {
				$password = (string) $call->arg( 'password' );
				$hash     = $call->return_value();
				if ( '*' === $hash ) {
					$call->expect( strlen( $password ) > 4096, 'wp_hash_password() returned * for a password of %d bytes.', strlen( $password ) );
					return;
				}

				// Only the start of the hash is reported, and never the password.
				$call->expect(
					is_string( $hash ) && '' !== $hash && '$' === $hash[0] && ( strlen( $password ) < 8 || false === strpos( $hash, $password ) ),
					'wp_hash_password() returned %s… for a password of %d bytes, which is not a hash of it.',
					is_string( $hash ) ? substr( $hash, 0, 7 ) : $hash,
					strlen( $password )
				);
			}
		),

	WP_Invariant::define( 'nonce-result' )
		->describe( 'wp_verify_nonce() returns false, 1 or 2.' )
		->include_test_callers()
		->after(
			'wp_verify_nonce',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( in_array( $call->return_value(), array( false, 1, 2 ), true ), 'wp_verify_nonce() returned %s.', $call->return_value() );
			}
		),

	WP_Invariant::define( 'check-password-accepts-own-hash' )
		->describe( 'wp_check_password() accepts a password with the hash wp_hash_password() made from it, unless a check_password filter decides otherwise.' )
		->include_test_callers()
		->state(
			static function () {
				return array();
			},
			'test'
		)
		->after(
			'wp_hash_password',
			static function ( WP_Invariant_Call $call, array &$hashes ) {
				$hash = $call->return_value();
				if ( is_string( $hash ) && '*' !== $hash ) {
					$hashes[ $hash ] = (string) $call->arg( 'password' );
					if ( count( $hashes ) > 64 ) {
						unset( $hashes[ array_key_first( $hashes ) ] );
					}
				}
			}
		)
		->after(
			'wp_check_password',
			static function ( WP_Invariant_Call $call, array &$hashes ) {
				$hash = $call->arg( 'hash' );
				if ( ! is_string( $hash ) || ! isset( $hashes[ $hash ] ) || $hashes[ $hash ] !== $call->arg( 'password' ) || WP_Invariant::has_callbacks( 'check_password' ) || ! empty( $GLOBALS['wp_hasher'] ) ) {
					return;
				}

				// The report never includes the password.
				$password = $hashes[ $hash ];
				$call->expect(
					true === $call->return_value(),
					'wp_check_password() rejected a password of %d bytes%s with the hash wp_hash_password() made from it.',
					strlen( $password ),
					trim( $password ) === $password ? '' : ', with whitespace at its start or end,'
				);
			}
		),
);
