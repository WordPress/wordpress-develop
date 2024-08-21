<?php
/**
 * Unit tests covering WP_HTML_Processor fragment parsing functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.7.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorFragmentParsing extends WP_UnitTestCase {
	/**
	 * Verifies that the fragment parser doesn't allow invalid context nodes.
	 *
	 * This includes void elements and self-contained elements because they can
	 * contain no inner HTML. Operations on self-contained elements should occur
	 * through methods such as {@see WP_HTML_Tag_Processor::set_modifiable_text}.
	 *
	 * @ticket 61576
	 *
	 * @dataProvider data_invalid_fragment_contexts
	 *
	 * @expectedIncorrectUsage WP_HTML_Processor::create_fragment
	 *
	 * @param string $context Invalid context node for fragment parser.
	 */
	public function test_rejects_invalid_fragment_contexts( string $context ) {
		$this->assertNull(
			WP_HTML_Processor::create_fragment( 'just a test', $context ),
			"Should not have been able to create a fragment parser with context node {$context}"
		);
	}

	/**
	 * Data provider.
	 *
	 * @ticket 61576
	 *
	 * @return array[]
	 */
	public static function data_invalid_fragment_contexts() {
		return array(
			// Invalid contexts.
			'Invalid text'     => array( 'just some text' ),
			'Invalid comment'  => array( '<!-- comment -->' ),
			'Invalid closing'  => array( '</div>' ),
			'Invalid DOCTYPE'  => array( '<!DOCTYPE html>' ),

			// Void elements.
			'AREA'             => array( '<area>' ),
			'BASE'             => array( '<base>' ),
			'BASEFONT'         => array( '<basefont>' ),
			'BGSOUND'          => array( '<bgsound>' ),
			'BR'               => array( '<br>' ),
			'COL'              => array( '<col>' ),
			'EMBED'            => array( '<embed>' ),
			'FRAME'            => array( '<frame>' ),
			'HR'               => array( '<hr>' ),
			'IMG'              => array( '<img>' ),
			'INPUT'            => array( '<input>' ),
			'KEYGEN'           => array( '<keygen>' ),
			'LINK'             => array( '<link>' ),
			'META'             => array( '<meta>' ),
			'PARAM'            => array( '<param>' ),
			'SOURCE'           => array( '<source>' ),
			'TRACK'            => array( '<track>' ),
			'WBR'              => array( '<wbr>' ),

			// Self-contained elements.
			'IFRAME'           => array( '<iframe>' ),
			'NOEMBED'          => array( '<noembed>' ),
			'NOFRAMES'         => array( '<noframes>' ),
			'SCRIPT'           => array( '<script>' ),
			'SCRIPT with type' => array( '<script type="javascript">' ),
			'STYLE'            => array( '<style>' ),
			'TEXTAREA'         => array( '<textarea>' ),
			'TITLE'            => array( '<title>' ),
			'XMP'              => array( '<xmp>' ),
		);
	}
}
