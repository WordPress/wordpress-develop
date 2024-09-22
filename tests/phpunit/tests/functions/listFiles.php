<?php

/**
 * Test list_files().
 *
 * @group functions
 *
 * @covers ::list_files
 */
class Tests_Functions_ListFiles extends WP_UnitTestCase {

	public function test_list_files_returns_a_list_of_files() {
		$admin_files = list_files( ABSPATH . 'wp-admin/' );
		$this->assertIsArray( $admin_files );
		$this->assertNotEmpty( $admin_files );
		$this->assertContains( ABSPATH . 'wp-admin/index.php', $admin_files );
	}

	public function test_list_files_can_exclude_files() {
		$admin_files = list_files( ABSPATH . 'wp-admin/', 100, array( 'index.php' ) );
		$this->assertNotContains( ABSPATH . 'wp-admin/index.php', $admin_files );
	}

	/**
	 * Tests that list_files() optionally includes hidden files.
	 *
	 * @ticket 53659
	 *
	 * @dataProvider data_list_files_should_optionally_include_hidden_files
	 *
	 * @param string   $filename       The name of the hidden file.
	 * @param bool     $include_hidden Whether to include hidden ("." prefixed) files.
	 * @param string[] $exclusions     List of folders and files to skip.
	 * @param bool     $expected       Whether the file should be included in the results.
	 */
	public function test_list_files_should_optionally_include_hidden_files( $filename, $include_hidden, $exclusions, $expected ) {
		$test_dir    = get_temp_dir() . 'test-list-files/';
		$hidden_file = $test_dir . $filename;

		mkdir( $test_dir );
		touch( $hidden_file );

		$actual = list_files( $test_dir, 100, $exclusions, $include_hidden );

		unlink( $hidden_file );
		rmdir( $test_dir );

		if ( $expected ) {
			$this->assertContains( $hidden_file, $actual, 'The file was not included.' );
		} else {
			$this->assertNotContains( $hidden_file, $actual, 'The file was included.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_list_files_should_optionally_include_hidden_files() {
		return array(
			'$include_hidden = false and no exclusions' => array(
				'filename'       => '.hidden_file',
				'include_hidden' => false,
				'exclusions'     => array(),
				'expected'       => false,
			),
			'$include_hidden = true and no exclusions'  => array(
				'filename'       => '.hidden_file',
				'include_hidden' => true,
				'exclusions'     => array(),
				'expected'       => true,
			),
			'$include_hidden = true and an excluded filename' => array(
				'filename'       => '.hidden_file',
				'include_hidden' => true,
				'exclusions'     => array( '.hidden_file' ),
				'expected'       => false,
			),
		);
	}
}
