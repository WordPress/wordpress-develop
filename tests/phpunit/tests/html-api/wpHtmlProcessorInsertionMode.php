<?php
/**
 * Unit tests covering WP_HTML_Processor insertion mode functionality.
 *
 * The tests in this file are not behavioral tests for the output of the HTML API, but rather
 * internal tests ensuring a proper implementation of the insertion mode functionality.
 *
 * It's important that this works properly and much easier to test in isolation than through
 * the public interface of the HTML API.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.7.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor_State
 */
class Tests_HtmlApi_WpHtmlProcessorInsertionMode extends WP_UnitTestCase {
	/**
	 * Ensures that the "reset the insertion mode appropriately" algorithm
	 * is properly implemented according to the HTML specification.
	 *
	 * @see https://html.spec.whatwg.org/#reset-the-insertion-mode-appropriately
	 *
	 * @dataProvider data_insertion_mode_cases
	 *
	 * @ticket 61549
	 *
	 * @param array  $stack_of_open_elements  Stack of open elements.
	 * @param string $expected_insertion_mode Expected insertion mode after running the algorithm.
	 */
	public function test_reset_insertion_mode( array $stack_of_open_elements, string $expected_insertion_mode ): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );

		// Set up the stack of open elements in a specific configuration. Bypass internal rules.
		foreach ( $stack_of_open_elements as $bookmark_name => $tag_name ) {
			$this->assertTrue(
				self::is_tag_name( $tag_name ),
				"Expected a tag name in test setup, but given '{$tag_name}' instead: check test data provider."
			);
			$state->stack_of_open_elements->stack[] = new WP_HTML_Token( $bookmark_name, $tag_name, false );
		}

		$state->reset_insertion_mode();

		$this->assertSame(
			$expected_insertion_mode,
			$state->insertion_mode,
			'Failed to reset the insertion mode into the expected mode.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_insertion_mode_cases(): array {
		return array(
			'SELECT last element'         => array( array( 'HTML', 'SELECT' ), WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT ),
			'SELECT'                      => array( array( 'HTML', 'BODY', 'SELECT' ), WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT ),
			'SELECT in table'             => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY', 'TR', 'TD', 'SELECT' ), WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT_IN_TABLE ),
			'SELECT in template in table' => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY', 'TR', 'TD', 'TEMPLATE', 'SELECT' ), WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT ),
			'SELECT > OPTION'             => array( array( 'HTML', 'BODY', 'SELECT', 'OPTION' ), WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT ),
			'SELECT > OPTGROUP > OPTION'  => array( array( 'HTML', 'BODY', 'SELECT', 'OPTGROUP', 'OPTION' ), WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT ),
			'TD'                          => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY', 'TR', 'TD' ), WP_HTML_Processor_State::INSERTION_MODE_IN_CELL ),
			'TD (last element)'           => array( array( 'TD' ), WP_HTML_Processor_State::INSERTION_MODE_IN_BODY ),
			'TH'                          => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY', 'TR', 'TH' ), WP_HTML_Processor_State::INSERTION_MODE_IN_CELL ),
			'TH (last element)'           => array( array( 'TH' ), WP_HTML_Processor_State::INSERTION_MODE_IN_BODY ),
			'TR'                          => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY', 'TR' ), WP_HTML_Processor_State::INSERTION_MODE_IN_ROW ),
			'TBODY'                       => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY ),
			'THEAD'                       => array( array( 'HTML', 'BODY', 'TABLE', 'THEAD' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY ),
			'TFOOT'                       => array( array( 'HTML', 'BODY', 'TABLE', 'TFOOT' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY ),
			'CAPTION'                     => array( array( 'HTML', 'BODY', 'TABLE', 'CAPTION' ), WP_HTML_Processor_State::INSERTION_MODE_IN_CAPTION ),
			'COLGROUP'                    => array( array( 'HTML', 'BODY', 'TABLE', 'COLGROUP' ), WP_HTML_Processor_State::INSERTION_MODE_IN_COLUMN_GROUP ),
			'TABLE'                       => array( array( 'HTML', 'BODY', 'TABLE' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE ),
			'HEAD'                        => array( array( 'HTML', 'HEAD' ), WP_HTML_Processor_State::INSERTION_MODE_IN_HEAD ),
			'HEAD (last element)'         => array( array( 'HEAD' ), WP_HTML_Processor_State::INSERTION_MODE_IN_BODY ),
			'BODY'                        => array( array( 'HTML', 'BODY' ), WP_HTML_Processor_State::INSERTION_MODE_IN_BODY ),
			'FRAMESET'                    => array( array( 'HTML', 'BODY', 'FRAMESET' ), WP_HTML_Processor_State::INSERTION_MODE_IN_FRAMESET ),
			'Last element (DIV)'          => array( array( 'DIV' ), WP_HTML_Processor_State::INSERTION_MODE_IN_BODY ),
		);
	}

	/**
	 * Ensures that the "reset the insertion mode appropriately" algorithm properly
	 * takes into account any open stack of template insertion modes.
	 *
	 * @ticket 61549
	 */
	public function test_template_insertion_mode_reset(): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );

		// Set up the stack of template insertion modes in a specific configuration. Bypass internal rules.
		$newest_template_insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT_IN_TABLE;

		array_push(
			$state->stack_of_template_insertion_modes,
			WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE,
			WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY,
			WP_HTML_Processor_State::INSERTION_MODE_IN_ROW,
			WP_HTML_Processor_State::INSERTION_MODE_IN_CELL,
			$newest_template_insertion_mode
		);

		foreach ( array( 'TABLE', 'TBODY', 'TR', 'TD', 'SELECT', 'TEMPLATE' ) as $bookmark_name => $tag_name ) {
			$this->assertTrue(
				self::is_tag_name( $tag_name ),
				"Expected a tag name in test setup, but given '{$tag_name}' instead: check test data provider."
			);
			$state->stack_of_open_elements->stack[] = new WP_HTML_Token( $bookmark_name, $tag_name, false );
		}

		$state->reset_insertion_mode();

		$this->assertSame(
			$newest_template_insertion_mode,
			$state->insertion_mode,
			'Failed to reset insertion mode to the newest item in the template insertion mode stack.'
		);
	}

	/**
	 * Ensures that the "reset the insertion mode appropriately" algorithm properly
	 * resets for the fresh fragment parser state with BODY as the context node.
	 *
	 * @ticket 61549
	 */
	public function test_html_reset_insertion_mode_before_head(): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );

		// Set up the stack of open elements in a specific configuration. Bypass internal rules.
		$state->stack_of_open_elements->stack[] = new WP_HTML_Token( 0, 'HTML', false );

		$state->reset_insertion_mode();

		$this->assertSame(
			WP_HTML_Processor_State::INSERTION_MODE_BEFORE_HEAD,
			$state->insertion_mode,
			'Failed to properly reset insertion mode.'
		);
	}

	/**
	 * Ensures that the "reset the insertion mode appropriately" algorithm properly
	 * resets to AFTER HEAD when a head element is set.
	 *
	 * @ticket 61549
	 */
	public function test_html_reset_insertion_mode_after_head(): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );
		$state->head_element = new WP_HTML_Token( 'head', 'HEAD', false );

		// Set up the stack of open elements in a specific configuration. Bypass internal rules.
		$state->stack_of_open_elements->stack[] = new WP_HTML_Token( 0, 'HTML', false );

		$state->reset_insertion_mode();

		$this->assertSame(
			WP_HTML_Processor_State::INSERTION_MODE_AFTER_HEAD,
			$state->insertion_mode,
			'Failed to properly reset insertion mode.'
		);
	}

	// Test helper methods.

	/**
	 * Indicates if a given node name represents a tag name, vs. a comment,
	 * HTML doctype declaration, text node, etc…
	 *
	 * Example:
	 *
	 *     false === is_tag_name( '#text' );
	 *     false === is_tag_name( 'html' ); // This is a DOCTYPE declaration.
	 *     true  === is_tag_name( 'HTML' );
	 *
	 * @param string $node_name Node name as returned from the stack of open elements.
	 * @return bool
	 */
	private function is_tag_name( string $node_name ) {
		return ctype_upper( $node_name );
	}
}
