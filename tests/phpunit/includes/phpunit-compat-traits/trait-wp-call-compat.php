<?php

trait WPCallCompat {
	function __call( $method, $args ) {
		$compat_method = "_{$method}";
		if ( is_callable( [ $this, $compat_method ] ) ) {
			call_user_func_array( [ $this, $compat_method ], $args );
		} else {
			trigger_error( "Call to undefined method WP_UnitTestCase::{$method}()", E_USER_ERROR );
		}
	}

	public static function __callStatic( $method, $args ) {
		$compat_method = "static::_{$method}";
		if ( is_callable( $compat_method ) ) {
			call_user_func_array( $compat_method, $args );
		} else {
			trigger_error( "Call to undefined method WP_UnitTestCase::{$method}()", E_USER_ERROR );
		}
	}
}