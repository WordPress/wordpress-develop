<?php

/**
 * Tests for is_blog_admin().
 *
 * @group load
 *
 * @covers ::is_blog_admin
 */
class Tests_Load_IsBlogAdmin extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_without_current_screen() {
		unset( $GLOBALS['current_screen'] );

		$this->assertFalse( is_blog_admin() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_for_site_admin_screen() {
		set_current_screen( 'dashboard' );

		$this->assertTrue( is_blog_admin() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_network_admin_screen() {
		set_current_screen( 'dashboard-network' );

		$this->assertFalse( is_blog_admin() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_user_admin_screen() {
		set_current_screen( 'dashboard-user' );

		$this->assertFalse( is_blog_admin() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_front_screen() {
		set_current_screen( 'front' );

		$this->assertFalse( is_blog_admin() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_constant_value_when_screen_is_unset() {
		unset( $GLOBALS['current_screen'] );

		define( 'WP_BLOG_ADMIN', true );

		$this->assertTrue( is_blog_admin() );
	}
}
