<?php
/**
 * Invariants of the REST API.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// The permission checks of the core controllers, read from their source.
$permission_checks = WP_Invariant::methods_in( dirname( __DIR__, 3 ) . '/src/wp-includes/rest-api/endpoints', '/_permissions_check$/' );

return array(
	WP_Invariant::define( 'permission-result-is-bool-or-error' )
		->describe( 'A permission check of a core REST controller returns true, false or a WP_Error. WP_REST_Server::respond_to_request() treats any other value but null as allowed, even 0, an empty string or an empty array.' )
		->include_test_callers()
		->after_each(
			$permission_checks,
			static function ( WP_Invariant_Call $call ) {
				$result = $call->return_value();
				$call->expect( is_bool( $result ) || $result instanceof WP_Error, '%s() returned %s.', $call->target(), is_scalar( $result ) ? var_export( $result, true ) : gettype( $result ) );
			}
		),
);
