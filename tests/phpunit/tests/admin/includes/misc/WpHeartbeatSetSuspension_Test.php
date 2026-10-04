<?php

/**
 * @group admin
 *
 * @covers ::wp_heartbeat_set_suspension
 */
class Tests_Admin_Includes_Misc_WpHeartbeatSetSuspension_Test extends WP_UnitTestCase {

	/**
	 * Original value of $pagenow.
	 *
	 * @var string
	 */
	private $orig_pagenow;

	public function set_up() {
		global $pagenow;

		parent::set_up();

		$this->orig_pagenow = $pagenow;
	}

	public function tear_down() {
		global $pagenow;

		$pagenow = $this->orig_pagenow;

		parent::tear_down();
	}

	/**
	 * Tests that wp_heartbeat_set_suspension() disables suspension on post screens.
	 *
	 * @dataProvider data_wp_heartbeat_set_suspension
	 *
	 * @ticket 65200
	 *
	 * @param string $pagenow_value The value for the $pagenow global.
	 * @param string $expected      The expected value of 'suspension' in settings.
	 */
	public function test_wp_heartbeat_set_suspension( $pagenow_value, $expected ) {
		global $pagenow;

		$pagenow = $pagenow_value;

		$settings = array( 'suspension' => 'initial' );
		$result   = wp_heartbeat_set_suspension( $settings );

		$this->assertSame( $expected, $result['suspension'], "Suspension should be '{$expected}' when \$pagenow is {$pagenow_value}." );
	}

	/**
	 * Data provider for test_wp_heartbeat_set_suspension().
	 *
	 * @return array<string, array{
	 *     pagenow_value: string,
	 *     expected:      string,
	 * }>
	 */
	public function data_wp_heartbeat_set_suspension(): array {
		return array(
			'post.php'     => array(
				'pagenow_value' => 'post.php',
				'expected'      => 'disable',
			),
			'post-new.php' => array(
				'pagenow_value' => 'post-new.php',
				'expected'      => 'disable',
			),
			'index.php'    => array(
				'pagenow_value' => 'index.php',
				'expected'      => 'initial',
			),
			'edit.php'     => array(
				'pagenow_value' => 'edit.php',
				'expected'      => 'initial',
			),
		);
	}

	/**
	 * Tests that wp_heartbeat_set_suspension() exposes the default post lock window on post screens.
	 *
	 * @dataProvider data_post_screens
	 *
	 * @ticket 65171
	 *
	 * @param string $pagenow_value The value for the $pagenow global.
	 */
	public function test_wp_heartbeat_set_suspension_sets_post_lock_window_on_post_screens( $pagenow_value ) {
		global $pagenow;

		$pagenow = $pagenow_value;

		$result = wp_heartbeat_set_suspension( array() );

		$this->assertArrayHasKey( 'post_lock_window', $result, "'post_lock_window' should be present when \$pagenow is {$pagenow_value}." );
		$this->assertSame( 150, $result['post_lock_window'], "'post_lock_window' should default to 150 when \$pagenow is {$pagenow_value}." );
	}

	/**
	 * Tests that wp_heartbeat_set_suspension() does not expose a post lock window on non-post screens.
	 *
	 * @dataProvider data_non_post_screens
	 *
	 * @ticket 65171
	 *
	 * @param string $pagenow_value The value for the $pagenow global.
	 */
	public function test_wp_heartbeat_set_suspension_does_not_set_post_lock_window_on_other_screens( $pagenow_value ) {
		global $pagenow;

		$pagenow = $pagenow_value;

		$result = wp_heartbeat_set_suspension( array() );

		$this->assertArrayNotHasKey( 'post_lock_window', $result, "'post_lock_window' should not be set when \$pagenow is {$pagenow_value}." );
	}

	/**
	 * Tests that wp_heartbeat_set_suspension() honors the wp_check_post_lock_window filter.
	 *
	 * @ticket 65171
	 */
	public function test_wp_heartbeat_set_suspension_post_lock_window_respects_filter() {
		global $pagenow;

		$pagenow = 'post.php';

		add_filter(
			'wp_check_post_lock_window',
			static function () {
				return 60;
			}
		);

		$result = wp_heartbeat_set_suspension( array() );

		$this->assertSame( 60, $result['post_lock_window'], "'post_lock_window' should reflect the wp_check_post_lock_window filter value." );
	}

	/**
	 * Data provider: $pagenow values for the Add/Edit Post screens.
	 *
	 * @return array<string, array{pagenow_value: string}>
	 */
	public function data_post_screens(): array {
		return array(
			'post.php'     => array( 'pagenow_value' => 'post.php' ),
			'post-new.php' => array( 'pagenow_value' => 'post-new.php' ),
		);
	}

	/**
	 * Data provider: $pagenow values for screens that are not Add/Edit Post.
	 *
	 * @return array<string, array{pagenow_value: string}>
	 */
	public function data_non_post_screens(): array {
		return array(
			'index.php' => array( 'pagenow_value' => 'index.php' ),
			'edit.php'  => array( 'pagenow_value' => 'edit.php' ),
		);
	}
}
