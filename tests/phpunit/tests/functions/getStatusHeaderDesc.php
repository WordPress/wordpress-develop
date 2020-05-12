<?php

/**
 * Tests get_status_header_desc function
 *
 * @since 5.3.0
 *
 * @group functions.php
 */
class Tests_Functions_GetStatusHeaderDesc extends WP_UnitTestCase {

	/**
	 * @dataProvider _status_strings
	 *
	 * @param int    $code     HTTP status code.
	 * @param string $expected Status description.
	 */
	public function test_get_status_header_desc( $code, $expected ) {
		$this->assertSame( get_status_header_desc( $code ), $expected );
	}

	/**
	 * Data provider for test_get_status_header_desc().
	 *
	 * @return array
	 */
	public function _status_strings() {
		return array(
			array( 200, 'OK' ),
			array( 301, 'Moved Permanently' ),
			array( 404, 'Not Found' ),
			array( 500, 'Internal Server Error' ),

			// A string to make sure that the absint() is working.
			array( '200', 'OK' ),

			// Not recognized codes return empty strings.
			array( 9999, '' ),
			array( 'random', '' ),
		);
	}
}
