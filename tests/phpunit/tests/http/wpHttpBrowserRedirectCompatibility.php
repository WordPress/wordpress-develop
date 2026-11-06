<?php

/**
 * Tests for the WP_Http::browser_redirect_compatibility() method.
 *
 * @group http
 *
 * @covers WP_Http::browser_redirect_compatibility
 */
class Tests_HTTP_WpHttpBrowserRedirectCompatibility extends WP_UnitTestCase {

	/**
	 * Gets a response with a status code.
	 *
	 * @param int $status_code Status code.
	 * @return WpOrg\Requests\Response Response.
	 */
	private function get_response( $status_code ) {
		$response              = new WpOrg\Requests\Response();
		$response->status_code = $status_code;

		return $response;
	}

	/**
	 * Tests that the request of a 302 redirect is changed to GET, like the browsers do.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_request_types
	 *
	 * @param string $type Type of the request.
	 */
	public function test_should_change_the_request_to_get_for_a_302_response( $type ) {
		$options = array(
			'type' => $type,
			'data' => array( 'a' => 1 ),
		);

		WP_Http::browser_redirect_compatibility( 'https://example.org/', array(), '', $options, $this->get_response( 302 ) );

		$this->assertSame( WpOrg\Requests\Requests::GET, $options['type'] );
		$this->assertSame( array( 'a' => 1 ), $options['data'], 'The other options should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_request_types() {
		return array(
			'POST'   => array( 'POST' ),
			'PUT'    => array( 'PUT' ),
			'DELETE' => array( 'DELETE' ),
			'GET'    => array( 'GET' ),
		);
	}

	/**
	 * Tests that the request is not changed for other status codes.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_status_codes_that_do_not_change_the_request
	 *
	 * @param int $status_code Status code of the response.
	 */
	public function test_should_not_change_the_request_for_other_status_codes( $status_code ) {
		$options = array( 'type' => 'POST' );

		WP_Http::browser_redirect_compatibility( 'https://example.org/', array(), '', $options, $this->get_response( $status_code ) );

		$this->assertSame( 'POST', $options['type'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_status_codes_that_do_not_change_the_request() {
		return array(
			'200' => array( 200 ),
			'301' => array( 301 ),
			'303' => array( 303 ),
			'307' => array( 307 ),
			'308' => array( 308 ),
			'404' => array( 404 ),
		);
	}

	/**
	 * Tests that the status code is compared strictly.
	 *
	 * @ticket 66507
	 */
	public function test_should_compare_the_status_code_strictly() {
		$options = array( 'type' => 'POST' );

		WP_Http::browser_redirect_compatibility( 'https://example.org/', array(), '', $options, $this->get_response( '302' ) );

		$this->assertSame( 'POST', $options['type'] );
	}
}
