<?php

/**
 * Tests for wp_doing_cron().
 *
 * @group load
 *
 * @covers ::wp_doing_cron
 */
class Tests_Load_WpDoingCron extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_doing_cron_is_undefined() {
		$this->assertFalse( defined( 'DOING_CRON' ) );
		$this->assertFalse( wp_doing_cron() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_true_when_doing_cron_is_true() {
		define( 'DOING_CRON', true );

		$this->assertTrue( wp_doing_cron() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_false_when_doing_cron_is_false() {
		define( 'DOING_CRON', false );

		$this->assertFalse( wp_doing_cron() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_force_cron_when_undefined() {
		add_filter( 'wp_doing_cron', '__return_true' );
		$this->assertTrue( wp_doing_cron() );
		remove_filter( 'wp_doing_cron', '__return_true' );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_can_override_when_doing_cron_is_true() {
		define( 'DOING_CRON', true );

		add_filter( 'wp_doing_cron', '__return_false' );
		$this->assertFalse( wp_doing_cron() );
		remove_filter( 'wp_doing_cron', '__return_false' );
	}
}
