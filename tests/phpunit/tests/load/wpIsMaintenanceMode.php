<?php
/**
 * Unit tests for `wp_is_maintenance_mode()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group load
 *
 * @covers ::wp_is_maintenance_mode
 */
class Test_WP_Is_Maintenance_Mode extends WP_UnitTestCase {

	public function tear_down() {
		if ( is_dir( ABSPATH . '.maintenance' ) ) {
			rmdir( ABSPATH . '.maintenance' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65911
	 */
	public function test_should_return_false_when_the_maintenance_file_cannot_be_loaded() {
		if ( file_exists( ABSPATH . '.maintenance' ) ) {
			$this->markTestSkipped( 'A .maintenance file already exists in ABSPATH.' );
		}

		mkdir( ABSPATH . '.maintenance' );

		$this->assertFalse( wp_is_maintenance_mode() );
	}
}
