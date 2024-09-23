<?php

/**
 * Tests for WP native customizations added to the Text_Diff::check() method.
 *
 * @group diff
 *
 * @covers Text_Diff::_check
 */
final class Text_Diff_Check_Test extends WP_UnitTestCase {

	const FILE_A = array(
		'Line 1',
		'Line 2',
		'Line 3',
	);

	const FILE_B = array(
		'Line 11',
		'Line 2',
		'Line 13',
	);

	public static function set_up_before_class() {
		require_once ABSPATH . 'wp-includes/Text/Diff.php';
	}

	/**
	 * Disable WP specific set up as it is not needed.
	 */
	public function set_up() {}

	public function test_check_passes_when_passed_same_input() {
		$diff = new Text_Diff( 'auto', array( self::FILE_A, self::FILE_B ) );
		$this->assertTrue( $diff->_check( self::FILE_A, self::FILE_B ) );
	}
}
