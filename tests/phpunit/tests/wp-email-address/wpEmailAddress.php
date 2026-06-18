<?php
/**
 * Unit tests covering WP_Email_Address functionality.
 *
 * @package WordPress
 *
 * @since 7.1.0
 * @group email
 *
 * @coversDefaultClass WP_Email_Address
 */
class Tests_WpEmailAddress extends WP_UnitTestCase {

	/**
	 * Tests that from_string() returns a WP_Email_Address instance.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_from_string
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_from_string_returns_instance( $address ) {
		$result = WP_Email_Address::from_string( $address );
		$this->assertInstanceOf( WP_Email_Address::class, $result );
	}

	/**
	 * Tests that get_..._address() methods return a string that can be passed back to from_string().
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_from_string
	 * @covers WP_Email_Address::get_unicode_address
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_get_address_is_roundtrippable( $address ) {
		$instance = WP_Email_Address::from_string( $address );

		$round_trip = WP_Email_Address::from_string( $instance->get_ascii_address() );
		$this->assertInstanceOf( WP_Email_Address::class, $round_trip );
		$this->assertSame( $instance->get_ascii_address(), $round_trip->get_ascii_address() );

		$round_trip = WP_Email_Address::from_string( $instance->get_unicode_address() );
		$this->assertInstanceOf( WP_Email_Address::class, $round_trip );
		$this->assertSame( $instance->get_unicode_address(), $round_trip->get_unicode_address() );
	}

	/**
	 * Tests that get_localpart() and get_..._domain() methods combine to form the full address.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_from_string
	 * @covers WP_Email_Address::get_localpart
	 * @covers WP_Email_Address::get_ascii_domain
	 * @covers WP_Email_Address::get_unicode_domain
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_localpart_and_domain_compose_address( $address ) {
		$instance = WP_Email_Address::from_string( $address );

		$this->assertSame(
			$instance->get_localpart() . '@' . $instance->get_ascii_domain(),
			$instance->get_ascii_address()
		);

		$this->assertSame(
			$instance->get_localpart() . '@' . $instance->get_unicode_domain(),
			$instance->get_unicode_address()
		);
	}

	/**
	 * Tests that from_string() accepts valid Unicode local parts when accepting Unicode characters.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_from_string_unicode
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The email address to parse.
	 */
	public function test_from_string_unicode_returns_instance( $address ) {
		$this->assertInstanceOf( WP_Email_Address::class, WP_Email_Address::from_string( $address, 'unicode' ) );
	}

	/**
	 * Data provider for valid addresses accepted only in Unicode mode.
	 *
	 * @return array[]
	 */
	public function data_from_string_unicode() {
		return array(
			'unicode letter in local part'        => array( 'ıstanbul@example.com' ),
			'CJK characters in local part'        => array( '用户@example.com' ),
			'letter with combining mark in local' => array( "a\xCC\x81@example.com" ),
			'latin unicode domain'                => array( 'info@grå.org' ),
			'han unicode domain'                  => array( '阿Q@慕田峪长城.网址' ),
		);
	}

	/**
	 * Tests that an is_email filter returning true rescues a local_invalid_chars failure.
	 *
	 * @ticket 31992
	 *
	 * @covers WP_Email_Address::from_string
	 */
	public function test_local_invalid_chars_filter_can_rescue() {
		$filter = static function ( $value, $email, $context ) {
			return 'local_invalid_chars' === $context ? true : $value;
		};
		add_filter( 'is_email', $filter, 10, 3 );
		// Quoted local part is valid per RFC 5321 but rejected by the WHATWG charset. WordPress agrees with the browsers.
		$result = WP_Email_Address::from_string( '"quoted"@example.com', 'ascii' );
		remove_filter( 'is_email', $filter, 10 );
		$this->assertInstanceOf( WP_Email_Address::class, $result );
	}

	/**
	 * Tests that an is_email filter returning true rescues a domain_no_periods failure.
	 *
	 * @ticket 31992
	 *
	 * @covers WP_Email_Address::from_string
	 */
	public function test_domain_no_periods_filter_can_rescue() {
		$filter = static function ( $value, $email, $context ) {
			return 'domain_no_periods' === $context ? true : $value;
		};
		add_filter( 'is_email', $filter, 10, 3 );
		// Single-label domain is used for intranet mail servers.
		$result = WP_Email_Address::from_string( 'user@mailserver', 'ascii' );
		remove_filter( 'is_email', $filter, 10 );
		$this->assertInstanceOf( WP_Email_Address::class, $result );
	}

