<?php

/**
 * Tests the status_header() function.
 *
 * @group functions
 *
 * @covers ::status_header
 */
class Tests_Functions_StatusHeader extends WP_UnitTestCase {

	/**
	 * Tests that status_header() correctly filters the header.
	 *
	 * Since we cannot easily test the actual header() call in PHPUnit,
	 * we rely on the 'status_header' filter to verify the generated string.
	 *
	 * @ticket 65650
	 *
	 * @dataProvider data_status_header
	 *
	 * @param int    $code           HTTP status code.
	 * @param string $description    Custom description.
	 * @param string $expected_regex Regex to match the generated status header.
	 */
	public function test_status_header_filter( $code, $description, $expected_regex ) {
		$filter = new MockAction();
		add_filter( 'status_header', array( $filter, 'filter' ), 10, 4 );

		status_header( $code, $description );

		$args = $filter->get_args();
		$this->assertNotEmpty( $args, 'The status_header filter was not called.' );

		$actual_header = $args[0][0];
		$this->assertMatchesRegularExpression( $expected_regex, $actual_header );
		$this->assertSame( (int) $code, $args[0][1], 'The status code passed to the filter does not match.' );

		if ( $description ) {
			$this->assertSame( $description, $args[0][2], 'The description passed to the filter does not match.' );
		} else {
			$this->assertSame( get_status_header_desc( $code ), $args[0][2], 'The default description passed to the filter does not match.' );
		}
	}

	/**
	 * Data provider for test_status_header_filter().
	 *
	 * @return array<string, array{
	 *     code:           int,
	 *     description:    string,
	 *     expected_regex: string,
	 * }>
	 */
	public function data_status_header(): array {
		return array(
			'200 OK'                => array(
				200,
				'',
				'/^HTTP\/1\.[012] 200 OK$/',
			),
			'404 Not Found'         => array(
				404,
				'',
				'/^HTTP\/1\.[012] 404 Not Found$/',
			),
			'Custom description'    => array(
				200,
				'Everything is Fine',
				'/^HTTP\/1\.[012] 200 Everything is Fine$/',
			),
			'301 Moved Permanently' => array(
				301,
				'',
				'/^HTTP\/1\.[012] 301 Moved Permanently$/',
			),
		);
	}

	/**
	 * Tests that status_header() returns early for unknown status codes without a description.
	 *
	 * @ticket 65650
	 */
	public function test_status_header_unknown_code_returns_early() {
		$filter = new MockAction();
		add_filter( 'status_header', array( $filter, 'filter' ), 10, 4 );

		status_header( 999 );

		$this->assertEmpty( $filter->get_args(), 'The status_header filter should not have been called for an unknown status code without description.' );
	}

	/**
	 * Tests that status_header() works with unknown status codes if a description is provided.
	 *
	 * @ticket 65650
	 */
	public function test_status_header_unknown_code_with_description() {
		$filter = new MockAction();
		add_filter( 'status_header', array( $filter, 'filter' ), 10, 4 );

		status_header( 999, 'Custom Status' );

		$args = $filter->get_args();
		$this->assertNotEmpty( $args, 'The status_header filter should have been called when a description is provided.' );
		$this->assertMatchesRegularExpression( '/^HTTP\/1\.[012] 999 Custom Status$/', $args[0][0] );
	}

	/**
	 * Tests that status_header() respects the server protocol.
	 *
	 * @ticket 65650
	 */
	public function test_status_header_respects_protocol() {
		$old_protocol               = $_SERVER['SERVER_PROTOCOL'] ?? null;
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

		$filter = new MockAction();
		add_filter( 'status_header', array( $filter, 'filter' ), 10, 4 );

		status_header( 200 );

		$args = $filter->get_args();
		$this->assertStringStartsWith( 'HTTP/1.1 200 OK', $args[0][0] );

		// Reset protocol.
		if ( null === $old_protocol ) {
			unset( $_SERVER['SERVER_PROTOCOL'] );
		} else {
			$_SERVER['SERVER_PROTOCOL'] = $old_protocol;
		}
	}
}
