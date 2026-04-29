<?php

/**
 * Tests for the got_mod_rewrite() function.
 *
 * @group admin
 * @group rewrite
 *
 * @covers ::got_mod_rewrite
 */
class Tests_got_mod_rewrite extends WP_UnitTestCase {

	/**
	 * Tests that got_mod_rewrite() correctly detects mod_rewrite based on server and filters.
	 *
	 * @ticket 65134
	 *
	 * @dataProvider data_got_mod_rewrite
	 *
	 * @param bool $expected         The expected result from got_mod_rewrite().
	 * @param bool $apache_loaded    Whether mod_rewrite is reported as loaded by Apache.
	 * @param bool|null $filter_value Optional value to return via the 'got_rewrite' filter.
	 */
	public function test_got_mod_rewrite( $expected, $apache_loaded, $filter_value = null ) {
		// Mock the Apache module check by filtering 'got_rewrite' if needed,
		// but since got_mod_rewrite calls apache_mod_loaded which we can't easily mock
		// without a framework, we rely on the filter for full control.

		if ( null !== $filter_value ) {
			add_filter(
				'got_rewrite',
				static function () use ( $filter_value ) {
					return $filter_value;
				}
			);
		}

		// If we are NOT filtering, we need to be aware of the environment.
		// However, the function's internal logic is:
		// return apply_filters( 'got_rewrite', apache_mod_loaded( 'mod_rewrite', true ) );
		// Since we want to test the function's behavior across different scenarios,
		// we use the filter to simulate the different outcomes of the internal check.

		$this->assertSame( $expected, got_mod_rewrite() );
	}

	/**
	 * Data provider for test_got_mod_rewrite.
	 *
	 * @return array<string, array{
	 *     expected:      bool,
	 *     apache_loaded: bool,
	 *     filter_value:  bool|null,
	 * }>
	 */
	public function data_got_mod_rewrite(): array {
		return array(
			'Default behavior (should match filter or internal check)' => array(
				'expected'      => true,
				'apache_loaded' => true,
				'filter_value'  => true,
			),
			'Filter returns false' => array(
				'expected'      => false,
				'apache_loaded' => true,
				'filter_value'  => false,
			),
			'Filter returns true even if Apache check might be false' => array(
				'expected'      => true,
				'apache_loaded' => false,
				'filter_value'  => true,
			),
		);
	}
}