	/**
	 * Tests that rescuing local_invalid_chars does not bypass later checks.
	 *
	 * @ticket 31992
	 *
	 * @covers WP_Email_Address::from_string
	 */
	public function test_local_invalid_chars_rescue_does_not_bypass_domain_check() {
		$filter = static function ( $value, $email, $context ) {
			return 'local_invalid_chars' === $context ? true : $value;
		};
		add_filter( 'is_email', $filter, 10, 3 );
		// Local part rescued, but domain has no dot — should still be rejected.
		$result = WP_Email_Address::from_string( '"quoted"@nodots' );
		remove_filter( 'is_email', $filter, 10 );
		$this->assertNull( $result );
	}

	/**
	 * Tests that from_string() returns false for invalid addresses.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_invalid_addresses
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The invalid email address string.
	 */
	public function test_from_string_rejects_invalid( $address ) {
		$this->assertNull( WP_Email_Address::from_string( $address ) );
	}

	/**
	 * Data provider for invalid addresses.
	 *
	 * @return array[]
	 */
	public function data_invalid_addresses() {
		return array(
			'quoted local part with iframe' => array( '"<iframe src=http://example.com>"@example.com' ),
			'null byte'                     => array( "user\x00name@example.com" ),
			'very invalid UTF8'             => array( "\x80\x20ouch@example.com" ),
			'overlong encoding of space'    => array( "us\xC0\xA0er@example.com" ),

			// Domain without a dot is not a routable internet domain.
			'domain without a dot'          => array( 'com@com' ),
		);
	}

	/**
	 * Tests that from_string() returns false for invalid addresses when Unicode is enabled.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_invalid_unicode_addresses
	 * @covers WP_Email_Address::from_string
	 *
	 * @param string $address The invalid email address string.
	 */
	public function test_from_string_rejects_invalid_unicode( $address ) {
		$this->assertNull( WP_Email_Address::from_string( $address ) );
	}

	/**
	 * Data provider for addresses that are invalid specifically in Unicode mode.
	 *
	 * @return array[]
	 */
	public function data_invalid_unicode_addresses() {
		return array(
			'reserved ACE prefix in domain'       => array( 'user@ab--reserved.com' ),
			'combining mark as sole domain label' => array( "user@\xCC\x81.example.com" ),
			'combining mark as sole local part'   => array( "\xCC\x81@example.com" ),
		);
	}

	/**
	 * Data provider for several tests.
	 *
	 * @return array[]
	 */
	public function data_from_string() {
		return array(
			'simple address'              => array( 'example@example.com' ),
			'dot in local part'           => array( 'user.name@example.com' ),
			'plus sign in local part'     => array( 'user+tag@example.com' ),
			'underscore in local part'    => array( 'user_name@example.org' ),
			'hyphen in local part'        => array( 'user-name@example.net' ),
			'apostrophe in local part'    => array( "mail'@example.com" ),
			'digits in local part'        => array( 'user123@example.com' ),
			'uppercase letters'           => array( 'USER@EXAMPLE.COM' ),
			'subdomain'                   => array( 'user@mail.example.com' ),
			'multiple subdomains'         => array( 'user@a.b.c.example.com' ),
			'hyphen in domain label'      => array( 'user@my-domain.com' ),
			'digits in domain'            => array( 'user@123.example.com' ),
			'short but valid'             => array( 'a@l.is' ),
			'special chars in local part' => array( 'a.!#$%*+/=?^_{|}~-@example.com' ),
			'local part is all digits'    => array( '1234567890@example.com' ),
			'long local part'             => array( 'abcdefghijklmnopqrstuvwxyz0123456789@example.com' ),
			'long domain'                 => array( 'user@abcdefghijklmnopqrstuvwxyz0123456789.example.com' ),
			'country-code TLD'            => array( 'user@example.co.uk' ),
			'long TLD'                    => array( 'user@example.engineering' ),
			// xn-- labels: grå.org and 慕田峪长城.网址 (https://慕田峪长城.网址).
			'latin punycode domain'       => array( 'user@xn--gr-zia.org' ),
			'han punycode domain'         => array( 'ahq@xn--uist2j67d64zv30b.xn--ses554g' ),
		);
	}
}
