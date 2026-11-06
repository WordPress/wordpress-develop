<?php
/**
 * Invariants of the secrets WordPress makes: salts, password hashes, keys, tokens and application passwords.
 *
 * Secrets are collected as WordPress makes them, and looked for in what it sends
 * back: REST responses, JSON responses, printed HTML, wp_die() messages, headers
 * and the error log. They may appear in email, which is how keys reach users.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Secrets made in the running test, and salts, which are made once for the run: secret => what it is.
$made  = WP_Invariant::shared( 'secrets/made' );
$salts = WP_Invariant::shared( 'secrets/salts', 'run' );

// Secrets this short could appear by chance.
$min_length = 16;

$remember = static function ( $holder, $secret, $label ) use ( $min_length ) {
	if ( is_string( $secret ) && strlen( $secret ) >= $min_length ) {
		if ( ! isset( $holder->values ) ) {
			$holder->values = array();
		}
		$holder->values[ $secret ] = $label;
	}
};

/*
 * Returns the password hashes in some text, by their formats: phpass ($P$, $H$),
 * WordPress's bcrypt ($wp$2y$), bcrypt ($2y$, $2b$, $2a$) and Argon2 ($argon2i$,
 * $argon2id$), with at least 30 characters of the hash alphabet after the marker.
 */
$hashes_in = static function ( $text ) {
	$hashes = array();
	foreach ( array( '$P$', '$H$', '$wp$2', '$2y$', '$2b$', '$2a$', '$argon2' ) as $marker ) {
		$at = strpos( $text, $marker );
		while ( false !== $at && count( $hashes ) < 10 ) {
			$length = strspn( $text, './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz$=,', $at );
			if ( $length >= 30 ) {
				$hashes[] = substr( $text, $at, $length );
			}
			$at = strpos( $text, $marker, $at + max( 1, $length ) );
		}
	}

	return array_values( array_unique( $hashes ) );
};

/*
 * Checks what WordPress sends back for secrets, and reports each one found,
 * unless the request itself sent it, as a user sends their own key back.
 */
$check = static function ( WP_Invariant_Call $call, array &$reported, $text, $sink, array $allowed = array() ) use ( $made, $salts, $hashes_in ) {
	if ( ! is_string( $text ) || strlen( $text ) < 16 ) {
		return;
	}

	$found = array();
	foreach ( array( $made, $salts ) as $holder ) {
		foreach ( isset( $holder->values ) ? $holder->values : array() as $secret => $label ) {
			$secret = (string) $secret;
			if ( ! isset( $allowed[ $label ] ) && false !== strpos( $text, $secret ) ) {
				$found[ $secret ] = $label;
			}
		}
	}
	foreach ( $hashes_in( $text ) as $hash ) {
		$found[ $hash ] = 'a password hash';
	}
	if ( array() === $found ) {
		return;
	}

	$sent = array_flip( WP_Invariant_Request::values() );
	foreach ( $found as $secret => $label ) {
		$key = "{$label} {$sink}";
		if ( isset( $sent[ $secret ] ) || isset( $reported[ $key ] ) ) {
			continue;
		}

		$reported[ $key ] = true;
		$at               = strpos( $text, (string) $secret );
		$call->expect( false, '%s sent %s: %s', $sink, $label, substr( $text, max( 0, $at - 60 ), 160 ) );
	}
};

$encode = static function ( $data ) {
	$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) : false;
	return is_string( $json ) ? $json : '';
};

$run_state = static function () {
	return array();
};

return array(
	WP_Invariant::define( 'never-sent' )
		->describe( 'No secret WordPress makes is sent back: no salt, password hash, password reset key, user request key, session token, auth cookie or application password appears in a REST response, a JSON response, printed HTML, a wp_die() message, a header or the error log, unless the request sent it itself. A new application password may appear in the REST response which creates it. Strings shaped like password hashes count wherever they came from. Each kind of secret is reported once for each place.' )
		->include_test_callers()
		->state( $run_state, 'run' )
		->after(
			'wp_salt',
			static function ( WP_Invariant_Call $call ) use ( $salts, $remember ) {
				$remember( $salts, $call->return_value(), 'a salt' );
			}
		)
		->after(
			'wp_hash_password',
			static function ( WP_Invariant_Call $call ) use ( $made, $remember ) {
				$remember( $made, $call->return_value(), 'a password hash' );
			}
		)
		->after(
			'get_password_reset_key',
			static function ( WP_Invariant_Call $call ) use ( $made, $remember ) {
				$remember( $made, $call->return_value(), 'a password reset key' );
			}
		)
		->after(
			'wp_generate_user_request_key',
			static function ( WP_Invariant_Call $call ) use ( $made, $remember ) {
				$remember( $made, $call->return_value(), 'a user request key' );
			}
		)
		->after(
			'WP_Session_Tokens::create',
			static function ( WP_Invariant_Call $call ) use ( $made, $remember ) {
				$remember( $made, $call->return_value(), 'a session token' );
			}
		)
		->after(
			'wp_generate_auth_cookie',
			static function ( WP_Invariant_Call $call ) use ( $made, $remember ) {
				$remember( $made, $call->return_value(), 'an auth cookie' );
			}
		)
		->after(
			'WP_Application_Passwords::create_new_application_password',
			static function ( WP_Invariant_Call $call ) use ( $made, $remember ) {
				$created = $call->return_value();
				if ( is_array( $created ) && isset( $created[0], $created[1] ) ) {
					$remember( $made, $created[0], 'an application password' );
					$remember( $made, is_array( $created[1] ) && isset( $created[1]['password'] ) ? $created[1]['password'] : null, 'a password hash' );
				}
			}
		)
		->after(
			'WP_REST_Server::dispatch',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $check, $encode ) {
				$response = $call->return_value();
				$request  = $call->arg( 'request' );
				if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) {
					return;
				}

				$route   = $request->get_route();
				$allowed = false !== strpos( $route, '/application-passwords' ) && 'POST' === $request->get_method() ? array( 'an application password' => true ) : array();
				$check( $call, $reported, $encode( $response->get_data() ), sprintf( 'The REST response to %s %s', $request->get_method(), $route ), $allowed );
			}
		)
		->before(
			'wp_send_json',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $check, $encode ) {
				$check( $call, $reported, $encode( $call->arg( 'response' ) ), 'wp_send_json()' );
			}
		)
		->before(
			'wp_die',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $check ) {
				$message = $call->arg( 'message' );
				if ( $message instanceof WP_Error ) {
					$message = implode( "\n", $message->get_error_messages() );
				}
				$check( $call, $reported, is_string( $message ) ? $message : '', 'wp_die()' );
			}
		)
		->before(
			'error_log',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $check ) {
				$check( $call, $reported, $call->arg( 'message' ), 'error_log()' );
			}
		)
		->before(
			'header',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $check ) {
				$check( $call, $reported, $call->arg( 'header' ), 'header()' );
			}
		)
		->after_each(
			array( 'ob_get_clean', 'ob_get_contents' ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $check ) {
				$check( $call, $reported, $call->return_value(), 'Printed output' );
			}
		),
);
