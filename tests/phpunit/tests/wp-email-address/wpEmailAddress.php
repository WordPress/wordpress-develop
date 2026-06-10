<?php
/**
 * Unit tests covering WP_Email_Address functionality.
 *
 * @package WordPress
 *
 * @since 7.0.0
 * @group email
 *
 * @coversDefaultClass WP_Email_Address
 */
class Tests_WpEmailAddress extends WP_UnitTestCase {

	/**
	 * Tests that from_string() returns a WP_Email_Address instance.
	 *
	 * @since 7.0.0
	 *
	 * @dataProvider data_from_string
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_from_string_returns_instance( $address ) {
		$result = WP_Email_Address::from_string( $address, false );
		$this->assertInstanceOf( WP_Email_Address::class, $result );
	}

	/**
	 * Tests that get_address() returns a string that can be passed back to from_string().
	 *
	 * @since 7.0.0
	 *
	 * @dataProvider data_from_string
	 * @covers WP_Email_Address::get_address
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_get_address_is_roundtrippable( $address ) {
		$instance  = WP_Email_Address::from_string( $address, false );
		$roundtrip = WP_Email_Address::from_string( $instance->get_address(), false );
		$this->assertInstanceOf( WP_Email_Address::class, $roundtrip );
		$this->assertSame( $instance->get_address(), $roundtrip->get_address() );
	}

	/**
	 * Tests that get_localpart() and get_domain() combine to form the full address.
	 *
	 * @since 7.0.0
	 *
	 * @dataProvider data_from_string
	 * @covers WP_Email_Address::get_localpart
	 * @covers WP_Email_Address::get_unicode_domain
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_localpart_and_domain_compose_address( $address ) {
		$instance = WP_Email_Address::from_string( $address, false );
		$this->assertSame(
			$instance->get_localpart() . '@' . $instance->get_unicode_domain(),
			$instance->get_address()
		);
	}

	/**
	 * Tests that from_string() accepts valid Unicode local parts when $unicode is true.
	 *
	 * @since 7.0.0
	 *
	 * @dataProvider data_from_string_unicode
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_from_string_unicode_returns_instance( $address ) {
		$this->assertInstanceOf( WP_Email_Address::class, WP_Email_Address::from_string( $address, true ) );
	}

	/**
	 * Data provider for valid addresses accepted only in Unicode mode.
	 *
	 * @return array[]
	 */
	public function data_from_string_unicode() {
		return array(
			'unicode letter in local part'        => array(
				'address' => 'ıstanbul@example.com',
			),
			'CJK characters in local part'        => array(
				'address' => '用户@example.com',
			),
			'letter with combining mark in local' => array(
				'address' => "a\xCC\x81@example.com",
			),
			'latin unicode domain'                => array(
				'address' => 'info@grå.org',
			),
			'han unicode domain'                  => array(
				'address' => '阿Q@慕田峪长城.网址',
			),
		);
	}

	/**
	 * Tests that an is_email filter returning true rescues a local_invalid_chars failure.
	 *
	 * @since 7.0.0
	 *
	 * @covers WP_Email_Address::from_string
	 */
	public function test_local_invalid_chars_filter_can_rescue() {
		$filter = static function ( $value, $email, $context ) {
			return 'local_invalid_chars' === $context ? true : $value;
		};
		add_filter( 'is_email', $filter, 10, 3 );
		// Quoted local part is valid per RFC 5321 but rejected by the WHATWG charset. WordPress agrees with the browsers.
		$result = WP_Email_Address::from_string( '"quoted"@example.com', false );
		remove_filter( 'is_email', $filter, 10 );
		$this->assertInstanceOf( WP_Email_Address::class, $result );
	}

	/**
	 * Tests that an is_email filter returning true rescues a domain_no_periods failure.
	 *
	 * @since 7.0.0
	 *
	 * @covers WP_Email_Address::from_string
	 */
	public function test_domain_no_periods_filter_can_rescue() {
		$filter = static function ( $value, $email, $context ) {
			return 'domain_no_periods' === $context ? true : $value;
		};
		add_filter( 'is_email', $filter, 10, 3 );
		// Single-label domain is used for intranet mail servers.
		$result = WP_Email_Address::from_string( 'user@mailserver', false );
		remove_filter( 'is_email', $filter, 10 );
		$this->assertInstanceOf( WP_Email_Address::class, $result );
	}

