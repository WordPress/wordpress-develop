<?php

/**
 * @group http
 * @covers WP_Http_Cookie
 */
class Tests_HTTP_WpHttpCookie extends WP_UnitTestCase {

	/**
	 * Tests getHeaderValue() with standard cookie data.
	 *
	 * @ticket 65817
	 * @covers WP_Http_Cookie::getHeaderValue
	 */
	public function test_get_header_value() {
		$cookie = new WP_Http_Cookie(
			array(
				'name'  => 'flavor',
				'value' => 'chocolate-chip',
			)
		);

		$this->assertSame( 'flavor=chocolate-chip', $cookie->getHeaderValue() );
	}

	/**
	 * Tests getHeaderValue() returns empty string when name or value is not set.
	 *
	 * @ticket 65817
	 * @covers WP_Http_Cookie::getHeaderValue
	 */
	public function test_get_header_value_when_name_or_value_not_set() {
		$cookie_empty = new WP_Http_Cookie( array() );
		$this->assertSame( '', $cookie_empty->getHeaderValue() );

		$cookie_no_value = new WP_Http_Cookie( array( 'name' => 'flavor' ) );
		$this->assertSame( '', $cookie_no_value->getHeaderValue() );

		$cookie_no_name = new WP_Http_Cookie( array( 'value' => 'chocolate-chip' ) );
		$this->assertSame( '', $cookie_no_name->getHeaderValue() );
	}

	/**
	 * Tests getFullHeader() with and without cookie values.
	 *
	 * @ticket 65817
	 * @covers WP_Http_Cookie::getFullHeader
	 */
	public function test_get_full_header() {
		$cookie = new WP_Http_Cookie(
			array(
				'name'  => 'flavor',
				'value' => 'chocolate-chip',
			)
		);

		$this->assertSame( 'Cookie: flavor=chocolate-chip', $cookie->getFullHeader() );

		$cookie_empty = new WP_Http_Cookie( array() );
		$this->assertSame( 'Cookie: ', $cookie_empty->getFullHeader() );
	}

	/**
	 * Tests that properties default to null when not provided.
	 *
	 * @ticket 65817
	 * @covers WP_Http_Cookie::__construct
	 */
	public function test_properties_null_when_not_provided() {
		$cookie = new WP_Http_Cookie( 'foo=bar' );

		$this->assertSame( 'foo', $cookie->name );
		$this->assertSame( 'bar', $cookie->value );
		$this->assertNull( $cookie->domain );

		$empty_cookie = new WP_Http_Cookie( array() );
		$this->assertNull( $empty_cookie->name );
		$this->assertNull( $empty_cookie->value );
		$this->assertNull( $empty_cookie->domain );
	}

	/**
	 * Tests get_attributes() returns expected cookie attributes.
	 *
	 * @ticket 65817
	 * @covers WP_Http_Cookie::get_attributes
	 */
	public function test_get_attributes() {
		$cookie = new WP_Http_Cookie(
			array(
				'name'    => 'session',
				'value'   => 'abc123xyz',
				'expires' => 1700000000,
				'path'    => '/wp-admin/',
				'domain'  => 'example.org',
			)
		);

		$expected = array(
			'expires' => 1700000000,
			'path'    => '/wp-admin/',
			'domain'  => 'example.org',
		);

		$this->assertSame( $expected, $cookie->get_attributes() );
	}
}
