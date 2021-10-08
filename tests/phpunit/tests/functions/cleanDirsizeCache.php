<?php

/**
 * Tests specific to the directory size caching.
 */
class Tests_Functions_CleanDirsizeCache extends WP_UnitTestCase {

	/**
	 * Test the handling of invalid data passed as the $path parameter.
	 *
	 * @ticket 52241
	 *
	 * @covers ::clean_dirsize_cache
	 *
	 * @dataProvider data_clean_dirsize_cache_with_invalid_inputs
	 *
	 * @param mixed $path Path input to use in the test.
	 */
	public function test_clean_dirsize_cache_with_invalid_inputs( $path ) {
		$this->setExpectedIncorrectUsage( 'clean_dirsize_cache' );

		clean_dirsize_cache( $path );
	}

	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function data_clean_dirsize_cache_with_invalid_inputs() {
		return array(
			'null'         => array( null ),
			'bool false'   => array( false ),
			'empty string' => array( '' ),
			'array'        => array( array( '.', './second/path/' ) ),
		);
	}

	/**
	 * Test the handling of a non-path text string passed as the $path parameter.
	 *
	 * @ticket 52241
	 *
	 * @covers ::clean_dirsize_cache
	 *
	 * @dataProvider data_clean_dirsize_cache_with_non_path_string
	 *
	 * @param string $path           Path input to use in the test.
	 * @param int    $expected_count Expected number of paths in the cache after cleaning.
	 */
	public function test_clean_dirsize_cache_with_non_path_string( $path, $expected_count ) {
		// Set the dirsize cache to our mock.
		set_transient( 'dirsize_cache', $this->mock_dirsize_cache_with_non_path_string() );

		clean_dirsize_cache( $path );

		$cache = get_transient( 'dirsize_cache' );
		$this->assertIsArray( $cache );
		$this->assertCount( $expected_count, $cache );
	}

	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function data_clean_dirsize_cache_with_non_path_string() {
		return array(
			'single dot'                        => array(
				'path'           => '.',
				'expected_count' => 1,
			),
			'non-path'                          => array(
				'path'           => 'string',
				'expected_count' => 1,
			),
			'non-existant string, but non-path' => array(
				'path'           => 'doesnotexist',
				'expected_count' => 2,
			),
		);
	}

	private function mock_dirsize_cache_with_non_path_string() {
		return array(
			'.'      => array( 'size' => 50 ),
			'string' => array( 'size' => 42 ),
		);
	}
}
