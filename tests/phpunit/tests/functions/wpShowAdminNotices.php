<?php

/**
 * Tests for `wp_show_admin_notices()` and `wp_should_show_admin_notices()`.
 *
 * @group functions
 * @group admin
 *
 * @covers ::wp_show_admin_notices
 * @covers ::wp_should_show_admin_notices
 */
class Tests_Functions_WpShowAdminNotices extends WP_UnitTestCase {

	/**
	 * Cleans up unmanaged global state after each test.
	 */
	public function tear_down() {
		global $wp_show_admin_notices;
		unset( $wp_show_admin_notices );

		parent::tear_down();
	}

	/**
	 * Tests that admin notices should be shown by default.
	 *
	 * @ticket 66350
	 */
	public function test_should_show_admin_notices_by_default() {
		$this->assertTrue(
			wp_should_show_admin_notices(),
			'Admin notices should be enabled by default.'
		);
	}

	/**
	 * Tests that `wp_show_admin_notices( false )` disables admin notices.
	 *
	 * @ticket 66350
	 */
	public function test_wp_show_admin_notices_false() {
		wp_show_admin_notices( false );

		$this->assertFalse(
			wp_should_show_admin_notices(),
			'Admin notices should be disabled when explicitly set to false.'
		);
	}

	/**
	 * Tests that `wp_show_admin_notices( true )` enables admin notices.
	 *
	 * @ticket 66350
	 */
	public function test_wp_show_admin_notices_true() {
		wp_show_admin_notices( false );
		wp_show_admin_notices( true );

		$this->assertTrue(
			wp_should_show_admin_notices(),
			'Admin notices should be enabled when explicitly re-enabled.'
		);
	}

	/**
	 * Tests that non-boolean values passed to `wp_show_admin_notices()` are cast to boolean.
	 *
	 * @ticket 66350
	 *
	 * @dataProvider data_non_boolean_values
	 *
	 * @param mixed $input    Input value.
	 * @param bool  $expected Expected result.
	 */
	public function test_wp_show_admin_notices_casts_to_boolean( $input, $expected ) {
		wp_show_admin_notices( $input );

		$this->assertSame(
			$expected,
			wp_should_show_admin_notices(),
			'Non-boolean inputs should be cast to boolean.'
		);
	}

	/**
	 * Data provider for non-boolean casting.
	 *
	 * @return array[]
	 */
	public function data_non_boolean_values() {
		return array(
			'zero integer'    => array( 0, false ),
			'empty string'    => array( '', false ),
			'null'            => array( null, false ),
			'positive int'    => array( 1, true ),
			'non-empty str'   => array( 'yes', true ),
			'non-empty array' => array( array( 1 ), true ),
		);
	}

	/**
	 * Tests that the `show_admin_notices` filter can override the global state.
	 *
	 * @ticket 66350
	 */
	public function test_show_admin_notices_filter_overrides_to_false() {
		wp_show_admin_notices( true );

		add_filter( 'show_admin_notices', '__return_false' );

		$this->assertFalse(
			wp_should_show_admin_notices(),
			'show_admin_notices filter returning false should override global state.'
		);
	}

	/**
	 * Tests that the `show_admin_notices` filter can force notices to show.
	 *
	 * @ticket 66350
	 */
	public function test_show_admin_notices_filter_overrides_to_true() {
		wp_show_admin_notices( false );

		add_filter( 'show_admin_notices', '__return_true' );

		$this->assertTrue(
			wp_should_show_admin_notices(),
			'show_admin_notices filter returning true should override global state.'
		);
	}
}
