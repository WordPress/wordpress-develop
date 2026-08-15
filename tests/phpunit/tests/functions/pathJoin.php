<?php

/**
 * @ticket 65526
 *
 * @group functions
 *
 * @covers ::path_join
 */
class Tests_Functions_PathJoin extends WP_UnitTestCase {

	/**
	 * Tests path_join().
	 *
	 * @ticket 55897
	 * @dataProvider data_path_join
	 */
	public function test_path_join( $base, $path, $expected ) {
		$this->assertSame( $expected, path_join( $base, $path ) );
	}

	/**
	 * Data provider for test_path_join().
	 *
	 * @return string[][]
	 */
	public function data_path_join() {
		return array(
			// Absolute paths.
			'absolute path should return path' => array(
				'base'     => 'base',
				'path'     => '/path',
				'expected' => '/path',
			),
			'windows path with slashes'        => array(
				'base'     => 'base',
				'path'     => '//path',
				'expected' => '//path',
			),
			'windows path with backslashes'    => array(
				'base'     => 'base',
				'path'     => '\\\\path',
				'expected' => '\\\\path',
			),
			// Non-absolute paths.
			'join base and path'               => array(
				'base'     => 'base',
				'path'     => 'path',
				'expected' => 'base/path',
			),
			'strip trailing slashes in base'   => array(
				'base'     => 'base///',
				'path'     => 'path',
				'expected' => 'base/path',
			),
			'empty path'                       => array(
				'base'     => 'base',
				'path'     => '',
				'expected' => 'base/',
			),
			'empty base'                       => array(
				'base'     => '',
				'path'     => 'path',
				'expected' => '/path',
			),
			'empty path and base'              => array(
				'base'     => '',
				'path'     => '',
				'expected' => '/',
			),
		);
	}
}
