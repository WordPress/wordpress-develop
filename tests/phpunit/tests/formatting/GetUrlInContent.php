<?php

/**
 * @group formatting
 */
class Tests_Formatting_GetUrlInContent extends WP_UnitTestCase {

	/**
	 * URL Content Data Provider
	 *
	 * array ( input_txt, converted_output_txt )
	 */
	public function get_input_output() {
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

	/**
	 * Validate the get_url_in_content function
	 *
	 * @dataProvider get_input_output
	 */
	function test_get_url_in_content( $in_str, $exp_str ) {
		$this->assertEquals( $exp_str, get_url_in_content( $in_str ) );
	}
}
