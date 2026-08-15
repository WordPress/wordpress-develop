<?php

/**
 * @ticket 65526
 *
 * @group functions
 *
 * @covers ::wp_normalize_path
 */
class Tests_Functions_WpNormalizePath extends WP_UnitTestCase {

	/**
	 * @ticket 33265
	 * @ticket 35996
	 *
	 * @dataProvider data_wp_normalize_path
	 */
	public function test_wp_normalize_path( $path, $expected ) {
		$this->assertSame( $expected, wp_normalize_path( $path ) );
	}

	public function data_wp_normalize_path() {
		return array(
			// Windows paths.
			array( 'C:\\www\\path\\', 'C:/www/path/' ),
			array( 'C:\\www\\\\path\\', 'C:/www/path/' ),
			array( 'c:/www/path', 'C:/www/path' ),
			array( 'c:\\www\\path\\', 'C:/www/path/' ), // Uppercase drive letter.
			array( 'c:\\\\www\\path\\', 'C:/www/path/' ),
			array( '\\\\Domain\\DFSRoots\\share\\path\\', '//Domain/DFSRoots/share/path/' ),
			array( '\\\\Server\\share\\path', '//Server/share/path' ),
			array( '\\\\Server\\share', '//Server/share' ),

			// Linux paths.
			array( '/www/path/', '/www/path/' ),
			array( '/www/path/////', '/www/path/' ),
			array( '/www/path', '/www/path' ),

			// PHP stream wrappers.
			array( 'php://input', 'php://input' ),
			array( 'http://example.com//path.ext', 'http://example.com/path.ext' ),
			array( 'file://c:\\www\\path\\', 'file://C:/www/path/' ),

			// Edge cases.
			array( '', '' ), // Empty string should return empty string.
			array( 123, '123' ), // Integer should be cast to string.
		);
	}

	/**
	 * Tests that wp_normalize_path() works with objects that have __toString().
	 *
	 * This is important because the function uses a static cache, and the input
	 * must be cast to string before being used as an array key.
	 *
	 * @ticket 64538
	 */
	public function test_wp_normalize_path_with_stringable_object() {
		$file_info = new SplFileInfo( '/var/www/html\\test' );

		$this->assertSame( '/var/www/html/test', wp_normalize_path( $file_info ) );
	}

	/**
	 * Tests that wp_normalize_path() returns consistent results on repeated calls.
	 *
	 * The function uses a static cache, so this verifies cache behavior.
	 *
	 * @ticket 64538
	 */
	public function test_wp_normalize_path_returns_consistent_results() {
		$path = 'C:\\www\\path\\';

		$first_call  = wp_normalize_path( $path );
		$second_call = wp_normalize_path( $path );
		$third_call  = wp_normalize_path( $path );

		$this->assertSame( $first_call, $second_call, 'Second call should return same result as first.' );
		$this->assertSame( $second_call, $third_call, 'Third call should return same result as second.' );
		$this->assertSame( 'C:/www/path/', $first_call, 'Normalized path should match expected value.' );
	}

	/**
	 * Tests that wp_normalize_path() static cache stores results.
	 *
	 * @ticket 64538
	 */
	public function test_wp_normalize_path_static_cache() {
		$path     = '/var/www/cache-test\\subdir\\';
		$expected = '/var/www/cache-test/subdir/';

		$result = wp_normalize_path( $path );
		$this->assertSame( $expected, $result );

		$reflection  = new ReflectionFunction( 'wp_normalize_path' );
		$static_vars = $reflection->getStaticVariables();

		$this->assertArrayHasKey( 'cache', $static_vars, 'Static cache array should exist.' );
		$this->assertArrayHasKey( $path, $static_vars['cache'], 'Cache should contain the normalized path.' );
		$this->assertSame( $expected, $static_vars['cache'][ $path ], 'Cached value should match the expected normalized path.' );
	}
}
