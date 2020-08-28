<?php

require_once __DIR__ . '/phpunit6-compat.php';
require_once __DIR__ . '/trait-wp-phpunit9-compat.php';
require_once __DIR__ . '/trait-wp-phpunit8-compat.php';
require_once __DIR__ . '/trait-wp-phpunit7-compat.php';
require_once __DIR__ . '/trait-wp-phpunit6-compat.php';

/**
 * This trait is a __call() function for the PHPUnit Compat traits.
 *
 * It's only job is to catch a method call to a non-existent function, which we may have a compat method for.
 *
 * All compat methods should be prefixed with an underscore, and will only be called if the current PHPUnit version doesn't support it natively.
 */
trait WP_PHPUnit_Compat {

	// The PHPUnit compat classes
	use WP_PHPUnit9_Compat;
	use WP_PHPUnit8_Compat;
	use WP_PHPUnit7_Compat;
	use WP_PHPUnit6_Compat;

	public function __call( $method, $args ) {
		$compat_method = "_{$method}";
		if ( '_' !== $method[0] && method_exists( $this, $compat_method ) ) {
			call_user_func_array( array( $this, $compat_method ), $args );
		} else {
			trigger_error( "Call to undefined method WP_UnitTestCase::{$method}()", E_USER_ERROR );
		}
	}

	public static function __callStatic( $method, $args ) {
		$compat_method = "_{$method}";
		if ( '_' !== $method[0] && is_callable( "static::{$compat_method}" ) ) {
			call_user_func_array( "static::{$compat_method}", $args );
		} else {
			trigger_error( "Call to undefined method WP_UnitTestCase::{$method}()", E_USER_ERROR );
		}
	}
}
