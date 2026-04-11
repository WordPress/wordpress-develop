<?php
/**
 * Tests for the antispambot() function.
 *
 * @group formatting
 * @covers ::antispambot
 */
class Tests_Formatting_Antispambot extends WP_UnitTestCase {

	/**
	 * This is basically a driveby test. While working on ticket
	 * 31992 I noticed that there was no unit testing for
	 * antispambot, so I added a little, just so I'd leave the code
	 * better than I found it.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_returns_valid_utf8
	 * @param string $address  The email address to obfuscate.
	 * @param bool   $validity Whether the obfuscated address should be valid UTF-8.
	 */
	public function test_returns_valid_utf8( $address, $validity ) {
		$this->assertSame( wp_is_valid_utf8( antispambot( $address ) ), $validity );
	}

	/**
	 * Data provider for test_returns_valid_utf8.
	 */
	public function data_returns_valid_utf8() {
		return array(
			'plain'                => array( 'bob@example.com', true ),
			'plain with ip'        => array( 'ace@204.32.222.14', true ),
			'deep subdomain'       => array( 'kevin@many.subdomains.make.a.happy.man.edu', true ),
			'short address'        => array( 'a@b.co', true ),
			'weird but legal dots' => array( '..@example.com', true ),
		);
	}

	/**
	 * This tests that antispambot performs some sort of
	 * obfuscation, and that its obfuscated form will be rendered
	 * sensibly by browsers.
	 *
	 * @dataProvider data_antispambot_obfuscates
	 * @param string $provided  The email address to obfuscate.
	 */
	public function test_antispambot_obfuscates( $provided ) {
		$obfuscated = antispambot( $provided );
		$p          = new WP_HTML_Tag_Processor( $obfuscated );
		$p->next_token();
		$decoded = $p->get_modifiable_text();
		$decoded = preg_replace_callback( '~%\d\d~', function () { }, $decoded );

		$this->assertNotEquals( $provided, $obfuscated );
		$this->assertSame( $provided, $decoded );
	}

	/**
	 * Data provider for test_antispambot_obfuscates.
	 */
	public function data_antispambot_obfuscates() {
		return array(
			'example@example.com',
			'#@example.com',
		);
	}
}
