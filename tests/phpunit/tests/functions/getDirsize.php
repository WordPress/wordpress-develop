<?php

/**
 * Tests the get_dirsize() function.
 *
 * @group functions
 *
 * @covers ::get_dirsize
 */
class Tests_Functions_GetDirsize extends WP_UnitTestCase {
	private $test_dir;

	public function set_up() {
		parent::set_up();
		$this->test_dir = get_temp_dir() . 'get_dirsize_test_' . uniqid();
		if ( ! is_dir( $this->test_dir ) ) {
			mkdir( $this->test_dir );
		}
	}

	public function tear_down() {
		$this->recursive_rmdir( $this->test_dir );
		parent::tear_down();
	}

	private function recursive_rmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = "$dir/$file";
			is_dir( $path ) ? $this->recursive_rmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	/**
	 * Tests basic directory size calculation.
	 *
	 * @ticket 65654
	 */
	public function test_get_dirsize_basic() {
		file_put_contents( $this->test_dir . '/file1.txt', '12345' );
		file_put_contents( $this->test_dir . '/file2.txt', '1234567890' );

		$this->assertSame( 15, get_dirsize( $this->test_dir ) );
	}

	/**
	 * Tests that get_dirsize() returns false for non-existent directories.
	 *
	 * @ticket 65654
	 */
	public function test_get_dirsize_non_existent() {
		$this->assertFalse( get_dirsize( $this->test_dir . '/non_existent' ) );
	}
}
