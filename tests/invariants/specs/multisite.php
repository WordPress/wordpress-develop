<?php
/**
 * Invariants of switching between the sites of a network.
 *
 * They only run when the suite runs as multisite, with WP_MULTISITE=1 or the
 * multisite configuration, since switch_to_blog() exists only there.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

$argv      = isset( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) ? $_SERVER['argv'] : array();
$multisite = '1' === getenv( 'WP_MULTISITE' ) || array() !== array_filter(
	$argv,
	static function ( $arg ) {
		return is_string( $arg ) && false !== strpos( $arg, 'multisite.xml' );
	}
);
if ( ! $multisite ) {
	return array();
}

$root        = dirname( __DIR__, 3 );
$directories = array( "{$root}/src/wp-includes", "{$root}/src/wp-admin" );

// Functions which call switch_to_blog() themselves, found in the source.
$switchers = WP_Invariant::cached(
	'switch-to-blog-callers',
	$directories,
	static function () use ( $directories ) {
		return WP_Invariant::functions_calling( $directories, 'switch_to_blog' );
	}
);

// Functions whose purpose is to leave the site switched.
$meant_to_switch = array( 'WP_Importer::set_blog' );
$switchers       = array_values( array_diff( $switchers, $meant_to_switch ) );

return array(
	WP_Invariant::define( 'blog-restored' )
		->describe( 'A WordPress function which calls switch_to_blog() has called restore_current_blog() as often by the time it returns, so that what runs after it works on the site it expects, not another site of the network.' )
		->before_each(
			$switchers,
			static function () {
				return isset( $GLOBALS['_wp_switched_stack'] ) ? count( $GLOBALS['_wp_switched_stack'] ) : 0;
			}
		)
		->after_each(
			$switchers,
			static function ( WP_Invariant_Call $call ) {
				$depth = isset( $GLOBALS['_wp_switched_stack'] ) ? count( $GLOBALS['_wp_switched_stack'] ) : 0;
				$call->expect( $depth === $call->before(), '%s() returned with %d more switch_to_blog() calls than restore_current_blog() calls; the current site is now %d.', $call->target(), $depth - $call->before(), get_current_blog_id() );
			}
		),
);
