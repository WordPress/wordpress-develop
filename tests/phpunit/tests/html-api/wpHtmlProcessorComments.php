<?php
/**
 * Unit tests covering WP_HTML_Processor functionality.
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
class Tests_HtmlApi_WpHtmlProcessorComments extends WP_UnitTestCase {
	/**
	 * Ensures that different types of comments are processed correctly.
	 *
	 * @ticket 61530
	 *
	 * @dataProvider data_comments
	 */
	public function test_comment_processing( $html, $expected_comment_type, $expected_modifiable_text, $expected_tag = null ) {
		$processor = WP_HTML_Processor::create_fragment( $html );
		$processor->next_token();

		$this->assertSame( '#comment', $processor->get_token_name() );
		$this->assertSame( $expected_comment_type, $processor->get_comment_type() );
		$this->assertSame( $expected_modifiable_text, $processor->get_modifiable_text() );
		$this->assertSame( $expected_tag, $processor->get_tag() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_comments() {
		return array(
			'Normative comment'              => array( '<!-- A comment. -->', WP_HTML_Processor::COMMENT_AS_HTML_COMMENT, ' A comment. ' ),
			'Abruptly closed comment'        => array( '<!-->', WP_HTML_Processor::COMMENT_AS_ABRUPTLY_CLOSED_COMMENT, '' ),
			'Invalid HTML comment !'         => array( '<! Bang opener >', WP_HTML_Processor::COMMENT_AS_INVALID_HTML, ' Bang opener ' ),
			'Invalid HTML comment ?'         => array( '<? Question opener >', WP_HTML_Processor::COMMENT_AS_INVALID_HTML, ' Question opener ' ),
			'CDATA comment'                  => array( '<![CDATA[ cdata body ]]>', WP_HTML_Processor::COMMENT_AS_CDATA_LOOKALIKE, ' cdata body ' ),
			'Processing instriction comment' => array( '<?pi-target Instruction body. ?>', WP_HTML_Processor::COMMENT_AS_PI_NODE_LOOKALIKE, ' Instruction body. ', 'pi-target' ),
			'Processing instriction php'     => array( '<?php const HTML_COMMENT = true; ?>', WP_HTML_Processor::COMMENT_AS_PI_NODE_LOOKALIKE, ' const HTML_COMMENT = true; ', 'php' ),
		);
	}

	/**
	 * Ensures that different types of comments are processed correctly.
	 *
	 * @ticket 61530
	 *
	 * @dataProvider data_funky_comments
	 */
	public function test_funky_comment( $html, $expected_modifiable_text ) {
		$processor = WP_HTML_Processor::create_fragment( $html );
		$processor->next_token();

		$this->assertSame( '#funky-comment', $processor->get_token_name() );
		$this->assertSame( $expected_modifiable_text, $processor->get_modifiable_text() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_funky_comments() {
		return array(
			'Funky comment # (empty)' => array( '</#>', '#' ),
			'Funky comment #'         => array( '</# foo>', '# foo' ),
			'Funky comment •'         => array( '</• bar>', '• bar' ),
		);
	}
}
