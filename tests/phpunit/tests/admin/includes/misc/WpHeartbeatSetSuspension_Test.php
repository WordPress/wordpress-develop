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
}
