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
class Tests_HtmlApi_WpHtmlProcessorComments extends WP_UnitTestCase {
	/**
	 * Ensures that normative Processing Instruction nodes are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_processing_instruction() {
		$processor = WP_HTML_Processor::create_fragment( '<?wp-bit{"just": "kidding"}?>' );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found comment token but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			WP_HTML_Processor::COMMENT_AS_PI_NODE_LOOKALIKE,
			$processor->get_comment_type(),
			'Should have detected a Processing Instruction-like invalid comment.'
		);

		$this->assertSame(
			'wp-bit',
			$processor->get_tag(),
			"Should have found PI target as tag name but found {$processor->get_tag()} instead."
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			'{"just": "kidding"}',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative Processing Instruction nodes are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_assertion_processing_instruction_descendant() {
		$processor = WP_HTML_Processor::create_fragment( '<div><?wp-bit{"just": "kidding"}?></div>' );
		$processor->next_token();
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found comment token but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			WP_HTML_Processor::COMMENT_AS_PI_NODE_LOOKALIKE,
			$processor->get_comment_type(),
			'Should have detected a Processing Instruction-like invalid comment.'
		);

		$this->assertSame(
			'wp-bit',
			$processor->get_tag(),
			"Should have found PI target as tag name but found {$processor->get_tag()} instead."
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			'{"just": "kidding"}',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}
}
