<?php

/**
 * Tests for the `get_archives_link()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::get_archives_link
 */
class Tests_General_GetArchivesLink extends WP_UnitTestCase {

	/**
	 * Tests the markup for each format.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_formats
	 *
	 * @param string $format   Link format.
	 * @param string $before   Content before the link text.
	 * @param string $after    Content after the link text.
	 * @param bool   $selected Whether the archive is selected.
	 * @param string $expected Expected markup.
	 */
	public function test_formats( $format, $before, $after, $selected, $expected ) {
		$this->assertSame(
			$expected,
			get_archives_link( 'https://example.org/2024/03/', 'March 2024', $format, $before, $after, $selected )
		);
	}

	/**
	 * Data provider for test_formats().
	 *
	 * @return array[]
	 */
	public static function data_formats() {
		return array(
			'html'                       => array( 'html', '', '', false, "\t<li><a href='https://example.org/2024/03/'>March 2024</a></li>\n" ),
			'html with before and after' => array( 'html', '<b>', '</b>', false, "\t<li><b><a href='https://example.org/2024/03/'>March 2024</a></b></li>\n" ),
			'html selected'              => array( 'html', '', '', true, "\t<li><a href='https://example.org/2024/03/' aria-current=\"page\">March 2024</a></li>\n" ),
			'option'                     => array( 'option', '', '', false, "\t<option value='https://example.org/2024/03/'> March 2024 </option>\n" ),
			'option with before, after'  => array( 'option', '[', ']', false, "\t<option value='https://example.org/2024/03/'>[ March 2024 ]</option>\n" ),
			'option selected'            => array( 'option', '', '', true, "\t<option value='https://example.org/2024/03/' selected='selected'> March 2024 </option>\n" ),
			'link'                       => array( 'link', '<b>', '</b>', false, "\t<link rel='archives' title='March 2024' href='https://example.org/2024/03/' />\n" ),
			'link selected'              => array( 'link', '', '', true, "\t<link rel='archives' title='March 2024' href='https://example.org/2024/03/' />\n" ),
			'custom'                     => array( 'custom', '<span>', '</span>', false, "\t<span><a href='https://example.org/2024/03/'>March 2024</a></span>\n" ),
			'custom selected'            => array( 'custom', '', '', true, "\t<a href='https://example.org/2024/03/' aria-current=\"page\">March 2024</a>\n" ),
		);
	}

	/**
	 * Tests that the URL is escaped.
	 *
	 * @ticket 65819
	 */
	public function test_escapes_url() {
		$this->assertSame(
			"\t<li><a href='https://example.org/?m=202403&#038;cat=1'>March 2024</a></li>\n",
			get_archives_link( 'https://example.org/?m=202403&cat=1', 'March 2024' )
		);
	}

	/**
	 * Tests that the text is texturized, and escaped as an attribute in the 'link' format.
	 *
	 * @ticket 65819
	 */
	public function test_texturizes_text_and_escapes_link_title() {
		$this->assertSame(
			"\t<li><a href='https://example.org/'><em>Posts</em>&#8230;</a></li>\n",
			get_archives_link( 'https://example.org/', '<em>Posts</em>...' ),
			'The html format should texturize the text.'
		);
		$this->assertSame(
			"\t<link rel='archives' title='&lt;em&gt;Posts&lt;/em&gt;&#8230;' href='https://example.org/' />\n",
			get_archives_link( 'https://example.org/', '<em>Posts</em>...', 'link' ),
			'The link format should escape the texturized text as an attribute.'
		);
	}

	/**
	 * Tests the arguments passed to the 'get_archives_link' filter and that its return value is used.
	 *
	 * @ticket 65819
	 */
	public function test_get_archives_link_filter() {
		$filter = new MockAction();
		add_filter( 'get_archives_link', array( $filter, 'filter' ), 10, 7 );
		add_filter(
			'get_archives_link',
			static function ( $link_html, $url, $text, $format ) {
				return '<!-- ' . $format . ' -->' . $link_html;
			},
			20,
			4
		);

		$link_html = get_archives_link( 'https://example.org/?m=202403&cat=1', 'Posts...', 'custom', '<span>', '</span>', true );

		$expected = "\t<span><a href='https://example.org/?m=202403&#038;cat=1' aria-current=\"page\">Posts&#8230;</a></span>\n";

		$this->assertSame(
			array( $expected, 'https://example.org/?m=202403&#038;cat=1', 'Posts&#8230;', 'custom', '<span>', '</span>', true ),
			$filter->get_args()[0],
			'The filter should receive the markup and the escaped and texturized arguments.'
		);
		$this->assertSame( '<!-- custom -->' . $expected, $link_html, 'The filtered markup should be returned.' );
	}
}
