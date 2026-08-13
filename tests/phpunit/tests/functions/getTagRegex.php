<?php

/**
 * Tests for the get_tag_regex() function.
 *
 * @group functions
 *
 * @covers ::get_tag_regex
 */
class Tests_Functions_GetTagRegex extends WP_UnitTestCase {

	/**
	 * @ticket 26674
	 *
	 * @dataProvider data_get_tag_regex_matches
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
	 * @return array[]
	 */
	public function data_get_tag_regex_matches() {
		return array(
			'a single tag with a body'                  => array(
				'iframe',
				'<iframe src="https://example.com/a"></iframe>',
				array( '<iframe src="https://example.com/a"></iframe>' ),
			),

			// The regression: a greedy match ran from the first opening tag to
			// the last closing tag, merging both embeds and the text between
			// them into a single match. See #26674.
			'two adjacent tags are matched separately'  => array(
				'iframe',
				'<iframe src="https://example.com/a"></iframe> text <iframe src="https://example.com/b"></iframe>',
				array(
					'<iframe src="https://example.com/a"></iframe>',
					'<iframe src="https://example.com/b"></iframe>',
				),
			),

			'a self-closing tag'                        => array(
				'iframe',
				'<iframe src="https://example.com/a" />',
				array( '<iframe src="https://example.com/a" />' ),
			),

			'a tag with a multiline body'               => array(
				'video',
				"<video>\n<source src=\"a.mp4\">\n</video>",
				array( "<video>\n<source src=\"a.mp4\">\n</video>" ),
			),

			'no match when the tag is absent'           => array(
				'iframe',
				'<p>No embeds here.</p>',
				array(),
			),
		);
	}

	/**
	 * @ticket 26674
	 */
	public function test_get_tag_regex_returns_empty_string_for_empty_tag() {
		$this->assertSame( '', get_tag_regex( '' ) );
	}

	/**
	 * The tag name is passed through tag_escape(), so casing and invalid
	 * characters do not change the generated pattern.
	 *
	 * @ticket 26674
	 */
	public function test_get_tag_regex_escapes_the_tag_name() {
		$this->assertSame( get_tag_regex( 'iframe' ), get_tag_regex( 'IFRAME' ) );
	}
}
