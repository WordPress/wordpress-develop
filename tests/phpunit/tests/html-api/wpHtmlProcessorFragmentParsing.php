<?php
/**
 * Unit tests covering WP_HTML_Processor fragment parsing functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorFragmentParsing extends WP_UnitTestCase {
	/**
	 * @ticket 62357
	 */
	public function test_create_fragment_at_current_node_in_foreign_content() {
		$processor = WP_HTML_Processor::create_full_parser( '<svg>' );
		$this->assertTrue( $processor->next_tag( 'SVG' ) );

		$fragment = $processor->create_fragment_at_current_node( "\0preceded-by-nul-byte<rect /><circle></circle><foreignobject><div></div></foreignobject><g>" );

		$this->assertSame( 'svg', $fragment->get_namespace() );
		$this->assertTrue( $fragment->next_token() );

		/*
		 * In HTML parsing, a nul byte would be ignored.
		 * In SVG it should be replaced with a replacement character.
		 */
		$this->assertSame( '#text', $fragment->get_token_type() );
		$this->assertSame( "\u{FFFD}", $fragment->get_modifiable_text() );

		$this->assertTrue( $fragment->next_tag( 'RECT' ) );
		$this->assertSame( 'svg', $fragment->get_namespace() );

		$this->assertTrue( $fragment->next_tag( 'CIRCLE' ) );
		$this->assertSame( array( 'HTML', 'BODY', 'SVG', 'CIRCLE' ), $fragment->get_breadcrumbs() );
		$this->assertTrue( $fragment->next_tag( 'foreignObject' ) );
		$this->assertSame( 'svg', $fragment->get_namespace() );
	}

	/**
	 * @ticket 62357
	 */
	public function test_create_fragment_at_current_node_in_foreign_content_integration_point() {
		$processor = WP_HTML_Processor::create_full_parser( '<svg><foreignObject>' );
		$this->assertTrue( $processor->next_tag( 'foreignObject' ) );

		$fragment = $processor->create_fragment_at_current_node( "<image>\0not-preceded-by-nul-byte<rect />" );

		// HTML parsing transforms IMAGE into IMG.
		$this->assertTrue( $fragment->next_tag( 'IMG' ) );
		$this->assertSame( 'html', $fragment->get_namespace() );

		$this->assertTrue( $fragment->next_token() );

		// In HTML parsing, the nul byte is ignored and the text is reached.
		$this->assertSame( '#text', $fragment->get_token_type() );
		$this->assertSame( 'not-preceded-by-nul-byte', $fragment->get_modifiable_text() );

		/*
		 * svg:foreignObject is an HTML integration point, so the processor should be in the HTML namespace.
		 * RECT is an HTML element here, meaning it may have the self-closing flag but does not self-close.
		 */
		$this->assertTrue( $fragment->next_tag( 'RECT' ) );
		$this->assertSame( array( 'HTML', 'BODY', 'SVG', 'FOREIGNOBJECT', 'RECT' ), $fragment->get_breadcrumbs() );
		$this->assertSame( 'html', $fragment->get_namespace() );
		$this->assertTrue( $fragment->has_self_closing_flag() );
		$this->assertTrue( $fragment->expects_closer() );
	}

	/**
	 * @expectedIncorrectUsage WP_HTML_Processor::create_fragment_at_current_node
	 * @ticket 62357
	 */
	public function test_prevent_fragment_creation_on_closers() {
		$processor = WP_HTML_Processor::create_full_parser( '<p></p>' );
		$processor->next_tag( 'P' );
		$processor->next_tag(
			array(
				'tag_name'    => 'P',
				'tag_closers' => 'visit',
			)
		);
		$this->assertSame( 'P', $processor->get_tag() );
		$this->assertTrue( $processor->is_tag_closer() );
		$this->assertNull( $processor->create_fragment_at_current_node( '<i>fragment HTML</i>' ) );
	}

	/**
	 * Verifies that the fragment parser doesn't allow invalid context nodes.
	 *
	 * This includes void elements and self-contained elements because they can
	 * contain no inner HTML. Operations on self-contained elements should occur
	 * through methods such as {@see WP_HTML_Tag_Processor::set_modifiable_text}.
	 *
	 * @ticket 62584
	 *
	 * @dataProvider data_invalid_fragment_contexts
	 *
	 * @param string $context Invalid context node for fragment parser.
	 */
	public function test_rejects_invalid_fragment_contexts( string $context, string $doing_it_wrong_method_name ) {
		$this->setExpectedIncorrectUsage( "WP_HTML_Processor::{$doing_it_wrong_method_name}" );
		$this->assertNull(
			WP_HTML_Processor::create_fragment( 'just a test', $context ),
			"Should not have been able to create a fragment parser with context node {$context}"
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_fragment_contexts() {
		return array(
			/*
			 * Invalid contexts.
			 */
			/*
			 * The text node is confused with a virtual body open tag.
			 * This should fail to set a bookmark in `create_fragment`
			 * but currently does not, it slips through and fails in
			 * `create_fragment_at_current_node`.
			 */
			'Invalid text'          => array( 'just some text', 'create_fragment_at_current_node' ),
			'Invalid comment'       => array( '<!-- comment -->', 'create_fragment' ),
			'Invalid closing'       => array( '</div>', 'create_fragment' ),
			'Invalid DOCTYPE'       => array( '<!DOCTYPE html>', 'create_fragment' ),
			/*
			 * PLAINTEXT should appear in the unsupported elements, but at the
			 * moment it's completely unsupported by the processor so
			 * the context element cannot be found.
			 */
			'Unsupported PLAINTEXT' => array( '<plaintext>', 'create_fragment' ),

			/*
			 * Invalid contexts.
			 */
			'AREA'                  => array( '<area>', 'create_fragment_at_current_node' ),
			'BASE'                  => array( '<base>', 'create_fragment_at_current_node' ),
			'BASEFONT'              => array( '<basefont>', 'create_fragment_at_current_node' ),
			'BGSOUND'               => array( '<bgsound>', 'create_fragment_at_current_node' ),
			'BR'                    => array( '<br>', 'create_fragment_at_current_node' ),
			'COL'                   => array( '<table><colgroup><col>', 'create_fragment_at_current_node' ),
			'EMBED'                 => array( '<embed>', 'create_fragment_at_current_node' ),
			'FRAME'                 => array( '<frameset><frame>', 'create_fragment_at_current_node' ),
			'HR'                    => array( '<hr>', 'create_fragment_at_current_node' ),
			'IMG'                   => array( '<img>', 'create_fragment_at_current_node' ),
			'INPUT'                 => array( '<input>', 'create_fragment_at_current_node' ),
			'KEYGEN'                => array( '<keygen>', 'create_fragment_at_current_node' ),
			'LINK'                  => array( '<link>', 'create_fragment_at_current_node' ),
			'META'                  => array( '<meta>', 'create_fragment_at_current_node' ),
			'PARAM'                 => array( '<param>', 'create_fragment_at_current_node' ),
			'SOURCE'                => array( '<source>', 'create_fragment_at_current_node' ),
			'TRACK'                 => array( '<track>', 'create_fragment_at_current_node' ),
			'WBR'                   => array( '<wbr>', 'create_fragment_at_current_node' ),

			/*
			 * Unsupported elements. Include a tag closer to ensure the element can be found
			 * and does not pause the parser at an incomplete token.
			 */
			'IFRAME'                => array( '<iframe></iframe>', 'create_fragment_at_current_node' ),
			'NOEMBED'               => array( '<noembed></noembed>', 'create_fragment_at_current_node' ),
			'NOFRAMES'              => array( '<noframes></noframes>', 'create_fragment_at_current_node' ),
			'SCRIPT'                => array( '<script></script>', 'create_fragment_at_current_node' ),
			'SCRIPT with type'      => array( '<script type="javascript"></script>', 'create_fragment_at_current_node' ),
			'STYLE'                 => array( '<style></style>', 'create_fragment_at_current_node' ),
			'TEXTAREA'              => array( '<textarea></textarea>', 'create_fragment_at_current_node' ),
			'TITLE'                 => array( '<title></title>', 'create_fragment_at_current_node' ),
			'XMP'                   => array( '<xmp></xmp>', 'create_fragment_at_current_node' ),
		);
	}

	/**
	 * Test that the fragment parser rejects invalid fragment HTML in a given context.
	 *
	 * @ticket 62357
	 *
	 * @dataProvider data_invalid_fragement_contents
	 *
	 * @param string $context Fragment context.
	 * @param string $html    Fragment HTML.
	 */
	public function test_rejects_invalid_fragment_contents( string $context, string $html ) {
		$processor = WP_HTML_Processor::create_fragment( $html, $context );

		while ( $processor->next_token() ) {
			// Progress through the document.
		}
		$this->assertSame( 'unsupported', $processor->get_last_error() );
		$this->assertMatchesRegularExpression(
			'/^Cannot remove a locked element from the stack of [a-z ]+ elements.$/',
			$processor->get_unsupported_exception()->getMessage()
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_fragement_contents() {
		return array(
			'P in P'                 => array( '<p>', '<p>' ),
			/*
			 * The fragment shorthand is always in no-quirks mode.
			 * P > TABLE is allowed in quirks mode.
			 */
			'TABLE in P (no-quirks)' => array( '<p>', '<table>' ),
			'TD in TD'               => array( '<table><td>', '<td>' ),
			'LI in LI'               => array( '<ul><li>', '<li>' ),
			'OPTION in OPTION'       => array( '<select><option>', '<option>' ),
			'DIV in SVG'             => array( '<svg>', '<div>' ),

			'Active formatting i'    => array( '<div><span><i><i><i></span><div>', '</i>' ),
			'Active formatting a'    => array( '<div><span><a></span><div>', '</a>' ),
		);
	}

	/**
	 * Test that the fragment parser rejects invalid fragment HTML that would
	 * close a FORM element outside of the fragment.
	 *
	 * @ticket 62357
	 *
	 * @dataProvider data_invalid_fragement_form_contents
	 */
	public function test_rejects_invalid_fragment_form_closers( string $context, string $html ) {
		$processor = WP_HTML_Processor::create_fragment( $html, $context );
		while ( $processor->next_token() ) {
			// Progress through the document.
		}
		$this->assertSame( 'unsupported', $processor->get_last_error() );
		$this->assertSame(
			'Cannot pop locked FORM element.',
			$processor->get_unsupported_exception()->getMessage()
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_fragement_form_contents() {
		return array(
			'FORM closing FORM'                   => array( '<form>', '</form>' ),
			'FORM does not nest, not allowed'     => array( '<form>', '<form></form>' ),
			'Arbitrary nesting still closes FORM' => array( '<form>', '<div><p></form>' ),
		);
	}

	/**
	 * Test that the fragment parser rejects invalid fragment HTML that would
	 * close a FORM element outside of the fragment.
	 *
	 * Seeking adjusts state that could lose the context FORM element and allow bad HTML input.
	 *
	 * @ticket 62357
	 */
	public function test_rejects_invalid_form_closer_when_seeking() {
		$processor = WP_HTML_Processor::create_fragment( '<div 1><div 2></form>', '<form>' );
		$this->assertTrue( $processor->next_tag( 'DIV' ) );
		$this->assertTrue( $processor->set_bookmark( 'mark' ) );
		$this->assertTrue( $processor->next_tag( 'DIV' ) );
		$this->assertTrue( $processor->seek( 'mark' ) );

		while ( $processor->next_token() ) {
			// Progress through the document.
		}
		$this->assertSame( 'unsupported', $processor->get_last_error() );
		$this->assertSame(
			'Cannot pop locked FORM element.',
			$processor->get_unsupported_exception()->getMessage()
		);
	}
}
