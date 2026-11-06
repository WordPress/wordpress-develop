<?php
/**
 * Invariants of the randomness behind secrets.
 *
 * These hook built-in PHP functions, which slows down every call to a built-in
 * function. Leave them out with WP_INVARIANTS_SKIP=random.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Functions which make secrets: passwords, keys, tokens, cookies and salts.
$secret_makers = array(
	'wp_generate_password'         => true,
	'get_password_reset_key'       => true,
	'wp_generate_user_request_key' => true,
	'wp_generate_auth_cookie'      => true,
	'wp_salt'                      => true,
	'wp_hash_password'             => true,
	'WP_Session_Tokens::create'    => true,
	'WP_Application_Passwords::create_new_application_password' => true,
);

return array(
	WP_Invariant::define( 'secrets-use-csprng' )
		->describe( 'No function which makes a secret, such as wp_generate_password(), get_password_reset_key(), WP_Session_Tokens::create() or wp_salt(), uses a random number generator an attacker can predict: mt_rand(), rand(), uniqid(), lcg_value(), str_shuffle(), array_rand() or shuffle(). Each place in WordPress is reported once.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->before_each(
			array( 'mt_rand', 'rand', 'uniqid', 'lcg_value', 'str_shuffle', 'array_rand', 'shuffle' ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $secret_makers ) {
				$maker = null;
				foreach ( WP_Invariant::running_functions() as $function ) {
					if ( isset( $secret_makers[ $function ] ) ) {
						$maker = $function;
						break;
					}
				}
				if ( null === $maker ) {
					return;
				}

				$caller = $call->caller();
				$site   = $caller['file'] . ':' . $caller['line'];
				if ( isset( $reported[ $site ] ) ) {
					return;
				}
				$reported[ $site ] = true;

				$call->expect( false, '%s() ran while %s() made a secret: its output can be predicted.', $call->target(), $maker );
			}
		),
);
