<?php
/**
 * Unit tests covering WP_REST_Request functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class Tests_REST_Request extends WP_UnitTestCase {
	public $request;

	public function set_up() {
		parent::set_up();

		$this->request = new WP_REST_Request();
	}

	public function test_header() {
		$value = 'application/x-wp-example';

		$this->request->set_header( 'Content-Type', $value );

		$this->assertSame( $value, $this->request->get_header( 'Content-Type' ) );
	}

	public function test_header_missing() {
		$this->assertNull( $this->request->get_header( 'missing' ) );
		$this->assertNull( $this->request->get_header_as_array( 'missing' ) );
	}

	public function test_remove_header() {
		$this->request->add_header( 'Test-Header', 'value' );
		$this->assertSame( 'value', $this->request->get_header( 'Test-Header' ) );

		$this->request->remove_header( 'Test-Header' );
		$this->assertNull( $this->request->get_header( 'Test-Header' ) );
	}

	public function test_header_multiple() {
		$value1 = 'application/x-wp-example-1';
		$value2 = 'application/x-wp-example-2';
		$this->request->add_header( 'Accept', $value1 );
		$this->request->add_header( 'Accept', $value2 );

		$this->assertSame( $value1 . ',' . $value2, $this->request->get_header( 'Accept' ) );
		$this->assertSame( array( $value1, $value2 ), $this->request->get_header_as_array( 'Accept' ) );
	}

	public static function header_provider() {
		return array(
			array( 'Test', 'test' ),
			array( 'TEST', 'test' ),
			array( 'Test-Header', 'test_header' ),
			array( 'test-header', 'test_header' ),
			array( 'Test_Header', 'test_header' ),
			array( 'test_header', 'test_header' ),
		);
	}

	/**
	 * @dataProvider header_provider
	 * @param string $original Original header key.
	 * @param string $expected Expected canonicalized version.
	 */
	public function test_header_canonicalization( $original, $expected ) {
		$this->assertSame( $expected, $this->request->canonicalize_header_name( $original ) );
	}

	public static function content_type_provider() {
		return array(
			// Check basic parsing.
			array( 'application/x-wp-example', 'application/x-wp-example', 'application', 'x-wp-example', '' ),
			array( 'application/x-wp-example; charset=utf-8', 'application/x-wp-example', 'application', 'x-wp-example', 'charset=utf-8' ),

			// Check case insensitivity.
			array( 'APPLICATION/x-WP-Example', 'application/x-wp-example', 'application', 'x-wp-example', '' ),
		);
	}

	/**
	 * @dataProvider content_type_provider
	 *
	 * @param string $header     Header value.
	 * @param string $value      Full type value.
	 * @param string $type       Main type (application, text, etc).
	 * @param string $subtype    Subtype (json, etc).
	 * @param string $parameters Parameters (charset=utf-8, etc).
	 */
	public function test_content_type_parsing( $header, $value, $type, $subtype, $parameters ) {
		// Check we start with nothing.
		$this->assertEmpty( $this->request->get_content_type() );

		$this->request->set_header( 'Content-Type', $header );
		$parsed = $this->request->get_content_type();

		$this->assertSame( $value, $parsed['value'] );
		$this->assertSame( $type, $parsed['type'] );
		$this->assertSame( $subtype, $parsed['subtype'] );
		$this->assertSame( $parameters, $parsed['parameters'] );
	}

	protected function request_with_parameters() {
		$this->request->set_url_params(
			array(
				'source'         => 'url',
				'has_url_params' => true,
			)
		);
		$this->request->set_query_params(
			array(
				'source'           => 'query',
				'has_query_params' => true,
			)
		);
		$this->request->set_body_params(
			array(
				'source'          => 'body',
				'has_body_params' => true,
			)
		);

		$json_data = wp_json_encode(
			array(
				'source'          => 'json',
				'has_json_params' => true,
			)
		);
		$this->request->set_body( $json_data );

		$this->request->set_default_params(
			array(
				'source'             => 'defaults',
				'has_default_params' => true,
			)
		);
	}

	public function test_parameter_order() {
		$this->request_with_parameters();

		$this->request->set_method( 'GET' );

		// Check that query takes precedence.
		$this->assertSame( 'query', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works).
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_default_params' ) );

		// POST and JSON parameters shouldn't be parsed.
		$this->assertEmpty( $this->request->get_param( 'has_body_params' ) );
		$this->assertEmpty( $this->request->get_param( 'has_json_params' ) );
	}

	public function test_parameter_order_post() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );
		$this->request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		$this->request->set_attributes( array( 'accept_json' => true ) );

		// Check that POST takes precedence.
		$this->assertSame( 'body', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works).
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_body_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_default_params' ) );

		// JSON shouldn't be parsed.
		$this->assertEmpty( $this->request->get_param( 'has_json_params' ) );
	}

	public static function alternate_json_content_type_provider() {
		return array(
			array( 'application/ld+json', 'json', true ),
			array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', 'json', true ),
			array( 'application/activity+json', 'json', true ),
			array( 'application/json+oembed', 'json', true ),
			array( 'application/nojson', 'body', false ),
			array( 'application/no.json', 'body', false ),
		);
	}

	/**
	 * @ticket 49404
	 * @dataProvider alternate_json_content_type_provider
	 *
	 * @param string $content_type The content-type header.
	 * @param string $source       The source value.
	 * @param bool   $accept_json  The accept_json value.
	 */
	public function test_alternate_json_content_type( $content_type, $source, $accept_json ) {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );
		$this->request->set_header( 'Content-Type', $content_type );
		$this->request->set_attributes( array( 'accept_json' => true ) );

		// Check that JSON takes precedence.
		$this->assertSame( $source, $this->request->get_param( 'source' ) );
		$this->assertEquals( $accept_json, $this->request->get_param( 'has_json_params' ) );
	}

	public static function is_json_content_type_provider() {
		return array(
			array( 'application/ld+json', true ),
			array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', true ),
			array( 'application/activity+json', true ),
			array( 'application/json+oembed', true ),
			array( 'application/nojson', false ),
			array( 'application/no.json', false ),
		);
	}

	/**
	 * @ticket 49404
	 * @dataProvider is_json_content_type_provider
	 *
	 * @param string $content_type The content-type header.
	 * @param bool   $is_json      The is_json value.
	 */
	public function test_is_json_content_type( $content_type, $is_json ) {
		$this->request_with_parameters();

		$this->request->set_header( 'Content-Type', $content_type );

		// Check for JSON content-type.
		$this->assertSame( $is_json, $this->request->is_json_content_type() );
	}

	/**
	 * @ticket 49404
	 */
	public function test_content_type_cache() {
		$this->request_with_parameters();
		$this->assertFalse( $this->request->is_json_content_type() );

		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->assertTrue( $this->request->is_json_content_type() );

		$this->request->set_header( 'Content-Type', 'application/activity+json' );
		$this->assertTrue( $this->request->is_json_content_type() );

		$this->request->set_header( 'Content-Type', 'application/nojson' );
		$this->assertFalse( $this->request->is_json_content_type() );

		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->assertTrue( $this->request->is_json_content_type() );

		$this->request->remove_header( 'Content-Type' );
		$this->assertFalse( $this->request->is_json_content_type() );
	}

	public function test_parameter_order_json() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );
		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->request->set_attributes( array( 'accept_json' => true ) );

		// Check that JSON takes precedence.
		$this->assertSame( 'json', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works).
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_body_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_json_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_default_params' ) );
	}

	public function test_parameter_order_json_invalid() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );
		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->request->set_attributes( array( 'accept_json' => true ) );

		// Use invalid JSON data.
		$this->request->set_body( '{ this is not json }' );

		// Check that JSON is ignored.
		$this->assertSame( 'body', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works).
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_body_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_default_params' ) );

		// JSON should be ignored.
		$this->assertEmpty( $this->request->get_param( 'has_json_params' ) );
	}

	public function non_post_http_methods_with_request_body_provider() {
		return array(
			array( 'PUT' ),
			array( 'PATCH' ),
			array( 'DELETE' ),
		);
	}

	/**
	 * Tests that methods supporting request bodies have access to the
	 * request's body.  For POST this is straightforward via `$_POST`; for
	 * other methods `WP_REST_Request` needs to parse the body for us.
	 *
	 * @dataProvider non_post_http_methods_with_request_body_provider
	 */
	public function test_non_post_body_parameters( $request_method ) {
		$data = array(
			'foo'  => 'bar',
			'alot' => array(
				'of' => 'parameters',
			),
			'list' => array(
				'of',
				'cool',
				'stuff',
			),
		);
		$this->request->set_method( $request_method );
		$this->request->set_body_params( array() );
		$this->request->set_body( http_build_query( $data ) );
		foreach ( $data as $key => $expected_value ) {
			$this->assertSame( $expected_value, $this->request->get_param( $key ) );
		}
	}

	public function test_parameters_for_json_put() {
		$data = array(
			'foo'  => 'bar',
			'alot' => array(
				'of' => 'parameters',
			),
			'list' => array(
				'of',
				'cool',
				'stuff',
			),
		);

		$this->request->set_method( 'PUT' );
		$this->request->add_header( 'content-type', 'application/json' );
		$this->request->set_body( wp_json_encode( $data ) );

		foreach ( $data as $key => $expected_value ) {
			$this->assertSame( $expected_value, $this->request->get_param( $key ) );
		}
	}

	public function test_parameters_for_json_post() {
		$data = array(
			'foo'  => 'bar',
			'alot' => array(
				'of' => 'parameters',
			),
			'list' => array(
				'of',
				'cool',
				'stuff',
			),
		);

		$this->request->set_method( 'POST' );
		$this->request->add_header( 'content-type', 'application/json' );
		$this->request->set_body( wp_json_encode( $data ) );

		foreach ( $data as $key => $expected_value ) {
			$this->assertSame( $expected_value, $this->request->get_param( $key ) );
		}
	}

	public function test_parameter_merging() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );

		$expected = array(
			'source'             => 'body',
			'has_default_params' => true,
			'has_url_params'     => true,
			'has_query_params'   => true,
			'has_body_params'    => true,
		);
		$this->assertSame( $expected, $this->request->get_params() );
	}

	public function test_parameter_merging_with_numeric_keys() {
		$this->request->set_query_params(
			array(
				'1' => 'hello',
				'2' => 'goodbye',
			)
		);
		$expected = array(
			'1' => 'hello',
			'2' => 'goodbye',
		);
		$this->assertSame( $expected, $this->request->get_params() );
	}

	public function test_sanitize_params() {
		$this->request->set_url_params(
			array(
				'someinteger' => '123',
				'somestring'  => 'hello',
			)
		);

		$this->request->set_attributes(
			array(
				'args' => array(
					'someinteger' => array(
						'sanitize_callback' => 'absint',
					),
					'somestring'  => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		$this->request->sanitize_params();

		$this->assertSame( 123, $this->request->get_param( 'someinteger' ) );
		$this->assertSame( 0, $this->request->get_param( 'somestring' ) );
	}

	public function test_sanitize_params_error() {
		$this->request->set_url_params(
			array(
				'successparam' => '123',
				'failparam'    => '123',
			)
		);
		$this->request->set_attributes(
			array(
				'args' => array(
					'successparam' => array(
						'sanitize_callback' => 'absint',
					),
					'failparam'    => array(
						'sanitize_callback' => array( $this, '_return_wp_error_on_validate_callback' ),
					),
				),
			)
		);

		$valid = $this->request->sanitize_params();
		$this->assertWPError( $valid );
		$this->assertSame( 'rest_invalid_param', $valid->get_error_code() );
	}

	/**
	 * @ticket 46191
	 */
	public function test_sanitize_params_error_multiple_messages() {
		$this->request->set_url_params(
			array(
				'failparam' => '123',
			)
		);
		$this->request->set_attributes(
			array(
				'args' => array(
					'failparam' => array(
						'sanitize_callback' => static function () {
							$error = new WP_Error( 'invalid', 'Invalid.' );
							$error->add( 'invalid', 'Super Invalid.' );
							$error->add( 'broken', 'Broken.' );

							return $error;
						},
					),
				),
			)
		);

		$valid = $this->request->sanitize_params();
		$this->assertWPError( $valid );
		$data = $valid->get_error_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'params', $data );
		$this->assertArrayHasKey( 'failparam', $data['params'] );
		$this->assertSame( 'Invalid. Super Invalid. Broken.', $data['params']['failparam'] );
	}

	/**
	 * @ticket 46191
	 */
	public function test_sanitize_params_provides_detailed_errors() {
		$this->request->set_url_params(
			array(
				'failparam' => '123',
			)
		);
		$this->request->set_attributes(
			array(
				'args' => array(
					'failparam' => array(
						'sanitize_callback' => static function () {
							return new WP_Error( 'invalid', 'Invalid.', 'mydata' );
						},
					),
				),
			)
		);

		$valid = $this->request->sanitize_params();
		$this->assertWPError( $valid );

		$data = $valid->get_error_data();
		$this->assertArrayHasKey( 'details', $data );
		$this->assertArrayHasKey( 'failparam', $data['details'] );
		$this->assertSame(
			array(
				'code'    => 'invalid',
				'message' => 'Invalid.',
				'data'    => 'mydata',
			),
			$data['details']['failparam']
		);
	}

	public function test_sanitize_params_with_null_callback() {
		$this->request->set_url_params(
			array(
				'some_email' => '',
			)
		);

		$this->request->set_attributes(
			array(
				'args' => array(
					'some_email' => array(
						'type'              => 'string',
						'format'            => 'email',
						'sanitize_callback' => null,
					),
				),
			)
		);

		$this->assertTrue( $this->request->sanitize_params() );
	}

	public function test_sanitize_params_with_false_callback() {
		$this->request->set_url_params(
			array(
				'some_uri' => 1.23422,
			)
		);

		$this->request->set_attributes(
			array(
				'args' => array(
					'some_uri' => array(
						'type'              => 'string',
						'format'            => 'uri',
						'sanitize_callback' => false,
					),
				),
			)
		);

		$this->assertTrue( $this->request->sanitize_params() );
	}

	public function test_has_valid_params_required_flag() {
		$this->request->set_attributes(
			array(
				'args' => array(
					'someinteger' => array(
						'required' => true,
					),
				),
			)
		);

		$valid = $this->request->has_valid_params();

		$this->assertWPError( $valid );
		$this->assertSame( 'rest_missing_callback_param', $valid->get_error_code() );
	}

	public function test_has_valid_params_required_flag_multiple() {
		$this->request->set_attributes(
			array(
				'args' => array(
					'someinteger'      => array(
						'required' => true,
					),
					'someotherinteger' => array(
						'required' => true,
					),
				),
			)
		);

		$valid = $this->request->has_valid_params();

		$this->assertWPError( $valid );
		$this->assertSame( 'rest_missing_callback_param', $valid->get_error_code() );

		$data = $valid->get_error_data( 'rest_missing_callback_param' );

		$this->assertContains( 'someinteger', $data['params'] );
		$this->assertContains( 'someotherinteger', $data['params'] );
	}

	public function test_has_valid_params_validate_callback() {
		$this->request->set_url_params(
			array(
				'someinteger' => '123',
			)
		);

		$this->request->set_attributes(
			array(
				'args' => array(
					'someinteger' => array(
						'validate_callback' => '__return_false',
					),
				),
			)
		);

		$valid = $this->request->has_valid_params();

		$this->assertWPError( $valid );
		$this->assertSame( 'rest_invalid_param', $valid->get_error_code() );
	}

	public function test_has_valid_params_json_error() {
		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->request->set_body( '{"invalid": JSON}' );

		$valid = $this->request->has_valid_params();
		$this->assertWPError( $valid );
		$this->assertSame( 'rest_invalid_json', $valid->get_error_code() );
		$data = $valid->get_error_data();
		$this->assertSame( JSON_ERROR_SYNTAX, $data['json_error_code'] );
	}


	public function test_has_valid_params_empty_json_no_error() {
		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->request->set_body( '' );

		$valid = $this->request->has_valid_params();
		$this->assertNotWPError( $valid );
	}

	public function test_has_multiple_invalid_params_validate_callback() {
		$this->request->set_url_params(
			array(
				'someinteger'      => '123',
				'someotherinteger' => '123',
			)
		);

		$this->request->set_attributes(
			array(
				'args' => array(
					'someinteger'      => array(
						'validate_callback' => '__return_false',
					),
					'someotherinteger' => array(
						'validate_callback' => '__return_false',
					),
				),
			)
		);

		$valid = $this->request->has_valid_params();

		$this->assertWPError( $valid );
		$this->assertSame( 'rest_invalid_param', $valid->get_error_code() );

		$data = $valid->get_error_data( 'rest_invalid_param' );

		$this->assertArrayHasKey( 'someinteger', $data['params'] );
		$this->assertArrayHasKey( 'someotherinteger', $data['params'] );
	}

	public function test_invalid_params_error_response_format() {
		$this->request->set_url_params(
			array(
				'someinteger'     => '123',
				'someotherparams' => '123',
			)
		);

		$this->request->set_attributes(
			array(
				'args' => array(
					'someinteger'     => array(
						'validate_callback' => '__return_false',
					),
					'someotherparams' => array(
						'validate_callback' => array( $this, '_return_wp_error_on_validate_callback' ),
					),
				),
			)
		);

		$valid = $this->request->has_valid_params();
		$this->assertWPError( $valid );
		$error_data = $valid->get_error_data();

		$this->assertSame( array( 'someinteger', 'someotherparams' ), array_keys( $error_data['params'] ) );
		$this->assertSame( 'This is not valid!', $error_data['params']['someotherparams'] );
	}


	/**
	 * @ticket 46191
	 */
	public function test_invalid_params_error_multiple_messages() {
		$this->request->set_url_params(
			array(
				'failparam' => '123',
			)
		);
		$this->request->set_attributes(
			array(
				'args' => array(
					'failparam' => array(
						'validate_callback' => static function () {
							$error = new WP_Error( 'invalid', 'Invalid.' );
							$error->add( 'invalid', 'Super Invalid.' );
							$error->add( 'broken', 'Broken.' );

							return $error;
						},
					),
				),
			)
		);

		$valid = $this->request->has_valid_params();
		$this->assertWPError( $valid );
		$data = $valid->get_error_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'params', $data );
		$this->assertArrayHasKey( 'failparam', $data['params'] );
		$this->assertSame( 'Invalid. Super Invalid. Broken.', $data['params']['failparam'] );
	}

	/**
	 * @ticket 46191
	 */
	public function test_invalid_params_provides_detailed_errors() {
		$this->request->set_url_params(
			array(
				'failparam' => '123',
			)
		);
		$this->request->set_attributes(
			array(
				'args' => array(
					'failparam' => array(
						'validate_callback' => static function () {
							return new WP_Error( 'invalid', 'Invalid.', 'mydata' );
						},
					),
				),
			)
		);

		$valid = $this->request->has_valid_params();
		$this->assertWPError( $valid );

		$data = $valid->get_error_data();
		$this->assertArrayHasKey( 'details', $data );
		$this->assertArrayHasKey( 'failparam', $data['details'] );
		$this->assertSame(
			array(
				'code'    => 'invalid',
				'message' => 'Invalid.',
				'data'    => 'mydata',
			),
			$data['details']['failparam']
		);
	}

	public function _return_wp_error_on_validate_callback() {
		return new WP_Error( 'some-error', 'This is not valid!' );
	}

	public function data_from_url() {
		return array(
			array(
				'permalink_structure' => '/%post_name%/',
				'original_url'        => 'http://' . WP_TESTS_DOMAIN . '/wp-json/wp/v2/posts/1?foo=bar',
			),
			array(
				'permalink_structure' => '',
				'original_url'        => 'http://' . WP_TESTS_DOMAIN . '/index.php?rest_route=%2Fwp%2Fv2%2Fposts%2F1&foo=bar',
			),
		);
	}

	/**
	 * @dataProvider data_from_url
	 */
	public function test_from_url( $permalink_structure, $original_url ) {
		update_option( 'permalink_structure', $permalink_structure );
		$url = add_query_arg( 'foo', 'bar', rest_url( '/wp/v2/posts/1' ) );
		$this->assertSame( $original_url, $url );
		$request = WP_REST_Request::from_url( $url );
		$this->assertInstanceOf( 'WP_REST_Request', $request );
		$this->assertSame( '/wp/v2/posts/1', $request->get_route() );
		$this->assertSameSets(
			array(
				'foo' => 'bar',
			),
			$request->get_query_params()
		);
	}

	/**
	 * @dataProvider data_from_url
	 */
	public function test_from_url_invalid( $permalink_structure ) {
		update_option( 'permalink_structure', $permalink_structure );
		$using_site = site_url( '/wp/v2/posts/1' );
		$request    = WP_REST_Request::from_url( $using_site );
		$this->assertFalse( $request );

		$using_home = home_url( '/wp/v2/posts/1' );
		$request    = WP_REST_Request::from_url( $using_home );
		$this->assertFalse( $request );
	}

	public function test_set_param() {
		$request = new WP_REST_Request();
		$request->set_param( 'param', 'value' );
		$this->assertSame( 'value', $request->get_param( 'param' ) );
	}

	public function test_set_param_follows_parameter_order() {
		$request = new WP_REST_Request();
		$request->add_header( 'content-type', 'application/json' );
		$request->set_method( 'POST' );
		$request->set_body(
			wp_json_encode(
				array(
					'param' => 'value',
				)
			)
		);
		$this->assertSame( 'value', $request->get_param( 'param' ) );
		$this->assertSame(
			array( 'param' => 'value' ),
			$request->get_json_params()
		);

		$request->set_param( 'param', 'new_value' );
		$this->assertSame( 'new_value', $request->get_param( 'param' ) );
		$this->assertSame(
			array( 'param' => 'new_value' ),
			$request->get_json_params()
		);
	}

	/**
	 * @ticket 40838
	 */
	public function test_set_param_updates_param_in_json_and_query() {
		$request = new WP_REST_Request();
		$request->add_header( 'content-type', 'application/json' );
		$request->set_method( 'POST' );
		$request->set_body(
			wp_json_encode(
				array(
					'param' => 'value_body',
				)
			)
		);
		$request->set_query_params(
			array(
				'param' => 'value_query',
			)
		);
		$request->set_param( 'param', 'new_value' );

		$this->assertSame( 'new_value', $request->get_param( 'param' ) );
		$this->assertSame( array(), $request->get_body_params() );
		$this->assertSame( array( 'param' => 'new_value' ), $request->get_json_params() );
		$this->assertSame( array( 'param' => 'new_value' ), $request->get_query_params() );
	}

	/**
	 * @ticket 40838
	 */
	public function test_set_param_updates_param_if_already_exists_in_query() {
		$request = new WP_REST_Request();
		$request->add_header( 'content-type', 'application/json' );
		$request->set_method( 'POST' );
		$request->set_body(
			wp_json_encode(
				array(
					'param_body' => 'value_body',
				)
			)
		);
		$original_defaults = array(
			'param_query' => 'default_query_value',
			'param_body'  => 'default_body_value',
		);
		$request->set_default_params( $original_defaults );
		$request->set_query_params(
			array(
				'param_query' => 'value_query',
			)
		);
		$request->set_param( 'param_query', 'new_value' );

		$this->assertSame( 'new_value', $request->get_param( 'param_query' ) );
		$this->assertSame( array(), $request->get_body_params() );
		$this->assertSame( array( 'param_body' => 'value_body' ), $request->get_json_params() );
		$this->assertSame( array( 'param_query' => 'new_value' ), $request->get_query_params() );
		// Verify the default wasn't overwritten.
		$this->assertSame( $original_defaults, $request->get_default_params() );
	}

	/**
	 * @ticket 40838
	 */
	public function test_set_param_to_null_updates_param_in_json_and_query() {
		$request = new WP_REST_Request();
		$request->add_header( 'content-type', 'application/json' );
		$request->set_method( 'POST' );
		$request->set_body(
			wp_json_encode(
				array(
					'param' => 'value_body',
				)
			)
		);
		$request->set_query_params(
			array(
				'param' => 'value_query',
			)
		);
		$request->set_param( 'param', null );

		$this->assertNull( $request->get_param( 'param' ) );
		$this->assertSame( array(), $request->get_body_params() );
		$this->assertSame( array( 'param' => null ), $request->get_json_params() );
		$this->assertSame( array( 'param' => null ), $request->get_query_params() );
	}

	/**
	 * @ticket 40838
	 */
	public function test_set_param_from_null_updates_param_in_json_and_query_with_null() {
		$request = new WP_REST_Request();
		$request->add_header( 'content-type', 'application/json' );
		$request->set_method( 'POST' );
		$request->set_body(
			wp_json_encode(
				array(
					'param' => null,
				)
			)
		);
		$request->set_query_params(
			array(
				'param' => null,
			)
		);
		$request->set_param( 'param', 'new_value' );

		$this->assertSame( 'new_value', $request->get_param( 'param' ) );
		$this->assertSame( array(), $request->get_body_params() );
		$this->assertSame( array( 'param' => 'new_value' ), $request->get_json_params() );
		$this->assertSame( array( 'param' => 'new_value' ), $request->get_query_params() );
	}

	/**
	 * @ticket 50786
	 */
	public function test_set_param_with_invalid_json() {
		$request = new WP_REST_Request();
		$request->add_header( 'content-type', 'application/json' );
		$request->set_method( 'POST' );
		$request->set_body( '' );
		$request->set_param( 'param', 'value' );

		$this->assertTrue( $request->has_param( 'param' ) );
		$this->assertSame( 'value', $request->get_param( 'param' ) );
	}

	/**
	 * @ticket 51255
	 */
	public function test_route_level_validate_callback() {
		$request = new WP_REST_Request();
		$request->set_query_params( array( 'test' => 'value' ) );

		$error    = new WP_Error( 'error_code', __( 'Error Message' ), array( 'status' => 400 ) );
		$callback = $this->createPartialMock( 'stdClass', array( '__invoke' ) );
		$callback->expects( self::once() )->method( '__invoke' )->with( self::identicalTo( $request ) )->willReturn( $error );
		$request->set_attributes(
			array(
				'args'              => array(
					'test' => array(
						'validate_callback' => '__return_true',
					),
				),
				'validate_callback' => $callback,
			)
		);

		$this->assertSame( $error, $request->has_valid_params() );
	}

	/**
	 * @ticket 51255
	 */
	public function test_route_level_validate_callback_no_parameter_callbacks() {
		$request = new WP_REST_Request();
		$request->set_query_params( array( 'test' => 'value' ) );

		$error    = new WP_Error( 'error_code', __( 'Error Message' ), array( 'status' => 400 ) );
		$callback = $this->createPartialMock( 'stdClass', array( '__invoke' ) );
		$callback->expects( self::once() )->method( '__invoke' )->with( self::identicalTo( $request ) )->willReturn( $error );
		$request->set_attributes(
			array(
				'validate_callback' => $callback,
			)
		);

		$this->assertSame( $error, $request->has_valid_params() );
	}

	/**
	 * @ticket 51255
	 */
	public function test_route_level_validate_callback_is_not_executed_if_parameter_validation_fails() {
		$request = new WP_REST_Request();
		$request->set_query_params( array( 'test' => 'value' ) );

		$callback = $this->createPartialMock( 'stdClass', array( '__invoke' ) );
		$callback->expects( self::never() )->method( '__invoke' );
		$request->set_attributes(
			array(
				'validate_callback' => $callback,
				'args'              => array(
					'test' => array(
						'validate_callback' => '__return_false',
					),
				),
			)
		);

		$valid = $request->has_valid_params();
		$this->assertWPError( $valid );
		$this->assertSame( 'rest_invalid_param', $valid->get_error_code() );
	}
}
