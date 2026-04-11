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
		$this->assertSame(
			$expected,
			sanitize_email( $address ),
			'Should have produced the known sanitized form of the email.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_sanitized_email_pairs() {
		return array(
			'shorter than 6 characters'      => array( 'a@b', '' ),
			'contains no @'                  => array( 'ab', '' ),
			'just a TLD'                     => array( 'abc@com', '' ),
			'plain'                          => array( 'abc@example.com', 'abc@example.com' ),
			'invalid utf8 in local'          => array( "a\x80b@example.com", '' ),
			'invalid utf8 subdomain dropped' => array( "abc@sub.\x80.org", 'abc@sub.org' ),
			'all subdomains invalid utf8'    => array( "abc@\x80.org", '' ),
		);
	}
}
