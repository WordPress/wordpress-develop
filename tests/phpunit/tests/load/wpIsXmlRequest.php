<?php

/**
 * Tests for wp_is_xml_request().
 *
 * @group load
 *
 * @covers ::wp_is_xml_request
 */
class Tests_Load_WpIsXmlRequest extends WP_UnitTestCase {

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

		$this->original_http_accept  = isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : null;
		$this->original_content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? $_SERVER['CONTENT_TYPE'] : null;
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
	 * Tests that non-XML Accept and Content-Type headers are not XML requests.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_xml_headers() {
		unset( $_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE'] );

		$this->assertFalse( wp_is_xml_request() );

		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$this->assertFalse( wp_is_xml_request() );

		unset( $_SERVER['HTTP_ACCEPT'] );
		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$this->assertFalse( wp_is_xml_request() );
	}

	/**
	 * Tests XML detection via the Accept header.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_xml_accept_headers
	 *
	 * @param string $accept Accept header value.
	 */
	public function test_returns_true_for_xml_accept_header( $accept ) {
		unset( $_SERVER['CONTENT_TYPE'] );
		$_SERVER['HTTP_ACCEPT'] = $accept;

		$this->assertTrue( wp_is_xml_request() );
	}

	/**
	 * Data provider for XML Accept headers.
	 *
	 * @return array[]
	 */
	public static function data_xml_accept_headers() {
		return array(
			'text/xml'                 => array( 'text/xml' ),
			'application/rss+xml'      => array( 'application/rss+xml' ),
			'application/atom+xml'     => array( 'application/atom+xml' ),
			'application/rdf+xml'      => array( 'application/rdf+xml' ),
			'text/xml+oembed'          => array( 'text/xml+oembed' ),
			'application/xml+oembed'   => array( 'application/xml+oembed' ),
			'accept among other types' => array( 'text/html, application/rss+xml, */*' ),
		);
	}

	/**
	 * Tests XML detection via the Content-Type header.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_xml_content_types
	 *
	 * @param string $content_type Content-Type header value.
	 */
	public function test_returns_true_for_xml_content_type( $content_type ) {
		unset( $_SERVER['HTTP_ACCEPT'] );
		$_SERVER['CONTENT_TYPE'] = $content_type;

		$this->assertTrue( wp_is_xml_request() );
	}

	/**
	 * Data provider for XML Content-Type headers.
	 *
	 * @return array[]
	 */
	public static function data_xml_content_types() {
		return array(
			'text/xml'               => array( 'text/xml' ),
			'application/rss+xml'    => array( 'application/rss+xml' ),
			'application/atom+xml'   => array( 'application/atom+xml' ),
			'application/rdf+xml'    => array( 'application/rdf+xml' ),
			'text/xml+oembed'        => array( 'text/xml+oembed' ),
			'application/xml+oembed' => array( 'application/xml+oembed' ),
		);
	}
}
