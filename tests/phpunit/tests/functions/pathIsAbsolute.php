<?php

/**
 * @ticket 65526
 *
 * @group functions
 *
 * @covers ::path_is_absolute
 */
class Tests_Functions_PathIsAbsolute extends WP_UnitTestCase {

	public function test_path_is_absolute() {
		$absolute_paths = array(
			'/',
			'/foo/',
			'/foo',
			'/FOO/bar',
			'/foo/bar/',
			'/foo/../bar/',
			'\\WINDOWS',
			'C:\\',
			'C:\\WINDOWS',
			'\\\\sambashare\\foo',
		);
		foreach ( $absolute_paths as $path ) {
			$this->assertTrue( path_is_absolute( $path ), "path_is_absolute('$path') should return true" );
		}
	}

	public function test_path_is_not_absolute() {
		$relative_paths = array(
			'',
			'.',
			'..',
			'../foo',
			'../',
			'../foo.bar',
			'foo/bar',
			'foo',
			'FOO',
			'..\\WINDOWS',
		);
		foreach ( $relative_paths as $path ) {
			$this->assertFalse( path_is_absolute( $path ), "path_is_absolute('$path') should return false" );
		}
	}
}
