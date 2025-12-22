<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor script tag functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Tag_Processor
 */
class Tests_HtmlApi_WpHtmlTagProcessorScriptTag extends WP_UnitTestCase {
	/**
	 * @ticket 64419
	 *
	 * @covers WP_HTML_Tag_Processor::is_javascript_script_tag
	 *
	 * @dataProvider data_is_javascript_script_tag
	 *
	 * @param string $html             HTML containing a script tag.
	 * @param bool   $expected_result  Whether the script tag should be identified as JavaScript.
	 */
	public function test_is_javascript_script_tag( string $html, bool $expected_result ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();

		$result = ( function () {
			return $this->is_javascript_script_tag();
		} )->call( $processor );
		$this->assertSame(
			$expected_result,
			$result,
			'Failed to correctly identify JavaScript script tag'
		);
	}

	/**
	 * Data provider for test_is_javascript_script_tag.
	 *
	 * @return array[]
	 */
	public static function data_is_javascript_script_tag(): array {
		return array(
			// Script tags without type or language attributes - should be JavaScript.
			'Script tag without attributes'              => array( '<script></script>', true ),
			'Script tag with other attributes'           => array( '<script id="test"></script>', true ),

			// Script tags with empty type attribute - should be JavaScript.
			'Script tag with empty type attribute'       => array( '<script type=""></script>', true ),
			'Script tag with boolean type attribute'     => array( '<script type></script>', true ),

			// Script tags with falsy but non-empty type attribute.
			'Script tag with type="0"'                   => array( '<script type="0"></script>', false ),

			// Script tags without type but with language attribute - should be JavaScript.
			'Script tag with empty language attribute'   => array( '<script language=""></script>', true ),
			'Script tag with boolean language attribute' => array( '<script language></script>', true ),

			// Script tags with falsy but non-empty language attribute.
			'Script tag with language="0"'               => array( '<script language="0"></script>', false ),

			// Script tags with JavaScript MIME essence - should be JavaScript.
			'Script tag with application/ecmascript'     => array( '<script type="application/ecmascript"></script>', true ),
			'Script tag with application/javascript'     => array( '<script type="application/javascript"></script>', true ),
			'Script tag with application/x-ecmascript'   => array( '<script type="application/x-ecmascript"></script>', true ),
			'Script tag with application/x-javascript'   => array( '<script type="application/x-javascript"></script>', true ),
			'Script tag with text/ecmascript'            => array( '<script type="text/ecmascript"></script>', true ),
			'Script tag with text/javascript'            => array( '<script type="text/javascript"></script>', true ),
			'Script tag with text/javascript1.0'         => array( '<script type="text/javascript1.0"></script>', true ),
			'Script tag with text/javascript1.1'         => array( '<script type="text/javascript1.1"></script>', true ),
			'Script tag with text/javascript1.2'         => array( '<script type="text/javascript1.2"></script>', true ),
			'Script tag with text/javascript1.3'         => array( '<script type="text/javascript1.3"></script>', true ),
			'Script tag with text/javascript1.4'         => array( '<script type="text/javascript1.4"></script>', true ),
			'Script tag with text/javascript1.5'         => array( '<script type="text/javascript1.5"></script>', true ),
			'Script tag with text/jscript'               => array( '<script type="text/jscript"></script>', true ),
			'Script tag with text/livescript'            => array( '<script type="text/livescript"></script>', true ),
			'Script tag with text/x-ecmascript'          => array( '<script type="text/x-ecmascript"></script>', true ),
			'Script tag with text/x-javascript'          => array( '<script type="text/x-javascript"></script>', true ),

			// Case-insensitive matching for JavaScript MIME essence.
			'Script tag with UPPERCASE type'             => array( '<script type="TEXT/JAVASCRIPT"></script>', true ),
			'Script tag with MixedCase type'             => array( '<script type="Text/JavaScript"></script>', true ),
			'Script tag with APPLICATION/JAVASCRIPT'     => array( '<script type="APPLICATION/JAVASCRIPT"></script>', true ),

			// Script tags with module type - should be JavaScript.
			'Script tag with module type'                => array( '<script type="module"></script>', true ),
			'Script tag with MODULE type uppercase'      => array( '<script type="MODULE"></script>', true ),
			'Script tag with MoDuLe type mixed case'     => array( '<script type="MoDuLe"></script>', true ),

			// Script tags with whitespace around type - should strip whitespace.
			'Script tag with leading whitespace'         => array( '<script type=" text/javascript"></script>', true ),
			'Script tag with trailing whitespace'        => array( '<script type="text/javascript "></script>', true ),
			'Script tag with surrounding whitespace'     => array( '<script type=" text/javascript "></script>', true ),
			'Script tag with tab whitespace'             => array( "<script type=\"\ttext/javascript\t\"></script>", true ),
			'Script tag with newline whitespace'         => array( "<script type=\"\ntext/javascript\n\"></script>", true ),
			'Script tag with mixed whitespace'           => array( "<script type=\" \t\ntext/javascript \t\n\"></script>", true ),

			// Script tags with language attribute and non-empty value - should use text/{language}.
			'Script tag with language="javascript"'      => array( '<script language="javascript"></script>', true ),
			'Script tag with language="JavaScript"'      => array( '<script language="JavaScript"></script>', true ),
			'Script tag with language="ecmascript"'      => array( '<script language="ecmascript"></script>', true ),
			'Script tag with language="jscript"'         => array( '<script language="jscript"></script>', true ),
			'Script tag with language="livescript"'      => array( '<script language="livescript"></script>', true ),

			// Whitespace is not trimmed in the language attribute.
			'Script tag with language=" javascript"'     => array( '<script language=" javascript"></script>', false ),

			// Non-JavaScript script tags - should NOT be JavaScript.
			'Script tag with importmap type'             => array( '<script type="importmap"></script>', false ),
			'Script tag with speculationrules type'      => array( '<script type="speculationrules"></script>', false ),
			'Script tag with application/json type'      => array( '<script type="application/json"></script>', false ),
			'Script tag with text/json type'             => array( '<script type="text/json"></script>', false ),
			'Script tag with unknown MIME type'          => array( '<script type="text/plain"></script>', false ),
			'Script tag with application/xml type'       => array( '<script type="application/xml"></script>', false ),
			'Script tag with random type'                => array( '<script type="random/type"></script>', false ),

			// Non-script tags - should NOT be JavaScript.
			'DIV tag'                                    => array( '<div></div>', false ),
			'SPAN tag'                                   => array( '<span></span>', false ),
			'P tag'                                      => array( '<p></p>', false ),
		);
	}

