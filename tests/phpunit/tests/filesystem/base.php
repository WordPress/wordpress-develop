<?php

/**
 * This class is designed to make use of MockFS, a Virtual in-memory filesystem compatible with WP_Filesystem
 */
abstract class WP_Filesystem_UnitTestCase extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		add_filter( 'filesystem_method_file', array( $this, 'filter_abstraction_file' ) );
		add_filter( 'filesystem_method', array( $this, 'filter_fs_method' ) );
		WP_Filesystem();
	}

	public function tear_down() {
		global $wp_filesystem;
		remove_filter( 'filesystem_method_file', array( $this, 'filter_abstraction_file' ) );
		remove_filter( 'filesystem_method', array( $this, 'filter_fs_method' ) );
		unset( $wp_filesystem );

		parent::tear_down();
	}

	public function filter_fs_method( $method ) {
		return 'MockFS';
	}
	public function filter_abstraction_file( $file ) {
		return dirname( dirname( __DIR__ ) ) . '/includes/mock-fs.php';
	}

	public function test_is_MockFS_sane() {
		global $wp_filesystem;
		$this->assertInstanceOf( 'WP_Filesystem_MockFS', $wp_filesystem );

		$wp_filesystem->init( '/' );

		// Test creation/exists checks.
		$this->assertFalse( $wp_filesystem->is_dir( '/test/' ) );
		$wp_filesystem->mkdir( '/test' );
		$this->assertTrue( $wp_filesystem->exists( '/test' ) );
		$this->assertTrue( $wp_filesystem->is_dir( '/test/' ) );
		$this->assertFalse( $wp_filesystem->is_file( '/test' ) );
		// $this->assertFalse( true );
	}
}
