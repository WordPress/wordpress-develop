<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `wp_mkdir_p()` function.
 *
 * @group functions
 *
 * @covers ::wp_mkdir_p
 */
class WpMkdirPTest extends WP_UnitTestCase {

	/**
	 * Temporary directories created during testing.
	 *
	 * @var string[]
	 */
	protected $created_dirs = array();

	public function tear_down() {
		foreach ( array_reverse( $this->created_dirs ) as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}

		parent::tear_down();
	}

	public function test_folder_is_made() {
		$upload_dir           = wp_upload_dir();
		$target               = $upload_dir['basedir'] . '/test';
		$this->created_dirs[] = $target;

		$this->assertTrue( wp_mkdir_p( $target ) );
		$this->assertDirectoryExists( $target );
	}

	public function test_if_file_with_name_exists() {
		$this->assertFalse( wp_mkdir_p( ABSPATH . 'wp-admin/index.php' ) );
	}

	public function test_if_folder_with_name_exists() {
		$this->assertTrue( wp_mkdir_p( ABSPATH . 'wp-admin' ) );
	}

	// should return false ../ in path
	// Do not allow path traversals.
	public function test_if_up_tree_in_target() {
		$this->assertFalse( wp_mkdir_p( '../wp-admin' ) );
		$this->assertFalse( wp_mkdir_p( '../../test' ) );
		$this->assertFalse( wp_mkdir_p( '..' . DIRECTORY_SEPARATOR . 'test' ) );
		$this->assertFalse( wp_mkdir_p( ABSPATH . 'test/../../' ) );

		// This resolves to the root directory which exists, so it returns true.
		$this->assertTrue( wp_mkdir_p( '../../../../' ) );
	}

	public function test_permissions_are_set() {
		$upload_dir           = wp_upload_dir();
		$target               = $upload_dir['basedir'] . '/permission_test';
		$this->created_dirs[] = $target;
		$parent_stat          = stat( $upload_dir['basedir'] );

		$this->assertTrue( wp_mkdir_p( $target ) );
		$target_stat = stat( $target );
		$this->assertSame( $parent_stat['mode'], $target_stat['mode'] );
	}
}
