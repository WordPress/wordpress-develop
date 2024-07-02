<?php
/**
 * Unit tests covering WP_HTML_Processor_State functionality.
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
class Tests_HtmlApi_WpHtmlProcessorState extends WP_UnitTestCase {
	/**
	 * @dataProvider data_insertion_mode_cases
	 *
	 * @ticket 61549
	 *
	 * @param array $stack_of_open_elements   Stack of open elements.
	 * @param string $expected_insertion_mode Expected insertion mode after running the algorithm.
	 */
	public function test_reset_insertion_mode(
		array $stack_of_open_elements,
		string $expected_insertion_mode
	): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );

		foreach ( $stack_of_open_elements as $i => $tag_name ) {
			if ( ! ctype_upper( $tag_name ) ) {
				throw new Error( 'Expected upper case tag names.' );
			}
			$state->stack_of_open_elements->push( new WP_HTML_Token( $i, $tag_name, false ) );
		}
		$state->reset_insertion_mode();

		$this->assertSame( $expected_insertion_mode, $state->insertion_mode );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_insertion_mode_cases(): array {
		return array(
			'SELECT last element'         => array( array( 'SELECT' ), WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT ),
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
	 * @ticket 61549
	 */
	public function test_template_insertion_mode_reset(): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );
		array_push(
			$state->stack_of_template_insertion_modes,
			WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE,
			WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY,
			WP_HTML_Processor_State::INSERTION_MODE_IN_ROW,
			WP_HTML_Processor_State::INSERTION_MODE_IN_CELL,
			WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT_IN_TABLE
		);

		foreach ( array( 'TABLE', 'TBODY', 'TR', 'TD', 'SELECT', 'TEMPLATE' ) as $i => $tag_name ) {
			if ( ! ctype_upper( $tag_name ) ) {
				throw new Error( 'Expected upper case tag names.' );
			}
			$state->stack_of_open_elements->push( new WP_HTML_Token( $i, $tag_name, false ) );
		}
		$state->reset_insertion_mode();
		$this->assertSame(
			WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT_IN_TABLE,
			$state->insertion_mode
		);
	}

	/**
	 * @ticket 61549
	 */
	public function test_html_reset_insertion_mode_before_head(): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );

		$state->stack_of_open_elements->push( new WP_HTML_Token( 0, 'HTML', false ) );
		$state->reset_insertion_mode();
		$this->assertSame(
			WP_HTML_Processor_State::INSERTION_MODE_BEFORE_HEAD,
			$state->insertion_mode
		);
	}

	/**
	 * @ticket 61549
	 */
	public function test_html_reset_insertion_mode_after_head(): void {
		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );
		$state->head_element = new WP_HTML_Token( 'head', 'HEAD', false );

		$state->stack_of_open_elements->push( new WP_HTML_Token( 0, 'HTML', false ) );
		$state->reset_insertion_mode();
		$this->assertSame(
			WP_HTML_Processor_State::INSERTION_MODE_AFTER_HEAD,
			$state->insertion_mode
		);
	}
}
