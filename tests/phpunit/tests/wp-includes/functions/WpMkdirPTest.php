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
		$target               = $upload_dir['basedir'] . '/wp-mkdir-p-test-' . wp_generate_password( 6, false );
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

	/**
	 * Do not allow path traversals.
	 *
	 * The targets are built from a unique component that is asserted missing first:
	 * wp_mkdir_p() returns for an existing path before it rejects traversal, so a
	 * target that happens to exist relative to the working directory would pass for
	 * the wrong reason.
	 */
	public function test_if_up_tree_in_target() {
		$missing = 'wp-mkdir-p-missing-' . wp_generate_password( 6, false );

		$this->assertFileDoesNotExist( ABSPATH . $missing, 'The target must not exist for this test to mean anything.' );

		$this->assertFalse( wp_mkdir_p( ABSPATH . $missing . '/../' . $missing ) );
		$this->assertFalse( wp_mkdir_p( ABSPATH . $missing . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $missing ) );
		$this->assertFalse( wp_mkdir_p( ABSPATH . $missing . '/../../' . $missing ) );

		// Relative too: the unique component cannot exist relative to any directory.
		$this->assertFalse( wp_mkdir_p( $missing . '/../' . $missing ) );
		$this->assertFalse( wp_mkdir_p( $missing . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $missing ) );

		$this->assertDirectoryDoesNotExist( ABSPATH . $missing, 'A rejected traversal must not create anything.' );
	}

	/**
	 * An existing target is accepted even though the path contains a traversal
	 * segment: wp_mkdir_p() reports an existing path before it rejects traversal.
	 */
	public function test_existing_target_with_a_traversal_segment_is_accepted() {
		$this->assertTrue( wp_mkdir_p( ABSPATH . 'wp-admin/../wp-admin' ) );
	}

	public function test_permissions_are_set() {
		$upload_dir           = wp_upload_dir();
		$target               = $upload_dir['basedir'] . '/wp-mkdir-p-perm-test-' . wp_generate_password( 6, false );
		$this->created_dirs[] = $target;
		$parent_stat          = stat( $upload_dir['basedir'] );

		$this->assertTrue( wp_mkdir_p( $target ) );
		$target_stat = stat( $target );
		$this->assertSame( $parent_stat['mode'], $target_stat['mode'] );
	}

	/**
	 * A directory created by another process between the existence check and
	 * mkdir() is accepted, but its permissions are left alone: this call did not
	 * create it, so widening them could expose a directory another process made
	 * deliberately narrow.
	 *
	 * The race cannot be produced in-process on a real filesystem, so the target
	 * is a stream wrapper whose mkdir() fails and reports the directory present
	 * from that moment on, exactly as a losing race does.
	 *
	 * @ticket 57081
	 */
	public function test_directory_created_by_another_process_keeps_its_permissions() {
		stream_wrapper_register( 'wpmkdirprace', WpMkdirPRaceStream::class );
		WpMkdirPRaceStream::reset();

		// A umask that alters the inherited bits, so the permission block is reached.
		$previous_umask = umask( 0022 );

		try {
			$created = wp_mkdir_p( 'wpmkdirprace://bucket/parent/child' );
		} finally {
			umask( $previous_umask );
			stream_wrapper_unregister( 'wpmkdirprace' );
		}

		$this->assertSame( 1, WpMkdirPRaceStream::$mkdir_calls, 'The race path was not exercised, so the rest of this test proves nothing.' );
		$this->assertTrue( $created, 'Losing the race is not a failure: the directory exists.' );
		$this->assertSame( 0, WpMkdirPRaceStream::$chmod_calls, 'wp_mkdir_p() must not change permissions on a directory it did not create.' );
	}
}

/**
 * Stream wrapper that loses the mkdir race.
 *
 * mkdir() reports failure and the target is present from that call onwards, which
 * is the state wp_mkdir_p() sees when another process created the directory first.
 * Permission changes are counted rather than applied.
 */
class WpMkdirPRaceStream {

	/**
	 * Number of permission changes attempted through this wrapper.
	 *
	 * @var int
	 */
	public static $chmod_calls = 0;

	/**
	 * Number of directory creations attempted through this wrapper.
	 *
	 * @var int
	 */
	public static $mkdir_calls = 0;

	/**
	 * Whether the target directory exists yet.
	 *
	 * @var bool
	 */
	public static $target_exists = false;

	/**
	 * The current context.
	 *
	 * @var resource|null
	 */
	public $context;

	/**
	 * Returns the wrapper to its starting state.
	 */
	public static function reset() {
		self::$chmod_calls   = 0;
		self::$mkdir_calls   = 0;
		self::$target_exists = false;
	}

	/**
	 * Reports the parent as an existing 0777 directory, and the target only once
	 * mkdir() has been attempted.
	 *
	 * @param string $path  The path being stat'ed.
	 * @param int    $flags Stat flags.
	 * @return array<string, int>|false
	 */
	public function url_stat( $path, $flags ) {
		$file = parse_url( $path, PHP_URL_PATH );

		if ( '/parent' === $file ) {
			return $this->directory_stat();
		}

		if ( '/parent/child' === $file && self::$target_exists ) {
			return $this->directory_stat();
		}

		return false;
	}

	/**
	 * Fails, and reports the directory present from now on.
	 *
	 * @param string $path    The directory to create.
	 * @param int    $mode    Permissions.
	 * @param int    $options Option flags.
	 * @return false
	 */
	public function mkdir( $path, $mode, $options ) {
		++self::$mkdir_calls;
		self::$target_exists = true;

		return false;
	}

	/**
	 * Counts permission changes instead of applying them.
	 *
	 * @param string $path   The path being changed.
	 * @param int    $option The metadata option.
	 * @param mixed  $value  The new value.
	 * @return bool
	 */
	public function stream_metadata( $path, $option, $value ) {
		if ( STREAM_META_ACCESS === $option ) {
			++self::$chmod_calls;
		}

		return true;
	}

	/**
	 * A stat array describing a 0777 directory.
	 *
	 * @return array<string, int>
	 */
	private function directory_stat() {
		return array(
			'dev'     => 0,
			'ino'     => 0,
			'mode'    => 040777,
			'nlink'   => 0,
			'uid'     => 0,
			'gid'     => 0,
			'rdev'    => 0,
			'size'    => 0,
			'atime'   => 0,
			'mtime'   => 0,
			'ctime'   => 0,
			'blksize' => -1,
			'blocks'  => -1,
		);
	}
}
