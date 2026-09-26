<?php
/**
 * Tests for the get_tag_regex() function.
 *
 * @package WordPress\UnitTests
 *
 * @since 7.2.0
 *
 * @group functions
 * @covers ::get_tag_regex
 */
class Tests_Functions_GetTagRegex extends WP_UnitTestCase {

	/**
	 * Tests that get_tag_regex() properly generates regex patterns for various HTML tags.
	 *
	 * @ticket 26674
	 *
	 * @dataProvider data_get_tag_regex_matches
	 *
	 * @since 7.2.0
	 *
	 * @param string   $tag      Tag name to build the regex for.
	 * @param string   $content  Content to match against.
	 * @param string[] $expected Expected full matches, in order.
	 */
	public function test_get_tag_regex_matches( $tag, $content, $expected ) {
		preg_match_all( '#' . get_tag_regex( $tag ) . '#', $content, $matches );
		$this->assertSame( $expected, $matches[0] );
	}

	/**
	 * Data provider.
	 *
	 * @since 7.2.0
	 *
	 * @return array[]
	 */
	public function data_get_tag_regex_matches() {
		// Each case embeds the element(s) within surrounding HTML, mirroring the
		// original usage in get_media_embedded_in_content(), which is passed a
		// string of HTML that may contain media elements rather than the bare
		// element on its own.
		return array(
			'a single tag with a body'                 => array(
				'iframe',
				'<div><p>Before.</p><iframe src="https://example.com/a"></iframe><p>After.</p></div>',
				array( '<iframe src="https://example.com/a"></iframe>' ),
			),

			// The regression: a greedy match ran from the first opening tag to
			// the last closing tag, merging both embeds and the text between
			// them into a single match. See #26674.
			'two adjacent tags are matched separately' => array(
				'iframe',
				'<div><p>Intro.</p><iframe src="https://example.com/a"></iframe> text <iframe src="https://example.com/b"></iframe><p>Outro.</p></div>',
				array(
					'<iframe src="https://example.com/a"></iframe>',
					'<iframe src="https://example.com/b"></iframe>',
				),
			),

			// A self-closing void element (an iframe cannot self-close), matched
			// both with and without the space before the slash (comment:16).
			'a self-closing tag with a space'          => array(
				'input',
				'<div>A form field: <input type="text" /> and more text.</div>',
				array( '<input type="text" />' ),
			),

			'a self-closing tag without a space'       => array(
				'input',
				'<div>A form field: <input type="text"/> and more text.</div>',
				array( '<input type="text"/>' ),
			),

			'a tag with a multiline body'              => array(
				'video',
				"<div><p>Watch:</p>\n<video>\n<source src=\"a.mp4\">\n</video>\n<p>End.</p></div>",
				array( "<video>\n<source src=\"a.mp4\">\n</video>" ),
			),

			'no match when the tag is absent'          => array(
				'iframe',
				'<div><p>No embeds here.</p></div>',
				array(),
			),
		);
	}

	/**
	 * Tests that calling get_tag_regex() with an empty tag name returns an empty string.
	 *
	 * @ticket 26674
	 *
	 * @since 7.2.0
	 */
	public function test_get_tag_regex_returns_empty_string_for_empty_tag() {
		$this->assertSame( '', get_tag_regex( '' ) );
	}

	/**
	 * Tests that the tag name is passed through tag_escape(), so casing and
	 * invalid characters do not change the generated pattern.
	 *
	 * @ticket 26674
	 *
	 * @since 7.2.0
	 */
	public function test_get_tag_regex_escapes_the_tag_name() {
		$this->assertSame( get_tag_regex( 'iframe' ), get_tag_regex( 'IFRAME' ) );
	}
}
