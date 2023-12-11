<?php

/**
 * @group formatting
 *
 * @covers ::get_url_in_content
 */
class Tests_Formatting_GetUrlInContent extends WP_UnitTestCase {

	/**
	 * Tests the get_url_in_content() function.
	 *
	 * @dataProvider data_get_url_in_content
	 */
	public function test_get_url_in_content( $input, $expected ) {
		$this->assertSame( $expected, get_url_in_content( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input    Input content.
	 *         @type string $expected Expected output.
	 *     }
	 * }
	 */
	public function data_get_url_in_content() {
		return array(
			array( // Empty content.
				'',
				false,
			),
			array( // No URLs.
				'<div>NO URL CONTENT</div>',
				false,
			),
			array( // Ignore none link elements.
				'<div href="/relative.php">NO URL CONTENT</div>',
				false,
			),
			array( // Single link.
				'ABC<div><a href="/relative.php">LINK</a> CONTENT</div>',
				'/relative.php',
			),
			array( // Multiple links.
				'ABC<div><a href="/relative.php">LINK</a> CONTENT <a href="/suppress.php">LINK</a></div>',
				'/relative.php',
			),
			array( // Escape link.
				'ABC<div><a href="http://example.com/Mr%20WordPress 2">LINK</a> CONTENT </div>',
				'http://example.com/Mr%20WordPress%202',
			),
		);
	}
}
