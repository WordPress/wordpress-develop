<?php

/**
 * Tests for wp_using_themes().
 *
 * @group load
 *
 * @covers ::wp_using_themes
 */
class Tests_Load_WpUsingThemes extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_wp_use_themes_is_undefined() {
		$this->assertFalse( defined( 'WP_USE_THEMES' ) );
		$this->assertFalse( wp_using_themes() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_true_when_wp_use_themes_is_true() {
		define( 'WP_USE_THEMES', true );

		$this->assertTrue( wp_using_themes() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_false_when_wp_use_themes_is_false() {
		define( 'WP_USE_THEMES', false );

		$this->assertFalse( wp_using_themes() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_force_themes_when_undefined() {
		add_filter( 'wp_using_themes', '__return_true' );
		$this->assertTrue( wp_using_themes() );
		remove_filter( 'wp_using_themes', '__return_true' );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_can_override_when_wp_use_themes_is_true() {
		define( 'WP_USE_THEMES', true );

		add_filter( 'wp_using_themes', '__return_false' );
		$this->assertFalse( wp_using_themes() );
		remove_filter( 'wp_using_themes', '__return_false' );
	}
}
