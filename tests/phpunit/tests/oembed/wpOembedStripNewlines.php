<?php

/**
 * Tests for the WP_oEmbed::_strip_newlines() method.
 *
 * @group oembed
 *
 * @covers WP_oEmbed::_strip_newlines
 */
class Tests_Oembed_WpOembedStripNewlines extends WP_UnitTestCase {

	/**
	 * Object used by the tests.
	 *
	 * @var WP_oEmbed
	 */
	private $oembed;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . WPINC . '/class-wp-oembed.php';

		$this->oembed = new WP_oEmbed();
	}

	/**
	 * Tests that the line breaks are removed.
	 *
	 * @ticket 66508
	 *
	 * @dataProvider data_html_with_line_breaks
	 *
	 * @param string $html     HTML of the embed.
	 * @param string $expected Expected HTML.
	 */
	public function test_should_remove_the_line_breaks( $html, $expected ) {
		$this->assertSame( $expected, $this->oembed->_strip_newlines( $html, new stdClass(), 'https://example.org/embed' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_html_with_line_breaks() {
		return array(
			'unix line breaks'                => array( "<div>\n<p>One</p>\n<p>Two</p>\n</div>", '<div><p>One</p><p>Two</p></div>' ),
			'windows line breaks'             => array( "<div>\r\n<p>One</p>\r\n</div>", '<div><p>One</p></div>' ),
			'line break at the start and end' => array( "\n<p>One</p>\n", '<p>One</p>' ),
			'only line breaks'                => array( "\n\n", '' ),
		);
	}

	/**
	 * Tests that HTML without line breaks is returned as it is.
	 *
	 * @ticket 66508
	 *
	 * @dataProvider data_html_without_line_breaks
	 *
	 * @param string $html HTML of the embed.
	 */
	public function test_should_return_html_without_line_breaks_as_it_is( $html ) {
		$this->assertSame( $html, $this->oembed->_strip_newlines( $html, new stdClass(), 'https://example.org/embed' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_html_without_line_breaks() {
		return array(
			'empty string'         => array( '' ),
			'plain text'           => array( 'Text' ),
			'html'                 => array( '<p>One</p> <p>Two</p>' ),
			'tabs and spaces'      => array( "<p>\tOne  Two</p>" ),
			'carriage return only' => array( "<p>One\rTwo</p>" ),
		);
	}

	/**
	 * Tests that the line breaks, tabs and spaces inside a `pre` element are kept.
	 *
	 * @ticket 66508
	 */
	public function test_should_keep_the_content_of_a_pre_element() {
		$html = "<div>\n<pre>line one\n\tline two  \nline three</pre>\n</div>";

		$this->assertSame(
			"<div><pre>line one\n\tline two  \nline three</pre></div>",
			$this->oembed->_strip_newlines( $html, new stdClass(), 'https://example.org/embed' )
		);
	}

	/**
	 * Tests that the `pre` elements with attributes, in any case and with Windows line breaks, are kept.
	 *
	 * @ticket 66508
	 */
	public function test_should_keep_pre_elements_with_attributes_in_any_case() {
		$html = "<p>\nText\n</p>\n<PRE class=\"code\">a\r\nb</PRE>";

		$this->assertSame(
			"<p>Text</p><PRE class=\"code\">a\r\nb</PRE>",
			$this->oembed->_strip_newlines( $html, new stdClass(), 'https://example.org/embed' )
		);
	}

	/**
	 * Tests that several `pre` elements are kept, and the line breaks between them are removed.
	 *
	 * @ticket 66508
	 */
	public function test_should_keep_several_pre_elements() {
		$html = "<pre>a\nb</pre>\n<p>\nbetween\n</p>\n<pre>c\nd</pre>";

		$this->assertSame(
			"<pre>a\nb</pre><p>between</p><pre>c\nd</pre>",
			$this->oembed->_strip_newlines( $html, new stdClass(), 'https://example.org/embed' )
		);
	}

	/**
	 * Tests that the method is used by default to filter the HTML of the embeds.
	 *
	 * @ticket 66508
	 */
	public function test_should_be_hooked_to_the_dataparse_filter() {
		$this->assertSame( 10, has_filter( 'oembed_dataparse', array( $this->oembed, '_strip_newlines' ) ) );
	}
}
