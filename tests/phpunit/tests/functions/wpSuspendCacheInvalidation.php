<?php
/**
 * Tests for the wp_suspend_cache_invalidation function.
 *
 * @group functions.php
 *
 * @covers ::wp_suspend_cache_invalidation
 */
class Tests_functions_WpSuspendCacheInvalidation extends WP_UnitTestCase {

	/**
	 * @ticket 57266
	 */
	public function test_wp_suspend_cache_invalidation() {
		global $_wp_suspend_cache_invalidation;
		$default = $_wp_suspend_cache_invalidation;

		$value1 = $_wp_suspend_cache_invalidation;
		$value2 = wp_suspend_cache_invalidation();
		$value3 = $_wp_suspend_cache_invalidation;
		$value4 = wp_suspend_cache_invalidation( false);
		$value5 = $_wp_suspend_cache_invalidation;

		// reset to default value
		$_wp_suspend_cache_invalidation = $default;

		$this->assertEmpty( $value1, 'Check global' );
		$this->assertEmpty( $value2, 'call default' );

		$this->assertTrue( $value3, 'check is true' );
		// checked for not empty as this how it is used in core
		$this->assertNotEmpty( $value3, 'check is true' );
		$this->assertTrue( $value4, 'Set to false' );
		$this->assertEmpty( $value5, 'check is still false' );

	}

	/**
	 * Check passing none boolean string to wp_suspend_cache_invalidation doesn't set it.
	 *
	 * @ticket 57266
	 *
	 * @expectedDoingItWrong
	 */
	public function test_force_ssl_admin_try_test_string_which_casts_to_true() {
		global $_wp_suspend_cache_invalidation;
		$default = $_wp_suspend_cache_invalidation;

		$value1 = wp_suspend_cache_invalidation( 'a string' );;
		$value2 = $_wp_suspend_cache_invalidation;

		// reset to default value
		$_wp_suspend_cache_invalidation = $default;

		$this->setExpectedIncorrectUsage( 'wp_suspend_cache_invalidation' );
		// any string will set this to true
		$this->assertSame( $default, $value1, 'try to set a string' );
		$this->assertTrue( $value2, 'check is still as expecting after setting a string' );
	}

	/**
	 * Check passing string to wp_suspend_cache_invalidation set it with doing_it_wrong warning.
	 *
	 * @ticket 57266
	 *
	 * @expectedDoingItWrong
	 */
	public function test_force_ssl_admin_try_passing_string_true_which_should_set_but_doing_it_wrong_warning() {
		global $_wp_suspend_cache_invalidation;
		$default = $_wp_suspend_cache_invalidation;

		$value1 = wp_suspend_cache_invalidation( 'true' );
		$this->setExpectedIncorrectUsage( 'wp_suspend_cache_invalidation' );
		$value2 = $_wp_suspend_cache_invalidation;
		$value3 = wp_suspend_cache_invalidation( 'false' );
		$this->setExpectedIncorrectUsage( 'wp_suspend_cache_invalidation' );
		$value4 = $_wp_suspend_cache_invalidation;

		// reset to default value
		$_wp_suspend_cache_invalidation = $default;

		$this->assertSame( $default, $value1, 'Set to string true' );
		$this->assertTrue( $value2, 'Check is true' );

		$this->assertTrue( $value3, 'Set to string False' );
		$this->assertTrue( $value4, 'Check Is false/empty' );

	}
}
