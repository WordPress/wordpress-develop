<?php

/**
 * @group http
 *
 * @coversDefaultClass WP_Http_Cookie
 */
class Tests_HTTP_WpHttpCookie extends WP_UnitTestCase {

	/**
	 * Tests getHeaderValue() with standard cookie data.
	 *
	 * @covers ::getHeaderValue
	 */
	public function test_get_header_value(): void {
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
	 * @covers ::getHeaderValue
	 */
	public function test_get_header_value_when_name_or_value_not_set(): void {
		$cookie_empty = new WP_Http_Cookie( array() );
		$this->assertSame( '', $cookie_empty->getHeaderValue() );

		$cookie_no_value = new WP_Http_Cookie( array( 'name' => 'flavor' ) );
		$this->assertSame( '', $cookie_no_value->getHeaderValue() );

		// The constructor ignores the value when the name is missing, so unset the name afterward.
		$cookie_no_name       = new WP_Http_Cookie(
			array(
				'name'  => 'flavor',
				'value' => 'chocolate-chip',
			)
		);
		$cookie_no_name->name = null;
		$this->assertSame( 'chocolate-chip', $cookie_no_name->value );
		$this->assertSame( '', $cookie_no_name->getHeaderValue() );
	}

	/**
	 * Tests getFullHeader() with and without cookie values.
	 *
	 * @covers ::getFullHeader
	 */
	public function test_get_full_header(): void {
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
	 * @covers ::__construct
	 */
	public function test_properties_null_when_not_provided(): void {
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
	 * @covers ::get_attributes
	 */
	public function test_get_attributes(): void {
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

	/**
	 * Tests that the expiration date is stored as a Unix timestamp, false if unparsable, or null if not provided.
	 *
	 * @covers ::__construct
	 *
	 * @dataProvider data_expires
	 *
	 * @param string|array{ name: string, value: string, expires?: int|string } $data     Cookie data.
	 * @param int|false|null                                                    $expected Expected expiration.
	 */
	public function test_expires( $data, $expected ): void {
		$cookie = new WP_Http_Cookie( $data );

		$this->assertSame( $expected, $cookie->expires );
	}

	/**
	 * Data provider for {@see self::test_expires()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string|array{ name: string, value: string, expires?: int|string }, 1: int|false|null }>
	 */
	public static function data_expires(): array {
		return array(
			'timestamp from array'    => array(
				array(
					'name'    => 'flavor',
					'value'   => 'chocolate-chip',
					'expires' => 1445412480,
				),
				1445412480,
			),
			'date string from array'  => array(
				array(
					'name'    => 'flavor',
					'value'   => 'chocolate-chip',
					'expires' => 'Wed, 21 Oct 2015 07:28:00 GMT',
				),
				1445412480,
			),
			'unparsable from array'   => array(
				array(
					'name'    => 'flavor',
					'value'   => 'chocolate-chip',
					'expires' => 'garbage',
				),
				false,
			),
			'missing from array'      => array(
				array(
					'name'  => 'flavor',
					'value' => 'chocolate-chip',
				),
				null,
			),
			'date string from header' => array( 'flavor=chocolate-chip; expires=Wed, 21 Oct 2015 07:28:00 GMT', 1445412480 ),
			'unparsable from header'  => array( 'flavor=chocolate-chip; expires=garbage', false ),
			'missing from header'     => array( 'flavor=chocolate-chip', null ),
		);
	}

	/**
	 * Tests that the host-only flag is stored when provided, or null otherwise.
	 *
	 * @covers ::__construct
	 *
	 * @dataProvider data_host_only
	 *
	 * @param string|array{ name: string, value: string, host_only?: bool } $data     Cookie data.
	 * @param bool|null                                                     $expected Expected host-only flag.
	 */
	public function test_host_only( $data, ?bool $expected ): void {
		$cookie = new WP_Http_Cookie( $data );

		$this->assertSame( $expected, $cookie->host_only );
	}

	/**
	 * Data provider for {@see self::test_host_only()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string|array{ name: string, value: string, host_only?: bool }, 1: bool|null }>
	 */
	public static function data_host_only(): array {
		return array(
			'true from array'     => array(
				array(
					'name'      => 'flavor',
					'value'     => 'chocolate-chip',
					'host_only' => true,
				),
				true,
			),
			'false from array'    => array(
				array(
					'name'      => 'flavor',
					'value'     => 'chocolate-chip',
					'host_only' => false,
				),
				false,
			),
			'missing from array'  => array(
				array(
					'name'  => 'flavor',
					'value' => 'chocolate-chip',
				),
				null,
			),
			'missing from header' => array( 'flavor=chocolate-chip', null ),
		);
	}
}
