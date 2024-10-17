<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::flatten_dirlist()
 */
class Admin_WpUpgrader_FlattenDirlist_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::flatten_dirlist()` returns the expected file list.
	 *
	 * @ticket 54245
	 *
	 * @dataProvider data_should_flatten_dirlist
	 *
	 * @param array  $expected     The expected flattened dirlist.
	 * @param array  $nested_files Array of files as returned by WP_Filesystem_Base::dirlist().
	 * @param string $path         Optional. Relative path to prepend to child nodes. Default empty string.
	 */
	public function test_flatten_dirlist_should_flatten_the_provided_directory_list( $expected, $nested_files, $path = '' ) {
		$flatten_dirlist = new ReflectionMethod( self::$instance, 'flatten_dirlist' );
		$flatten_dirlist->setAccessible( true );
		$actual = $flatten_dirlist->invoke( self::$instance, $nested_files, $path );
		$flatten_dirlist->setAccessible( false );

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_flatten_dirlist() {
		return array(
			'empty array, default path'       => array(
				'expected'     => array(),
				'nested_files' => array(),
			),
			'root only'                       => array(
				'expected'     => array(
					'file1.php' => array( 'name' => 'file1.php' ),
					'file2.php' => array( 'name' => 'file2.php' ),
				),
				'nested_files' => array(
					'file1.php' => array( 'name' => 'file1.php' ),
					'file2.php' => array( 'name' => 'file2.php' ),
				),
			),
			'root only and custom path'       => array(
				'expected'     => array(
					'custom_path/file1.php' => array( 'name' => 'file1.php' ),
					'custom_path/file2.php' => array( 'name' => 'file2.php' ),
				),
				'nested_files' => array(
					'file1.php' => array( 'name' => 'file1.php' ),
					'file2.php' => array( 'name' => 'file2.php' ),
				),
				'path'         => 'custom_path/',
			),
			'one level deep'                  => array(
				'expected'     => array(
					'subdir1'              => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'subdir2'              => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
					'subdir1/subfile1.php' => array( 'name' => 'subfile1.php' ),
					'subdir1/subfile2.php' => array( 'name' => 'subfile2.php' ),
					'subdir2/subfile3.php' => array( 'name' => 'subfile3.php' ),
					'subdir2/subfile4.php' => array( 'name' => 'subfile4.php' ),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
				),
			),
			'one level deep and numeric keys' => array(
				'expected'     => array(
					'subdir1'   => array(
						'files' => array(
							0 => array( 'name' => '0' ),
							1 => array( 'name' => '1' ),
						),
					),
					'subdir2'   => array(
						'files' => array(
							2 => array( 'name' => '2' ),
							3 => array( 'name' => '3' ),
						),
					),
					'subdir1/0' => array( 'name' => '0' ),
					'subdir1/1' => array( 'name' => '1' ),
					'subdir2/2' => array( 'name' => '2' ),
					'subdir2/3' => array( 'name' => '3' ),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'0' => array( 'name' => '0' ),
							'1' => array( 'name' => '1' ),
						),
					),
					'subdir2' => array(
						'files' => array(
							'2' => array( 'name' => '2' ),
							'3' => array( 'name' => '3' ),
						),
					),
				),
			),
			'one level deep and custom path'  => array(
				'expected'     => array(
					'custom_path/subdir1'              => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'custom_path/subdir2'              => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
					'custom_path/subdir1/subfile1.php' => array(
						'name' => 'subfile1.php',
					),
					'custom_path/subdir1/subfile2.php' => array(
						'name' => 'subfile2.php',
					),
					'custom_path/subdir2/subfile3.php' => array(
						'name' => 'subfile3.php',
					),
					'custom_path/subdir2/subfile4.php' => array(
						'name' => 'subfile4.php',
					),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
				),
				'path'         => 'custom_path/',
			),
			'two levels deep'                 => array(
				'expected'     => array(
					'subdir1'                            => array(
						'files' => array(
							'subfile1.php' => array(
								'name' => 'subfile1.php',
							),
							'subfile2.php' => array(
								'name' => 'subfile2.php',
							),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'subdir1/subfile1.php'               => array(
						'name' => 'subfile1.php',
					),
					'subdir1/subfile2.php'               => array(
						'name' => 'subfile2.php',
					),
					'subdir1/subsubdir1'                 => array(
						'files' => array(
							'subsubfile1.php' => array(
								'name' => 'subsubfile1.php',
							),
							'subsubfile2.php' => array(
								'name' => 'subsubfile2.php',
							),
						),
					),
					'subdir1/subsubdir1/subsubfile1.php' => array(
						'name' => 'subsubfile1.php',
					),
					'subdir1/subsubdir1/subsubfile2.php' => array(
						'name' => 'subsubfile2.php',
					),
					'subdir2'                            => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
					'subdir2/subfile3.php'               => array(
						'name' => 'subfile3.php',
					),
					'subdir2/subfile4.php'               => array(
						'name' => 'subfile4.php',
					),
					'subdir2/subsubdir2'                 => array(
						'files' => array(
							'subsubfile3.php' => array(
								'name' => 'subsubfile3.php',
							),
							'subsubfile4.php' => array(
								'name' => 'subsubfile4.php',
							),
						),
					),
					'subdir2/subsubdir2/subsubfile3.php' => array(
						'name' => 'subsubfile3.php',
					),
					'subdir2/subsubdir2/subsubfile4.php' => array(
						'name' => 'subsubfile4.php',
					),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
				),
			),
			'two levels deep and custom path' => array(
				'expected'     => array(
					'custom_path/subdir1'              => array(
						'files' => array(
							'subfile1.php' => array(
								'name' => 'subfile1.php',
							),
							'subfile2.php' => array(
								'name' => 'subfile2.php',
							),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'custom_path/subdir1/subfile1.php' => array(
						'name' => 'subfile1.php',
					),
					'custom_path/subdir1/subfile2.php' => array(
						'name' => 'subfile2.php',
					),
					'custom_path/subdir1/subsubdir1'   => array(
						'files' => array(
							'subsubfile1.php' => array(
								'name' => 'subsubfile1.php',
							),
							'subsubfile2.php' => array(
								'name' => 'subsubfile2.php',
							),
						),
					),
					'custom_path/subdir1/subsubdir1/subsubfile1.php' => array(
						'name' => 'subsubfile1.php',
					),
					'custom_path/subdir1/subsubdir1/subsubfile2.php' => array(
						'name' => 'subsubfile2.php',
					),
					'custom_path/subdir2'              => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
					'custom_path/subdir2/subfile3.php' => array(
						'name' => 'subfile3.php',
					),
					'custom_path/subdir2/subfile4.php' => array(
						'name' => 'subfile4.php',
					),
					'custom_path/subdir2/subsubdir2'   => array(
						'files' => array(
							'subsubfile3.php' => array(
								'name' => 'subsubfile3.php',
							),
							'subsubfile4.php' => array(
								'name' => 'subsubfile4.php',
							),
						),
					),
					'custom_path/subdir2/subsubdir2/subsubfile3.php' => array(
						'name' => 'subsubfile3.php',
					),
					'custom_path/subdir2/subsubdir2/subsubfile4.php' => array(
						'name' => 'subsubfile4.php',
					),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
				),
				'path'         => 'custom_path/',
			),
		);
	}
}
