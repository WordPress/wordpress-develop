<?php

/**
 * Tests for the wp_spaces_regexp() function.
 *
 * @group formatting
 *
 * @covers ::wp_spaces_regexp
 */
class Tests_Formatting_WpSpacesRegexp extends WP_UnitTestCase {

	/**
	 * Tests wp_spaces_regexp() filter behavior during cold start (before static cache is populated).
	 *
	 * @ticket 60319
	 */
	public function test_wp_spaces_regexp_cold_start(): void {
		$php_binary = defined( 'WP_PHP_BINARY' ) ? WP_PHP_BINARY : PHP_BINARY;

		$php_code = sprintf(
			'require %s; require %s; $filter_calls = 0; $initial_pattern = null; add_filter( "wp_spaces_regexp", static function ( $pattern ) use ( &$filter_calls, &$initial_pattern ) { ++$filter_calls; $initial_pattern = $pattern; return "[ ]"; } ); $first = wp_spaces_regexp(); $second = wp_spaces_regexp(); echo json_encode( array( "initial" => $initial_pattern, "first" => $first, "second" => $second, "calls" => $filter_calls ) );',
			var_export( ABSPATH . WPINC . '/plugin.php', true ),
			var_export( ABSPATH . WPINC . '/formatting.php', true )
		);

		$command = ( defined( 'WP_PHP_BINARY' ) ? $php_binary : escapeshellarg( $php_binary ) ) . ' -r ' . escapeshellarg( $php_code );
		exec( $command, $output, $return_var );

		$this->assertSame( 0, $return_var, 'PHP execution should succeed.' );

		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result, 'Subprocess output should decode to an array.' );
		$this->assertSame( '[\r\n\t ]|\xC2\xA0|&nbsp;', $result['initial'], 'Filter should receive the default whitespace pattern.' );
		$this->assertSame( '[ ]', $result['first'], 'First call should return filtered pattern.' );
		$this->assertSame( 1, $result['calls'], 'Filter should be called exactly once on first invocation.' );
		$this->assertSame( '[ ]', $result['second'], 'Second call should return cached filtered pattern.' );
	}

}
