<?php
/**
 * Tests for the wp_update_php_annotation function.
 *
 * @group functions.php
 *
 * @covers ::wp_get_update_php_annotation
 */
class Tests_functions_wpUpdatePhpAnnotation extends WP_UnitTestCase {

	/**
	 * Test that the annotation is NOT return if the URLs are the same
	 *
	 * @ticket 59700
	 */
	public function test_wp_update_php_annotation() {
		$this->assertEquals( '', wp_update_php_annotation() );
	}

	/**
	 * Test that the annotation is return if the URLs are different
	 *
	 * @ticket 59700
	 */
	public function test_wp_update_php_annotation_dif_url() {
		// add filter to change the URL for the PHP updates from wordpress.org
		add_filter( 'wp_update_php_url', array( $this, 'filter_diff_url' ) );
		$this->assertStringContainsString( 'This resource is provided by your web host', wp_update_php_annotation( '', '', false ) );
	}

	/**
	 * Test that the annotation is echo'ed if the URLs are different
	 *
	 * @ticket 59700
	 */
	public function test_wp_update_php_annotation_dif_url_echo() {
		// add filter to change the URL for the PHP updates from wordpress.org
		add_filter( 'wp_update_php_url', array( $this, 'filter_diff_url' ) );
		ob_start();

		wp_update_php_annotation();

		$ouput = ob_get_clean();

		$this->assertStringContainsString( 'This resource is provided by your web host', $ouput );
	}

	/**
	 * Test that the annotation is wrapped in strings when provided
	 *
	 * @ticket 59700
	 */
	public function test_wp_update_php_annotation_dif_url_wrap() {
		// add filter to change the URL for the PHP updates from wordpress.org
		add_filter( 'wp_update_php_url', array( $this, 'filter_diff_url' ) );

		$this->assertStringContainsString( 'before', wp_update_php_annotation( 'before', '', false ) );
		$this->assertStringContainsString( 'after', wp_update_php_annotation( '', 'after', false ) );
	}


	/**
	 * Filter to change the URL
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function filter_diff_url() {

		return 'diff_url.com';
	}
}
