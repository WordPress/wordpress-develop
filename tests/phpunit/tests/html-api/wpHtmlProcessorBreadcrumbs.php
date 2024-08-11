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
		$processor = WP_HTML_Processor::create_fragment( $html );

		$this->assertTrue( $processor->next_token(), "Failed to step into supported {$tag_name} element." );
		$this->assertSame( $tag_name, $processor->get_tag(), "Misread {$tag_name} as a {$processor->get_tag()} element." );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_single_tag_of_supported_elements() {
		$supported_elements = array(
			'A',
			'ABBR',
			'ACRONYM', // Neutralized.
			'ADDRESS',
			'APPLET', // Deprecated.
			'AREA',
			'ARTICLE',
			'ASIDE',
			'AUDIO',
			'B',
			'BASE',
			'BDI',
			'BDO',
			'BGSOUND', // Deprectated.
			'BIG',
			'BLINK', // Deprecated.
			'BR',
			'BUTTON',
			'CANVAS',
			'CENTER', // Neutralized.
			'CITE',
			'CODE',
			'DATA',
			'DD',
			'DATALIST',
			'DFN',
			'DEL',
			'DETAILS',
			'DIALOG',
			'DIR',
			'DIV',
			'DL',
			'DT',
			'EM',
			'EMBED',
			'FIELDSET',
			'FIGCAPTION',
			'FIGURE',
			'FONT',
			'FORM',
			'FOOTER',
			'H1',
			'H2',
			'H3',
			'H4',
			'H5',
			'H6',
			'HEADER',
			'HGROUP',
			'HR',
			'I',
			'IMG',
			'INS',
			'LI',
			'LINK',
			'ISINDEX', // Deprecated.
			'KBD',
			'KEYGEN', // Deprecated.
			'LABEL',
			'LEGEND',
			'LINK',
			'LISTING', // Deprecated.
			'MAIN',
			'MAP',
			'MARK',
			'MARQUEE', // Deprecated.
			'MENU',
			'META',
			'METER',
			'MULTICOL', // Deprecated.
			'NAV',
			'NEXTID', // Deprecated.
			'NOBR', // Neutralized.
			'NOEMBED', // Neutralized.
			'NOFRAMES', // Neutralized.
			'NOSCRIPT',
			'OBJECT',
			'OL',
			'OUTPUT',
			'P',
			'PICTURE',
			'PROGRESS',
			'Q',
			'RB', // Neutralized.
			'RP',
			'RT',
			'RTC', // Neutralized.
			'RUBY',
			'SAMP',
			'SCRIPT',
			'SEARCH',
			'SECTION',
			'SLOT',
			'SMALL',
			'SPACER', // Deprecated.
			'SPAN',
			'STRIKE',
			'STRONG',
			'STYLE',
			'SUB',
			'SUMMARY',
			'SUP',
			'TABLE',
			'TEXTAREA',
			'TIME',
			'TITLE',
			'TT',
			'U',
			'UL',
			'VAR',
			'VIDEO',
			'XMP', // Deprecated, use PRE instead.
		);

		$data = array();
		foreach ( $supported_elements as $tag_name ) {
			$closer = in_array( $tag_name, array( 'NOEMBED', 'NOFRAMES', 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'XMP' ), true )
				? "</{$tag_name}>"
				: '';

			$data[ $tag_name ] = array( "<{$tag_name}>{$closer}", $tag_name );
		}

		$data['IMAGE (treated as an IMG)'] = array( '<image>', 'IMG' );

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
		$processor = WP_HTML_Processor::create_fragment( $html );

		while ( $processor->next_token() && null === $processor->get_attribute( 'supported' ) ) {
			continue;
		}

		$this->assertNull(
			$processor->get_last_error(),
			'Bailed on unsupported input before finding supported checkpoint: check test code.'
		);

		$this->assertTrue( $processor->get_attribute( 'supported' ), 'Did not find required supported element.' );
		$processor->next_token();
		$this->assertNotNull( $processor->get_last_error(), "Didn't properly reject unsupported markup: {$description}" );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unsupported_markup() {
		return array(
			'A with formatting following unclosed A' => array(
				'<a><strong>Click <span supported><a unsupported><big>Here</big></a></strong></a>',
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
		$processor = WP_HTML_Processor::create_fragment( $html );

		$processor->next_tag(
			array(
				'breadcrumbs'  => $breadcrumbs,
				'match_offset' => $n,
			)
		);

		$this->assertNotNull( $processor->get_tag(), 'Failed to find target node.' );
		$this->assertTrue( $processor->get_attribute( 'target' ), "Found {$processor->get_tag()} element didn't contain the necessary 'target' attribute." );
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
	 */
	public function test_reports_correct_breadcrumbs_for_html( $html, $breadcrumbs, $ignored_n ) {
		$processor = WP_HTML_Processor::create_fragment( $html );

		while ( $processor->next_tag() && null === $processor->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertNotNull( $processor->get_tag(), 'Failed to find the target node.' );
		$this->assertSame( $breadcrumbs, $processor->get_breadcrumbs(), 'Found the wrong path from the root of the HTML document to the target node.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_html_target_with_breadcrumbs() {
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
			'EM after closed EMs'                   => array( '<em></em><em><em></em></em><em></em><em></em><em target></em>', array( 'HTML', 'BODY', 'EM' ), 5 ),
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
			'MAIN inside MAIN inside SPAN'          => array( '<span><main><main target>', array( 'HTML', 'BODY', 'SPAN', 'MAIN', 'MAIN' ), 1 ),
			'MAIN next to unclosed P'               => array( '<p><main target>', array( 'HTML', 'BODY', 'MAIN' ), 1 ),
			'LI after unclosed LI'                  => array( '<li>one<li>two<li target>three', array( 'HTML', 'BODY', 'LI' ), 3 ),
			'LI in UL in LI'                        => array( '<ul><li>one<ul><li target>two', array( 'HTML', 'BODY', 'UL', 'LI', 'UL', 'LI' ), 1 ),
			'DD and DT mutually close, LI self-closes (dt 2)' => array( '<dd><dd><dt><dt target><dd><li><li>', array( 'HTML', 'BODY', 'DT' ), 2 ),
			'DD and DT mutually close, LI self-closes (dd 3)' => array( '<dd><dd><dt><dt><dd target><li><li>', array( 'HTML', 'BODY', 'DD' ), 3 ),
			'DD and DT mutually close, LI self-closes (li 1)' => array( '<dd><dd><dt><dt><dd><li target><li>', array( 'HTML', 'BODY', 'DD', 'LI' ), 1 ),
			'DD and DT mutually close, LI self-closes (li 2)' => array( '<dd><dd><dt><dt><dd><li><li target>', array( 'HTML', 'BODY', 'DD', 'LI' ), 2 ),

			// H1 - H6 close out _any_ H1 - H6 when encountering _any_ of H1 - H6, making this section surprising.
			'EM inside H3 after unclosed P'         => array( '<p><h3><em target>Important Message</em></h3>', array( 'HTML', 'BODY', 'H3', 'EM' ), 1 ),
			'H4 after H2'                           => array( '<h2>Major</h2><h4 target>Minor</h4>', array( 'HTML', 'BODY', 'H4' ), 1 ),
			'H4 after unclosed H2'                  => array( '<h2>Major<h4 target>Minor</h3>', array( 'HTML', 'BODY', 'H4' ), 1 ),
			'H4 inside H2'                          => array( '<h2><span>Major<h4 target>Minor</h3></span>', array( 'HTML', 'BODY', 'H2', 'SPAN', 'H4' ), 1 ),
			'H5 after unclosed H4 inside H2'        => array( '<h2><span>Major<h4>Minor</span></h3><h5 target>', array( 'HTML', 'BODY', 'H2', 'SPAN', 'H5' ), 1 ),
			'H5 after H4 inside H2'                 => array( '<h2><span>Major<h4>Minor</h4></span></h3><h5 target>', array( 'HTML', 'BODY', 'H5' ), 1 ),

			// Custom elements.
			'WP-EMOJI'                              => array( '<div><wp-emoji target></wp-emoji></div>', array( 'HTML', 'BODY', 'DIV', 'WP-EMOJI' ), 1 ),
			'WP-EMOJI then IMG'                     => array( '<div><wp-emoji></wp-emoji><img target></div>', array( 'HTML', 'BODY', 'DIV', 'IMG' ), 1 ),
		);
	}

	/**
	 * @ticket 59400
	 *
	 * @dataProvider data_html_with_breadcrumbs_of_various_specificity
	 *
	 * @param string   $html_with_target_node HTML with a node containing a "target" attribute.
	 * @param string[] $breadcrumbs           Breadcrumbs to test at the target node.
	 * @param bool     $should_match          Whether the target node should match the breadcrumbs.
	 */
	public function test_reports_if_tag_matches_breadcrumbs_of_various_specificity( $html_with_target_node, $breadcrumbs, $should_match ) {
		$processor = WP_HTML_Processor::create_fragment( $html_with_target_node );
		while ( $processor->next_tag() && null === $processor->get_attribute( 'target' ) ) {
			continue;
		}

		$matches = $processor->matches_breadcrumbs( $breadcrumbs );
		$path    = implode( ', ', $breadcrumbs );
		if ( $should_match ) {
			$this->assertTrue( $matches, "HTML tag {$processor->get_tag()} should have matched breadcrumbs but didn't: {$path}." );
		} else {
			$this->assertFalse( $matches, "HTML tag {$processor->get_tag()} should not have matched breadcrumbs but did: {$path}." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_html_with_breadcrumbs_of_various_specificity() {
		return array(
			// Test with void elements.
			'Inner IMG'                               => array( '<div><span><figure><img target></figure></span></div>', array( 'span', 'figure', 'img' ), true ),
			'Inner IMG wildcard'                      => array( '<div><span><figure><img target></figure></span></div>', array( 'span', '*', 'img' ), true ),
			'Inner IMG no wildcard'                   => array( '<div><span><figure><img target></figure></span></div>', array( 'span', 'img' ), false ),
			'Full specification'                      => array( '<div><span><figure><img target></figure></span></div>', array( 'html', 'body', 'div', 'span', 'figure', 'img' ), true ),
			'Invalid Full specification'              => array( '<div><span><figure><img target></figure></span></div>', array( 'html', 'div', 'span', 'figure', 'img' ), false ),

			// Test also with non-void elements that open and close.
			'Inner P'                                 => array( '<div><span><figure><p target></figure></span></div>', array( 'span', 'figure', 'p' ), true ),
			'Inner P wildcard'                        => array( '<div><span><figure><p target></figure></span></div>', array( 'span', '*', 'p' ), true ),
			'Inner P no wildcard'                     => array( '<div><span><figure><p target></figure></span></div>', array( 'span', 'p' ), false ),
			'Full specification (P)'                  => array( '<div><span><figure><p target></figure></span></div>', array( 'html', 'body', 'div', 'span', 'figure', 'p' ), true ),
			'Invalid Full specification (P)'          => array( '<div><span><figure><p target></figure></span></div>', array( 'html', 'div', 'span', 'figure', 'p' ), false ),

			// Ensure that matches aren't on tag closers.
			'Inner P (Closer)'                        => array( '<div><span><figure></p target></figure></span></div>', array( 'span', 'figure', 'p' ), false ),
			'Inner P wildcard (Closer)'               => array( '<div><span><figure></p target></figure></span></div>', array( 'span', '*', 'p' ), false ),
			'Inner P no wildcard (Closer)'            => array( '<div><span><figure></p target></figure></span></div>', array( 'span', 'p' ), false ),
			'Full specification (P) (Closer)'         => array( '<div><span><figure></p target></figure></span></div>', array( 'html', 'body', 'div', 'span', 'figure', 'p' ), false ),
			'Invalid Full specification (P) (Closer)' => array( '<div><span><figure></p target></figure></span></div>', array( 'html', 'div', 'span', 'figure', 'p' ), false ),

			// Test wildcard behaviors.
			'Single wildcard element'                 => array( '<figure><code><div><p><span><img target></span></p></div></code></figure>', array( '*' ), true ),
			'Child of wildcard element'               => array( '<figure><code><div><p><span><img target></span></p></div></code></figure>', array( 'SPAN', '*' ), true ),
		);
	}

	/**
	 * Ensures that updating tag's attributes doesn't shift the current position
	 * in the input HTML document.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 59607
	 *
	 * @covers WP_HTML_Tag_Processor::get_updated_html
	 */
	public function test_remains_stable_when_editing_attributes() {
		$processor = WP_HTML_Processor::create_fragment( '<div><button>First<button><b here>Second' );
		$processor->next_tag( array( 'breadcrumbs' => array( 'BUTTON', 'B' ) ) );

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV', 'BUTTON', 'B' ),
			$processor->get_breadcrumbs(),
			'Found the wrong nested structure at the matched tag.'
		);

		$processor->set_attribute( 'a-name', 'a-value' );

		$this->assertTrue(
			$processor->get_attribute( 'here' ),
			'Should have found the B tag but could not find expected "here" attribute.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV', 'BUTTON', 'B' ),
			$processor->get_breadcrumbs(),
			'Found the wrong nested structure at the matched tag.'
		);

		$processor->get_updated_html();

		$this->assertTrue(
			$processor->get_attribute( 'here' ),
			'Should have stayed at the B tag but could not find expected "here" attribute.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV', 'BUTTON', 'B' ),
			$processor->get_breadcrumbs(),
			'Found the wrong nested structure at the matched tag after updating attributes.'
		);
	}

	/**
	 * Ensures that the ability to set attributes isn't broken by the HTML Processor.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_can_modify_attributes_after_finding_tag() {
		$processor = WP_HTML_Processor::create_fragment( '<div><figure><img><figcaption>test</figcaption></figure>' );

		$this->assertTrue( $processor->next_tag( array( 'breadcrumbs' => array( 'figcaption' ) ) ), 'Unable to find given tag.' );

		$processor->set_attribute( 'found-it', true );
		$this->assertSame( '<div><figure><img><figcaption found-it>test</figcaption></figure>', $processor->get_updated_html() );
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
		$processor = WP_HTML_Processor::create_fragment( '<div><DIV><strong><img></strong></DIV>' );
		$processor->next_tag( 'IMG' );
		$processor->set_attribute( 'loading', 'lazy' );

		$this->assertSame( '<div><DIV><strong><img loading="lazy"></strong></DIV>', $processor->get_updated_html() );
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
		$processor = WP_HTML_Processor::create_fragment(
			<<<'HTML'
<div>text<p one>more stuff<div><![CDATA[this is not real CDATA]]><p><!-- hi --><div two><p><div><p>three comes soon<div><p three>' );
HTML
		);

		// Find first tag of interest.
		while ( $processor->next_tag() && null === $processor->get_attribute( 'one' ) ) {
			continue;
		}
		$processor->set_bookmark( 'first' );

		// Find second tag of interest.
		while ( $processor->next_tag() && null === $processor->get_attribute( 'two' ) ) {
			continue;
		}
		$processor->set_bookmark( 'second' );

		// Find third tag of interest.
		while ( $processor->next_tag() && null === $processor->get_attribute( 'three' ) ) {
			continue;
		}
		$processor->set_bookmark( 'third' );

		// Seek backwards.
		$processor->seek( 'first' );

		// Seek forwards. If the current token isn't also updated this could appear like a backwards seek.
		$processor->seek( 'second' );
		$this->assertTrue( $processor->get_attribute( 'two' ) );
	}

	/**
	 * Ensures that breadcrumbs are properly reported after seeking backward to a location
	 * inside an element which has been fully closed before the seek.
	 *
	 * @ticket 60687
	 */
	public function test_retains_proper_bookmarks_after_seeking_back_to_closed_element() {
		$processor = WP_HTML_Processor::create_fragment( '<div><img></div><div><hr></div>' );

		$processor->next_tag( 'IMG' );
		$processor->set_bookmark( 'first' );

		$processor->next_tag( 'HR' );

		$processor->seek( 'first' );
		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV', 'IMG' ),
			$processor->get_breadcrumbs(),
			'Should have retained breadcrumbs from bookmarked location after seeking backwards to it.'
		);
	}

	/**
	 * Ensures that breadcrumbs are properly reported on virtual nodes.
	 *
	 * @ticket 61348
	 *
	 * @dataProvider data_virtual_nodes_breadcrumbs
	 *
	 * @covers WP_HTML_Processor::get_breadcrumbs
	 */
	public function test_breadcrumbs_on_virtual_nodes( string $html, int $token_position, string $expected_tag_name, string $expect_open_close, array $expected_breadcrumbs ) {
		$processor = WP_HTML_Processor::create_fragment( $html );

		for ( $i = 0; $i < $token_position; $i++ ) {
			$processor->next_token();
		}

		$this->assertSame( $expected_tag_name, $processor->get_tag(), "Found incorrect tag name {$processor->get_token_name()}." );
		if ( 'open' === $expect_open_close ) {
			$this->assertFalse( $processor->is_tag_closer(), "Found closer when opener expected at {$processor->get_token_name()}." );
		} else {
			$this->assertTrue( $processor->is_tag_closer(), "Found opener when closer expected at {$processor->get_token_name()}." );
		}

		$this->assertSame( $expected_breadcrumbs, $processor->get_breadcrumbs(), "Found incorrect breadcrumbs in {$html}." );
	}

	/**
	 * Ensures that get_current_depth reports the correct depth on virtual nodes.
	 *
	 * @ticket 61348
	 *
	 * @dataProvider data_virtual_nodes_breadcrumbs
	 *
	 * @covers WP_HTML_Processor::get_current_depth
	 */
	public function test_depth_on_virtual_nodes( string $html, int $token_position, string $expected_tag_name, string $expect_open_close, array $expected_breadcrumbs ) {
		$processor = WP_HTML_Processor::create_fragment( $html );

		for ( $i = 0; $i < $token_position; $i++ ) {
			$processor->next_token();
		}

		$this->assertSame( count( $expected_breadcrumbs ), $processor->get_current_depth(), "Found incorrect depth in {$html}." );
	}

	/**
	 * Data provider for virtual nodes breadcrumbs with the following shape of arrays:
	 *     0: string        Input html.
	 *     1: int           Token index to seek.
	 *     2: string        Expected tag name.
	 *     3: string        'open' or 'close' indicating an opener or closer is expected.
	 *     4: array<string> Expected breadcrumbs.
	 *
	 * @return array[]
	 */
	public static function data_virtual_nodes_breadcrumbs() {
		return array(
			'Implied P tag opener on unmatched closer'    => array( '</p>', 1, 'P', 'open', array( 'HTML', 'BODY', 'P' ) ),
			'Implied heading tag closer on heading child' => array( '<h1><h2>', 2, 'H1', 'close', array( 'HTML', 'BODY' ) ),
			'Implied A tag closer on A tag child'         => array( '<a><a>', 2, 'A', 'close', array( 'HTML', 'BODY' ) ),
			'Implied A tag closer on A tag descendent'    => array( '<a><span><a>', 4, 'A', 'close', array( 'HTML', 'BODY' ) ),
		);
	}
}
