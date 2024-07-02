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
			'TH'                          => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY', 'TR', 'TH' ), WP_HTML_Processor_State::INSERTION_MODE_IN_CELL ),
			'TR'                          => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY', 'TR' ), WP_HTML_Processor_State::INSERTION_MODE_IN_ROW ),
			'TBODY'                       => array( array( 'HTML', 'BODY', 'TABLE', 'TBODY' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY ),
			'THEAD'                       => array( array( 'HTML', 'BODY', 'TABLE', 'THEAD' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY ),
			'TFOOT'                       => array( array( 'HTML', 'BODY', 'TABLE', 'TFOOT' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY ),
			'CAPTION'                     => array( array( 'HTML', 'BODY', 'TABLE', 'CAPTION' ), WP_HTML_Processor_State::INSERTION_MODE_IN_CAPTION ),
			'COLGROUP'                    => array( array( 'HTML', 'BODY', 'TABLE', 'COLGROUP' ), WP_HTML_Processor_State::INSERTION_MODE_IN_COLUMN_GROUP ),
			'TABLE'                       => array( array( 'HTML', 'BODY', 'TABLE' ), WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE ),
			'BODY'                        => array( array( 'HTML', 'BODY' ), WP_HTML_Processor_State::INSERTION_MODE_IN_BODY ),
			'FRAMESET'                    => array( array( 'HTML', 'BODY', 'FRAMESET' ), WP_HTML_Processor_State::INSERTION_MODE_IN_FRAMESET ),
			'Last element (DIV)'          => array( array( 'DIV' ), WP_HTML_Processor_State::INSERTION_MODE_IN_BODY ),
		);
	}

	/**
	 * @dataProvider data_insertion_mode_unsupported
	 *
	 * @ticket 61549
	 *
	 * @param array $stack_of_open_elements Stack of open elements.
	 */
	public function test_reset_insertion_mode_unsupported( array $stack_of_open_elements ): void {
		$this->expectException( WP_HTML_Unsupported_Exception::class );

		$state               = new WP_HTML_Processor_State();
		$state->context_node = array( 'BODY', array() );

		foreach ( $stack_of_open_elements as $i => $tag_name ) {
			if ( ! ctype_upper( $tag_name ) ) {
				throw new Error( 'Expected upper case tag names.' );
			}
			$state->stack_of_open_elements->push( new WP_HTML_Token( $i, $tag_name, false ) );
		}
		$state->reset_insertion_mode();
	}

	/**
	 * Data provider.
	 *
	 * These tests should be migrated to the supported tests as support for more elements is added.
	 *
	 * @return array[]
	 */
	public static function data_insertion_mode_unsupported(): array {
		return array(
			'TEMPLATE requires template insertion mode stack' => array( array( 'HTML', 'BODY', 'TEMPLATE' ) ),
			'HEAD requires more insertion modes' => array( array( 'HTML', 'HEAD' ) ),
			'HTML requires head pointer and insertion modes' => array( array( 'HTML' ) ),
		);
	}
}
