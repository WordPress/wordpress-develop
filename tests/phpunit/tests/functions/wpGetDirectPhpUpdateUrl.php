<?php

/**
 * Tests for the wp_get_direct_php_update_url and wp_direct_php_update_button functions.
 *
 * @group functions.php
 *
 */
class Tests_functions_wp_get_direct_php_update_url extends WP_UnitTestCase {

	/**
	 * chack the URL si retured if set
	 *
	 * @ticket 59697
	 *
	 * @covers ::wp_get_direct_php_update_url
	 */
	public function test_wp_get_direct_php_update_urlt() {

		$this->assertEquals( '', wp_get_direct_php_update_url() );
	}

	/**
	 * chack the URL si retured if set
	 *
	 * @ticket 59697
	 *
	 * @covers ::wp_get_direct_php_update_url
	 */
	public function test_wp_get_direct_php_update_url_is_set() {
		// Set expected result
		$expected = 'https://example.com/direct-php-update';

		// Set environment variable
		putenv( 'WP_DIRECT_UPDATE_PHP_URL=' . $expected );

		// Call the function and store the result
		$result = wp_get_direct_php_update_url();

		// Check that the result is as expected
		$this->assertEquals( $expected, $result );

		// Remove the environment variable
		putenv( 'WP_DIRECT_UPDATE_PHP_URL' );
	}

	/**
	 * Check that the filter works
	 *
	 * @ticket 59697
	 *
	 * @covers ::wp_get_direct_php_update_url
	 */
	public function test_wp_get_direct_php_update_url_empty() {

		$actual_url = wp_get_direct_php_update_url();
		$this->assertEquals( '', $actual_url );

		// Test 2: Check if apply_filters() returns expected value
		add_filter( 'wp_direct_php_update_url', array( $this, 'filter_wp_get_direct_php_update_url' ) );

		$expected_url = 'https://filtered.com/direct-php-update';
		$actual_url   = wp_get_direct_php_update_url();
		$this->assertEquals( $expected_url, $actual_url );

		remove_filter( 'wp_direct_php_update_url', array( $this, 'filter_wp_get_direct_php_update_url' ) );
	}

	/**
	 * check HTML and Button is echoed
	 *
	 * @ticket 59697
	 *
	 * @covers ::wp_direct_php_update_button
	 */
	public function test_wp_direct_php_update_button() {
		putenv( 'WP_DIRECT_UPDATE_PHP_URL=URL' );
		// Test 4: Check if the button is displayed
		ob_start();
		wp_direct_php_update_button();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<a class="button button-primary', $output );
	}

	/**
	 * check that no HTMl/button is outputted in wp_direct_php_update_button()
	 * @ticket 59697
	 *
	 * @covers ::wp_direct_php_update_button
	 */
	public function test_wp_direct_php_update_button_empty() {
		putenv( 'WP_DIRECT_UPDATE_PHP_URL' );

		ob_start();
		wp_direct_php_update_button();
		$output = ob_get_clean();
		$this->assertEquals( '', $output );
	}

	/**
	 * Filter for test
	 *
	 * @param string $url
	 */
	public function filter_wp_get_direct_php_update_url( $url ) {

		return 'https://filtered.com/direct-php-update';
	}
}
