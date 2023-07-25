<?php
/**
 * Unit tests covering WP_HTML_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.4.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorBreadcrumbs extends WP_UnitTestCase {
	/**
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::step
	 *
	 * @dataProvider data_single_tag_of_supported_elements
	 *
	 * @param string $html     HTML with at least one tag to scan.
	 * @param string $tag_name Name of first tag in HTML (because HTML treats IMAGE as IMG this may not match the HTML).
	 */
	public function test_navigates_into_normative_html_for_supported_elements( $html, $tag_name ) {
		$p = WP_HTML_Processor::createFragment( $html );

		$this->assertTrue( $p->step(), "Failed to step into supported {$tag_name} element." );
		$this->assertSame( $tag_name, $p->get_tag(), "Misread {$tag_name} as a {$p->get_tag()} element." );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_single_tag_of_supported_elements() {
		$supported_elements = array(
			'A',
			'B',
			'BIG',
			'CODE',
			'DIV',
			'EM',
			'FIGCAPTION',
			'FIGURE',
			'FONT',
			'I',
			'IMG',
			'P',
			'SMALL',
			'STRIKE',
			'STRONG',
			'TT',
			'U',
		);

		$data = array();
		foreach ( $supported_elements as $tag_name ) {
			$data[ $tag_name ] = array( "<{$tag_name}>", $tag_name );
		}

		$data['IMAGE (treated as an IMG)'] = array( '<image>', 'IMG' );

		return $data;
	}

	/**
	 * Ensures that no new HTML elements are accidentally partially-supported.
	 *
	 * When introducing support for new HTML elements, there are multiple places
	 * in the HTML Processor that need to be updated, until the time that the class
	 * has full HTML5 support. Because of this, these tests lock down the interface
	 * to ensure that support isn't accidentally updated in one place for a new
	 * element while overlooked in another.
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::step
	 *
	 * @dataProvider data_unsupported_elements
	 *
	 * @param string $html HTML string containing unsupported elements.
	 */
	public function test_fails_when_encountering_unsupported_tag( $html ) {
		$p = WP_HTML_Processor::createFragment( $html );

		$this->assertFalse( $p->step(), "Should not have stepped into unsupported {$p->get_tag()} element." );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_unsupported_elements() {
		$unsupported_elements = array(
			'ABBR',
			'ACRONYM', // Neutralized
			'ADDRESS',
			'APPLET', // Deprecated
			'AREA',
			'ARTICLE',
			'ASIDE',
			'AUDIO',
			'BASE',
			'BDI',
			'BDO',
			'BGSOUND', // Deprecated; self-closing if self-closing flag provided, otherwise normal.
			'BLINK', // Deprecated
			'BODY',
			'BR',
			'BUTTON',
			'CANVAS',
			'CAPTION',
			'CENTER', // Neutralized
			'CITE',
			'COL',
			'COLGROUP',
			'DATA',
			'DATALIST',
			'DD',
			'DEL',
			'DETAILS',
			'DEFN',
			'DIALOG',
			'DL',
			'DT',
			'EMBED',
			'FIELDSET',
			'FOOTER',
			'FORM',
			'FRAME',
			'FRAMESET',
			'H1',
			'H2',
			'H3',
			'H4',
			'H5',
			'H6',
			'HEAD',
			'HEADER',
			'HGROUP',
			'HR',
			'HTML',
			'IFRAME',
			'INPUT',
			'INS',
			'ISINDEX', // Deprecated
			'KBD',
			'KEYGEN', // Deprecated; void
			'LABEL',
			'LEGEND',
			'LI',
			'LINK',
			'LISTING', // Deprecated, use PRE instead.
			'MAIN',
			'MAP',
			'MARK',
			'MARQUEE', // Deprecated
			'MATH',
			'MENU',
			'META',
			'METER',
			'MULTICOL', // Deprecated
			'NAV',
			'NEXTID', // Deprecated
			'NOBR', // Neutralized
			'NOEMBED', // Neutralized
			'NOFRAMES', // Neutralized
			'NOSCRIPT',
			'OBJECT',
			'OL',
			'OPTGROUP',
			'OPTION',
			'OUTPUT',
			'PICTURE',
			'PLAINTEXT', // Neutralized
			'PRE',
			'PROGRESS',
			'Q',
			'RB', // Neutralized
			'RP',
			'RT',
			'RTC', // Neutralized
			'RUBY',
			'SAMP',
			'SCRIPT',
			'SECTION',
			'SELECT',
			'SLOT',
			'SOURCE',
			'SPACER', // Deprecated
			'SPAN',
			'STYLE',
			'SUB',
			'SUMMARY',
			'SUP',
			'SVG',
			'TABLE',
			'TBODY',
			'TD',
			'TEMPLATE',
			'TEXTAREA',
			'TFOOT',
			'TH',
			'THEAD',
			'TIME',
			'TITLE',
			'TR',
			'TRACK',
			'UL',
			'VAR',
			'VIDEO',
			'WBR',
			'XMP', // Deprecated, use PRE instead.

			// Made up elements, custom elements.
			'X-NOT-AN-HTML-ELEMENT',
			'HUMAN-TIME',
		);

		$data = array();
		foreach ( $unsupported_elements as $tag_name ) {
			$data[ $tag_name ] = array( "<{$tag_name}>" );
		}

		return $data;
	}

	/**
	 * @ticket 58517
	 *
	 * @dataProvider data_unsupported_markup
	 *
	 * @param string $html HTML containing unsupported markup.
	 */
	public function test_fails_when_encountering_unsupported_markup( $html, $description ) {
		$p = WP_HTML_Processor::createFragment( $html );

		while ( $p->step() && null === $p->get_attribute( 'supported' ) ) {
			continue;
		}

		$this->assertTrue( $p->get_attribute( 'supported' ), 'Did not find required supported element.' );
		$this->assertFalse( $p->step(), "Didn't properly reject unsupported markup: {$description}" );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_unsupported_markup() {
		return array(
			'A with formatting following unclosed A' => array(
				'<a><strong>Click <a supported><big unsupported>Here</big></a></strong></a>',
				'Unclosed formatting requires complicated reconstruction.',
			),

			'A after unclosed A inside DIV'          => array(
				'<a><div supported><a unsupported></div></a>',
				'A is a formatting element, which requires more complicated reconstruction.',
			),
		);
	}

	/**
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::next_tag
	 *
	 * @dataProvider data_html_target_with_breadcrumbs
	 *
	 * @param string $html        HTML string with tags in it, one of which contains the "target" attribute.
	 * @param array  $breadcrumbs Breadcrumbs of element with "target" attribute set.
	 * @param int    $n           How many breadcrumb matches to scan through in order to find "target" element.
	 */
	public function test_finds_correct_tag_given_breadcrumbs( $html, $breadcrumbs, $n ) {
		$p = WP_HTML_Processor::createFragment( $html );

		$p->next_tag(
			array(
				'breadcrumbs'  => $breadcrumbs,
				'match_offset' => $n,
			)
		);

		$this->assertNotNull( $p->get_tag(), 'Failed to find target node.' );
		$this->assertTrue( $p->get_attribute( 'target' ), "Found {$p->get_tag()} element didn't contain the necessary 'target' attribute." );
	}

	/**
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::get_breadcrumbs
	 *
	 * @dataProvider data_html_target_with_breadcrumbs
	 *
	 * @param string $html        HTML string with tags in it, one of which contains the "target" attribute.
	 * @param array  $breadcrumbs Breadcrumbs of element with "target" attribute set.
	 * @param int    $ignored_n   Not used in this test but provided in the dataset for other tests.
	 * @return void
	 */
	public function test_reports_correct_breadcrumbs_for_html( $html, $breadcrumbs, $ignored_n ) {
		$p = WP_HTML_Processor::createFragment( $html );

		while ( $p->next_tag() && null === $p->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertNotNull( $p->get_tag(), 'Failed to find the target node.' );
		$this->assertSame( $breadcrumbs, $p->get_breadcrumbs(), 'Found the wrong path from the root of the HTML document to the target node.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_html_target_with_breadcrumbs() {
		return array(
			'Simple IMG tag'                        => array( '<img target>', array( 'HTML', 'BODY', 'IMG' ), 1 ),
			'Two sibling IMG tags'                  => array( '<img><img target>', array( 'HTML', 'BODY', 'IMG' ), 2 ),
			'Three sibling IMG tags, an IMAGE in last place' => array( '<img><img><image target>', array( 'HTML', 'BODY', 'IMG' ), 3 ),
			'IMG inside a DIV'                      => array( '<div><img target></div>', array( 'HTML', 'BODY', 'DIV', 'IMG' ), 1 ),
			'DIV inside a DIV'                      => array( '<div><div target></div>', array( 'HTML', 'BODY', 'DIV', 'DIV' ), 1 ),
			'IMG inside many DIVS'                  => array( '<div><div><div><div><img target></div></div></div></div>', array( 'HTML', 'BODY', 'DIV', 'DIV', 'DIV', 'DIV', 'IMG' ), 1 ),
			'DIV inside DIV after IMG'              => array( '<div><img><div target></div></div>', array( 'HTML', 'BODY', 'DIV', 'DIV' ), 1 ),
			'IMG after DIV'                         => array( '<div></div><img target>', array( 'HTML', 'BODY', 'IMG' ), 1 ),
			'IMG after two DIVs'                    => array( '<div></div><div></div><img target>', array( 'HTML', 'BODY', 'IMG' ), 1 ),
			'IMG after two DIVs with nesting'       => array( '<div><div><img></div></div><div></div><img target>', array( 'HTML', 'BODY', 'IMG' ), 1 ),
			'IMG after invalid DIV closer'          => array( '</div><img target>', array( 'HTML', 'BODY', 'IMG' ), 1 ),
			'EM inside DIV'                         => array( '<div>The weather is <em target>beautiful</em>.</div>', array( 'HTML', 'BODY', 'DIV', 'EM' ), 1 ),
			'EM after closed EM'                    => array( '<em></em><em target></em>', array( 'HTML', 'BODY', 'EM' ), 2 ),
			'EM after closed EMs'                   => array( '<em></em><em><em></em></em><em></em><em></em><em target></em>', array( 'HTML', 'BODY', 'EM' ), 6 ),
			'EM after unclosed EM'                  => array( '<em><em target></em>', array( 'HTML', 'BODY', 'EM', 'EM' ), 1 ),
			'EM after unclosed EM after DIV'        => array( '<em><div><em target>', array( 'HTML', 'BODY', 'EM', 'DIV', 'EM' ), 1 ),
			// This should work for all formatting elements, but if two work, the others probably do too.
			'CODE after unclosed CODE after DIV'    => array( '<code><div><code target>', array( 'HTML', 'BODY', 'CODE', 'DIV', 'CODE' ), 1 ),
			'P after unclosed P'                    => array( '<p><p target>', array( 'HTML', 'BODY', 'P' ), 2 ),
			'Unclosed EM inside P after unclosed P' => array( '<em><p><p><em target>', array( 'HTML', 'BODY', 'EM', 'P', 'EM' ), 1 ),
			'P after closed P'                      => array( '<p><i>something</i></p><p target>This one</p>', array( 'HTML', 'BODY', 'P' ), 2 ),
			'A after unclosed A'                    => array( '<a><a target>', array( 'HTML', 'BODY', 'A' ), 2 ),
			'A after unclosed A, after a P'         => array( '<p><a><a target>', array( 'HTML', 'BODY', 'P', 'A' ), 2 ),
			// This one adds a test at a deep stack depth to ensure things work for situations beyond short test docs.
			'Large HTML document with deep P'       => array(
				'<div><div><div><div><div><div><div><div><p></p><p></p><p><div><strong><em><code></code></em></strong></div></p></div></div></div></div></div></div></div></div><div><div><div><div><div><div><div><div><p></p><p></p><p><div><strong><em><code target></code></em></strong></div></p></div></div></div></div></div></div></div></div>',
				array( 'HTML', 'BODY', 'DIV', 'DIV', 'DIV', 'DIV', 'DIV', 'DIV', 'DIV', 'DIV', 'DIV', 'STRONG', 'EM', 'CODE' ),
				2,
			),
		);
	}

	/**
	 * Ensures that the ability to set attributes isn't broken by the HTML Processor.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::set_attribute
	 */
	public function test_can_modify_attributes_after_finding_tag() {
		$p = WP_HTML_Processor::createFragment( '<div><figure><img><figcaption>test</figcaption></figure>' );

		$this->assertTrue( $p->next_tag( array( 'breadcrumbs' => array( 'figcaption' ) ) ), 'Unable to find given tag.' );

		$p->set_attribute( 'found-it', true );
		$this->assertSame( '<div><figure><img><figcaption found-it>test</figcaption></figure>', $p->get_updated_html() );
	}

	/**
	 * Ensures that the ability to scan for a given tag name isn't broken by the HTML Processor.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::next_tag
	 */
	public function test_can_query_an_element_by_tag_name() {
		$p = WP_HTML_Processor::createFragment( '<div><DIV><strong><img></strong></DIV>' );
		$p->next_tag( 'IMG' );
		$p->set_attribute( 'loading', 'lazy' );

		$this->assertSame( '<div><DIV><strong><img loading="lazy"></strong></DIV>', $p->get_updated_html() );
	}

	/**
	 * Ensures that basic seeking behavior isn't broken by the HTML Processor.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::seek
	 */
	public function test_can_seek_back_and_forth() {
		$p = WP_HTML_Processor::createFragment( '<div><p one><div><p><div two><p><div><p><div><p three>' );

		// Find first tag of interest.
		while ( $p->next_tag() && null === $p->get_attribute( 'one' ) ) {
			continue;
		}
		$p->set_bookmark( 'first' );

		// Find second tag of interest.
		while ( $p->next_tag() && null === $p->get_attribute( 'two' ) ) {
			continue;
		}
		$p->set_bookmark( 'second' );

		// Find third tag of interest.
		while ( $p->next_tag() && null === $p->get_attribute( 'three' ) ) {
			continue;
		}
		$p->set_bookmark( 'third' );

		// Seek backwards.
		$p->seek( 'first' );

		// Seek forwards. If the current token isn't also updated this could appear like a backwards seek.
		$p->seek( 'second' );
		$this->assertTrue( $p->get_attribute( 'two' ) );
	}
}
