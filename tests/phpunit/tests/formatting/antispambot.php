<?php
/**
 * Tests for the antispambot() function.
 *
 * @group formatting
 * @covers ::antispambot
 */
class Tests_Formatting_Antispambot extends WP_UnitTestCase {
	/**
	 * Ensures that antispambot will not produce invalid UTF-8 when hiding email addresses.
	 *
	 * Were a non-US-ASCII email address be sent into `antispambot()`, then a naive approach
	 * to obfuscation could break apart multibyte characters and leave invalid UTF-8 as a
	 * result.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_returns_valid_utf8
	 *
	 * @param string $email    The email address to obfuscate.
	 */
	public function test_returns_valid_utf8( $email ) {
		$this->assertTrue( wp_is_valid_utf8( antispambot( $email ) ) );
	}

	/**
	 * Data provider.
	 *
	 * return array[]
	 */
	public function data_returns_valid_utf8() {
		return array(
			'plain'                => array( 'bob@example.com' ),
			'plain with ip'        => array( 'ace@204.32.222.14' ),
			'deep subdomain'       => array( 'kevin@many.subdomains.make.a.happy.man.edu' ),
			'short address'        => array( 'a@b.co' ),
			'ascii@nonascii'       => array( 'info@grå.org' ),
			'nonascii@nonascii'    => array( 'grå@grå.org' ),
			'decomposed unicode'   => array( "gr\u{0061}\u{030a}blå@grå.org" ),
			'weird but legal dots' => array( '..@example.com' ),
			'umlauts'              => array( 'bücher@gmx.de' ),
			'three-byte UTF-8'     => array( "\u{FFFD}@who.knows.com" ),
		);
	}

	/**
	 * This tests that antispambot performs some sort of obfuscation
	 * and that the obfuscation maps back to the original value.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_antispambot_obfuscates
	 *
	 * @param string $provided The email address to obfuscate.
	 */
	public function test_antispambot_obfuscates( $provided ) {
		$obfuscated = antispambot( $provided, 1 );
		$processor  = new WP_HTML_Tag_Processor( $obfuscated );

		// The only token should be the email address, so advance once and treat as a text node.
		$processor->next_token();
		$decoded = rawurldecode( $processor->get_modifiable_text() );

		$this->assertNotSame(
			$provided,
			$obfuscated,
			'Should have produced an obfuscated representation.'
		);

		$this->assertSame(
			$provided,
			$decoded,
			'Should have decoded to the original email after restoring.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return Generator
	 */
	public function data_antispambot_obfuscates() {
		$addresses = array(
			'example@example.com',
			'#@example.com',
			'πετρος@example.com',
			"\u{FFFD}@mad.mail.com",
		);

		foreach ( $addresses as $address ) {
			yield $address => array( $address );
		}
	}
}
