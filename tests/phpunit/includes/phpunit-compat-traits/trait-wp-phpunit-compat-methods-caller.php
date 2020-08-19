<?php
/**
 * This trait is a __call() function for the PHPUnit Compat traits.
 * It's only job is to catch a method call to a non-existent function, which we may have a compat method for.
 * All compat methods are prefixed with an underscore.
 */
trait WP_PHPUnit_Compat_Methods_Caller {

	function __call( $method, $args ) {
		$compat_method = "_{$method}";
		if ( '_' != $method[0] && method_exists( $this, $compat_method ) ) {
			call_user_func_array( [ $this, $compat_method ], $args );
		} else {
			trigger_error( "Call to undefined method WP_UnitTestCase::{$method}()", E_USER_ERROR );
		}
	}

	public static function __callStatic( $method, $args ) {
		$compat_method = "_{$method}";
		if ( '_' != $method[0] && is_callable( "static::{$compat_method}" ) ) {
			call_user_func_array( "static::{$compat_method}", $args );
		} else {
			trigger_error( "Call to undefined method WP_UnitTestCase::{$method}()", E_USER_ERROR );
		}
	}
}