<?php
/**
 * Tests for the WP_Filesystem_Direct::chgrp() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::chgrp
 */
class Tests_Filesystem_WpFilesystemDirect_Chgrp extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::chgrp()`
	 * returns false for a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_fail_to_change_file_group( $path ) {
		$this->assertFalse( self::$filesystem->chgrp( self::$file_structure['test_dir']['path'] . $path, 0 ) );
	}
}
