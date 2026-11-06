<?php

/**
 * Tests for the WP_SimplePie_Sanitize_KSES class.
 *
 * @group feed
 *
 * @covers WP_SimplePie_Sanitize_KSES::sanitize
 */
class Tests_Feed_WpSimplePieSanitizeKses extends WP_UnitTestCase {

	/**
	 * Sanitizer used by the tests.
	 *
	 * @var WP_SimplePie_Sanitize_KSES
	 */
	private $sanitize;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . WPINC . '/class-simplepie.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php';
	}

	public function set_up() {
		parent::set_up();

		$this->sanitize = new WP_SimplePie_Sanitize_KSES();
	}

	/**
	 * Tests that the data of an HTML type is filtered with KSES.
	 *
	 * @ticket 66505
	 *
	 * @dataProvider data_html_types
	 *
	 * @param string $type Name of the constant of SimplePie for the type of the data.
	 */
	public function test_should_filter_html_with_kses( $type ) {
		$type = constant( 'SimplePie\SimplePie::' . $type );

		$data = '<p onclick="evil()">Hello <b>bold</b> <script>alert( 1 );</script><a href="https://example.org/" onmouseover="evil()">link</a></p>';

		$this->assertSame(
			'<p>Hello <b>bold</b> <a href="https://example.org/">link</a></p>',
			$this->sanitize->sanitize( $data, $type )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_html_types() {
		return array(
			'HTML'  => array( 'CONSTRUCT_HTML' ),
			'XHTML' => array( 'CONSTRUCT_XHTML' ),
		);
	}

	/**
	 * Tests that the tags that KSES allows for posts are kept.
	 *
	 * @ticket 66505
	 */
	public function test_should_keep_the_html_that_kses_allows() {
		$data = '<h2 class="title">Title</h2><ul><li><em>One</em></li></ul><a href="https://example.org/" title="A link">Link</a>';

		$this->assertSame( $data, $this->sanitize->sanitize( $data, SimplePie\SimplePie::CONSTRUCT_HTML ) );
	}

	/**
	 * Tests that the whitespace around the data is removed.
	 *
	 * @ticket 66505
	 *
	 * @dataProvider data_types_that_are_trimmed
	 *
	 * @param string $type     Name of the constant of SimplePie for the type of the data.
	 * @param string $data     Data to sanitize.
	 * @param string $expected Expected result.
	 */
	public function test_should_trim_the_data( $type, $data, $expected ) {
		$this->assertSame( $expected, $this->sanitize->sanitize( $data, constant( 'SimplePie\SimplePie::' . $type ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_types_that_are_trimmed() {
		return array(
			'HTML'  => array( 'CONSTRUCT_HTML', "  \n\t<p>text</p> \r\n", '<p>text</p>' ),
			'text'  => array( 'CONSTRUCT_TEXT', "  \n\ttext \r\n", 'text' ),
			'maybe' => array( 'CONSTRUCT_MAYBE_HTML', "  \n\ttext \r\n", 'text' ),
		);
	}

	/**
	 * Tests that the data of a text type is escaped by the parent class, and not filtered with KSES.
	 *
	 * @ticket 66505
	 */
	public function test_should_escape_text() {
		$this->assertSame(
			'&lt;b&gt;bold&lt;/b&gt; &amp; text',
			$this->sanitize->sanitize( '<b>bold</b> & text', SimplePie\SimplePie::CONSTRUCT_TEXT )
		);
	}

	/**
	 * Tests that data that may be HTML is handled as HTML when it has an entity or a closing tag.
	 *
	 * @ticket 66505
	 *
	 * @dataProvider data_maybe_html_that_is_html
	 *
	 * @param string $data     Data to sanitize.
	 * @param string $expected Expected result.
	 */
	public function test_should_handle_data_that_may_be_html_as_html( $data, $expected ) {
		$this->assertSame( $expected, $this->sanitize->sanitize( $data, SimplePie\SimplePie::CONSTRUCT_MAYBE_HTML ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_maybe_html_that_is_html() {
		return array(
			'named entity'   => array( 'a &amp; b', 'a &amp; b' ),
			'decimal entity' => array( 'a &#8217; <b>c', "a \u{2019} <b>c" ),
			'hex entity'     => array( 'a &#x27; <i>c', "a ' <i>c" ),
			'closing tag'    => array( 'text </b><script>alert( 1 );</script>', 'text </b>' ),
		);
	}

	/**
	 * Tests that data that may be HTML is handled as text when it has no entity or closing tag.
	 *
	 * @ticket 66505
	 */
	public function test_should_handle_data_that_may_be_html_as_text() {
		$this->assertSame(
			'plain &lt;b&gt;text',
			$this->sanitize->sanitize( 'plain <b>text', SimplePie\SimplePie::CONSTRUCT_MAYBE_HTML )
		);
	}

	/**
	 * Tests that base64 data is decoded before it is filtered.
	 *
	 * @ticket 66505
	 */
	public function test_should_decode_base64_html_before_filtering_it() {
		$data = base64_encode( '<p>Hello<script>alert( 1 );</script></p>' );

		$this->assertSame(
			'<p>Hello</p>',
			$this->sanitize->sanitize( $data, SimplePie\SimplePie::CONSTRUCT_BASE64 | SimplePie\SimplePie::CONSTRUCT_HTML )
		);
	}

	/**
	 * Tests that the data of an HTML type is converted to the output encoding.
	 *
	 * @ticket 66505
	 */
	public function test_should_convert_html_to_the_output_encoding() {
		$this->sanitize->set_registry( new SimplePie\Registry() );
		$this->sanitize->set_output_encoding( 'ISO-8859-1' );

		$result = $this->sanitize->sanitize( '<p>caf' . "\u{00E9}" . '</p>', SimplePie\SimplePie::CONSTRUCT_HTML );

		$this->assertSame( "<p>caf\xE9</p>", $result );
	}
}
