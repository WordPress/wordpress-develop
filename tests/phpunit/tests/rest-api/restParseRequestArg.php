<?php

/**
 * Tests for rest_parse_request_arg().
 *
 * @group restapi
 *
 * @covers ::rest_parse_request_arg
 */
class Tests_REST_API_RestParseRequestArg extends WP_UnitTestCase {

	/**
	 * Request with typed args.
	 *
	 * @var WP_REST_Request
	 */
	private $request;

	public function set_up() {
		parent::set_up();

		$this->request = new WP_REST_Request(
			'GET',
			'/wp/v2/testroute',
			array(
				'args' => array(
					'someinteger' => array(
						'type' => 'integer',
					),
					'someboolean' => array(
						'type' => 'boolean',
					),
				),
			)
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_sanitized_value_when_valid() {
		$this->assertSame( 123, rest_parse_request_arg( '123', $this->request, 'someinteger' ) );
		$this->assertFalse( rest_parse_request_arg( 'false', $this->request, 'someboolean' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_wp_error_when_invalid() {
		$result = rest_parse_request_arg( 'abc', $this->request, 'someinteger' );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_invalid_type', $result->get_error_code() );
	}
}