	/**
	 * @ticket 64419
	 *
	 * @covers WP_HTML_Tag_Processor::is_javascript_script_tag
	 */
	public function test_is_javascript_script_tag_returns_false_before_finding_tags() {
		$processor = new WP_HTML_Tag_Processor( 'Just some text' );
		$processor->next_token();
		$result = ( function () {
			return $this->is_javascript_script_tag();
		} )->call( $processor );
		$this->assertFalse(
			$result,
			'Should return false when not stopped on script tag'
		);
	}

	/**
	 * @ticket 64419
	 *
	 * @covers WP_HTML_Tag_Processor::is_javascript_script_tag
	 */
	public function test_is_javascript_script_tag_returns_false_for_non_html_namespace() {
		$processor = new WP_HTML_Tag_Processor( '<script></script>' );
		$processor->change_parsing_namespace( 'svg' );
		$processor->next_tag();
		$this->assertSame( 'SCRIPT', $processor->get_tag() );
		$result = ( function () {
			return $this->is_javascript_script_tag();
		} )->call( $processor );
		$this->assertFalse(
			$result,
			'Should return false for script tags in non-HTML namespace'
		);
	}

	/**
	 * @ticket 64419
	 *
	 * @covers WP_HTML_Tag_Processor::is_json_script_tag
	 *
	 * @dataProvider data_is_json_script_tag
	 *
	 * @param string $html             HTML containing a script tag.
	 * @param bool   $expected_result  Whether the script tag should be identified as JSON.
	 */
	public function test_is_json_script_tag( string $html, bool $expected_result ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();
		$result = ( function () {
			return $this->is_json_script_tag();
		} )->call( $processor );
		$this->assertSame(
			$expected_result,
			$result,
			'Failed to correctly identify JSON script tag'
		);
	}

