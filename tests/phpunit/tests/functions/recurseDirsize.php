<?php

/**
 * Tests the recurse_dirsize() function.
 *
 * @group functions
 *
 * @covers ::recurse_dirsize
 */
class Tests_Functions_RecurseDirsize extends WP_UnitTestCase {
	private $test_dir;

	public function set_up() {
		parent::set_up();
		$this->test_dir = get_temp_dir() . 'recurse_dirsize_test_' . uniqid();
		if ( ! is_dir( $this->test_dir ) ) {
			mkdir( $this->test_dir );
		}
	}

	public function tear_down() {
		$this->recursive_rmdir( $this->test_dir );
		delete_transient( 'dirsize_cache' );
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
	 * Tests basic recursive directory size calculation.
	 *
	 * @ticket 65654
	 */
	public function test_recurse_dirsize_basic() {
		mkdir( $this->test_dir . '/sub' );
		file_put_contents( $this->test_dir . '/file1.txt', '12345' ); // 5 bytes.
		file_put_contents( $this->test_dir . '/sub/file2.txt', '1234567890' ); // 10 bytes.

		$this->assertSame( 15, recurse_dirsize( $this->test_dir ) );
	}

	/**
	 * Tests that recurse_dirsize() respects the exclude parameter.
	 *
	 * @ticket 65654
	 */
	public function test_recurse_dirsize_exclude() {
		mkdir( $this->test_dir . '/exclude_me' );
		file_put_contents( $this->test_dir . '/file1.txt', '12345' ); // 5 bytes.
		file_put_contents( $this->test_dir . '/exclude_me/file2.txt', '1234567890' ); // 10 bytes.

		$this->assertSame( 5, recurse_dirsize( $this->test_dir, $this->test_dir . '/exclude_me' ) );
	}

	/**
	 * Tests the pre_recurse_dirsize filter.
	 *
	 * @ticket 65654
	 */
	public function test_pre_recurse_dirsize_filter() {
		add_filter(
			'pre_recurse_dirsize',
			static function () {
				return 1234;
			}
		);
		$this->assertSame( 1234, recurse_dirsize( $this->test_dir ) );
	}

	/**
	 * Tests that the result is cached in a transient.
	 *
	 * @ticket 65654
	 */
	public function test_recurse_dirsize_caching() {
		file_put_contents( $this->test_dir . '/file1.txt', '12345' );

		// First call populates cache.
		recurse_dirsize( $this->test_dir );

		$cache = get_transient( 'dirsize_cache' );
		$this->assertIsArray( $cache );
		$path = untrailingslashit( $this->test_dir );
		$this->assertArrayHasKey( $path, $cache );
		$this->assertSame( 5, $cache[ $path ] );

		// Modify file and check that cached value is still returned.
		file_put_contents( $this->test_dir . '/file1.txt', '1234567890' );
		$this->assertSame( 5, recurse_dirsize( $this->test_dir ), 'Cached value should be returned.' );
	}
}
