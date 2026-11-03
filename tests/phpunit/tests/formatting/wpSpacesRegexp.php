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
			'require %s; require %s; $filter_calls = 0; add_filter( "wp_spaces_regexp", static function ( $pattern ) use ( &$filter_calls ) { ++$filter_calls; return "[ ]"; } ); $first = wp_spaces_regexp(); $second = wp_spaces_regexp(); echo json_encode( array( "first" => $first, "second" => $second, "calls" => $filter_calls ) );',
			var_export( ABSPATH . WPINC . '/plugin.php', true ),
			var_export( ABSPATH . WPINC . '/formatting.php', true )
		);

		$command = escapeshellcmd( $php_binary ) . ' -r ' . escapeshellarg( $php_code );
		exec( $command, $output, $return_var );

		$this->assertSame( 0, $return_var, 'PHP execution should succeed.' );

		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result, 'Subprocess output should decode to an array.' );
		$this->assertSame( '[ ]', $result['first'], 'First call should return filtered pattern.' );
		$this->assertSame( 1, $result['calls'], 'Filter should be called exactly once on first invocation.' );
		$this->assertSame( '[ ]', $result['second'], 'Second call should return cached filtered pattern.' );
	}

	/**
	 * Tests that wp_spaces_regexp() returns the default pattern and uses the static cache.
	 *
	 * @ticket 60319
	 */
	public function test_wp_spaces_regexp_warmed_cache(): void {
		$filter = new MockAction();
		add_filter( 'wp_spaces_regexp', array( $filter, 'filter' ) );

		$this->assertSame( '[\r\n\t ]|\xC2\xA0|&nbsp;', wp_spaces_regexp(), 'Should return default regex pattern.' );
		$this->assertSame( 0, $filter->get_call_count(), 'Filter should not be called when static cache is already populated.' );
	}
}