	/**
	 * Data provider for test_is_json_script_tag.
	 *
	 * @return array[]
	 */
	public static function data_is_json_script_tag(): array {
		return array(
			// JSON MIME types - should be JSON.
			'Script tag with application/json type'      => array( '<script type="application/json"></script>', true ),
			'Script tag with text/json type'             => array( '<script type="text/json"></script>', true ),

			// importmap and speculationrules - should be JSON.
			'Script tag with importmap type'             => array( '<script type="importmap"></script>', true ),
			'Script tag with speculationrules type'      => array( '<script type="speculationrules"></script>', true ),

			// Case-insensitive matching for JSON types.
			'Script tag with APPLICATION/JSON uppercase' => array( '<script type="APPLICATION/JSON"></script>', true ),
			'Script tag with Text/Json mixed case'       => array( '<script type="Text/Json"></script>', true ),
			'Script tag with IMPORTMAP uppercase'        => array( '<script type="IMPORTMAP"></script>', true ),
			'Script tag with ImportMap mixed case'       => array( '<script type="ImportMap"></script>', true ),
			'Script tag with SPECULATIONRULES uppercase' => array( '<script type="SPECULATIONRULES"></script>', true ),
			'Script tag with SpeculationRules mixed'     => array( '<script type="SpeculationRules"></script>', true ),

			// Whitespace handling - should strip whitespace.
			'Script tag with leading whitespace'         => array( '<script type=" application/json"></script>', true ),
			'Script tag with trailing whitespace'        => array( '<script type="application/json "></script>', true ),
			'Script tag with surrounding whitespace'     => array( '<script type=" application/json "></script>', true ),
			'Script tag with tab whitespace'             => array( "<script type=\"\tapplication/json\t\"></script>", true ),
			'Script tag with newline whitespace'         => array( "<script type=\"\napplication/json\n\"></script>", true ),
			'Script tag with mixed whitespace'           => array( "<script type=\" \t\napplication/json \t\n\"></script>", true ),

			// Non-JSON script tags - should NOT be JSON.
			'Script tag without type attribute'          => array( '<script></script>', false ),
			'Script tag with empty type attribute'       => array( '<script type=""></script>', false ),
			'Script tag with boolean type attribute'     => array( '<script type></script>', false ),

			// Script tags with falsy but non-empty type attribute.
			'Script tag with type="0"'                   => array( '<script type="0"></script>', false ),

			'Script tag with text/javascript type'       => array( '<script type="text/javascript"></script>', false ),
			'Script tag with module type'                => array( '<script type="module"></script>', false ),
			'Script tag with unknown MIME type'          => array( '<script type="text/plain"></script>', false ),
			'Script tag with application/xml type'       => array( '<script type="application/xml"></script>', false ),

			// Non-script tags - should NOT be JSON.
			'DIV tag'                                    => array( '<div></div>', false ),
			'SPAN tag'                                   => array( '<span></span>', false ),
			'P tag'                                      => array( '<p></p>', false ),
		);
	}

	/**
	 * @ticket 64419
	 *
	 * @covers WP_HTML_Tag_Processor::is_json_script_tag
	 */
	public function test_is_json_script_tag_returns_false_before_finding_tags() {
		$processor = new WP_HTML_Tag_Processor( 'Just some text' );
		$processor->next_token();
		$result = ( function () {
			return $this->is_json_script_tag();
		} )->call( $processor );
		$this->assertFalse(
			$result,
			'Should return false when not stopped on script tag'
		);
	}

	/**
	 * @ticket 64419
	 *
	 * @covers WP_HTML_Tag_Processor::is_json_script_tag
	 */
	public function test_is_json_script_tag_returns_false_for_non_html_namespace() {
		$processor = new WP_HTML_Tag_Processor( '<script></script>' );
		$processor->change_parsing_namespace( 'svg' );
		$processor->next_tag();
		$this->assertSame( 'SCRIPT', $processor->get_tag() );
		$result = ( function () {
			return $this->is_json_script_tag();
		} )->call( $processor );
		$this->assertFalse(
			$result,
			'Should return false for script tags in non-HTML namespace'
		);
	}
}