	/**
	 * Tests that rescuing local_invalid_chars does not bypass later checks.
	 *
	 * @since 7.0.0
	 *
	 * @covers WP_Email_Address::from_string
	 */
	public function test_local_invalid_chars_rescue_does_not_bypass_domain_check() {
		$filter = static function ( $value, $email, $context ) {
			return 'local_invalid_chars' === $context ? true : $value;
		};
		add_filter( 'is_email', $filter, 10, 3 );
		// Local part rescued, but domain has no dot — should still be rejected.
		$result = WP_Email_Address::from_string( '"quoted"@nodots', false );
		remove_filter( 'is_email', $filter, 10 );
		$this->assertFalse( $result );
	}

	/**
	 * Tests that from_string() returns false for invalid addresses.
	 *
	 * @since 7.0.0
	 *
	 * @dataProvider data_invalid_addresses
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The invalid email address string.
	 */
	public function test_from_string_rejects_invalid( $address ) {
		$this->assertFalse( WP_Email_Address::from_string( $address, false ) );
	}

	/**
	 * Data provider for invalid addresses.
	 *
	 * @return array[]
	 */
	public function data_invalid_addresses() {
		return array(
			'quoted local part with iframe' => array(
				'address' => '"<iframe src=http://example.com>"@example.com',
			),
			'null byte'                     => array(
				'address' => "user\x00name@example.com",
			),
			'very invalid UTF8'             => array(
				'address' => "\x80\x20ouch@example.com",
			),
			'overlong encoding of space'    => array(
				'address' => "us\xC0\xA0er@example.com",
			),
			// Domain without a dot is not a routable internet domain.
			'domain without a dot'          => array(
				'address' => 'com@com',
			),
		);
	}

	/**
	 * Tests that from_string() returns false for invalid addresses when Unicode is enabled.
	 *
	 * @since 7.0.0
	 *
	 * @dataProvider data_invalid_unicode_addresses
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The invalid email address string.
	 */
	public function test_from_string_rejects_invalid_unicode( $address ) {
		$this->assertFalse( WP_Email_Address::from_string( $address, true ) );
	}

	/**
	 * Data provider for addresses that are invalid specifically in Unicode mode.
	 *
	 * @return array[]
	 */
	public function data_invalid_unicode_addresses() {
		return array(
			'reserved ACE prefix in domain'       => array(
				'address' => 'user@ab--reserved.com',
			),
			'combining mark as sole domain label' => array(
				'address' => "user@\xCC\x81.example.com",
			),
			'combining mark as sole local part'   => array(
				'address' => "\xCC\x81@example.com",
			),
		);
	}

	/**
	 * Data provider for several tests.
	 *
	 * @return array[]
	 */
	public function data_from_string() {
		return array(
			'simple address'              => array(
				'address' => 'example@example.com',
			),
			'dot in local part'           => array(
				'address' => 'user.name@example.com',
			),
			'plus sign in local part'     => array(
				'address' => 'user+tag@example.com',
			),
			'underscore in local part'    => array(
				'address' => 'user_name@example.org',
			),
			'hyphen in local part'        => array(
				'address' => 'user-name@example.net',
			),
			'apostrophe in local part'    => array(
				'address' => "mail'@example.com",
			),
			'digits in local part'        => array(
				'address' => 'user123@example.com',
			),
			'uppercase letters'           => array(
				'address' => 'USER@EXAMPLE.COM',
			),
			'subdomain'                   => array(
				'address' => 'user@mail.example.com',
			),
			'multiple subdomains'         => array(
				'address' => 'user@a.b.c.example.com',
			),
			'hyphen in domain label'      => array(
				'address' => 'user@my-domain.com',
			),
			'digits in domain'            => array(
				'address' => 'user@123.example.com',
			),
			'short but valid'             => array(
				'address' => 'a@l.is',
			),
			'special chars in local part' => array(
				'address' => 'a.!#$%*+/=?^_{|}~-@example.com',
			),
			'local part is all digits'    => array(
				'address' => '1234567890@example.com',
			),
			'long local part'             => array(
				'address' => 'abcdefghijklmnopqrstuvwxyz0123456789@example.com',
			),
			'long domain'                 => array(
				'address' => 'user@abcdefghijklmnopqrstuvwxyz0123456789.example.com',
			),
			'country-code TLD'            => array(
				'address' => 'user@example.co.uk',
			),
			'long TLD'                    => array(
				'address' => 'user@example.engineering',
			),
			// xn-- labels: grå.org and 慕田峪长城.网址 (https://慕田峪长城.网址).
			'latin punycode domain'       => array(
				'address' => 'user@xn--gr-zia.org',
			),
			'han punycode domain'         => array(
				'address' => 'ahq@xn--uist2j67d64zv30b.xn--ses554g',
			),
		);
	}
}
