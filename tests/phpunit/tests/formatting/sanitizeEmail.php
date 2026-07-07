<?php
/**
 * Tests for the sanitize_email() function.
 *
 * @group formatting
 * @covers ::sanitize_email
 */
class Tests_Formatting_SanitizeEmail extends WP_UnitTestCase {
	/**
	 * This test checks that email addresses are properly sanitized.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_sanitized_email_pairs
	 *
	 * @param string $address  The email address to sanitize.
	 * @param string $expected The expected sanitized email address.
	 */
	public function test_returns_stripped_email_address( $address, $expected ) {
		$sanitized = sanitize_email( $address );

		if ( $expected === $sanitized ) {
			$this->assertSame(
				$expected,
				$sanitized,
				'Should have produced the known sanitized form of the email.'
			);
		} else {
			$this->assertSame(
				$expected,
				self::invalid_utf8_as_ascii( $sanitized ),
				'Should have produced the known sanitized form of the email.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_sanitized_email_pairs() {
		return array(
			'shorter than 6 characters'        => array( 'a@b', '' ),
			'contains no @'                    => array( 'ab', '' ),
			'just a TLD'                       => array( 'abc@com', '' ),
			'plain'                            => array( 'abc@example.com', 'abc@example.com' ),
			'unicode domain'                   => array( 'abc@grå.org', 'abc@grå.org' ),
			'unicode local part'               => array( 'grå@example.com', 'grå@example.com' ),
			'unicode local and domain'         => array( 'grå@grå.org', 'grå@grå.org' ),
			'invalid utf8 in local'            => array( "a\x80b@example.com", '' ),
			'invalid utf8 subdomain'           => array( "abc@sub.\x80.org", '' ),
			'all subdomains invalid utf8'      => array( "abc@\x80.org", '' ),
			'soft hyphen before dot'           => array( "info@example\xC2\xAD.com", 'info@example.com' ),
			'soft hyphen after dot'            => array( "info@example.\xC2\xADcom", 'info@example.com' ),
			'space before dot'                 => array( 'info@example .com', 'info@example.com' ),
			'space after dot'                  => array( 'info@example. com', 'info@example.com' ),
			'soft hyphen and space around dot' => array( "info@example \xC2\xAD.com", 'info@example.com' ),
			'space around at sign'             => array( 'info @ example.com', 'info@example.com' ),
			'soft hyphen before at sign'       => array( "info\xC2\xAD@example.com", 'info@example.com' ),
			'display name with angle brackets' => array( 'Alice Example <alice@example.com>', 'alice@example.com' ),
			'angle brackets only'              => array( '<alice@example.com>', 'alice@example.com' ),
			'angle brackets invalid address'   => array( 'Alice <not-an-email>', '' ),
		);
	}

	/**
	 * Transforms invalid byte sequences in UTF-8 into representations of
	 * each byte value, according to the maximal subpart rule.
	 *
	 * Example:
	 *
	 *     // For valid UTF-8 the output is the input.
	 *     'test' === invalid_utf8_as_ascii( 'test' );
	 *
	 *     // Invalid bytes are represented with their hex value.
	 *     'a(0x80)b' === invalid_utf8_as_ascii( "a\x80b" );
	 *
	 *     // Invalid byte sequences form maximal subparts.
	 *     '(0xC2)(0xEF 0xBF)' === invalid_utf8_as_ascii( "\xC2\xEF\xBF" );
	 *
	 * @param string $text
	 * @return string
	 */
	private static function invalid_utf8_as_ascii( string $text ): string {
		$output        = '';
		$at            = 0;
		$was_at        = 0;
		$end           = strlen( $text );
		$invalid_bytes = 0;

		while ( $at < $end ) {
			if ( 0 === _wp_scan_utf8( $text, $at, $invalid_bytes ) && 0 === $invalid_bytes ) {
				break;
			}

			if ( $at > $was_at ) {
				$output .= substr( $text, $was_at, $at - $was_at );
			}

			if ( $invalid_bytes > 0 ) {
				$output .= '(';

				for ( $i = 0; $i < $invalid_bytes; $i++ ) {
					$space   = $i > 0 ? ' ' : '';
					$as_hex  = bin2hex( $text[ $at + $i ] );
					$output .= "{$space}0x{$as_hex}";
				}

				$output .= ')';
			}

			$at    += $invalid_bytes;
			$was_at = $at;
		}

		return $output;
	}
}
