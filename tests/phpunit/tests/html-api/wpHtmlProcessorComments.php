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
	public function test_comment_processing( string $html, string $expected_token_name, string $expected_modifiable_text = '', string $expected_comment_type = null, string $expected_tag = null ) {
		$processor = WP_HTML_Processor::create_fragment( $html );
		$processor->next_token();

		$this->assertSame(
			$expected_token_name,
			$processor->get_token_name(),
			"Found incorrect token name {$processor->get_token_name()}."
		);

		$this->assertSame(
			$expected_comment_type,
			$processor->get_comment_type(),
			"Found incorrect comment type {$processor->get_comment_type()}."
		);

		$this->assertSame(
			$expected_modifiable_text,
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);

		$this->assertSame(
			$expected_tag,
			$processor->get_tag(),
			"Found incorrect comment \"tag\" {$processor->get_tag()}."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_comments() {
		return array(
			'Normative comment'              => array( '<!-- A comment. -->', '#comment', ' A comment. ', WP_HTML_Processor::COMMENT_AS_HTML_COMMENT ),
			'Abruptly closed comment'        => array( '<!-->', '#comment', '', WP_HTML_Processor::COMMENT_AS_ABRUPTLY_CLOSED_COMMENT ),
			'Invalid HTML comment !'         => array( '<! Bang opener >', '#comment', ' Bang opener ', WP_HTML_Processor::COMMENT_AS_INVALID_HTML ),
			'Invalid HTML comment ?'         => array( '<? Question opener >', '#comment', ' Question opener ', WP_HTML_Processor::COMMENT_AS_INVALID_HTML ),
			'Funky comment # (empty)'        => array( '</#>', '#funky-comment', '#' ),
			'Funky comment #'                => array( '</# foo>', '#funky-comment', '# foo' ),
			'Funky comment •'                => array( '</• bar>', '#funky-comment', '• bar' ),
			'Processing instriction comment' => array( '<?pi-target Instruction body. ?>', '#comment', ' Instruction body. ', WP_HTML_Processor::COMMENT_AS_PI_NODE_LOOKALIKE, 'pi-target' ),
			'Processing instriction php'     => array( '<?php const HTML_COMMENT = true; ?>', '#comment', ' const HTML_COMMENT = true; ', WP_HTML_Processor::COMMENT_AS_PI_NODE_LOOKALIKE, 'php' ),
			'CDATA comment'                  => array( '<![CDATA[ cdata body ]]>', '#comment', ' cdata body ', WP_HTML_Processor::COMMENT_AS_CDATA_LOOKALIKE ),
		);
	}
}
