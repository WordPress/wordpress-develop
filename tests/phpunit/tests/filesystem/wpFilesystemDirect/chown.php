<?php
/**
 * Tests for the WP_Filesystem_Direct::chown() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::chown
 */
class Tests_Filesystem_WpFilesystemDirect_Chown extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::chown()`
	 * returns false for a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_return_false( $path ) {
		$this->assertFalse( self::$filesystem->chown( $path, fileowner( __FILE__ ) ) );
	}

	/**
	 * Tests that recursive {@see WP_Filesystem_Direct::chown()} descends into subdirectories.
	 *
	 * The resulting owner cannot be asserted without elevated privileges, so recursion is
	 * verified by recording the paths passed to {@see WP_Filesystem_Direct::chown()}. Changing each item to its current
	 * owner is a permitted no-op that avoids requiring root and its "Operation not permitted" warning.
	 *
	 * @ticket 65584
	 */
	public function test_should_recurse_into_subdirectories(): void {
		$directory   = untrailingslashit( self::$file_structure['test_dir']['path'] );
		$nested_file = self::$file_structure['subfile']['path'];

		$spy = new class( null ) extends WP_Filesystem_Direct {
			/** @var string[] */
			public array $visited = array();

			public function chown( $file, $owner, $recursive = false ) {
				$this->visited[] = $file;
				return parent::chown( $file, $owner, $recursive );
			}
		};

		$spy->chown( $directory, (int) fileowner( $directory ), true );

		$this->assertContains(
			$nested_file,
			$spy->visited,
			'chown() did not recurse into the nested subdirectory.'
		);
	}
}
