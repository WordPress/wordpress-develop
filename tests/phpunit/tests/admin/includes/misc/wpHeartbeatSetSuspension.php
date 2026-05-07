<?php

/**
 * Tests for the wp_heartbeat_set_suspension() function.
 *
 * @group admin
 * @group misc
 *
 * @covers ::wp_heartbeat_set_suspension
 */
class Tests_Admin_Includes_Misc_WpHeartbeatSetSuspension extends WP_UnitTestCase {

	/**
	 * Original value of $pagenow.
	 *
	 * @var string
	 */
	private $orig_pagenow;

	public function set_up() {
		parent::set_up();
		global $pagenow;
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
	 * @param string $pagenow_val The value for the $pagenow global.
	 * @param string $expected    The expected value of 'suspension' in settings.
	 */
	public function test_wp_heartbeat_set_suspension( $pagenow_val, $expected ) {
		global $pagenow;
		$pagenow = $pagenow_val;

		$settings = array( 'suspension' => 'initial' );
		$result   = wp_heartbeat_set_suspension( $settings );

		if ( 'disable' === $expected ) {
			$this->assertSame( 'disable', $result['suspension'], "Suspension should be 'disable' when \$pagenow is {$pagenow_val}." );
		} else {
			$this->assertSame( 'initial', $result['suspension'], "Suspension should remain 'initial' when \$pagenow is {$pagenow_val}." );
		}
	}

	/**
	 * Data provider for test_wp_heartbeat_set_suspension.
	 *
	 * @return array<string, array{
	 *     pagenow_val: string,
	 *     expected:    string,
	 * }>
	 */
	public function data_wp_heartbeat_set_suspension(): array {
		return array(
			'post.php'     => array(
				'pagenow_val' => 'post.php',
				'expected'    => 'disable',
			),
			'post-new.php' => array(
				'pagenow_val' => 'post-new.php',
				'expected'    => 'disable',
			),
			'index.php'    => array(
				'pagenow_val' => 'index.php',
				'expected'    => 'initial',
			),
			'edit.php'     => array(
				'pagenow_val' => 'edit.php',
				'expected'    => 'initial',
			),
		);
	}
}
