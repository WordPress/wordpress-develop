<?php

/**
 * Tests for the unescape_invalid_shortcodes() function.
 *
 * @group shortcode
 *
 * @covers ::unescape_invalid_shortcodes
 */
class Tests_Shortcodes_UnescapeInvalidShortcodes extends WP_UnitTestCase {

	/**
	 * Tests that the placeholders of the square brackets are replaced with the brackets.
	 *
	 * @ticket 66500
	 *
	 * @dataProvider data_content
	 *
	 * @param string $content  Content to unescape.
	 * @param string $expected Expected content.
	 */
	public function test_should_replace_the_placeholders_of_the_square_brackets( $content, $expected ) {
		$this->assertSame( $expected, unescape_invalid_shortcodes( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content() {
		return array(
			'opening bracket'                   => array( '&#91;', '[' ),
			'closing bracket'                   => array( '&#93;', ']' ),
			'both brackets'                     => array( '&#91;shortcode&#93;', '[shortcode]' ),
			'shortcode with attributes'         => array( '&#91;gallery ids="1,2"&#93;', '[gallery ids="1,2"]' ),
			'repeated placeholders'             => array( '&#91;&#91;a&#93;&#93; &#91;b&#93;', '[[a]] [b]' ),
			'inside html'                       => array( '<a title="&#91;x&#93;">&#91;y&#93;</a>', '<a title="[x]">[y]</a>' ),
			'placeholders of a conditional tag' => array( '<!--&#91;if IE&#93;>', '<!--[if IE]>' ),
			'empty string'                      => array( '', '' ),
		);
	}

	/**
	 * Tests that content without the placeholders is not changed.
	 *
	 * @ticket 66500
	 *
	 * @dataProvider data_content_to_keep
	 *
	 * @param string $content Content that should stay the same.
	 */
	public function test_should_not_change_other_content( $content ) {
		$this->assertSame( $content, unescape_invalid_shortcodes( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_to_keep() {
		return array(
			'plain text'                        => array( 'Plain text' ),
			'real brackets'                     => array( '[shortcode] [/shortcode]' ),
			'other character references'        => array( '&amp; &#38; &lt;b&gt; &#x5B;' ),
			'placeholder of another number'     => array( '&#92; &#94; &#9;' ),
			'placeholder without the semicolon' => array( '&#91 &#93' ),
			'uppercase hexadecimal form'        => array( '&#X5B;' ),
		);
	}

	/**
	 * Tests that the placeholders are replaced in the whole content, including the markup.
	 *
	 * @ticket 66500
	 */
	public function test_should_replace_the_placeholders_in_the_whole_content() {
		$content = "<p class=\"&#91;a&#93;\">\n&#91;b&#93;\n</p>\n<!-- &#91;c&#93; -->";

		$this->assertSame( "<p class=\"[a]\">\n[b]\n</p>\n<!-- [c] -->", unescape_invalid_shortcodes( $content ) );
	}

	/**
	 * Tests that the function is the counterpart of the escaping that is done when shortcodes inside HTML are skipped.
	 *
	 * @ticket 66500
	 */
	public function test_should_restore_the_brackets_that_do_shortcode_leaves_inside_html() {
		add_shortcode( 'unescape-skip', static fn() => 'processed' );

		$content = '<a href="[unescape-skip]">[unescape-skip]</a>';

		try {
			$this->assertSame( '<a href="[unescape-skip]">processed</a>', do_shortcode( $content, true ) );
		} finally {
			remove_shortcode( 'unescape-skip' );
		}
	}
}
