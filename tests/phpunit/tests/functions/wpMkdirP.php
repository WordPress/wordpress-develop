<?php

/**
 * Test wp_mkdir_p().
 *
 * @group functions.php
 * @covers ::wp_mkdir_p
 */
class Tests_Functions_wpMkdirP extends WP_UnitTestCase {

	public function test_folder_is_made() {

		$upload_dir = wp_upload_dir();
		$target     = $upload_dir['basedir'] . 'test';
		$this->assertTrue( wp_mkdir_p( $target ) );
		$this->assertDirectoryExists( $target );
	}

	public function test_if_file_with_name_extists() {

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

		// this resolves to current dir so is found
		$this->assertTrue( wp_mkdir_p( '../../../../' ) );
	}

	public function test_permissions_are_set() {

		$upload_dir  = wp_upload_dir();
		$target      = $upload_dir['basedir'] . 'permission_test';
		$parent_stat = stat( $upload_dir['basedir'] );
		$this->assertTrue( wp_mkdir_p( $target ) );
		$target_stat = stat( $target );
		$this->assertSame( $parent_stat['mode'], $target_stat['mode'] );
	}
}
