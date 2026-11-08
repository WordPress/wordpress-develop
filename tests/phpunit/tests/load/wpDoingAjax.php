<?php

/**
 * Tests for wp_doing_ajax().
 *
 * @group load
 *
 * @covers ::wp_doing_ajax
 */
class Tests_Load_WpDoingAjax extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_doing_ajax_is_undefined() {
		$this->assertFalse( defined( 'DOING_AJAX' ) );
		$this->assertFalse( wp_doing_ajax() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_true_when_doing_ajax_is_true() {
		define( 'DOING_AJAX', true );

		$this->assertTrue( wp_doing_ajax() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_false_when_doing_ajax_is_false() {
		define( 'DOING_AJAX', false );

		$this->assertFalse( wp_doing_ajax() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_force_ajax_when_undefined() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->assertTrue( wp_doing_ajax() );
		remove_filter( 'wp_doing_ajax', '__return_true' );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_can_override_when_doing_ajax_is_true() {
		define( 'DOING_AJAX', true );

		add_filter( 'wp_doing_ajax', '__return_false' );
		$this->assertFalse( wp_doing_ajax() );
		remove_filter( 'wp_doing_ajax', '__return_false' );
	}
}
