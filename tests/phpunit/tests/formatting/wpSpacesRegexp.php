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
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_spaces_regexp_cold_start(): void {
		$custom_pattern = '[ ]';
		$filter_calls   = 0;

		add_filter(
			'wp_spaces_regexp',
			static function ( $pattern ) use ( &$filter_calls, $custom_pattern ) {
				++$filter_calls;
				return $custom_pattern;
			}
		);

		// First invocation should apply the filter and cache the result.
		$this->assertSame( $custom_pattern, wp_spaces_regexp(), 'First call should return filtered pattern.' );
		$this->assertSame( 1, $filter_calls, 'Filter should be called exactly once on first invocation.' );

		// Subsequent invocations should return cached result without calling the filter again.
		$this->assertSame( $custom_pattern, wp_spaces_regexp(), 'Second call should return cached filtered pattern.' );
		$this->assertSame( 1, $filter_calls, 'Filter should not be called again on subsequent invocations.' );
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
