<?php

/**
 * Tests for wp_is_json_request().
 *
 * @group load
 *
 * @covers ::wp_is_json_request
 */
class Tests_Load_WpIsJsonRequest extends WP_UnitTestCase {

	/**
	 * Original HTTP_ACCEPT value.
	 *
	 * @var string|null
	 */
	private $original_http_accept;

	/**
	 * Original CONTENT_TYPE value.
	 *
	 * @var string|null
	 */
	private $original_content_type;

	public function set_up() {
		parent::set_up();

		$this->original_http_accept  = $_SERVER['HTTP_ACCEPT'] ?? null;
		$this->original_content_type = $_SERVER['CONTENT_TYPE'] ?? null;
	}

	public function tear_down() {
		if ( null === $this->original_http_accept ) {
			unset( $_SERVER['HTTP_ACCEPT'] );
		} else {
			$_SERVER['HTTP_ACCEPT'] = $this->original_http_accept;
		}

		if ( null === $this->original_content_type ) {
			unset( $_SERVER['CONTENT_TYPE'] );
		} else {
			$_SERVER['CONTENT_TYPE'] = $this->original_content_type;
		}

		parent::tear_down();
	}

	/**
	 * Tests that non-JSON Accept and Content-Type headers are not JSON requests.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_json_headers() {
		unset( $_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE'] );

		$this->assertFalse( wp_is_json_request() );

		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$this->assertFalse( wp_is_json_request() );

		unset( $_SERVER['HTTP_ACCEPT'] );
		$_SERVER['CONTENT_TYPE'] = 'text/plain';
		$this->assertFalse( wp_is_json_request() );
	}

	/**
	 * Tests JSON detection via the Accept header.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_json_accept_headers
	 *
	 * @param string $accept Accept header value.
	 */
	public function test_returns_true_for_json_accept_header( $accept ) {
		unset( $_SERVER['CONTENT_TYPE'] );
		$_SERVER['HTTP_ACCEPT'] = $accept;

		$this->assertTrue( wp_is_json_request() );
	}

	/**
	 * Data provider for JSON Accept headers.
	 *
	 * @return array[]
	 */
	public static function data_json_accept_headers() {
		return array(
			'application/json'         => array( 'application/json' ),
			'vendor json'              => array( 'application/vnd.api+json' ),
			'accept among other types' => array( 'text/html, application/json, */*' ),
		);
	}

	/**
	 * Tests JSON detection via the Content-Type header.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_json_content_types
	 *
	 * @param string $content_type Content-Type header value.
	 */
	public function test_returns_true_for_json_content_type( $content_type ) {
		unset( $_SERVER['HTTP_ACCEPT'] );
		$_SERVER['CONTENT_TYPE'] = $content_type;

		$this->assertTrue( wp_is_json_request() );
	}

	/**
	 * Data provider for JSON Content-Type headers.
	 *
	 * @return array[]
	 */
	public static function data_json_content_types() {
		return array(
			'application/json' => array( 'application/json' ),
			'vendor json'      => array( 'application/vnd.api+json' ),
		);
	}
}
