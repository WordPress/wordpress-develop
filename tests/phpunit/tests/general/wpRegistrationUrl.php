<?php

/**
 * Tests for the wp_registration_url() function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_registration_url
 */
class Tests_General_WpRegistrationUrl extends WP_UnitTestCase {

	/**
	 * Tests that wp_registration_url() returns the expected default registration URL.
	 *
	 * @ticket 65819
	 */
	public function test_wp_registration_url_default() {
		$expected = site_url( 'wp-login.php?action=register', 'login' );

		$this->assertSame( $expected, wp_registration_url() );
	}

	/**
	 * Tests that the 'register_url' filter receives the expected argument and modifies the output.
	 *
	 * @ticket 65819
	 */
	public function test_wp_registration_url_filtered() {
		$filter = new MockAction();
		add_filter( 'register_url', array( $filter, 'filter' ) );

		wp_registration_url();

		$this->assertSame( 1, $filter->get_call_count() );
		$args = $filter->get_args();
		$this->assertSame( site_url( 'wp-login.php?action=register', 'login' ), $args[0][0] );

		add_filter(
			'register_url',
			static function () {
				return 'https://example.org/custom-register';
			}
		);

		$this->assertSame( 'https://example.org/custom-register', wp_registration_url() );
	}
}
