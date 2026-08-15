<?php

/**
 * @group functions
 *
 * @covers ::validate_file
 */
class Tests_Functions_ValidateFile extends WP_UnitTestCase {

	/**
	 * Test file path validation
	 *
	 * @ticket 42016
	 * @ticket 61488
	 * @dataProvider data_validate_file
	 *
	 * @param string $file          File path.
	 * @param array  $allowed_files List of allowed files.
	 * @param int    $expected      Expected result.
	 */
	public function test_validate_file( $file, $allowed_files, $expected ) {
		$this->assertSame( $expected, validate_file( $file, $allowed_files ) );
	}

	/**
	 * Data provider for file validation.
	 *
	 * @return array {
	 *     @type array ...$0 {
	 *         @type string $0 File path.
	 *         @type array  $1 List of allowed files.
	 *         @type int    $2 Expected result.
	 *     }
	 * }
	 */
	public function data_validate_file() {
		return array(

			// Allowed files:
			array(
				null,
				array(),
				0,
			),
			array(
				'',
				array(),
				0,
			),
			array(
				' ',
				array(),
				0,
			),
			array(
				'.',
				array(),
				0,
			),
			array(
				'..',
				array(),
				0,
			),
			array(
				'./',
				array(),
				0,
			),
			array(
				'foo.ext',
				array( 'foo.ext' ),
				0,
			),
			array(
				'dir/foo.ext',
				array(),
				0,
			),
			array(
				'foo..ext',
				array(),
				0,
			),
			array(
				'dir/dir/../',
				array(),
				0,
			),

			// Directory traversal:
			array(
				'../',
				array(),
				1,
			),
			array(
				'../../',
				array(),
				1,
			),
			array(
				'../file.ext',
				array(),
				1,
			),
			array(
				'../dir/../',
				array(),
				1,
			),
			array(
				'/dir/dir/../../',
				array(),
				1,
			),
			array(
				'/dir/dir/../../',
				array( '/dir/dir/../../' ),
				1,
			),

			// Windows drives:
			array(
				'c:',
				array(),
				2,
			),
			array(
				'C:/WINDOWS/system32',
				array( 'C:/WINDOWS/system32' ),
				2,
			),

			// Windows Path with allowed file
			array(
				'Apache24\htdocs\wordpress/wp-content/themes/twentyten/style.css',
				array( 'Apache24\htdocs\wordpress/wp-content/themes/twentyten/style.css' ),
				0,
			),

			// Disallowed files:
			array(
				'foo.ext',
				array( 'bar.ext' ),
				3,
			),
			array(
				'foo.ext',
				array( '.ext' ),
				3,
			),
			array(
				'path/foo.ext',
				array( 'foo.ext' ),
				3,
			),

		);
	}
}
