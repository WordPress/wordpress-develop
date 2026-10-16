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
	 * @ticket 66305
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
	public function data_expires(): array {
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
	public function data_host_only(): array {
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

	/**
	 * Tests that a header string with neither a name nor a value is ignored.
	 *
	 * @ticket 66305
	 *
	 * @covers ::__construct
	 * @covers ::test
	 * @covers ::getHeaderValue
	 *
	 * @dataProvider data_header_without_name_or_value
	 *
	 * @param string $header Cookie header string.
	 */
	public function test_header_without_name_or_value( string $header ): void {
		$cookie = new WP_Http_Cookie( $header, 'https://example.org/' );

		$this->assertNull( $cookie->name, 'Expected the name to be null.' );
		$this->assertNull( $cookie->value, 'Expected the value to be null.' );
		$this->assertFalse( $cookie->test( 'https://example.org/' ), 'Expected the cookie not to be sent.' );
		$this->assertSame( '', $cookie->getHeaderValue(), 'Expected an empty header value.' );
	}

	/**
	 * Data provider for {@see self::test_header_without_name_or_value()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public function data_header_without_name_or_value(): array {
		return array(
			'empty'                => array( '' ),
			'whitespace'           => array( ' ; path=/' ),
			'empty name and value' => array( '=; path=/' ),
		);
	}

	/**
	 * Tests that a cookie with an empty or missing name is parsed as a cookie with an empty name.
	 *
	 * Per RFC 6265bis, a name-value pair lacking "=" is an empty name with the pair as its value.
	 *
	 * @ticket 66305
	 *
	 * @covers ::__construct
	 *
	 * @dataProvider data_cookie_with_empty_name
	 *
	 * @param string|array{ name: string, value: string } $data           Cookie data.
	 * @param string                                      $expected_value Expected cookie value.
	 */
	public function test_cookie_with_empty_name( $data, string $expected_value ): void {
		$cookie = new WP_Http_Cookie( $data, 'https://example.org/' );

		$this->assertSame( '', $cookie->name );
		$this->assertSame( $expected_value, $cookie->value );
		$this->assertTrue( $cookie->test( 'https://example.org/' ), 'Expected the cookie to be sent.' );
		$this->assertSame( '=' . $expected_value, $cookie->getHeaderValue() );
	}

	/**
	 * Data provider for {@see self::test_cookie_with_empty_name()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string|array{ name: string, value: string }, 1: string }>
	 */
	public function data_cookie_with_empty_name(): array {
		return array(
			'empty name from header'                  => array( '=abc; path=/', 'abc' ),
			'whitespace name from header'             => array( ' =abc', 'abc' ),
			'missing "=" from header'                 => array( 'novalue', 'novalue' ),
			'missing "=" with attributes from header' => array( 'novalue; path=/', 'novalue' ),
			'missing "=" with encoding from header'   => array( 'no%20value', 'no value' ),
			'empty name from array'                   => array(
				array(
					'name'  => '',
					'value' => 'abc',
				),
				'abc',
			),
			'whitespace name from array'              => array(
				array(
					'name'  => " \t ",
					'value' => 'abc',
				),
				'abc',
			),
		);
	}

	/**
	 * Tests that surrounding whitespace is trimmed from the cookie name.
	 *
	 * @ticket 66305
	 *
	 * @covers ::__construct
	 * @covers ::getHeaderValue
	 *
	 * @dataProvider data_cookie_name_is_trimmed
	 *
	 * @param string|array{ name: string, value: string } $data Cookie data.
	 */
	public function test_cookie_name_is_trimmed( $data ): void {
		$cookie = new WP_Http_Cookie( $data );

		$this->assertSame( 'flavor', $cookie->name );
		$this->assertSame( 'flavor=chocolate-chip', $cookie->getHeaderValue() );
	}

	/**
	 * Data provider for {@see self::test_cookie_name_is_trimmed()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string|array{ name: string, value: string } }>
	 */
	public function data_cookie_name_is_trimmed(): array {
		return array(
			'from header' => array( " \tflavor =chocolate-chip" ),
			'from array'  => array(
				array(
					'name'  => " \tflavor ",
					'value' => 'chocolate-chip',
				),
			),
		);
	}

	/**
	 * Tests that a cookie whose name is emptied after construction is sent with an empty name.
	 *
	 * @ticket 66305
	 *
	 * @covers ::test
	 * @covers ::getHeaderValue
	 *
	 * @dataProvider data_cookie_with_name_emptied_after_construction
	 *
	 * @param string $name Empty or whitespace-only name.
	 */
	public function test_cookie_with_name_emptied_after_construction( string $name ): void {
		$cookie       = new WP_Http_Cookie(
			array(
				'name'  => 'flavor',
				'value' => 'chocolate-chip',
			),
			'https://example.org/'
		);
		$cookie->name = $name;

		$this->assertTrue( $cookie->test( 'https://example.org/' ), 'Expected the cookie to be sent.' );
		$this->assertSame( '=chocolate-chip', $cookie->getHeaderValue() );
	}

	/**
	 * Data provider for {@see self::test_cookie_with_name_emptied_after_construction()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public function data_cookie_with_name_emptied_after_construction(): array {
		return array(
			'empty'           => array( '' ),
			'whitespace-only' => array( " \t " ),
		);
	}

	/**
	 * Tests that get_sendable_name() returns the trimmed name, or null when there is no name.
	 *
	 * @ticket 66305
	 *
	 * @covers ::get_sendable_name
	 *
	 * @dataProvider data_get_sendable_name
	 *
	 * @param string|null $name     Cookie name, set after construction.
	 * @param string|null $expected Expected sendable name.
	 */
	public function test_get_sendable_name( ?string $name, ?string $expected ): void {
		$cookie       = new WP_Http_Cookie(
			array(
				'name'  => 'flavor',
				'value' => 'chocolate-chip',
			)
		);
		$cookie->name = $name;

		$this->assertSame( $expected, $cookie->get_sendable_name() );
	}

	/**
	 * Data provider for {@see self::test_get_sendable_name()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string|null, 1: string|null }>
	 */
	public function data_get_sendable_name(): array {
		return array(
			'plain'           => array( 'flavor', 'flavor' ),
			'surrounded'      => array( " \tflavor ", 'flavor' ),
			'numeric'         => array( '0', '0' ),
			'empty'           => array( '', '' ),
			'whitespace-only' => array( " \t ", '' ),
			'null'            => array( null, null ),
		);
	}

	/**
	 * Tests that getHeaderValue() uses the trimmed name, as WP_Http::normalize_cookies() does.
	 *
	 * @ticket 66305
	 *
	 * @covers ::getHeaderValue
	 */
	public function test_get_header_value_uses_trimmed_name(): void {
		$cookie       = new WP_Http_Cookie(
			array(
				'name'  => 'flavor',
				'value' => 'chocolate-chip',
			)
		);
		$cookie->name = " \tflavor ";

		$this->assertSame( 'flavor=chocolate-chip', $cookie->getHeaderValue() );
	}

	/**
	 * Tests that a non-string name does not cause a fatal error.
	 *
	 * @ticket 66305
	 *
	 * @covers ::test
	 * @covers ::getHeaderValue
	 */
	public function test_non_string_name_is_invalid(): void {
		$cookie = new WP_Http_Cookie(
			array( // @phpstan-ignore argument.type (Intentionally passing an invalid name.)
				'name'  => array( 'flavor' ),
				'value' => 'chocolate-chip',
			),
			'https://example.org/'
		);

		$this->assertFalse( $cookie->test( 'https://example.org/' ), 'Expected the cookie not to be sent.' );
		$this->assertSame( '', $cookie->getHeaderValue(), 'Expected an empty header value.' );
	}

	/**
	 * Tests that getHeaderValue() omits a cookie with a non-scalar value, as WP_Http::normalize_cookies() does.
	 *
	 * @ticket 66305
	 *
	 * @covers ::getHeaderValue
	 */
	public function test_get_header_value_with_non_scalar_value(): void {
		$cookie = new WP_Http_Cookie(
			array(
				'name'  => 'prefs',
				'value' => array( 'a' => 1 ),
			)
		);

		$this->assertSame( '', $cookie->getHeaderValue() );
	}

	/**
	 * Tests that getHeaderValue() sends a non-scalar value that the wp_http_cookie_value filter makes scalar.
	 *
	 * @ticket 66305
	 *
	 * @covers ::getHeaderValue
	 */
	public function test_get_header_value_with_non_scalar_value_filtered_to_scalar(): void {
		$cookie = new WP_Http_Cookie(
			array(
				'name'  => 'prefs',
				'value' => array( 'a' => 1 ),
			)
		);

		add_filter(
			'wp_http_cookie_value',
			static function ( $value ) {
				return is_array( $value ) ? http_build_query( $value ) : $value;
			}
		);

		$this->assertSame( 'prefs=a=1', $cookie->getHeaderValue() );
	}

	/**
	 * Tests that getHeaderValue() sends a value that the wp_http_cookie_value filter makes a stringable object.
	 *
	 * @ticket 66305
	 *
	 * @covers ::getHeaderValue
	 */
	public function test_get_header_value_with_value_filtered_to_stringable_object(): void {
		$cookie = new WP_Http_Cookie(
			array(
				'name'  => 'flavor',
				'value' => 'chocolate-chip',
			)
		);

		add_filter(
			'wp_http_cookie_value',
			static function (): object {
				return new class() {
					public function __toString(): string {
						return 'CHOCOLATE-CHIP';
					}
				};
			}
		);

		$this->assertSame( 'flavor=CHOCOLATE-CHIP', $cookie->getHeaderValue() );
	}

	/**
	 * Tests that "name" and "value" attributes in a header string do not overwrite the cookie name and value.
	 *
	 * @ticket 66305
	 *
	 * @covers ::__construct
	 *
	 * @dataProvider data_header_attributes_do_not_overwrite_name_or_value
	 *
	 * @param string $header Cookie header string.
	 */
	public function test_header_attributes_do_not_overwrite_name_or_value( string $header ): void {
		$cookie = new WP_Http_Cookie( $header );

		$this->assertSame( 'session', $cookie->name );
		$this->assertSame( 'abc', $cookie->value );
		$this->assertSame( '/', $cookie->path, 'Expected other attributes to still be parsed.' );
	}

	/**
	 * Data provider for {@see self::test_header_attributes_do_not_overwrite_name_or_value()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public function data_header_attributes_do_not_overwrite_name_or_value(): array {
		return array(
			'name attribute'        => array( 'session=abc; Name=evil; path=/' ),
			'empty name attribute'  => array( 'session=abc; name=; path=/' ),
			'bare name attribute'   => array( 'session=abc; name; path=/' ),
			'value attribute'       => array( 'session=abc; Value=evil; path=/' ),
			'empty value attribute' => array( 'session=abc; value=; path=/' ),
		);
	}
}
