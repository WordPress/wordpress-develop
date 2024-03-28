<?php

/**
 * Test apply_filters_deprecated().
 *
 * @group hooks
 * @covers ::apply_filters_deprecated
 */
class Tests_Hooks_ApplyFiltersDeprecated extends WP_UnitTestCase {

	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_apply_filters_deprecated
	 */
	public function test_apply_filters_deprecated() {
		$p = 'Foo';

		add_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback' ) );
		$p = apply_filters_deprecated( 'tests_apply_filters_deprecated', array( $p ), '4.6.0' );
		remove_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback' ) );

		$this->assertSame( 'Bar', $p );
	}

	public static function deprecated_filter_callback( $p ) {
		$p = 'Bar';
		return $p;
	}

	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_apply_filters_deprecated
	 */
	public function test_apply_filters_deprecated_with_multiple_params() {
		$p1 = 'Foo1';
		$p2 = 'Foo2';

		add_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback_multiple_params' ), 10, 2 );
		$p1 = apply_filters_deprecated( 'tests_apply_filters_deprecated', array( $p1, $p2 ), '4.6.0' );
		remove_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback_multiple_params' ), 10, 2 );

		$this->assertSame( 'Bar1', $p1 );

		// Not passed by reference, so not modified.
		$this->assertSame( 'Foo2', $p2 );
	}

	public static function deprecated_filter_callback_multiple_params( $p1, $p2 ) {
		$p1 = 'Bar1';
		$p2 = 'Bar2';

		return $p1;
	}

	/**
	 * @ticket 10441
	 */
	public function test_apply_filters_deprecated_without_filter() {
		$val = 'Foobar';

		$this->assertSame( $val, apply_filters_deprecated( 'tests_apply_filters_deprecated', array( $val ), '4.6.0' ) );
	}
}
