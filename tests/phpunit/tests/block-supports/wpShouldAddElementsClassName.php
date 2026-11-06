<?php

/**
 * Tests for the wp_should_add_elements_class_name() function.
 *
 * @group block-supports
 *
 * @covers ::wp_should_add_elements_class_name
 */
class Tests_Block_Supports_WpShouldAddElementsClassName extends WP_UnitTestCase {

	/**
	 * Gets a block with styles for the elements.
	 *
	 * @param array $elements Styles of the elements.
	 * @return array Block.
	 */
	private function get_block( array $elements ) {
		return array(
			'blockName' => 'core/group',
			'attrs'     => array( 'style' => array( 'elements' => $elements ) ),
		);
	}

	/**
	 * Tests that the class name is added when a color of a supported element is set.
	 *
	 * @ticket 66510
	 *
	 * @dataProvider data_element_colors
	 *
	 * @param string $element  Element.
	 * @param array  $elements Styles of the elements.
	 */
	public function test_should_return_true_when_a_color_of_an_element_is_set( $element, $elements ) {
		$this->assertTrue( wp_should_add_elements_class_name( $this->get_block( $elements ), array() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_element_colors() {
		$data = array(
			'button text color'     => array( 'button', array( 'button' => array( 'color' => array( 'text' => '#111111' ) ) ) ),
			'button background'     => array( 'button', array( 'button' => array( 'color' => array( 'background' => '#222222' ) ) ) ),
			'button gradient'       => array( 'button', array( 'button' => array( 'color' => array( 'gradient' => 'linear-gradient(#000, #fff)' ) ) ) ),
			'link text color'       => array( 'link', array( 'link' => array( 'color' => array( 'text' => '#333333' ) ) ) ),
			'link hover text color' => array( 'link', array( 'link' => array( ':hover' => array( 'color' => array( 'text' => '#444444' ) ) ) ) ),
			'heading text color'    => array( 'heading', array( 'heading' => array( 'color' => array( 'text' => '#555555' ) ) ) ),
			'heading background'    => array( 'heading', array( 'heading' => array( 'color' => array( 'background' => '#666666' ) ) ) ),
			'heading gradient'      => array( 'heading', array( 'heading' => array( 'color' => array( 'gradient' => 'linear-gradient(#000, #fff)' ) ) ) ),
		);

		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $level ) {
			$data[ "$level text color" ] = array( 'heading', array( $level => array( 'color' => array( 'text' => '#777777' ) ) ) );
			$data[ "$level background" ] = array( 'heading', array( $level => array( 'color' => array( 'background' => '#888888' ) ) ) );
			$data[ "$level gradient" ]   = array( 'heading', array( $level => array( 'color' => array( 'gradient' => 'linear-gradient(#000, #fff)' ) ) ) );
		}

		return $data;
	}

	/**
	 * Tests that the class name is not added when the block has no styles for the elements.
	 *
	 * @ticket 66510
	 *
	 * @dataProvider data_blocks_without_elements
	 *
	 * @param array $block Block.
	 */
	public function test_should_return_false_when_the_block_has_no_elements( $block ) {
		$this->assertFalse( wp_should_add_elements_class_name( $block, array() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_blocks_without_elements() {
		return array(
			'no attributes'          => array( array( 'blockName' => 'core/group' ) ),
			'empty attributes'       => array( array( 'attrs' => array() ) ),
			'no style'               => array( array( 'attrs' => array( 'className' => 'x' ) ) ),
			'style without elements' => array( array( 'attrs' => array( 'style' => array( 'color' => array( 'text' => '#000000' ) ) ) ) ),
			'null elements'          => array( array( 'attrs' => array( 'style' => array( 'elements' => null ) ) ) ),
		);
	}

	/**
	 * Tests that the class name is not added for styles that are not colors of the supported elements.
	 *
	 * @ticket 66510
	 *
	 * @dataProvider data_elements_without_colors
	 *
	 * @param array $elements Styles of the elements.
	 */
	public function test_should_return_false_without_a_color_of_a_supported_element( $elements ) {
		$this->assertFalse( wp_should_add_elements_class_name( $this->get_block( $elements ), array() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_elements_without_colors() {
		return array(
			'empty elements'                         => array( array() ),
			'typography of a button'                 => array( array( 'button' => array( 'typography' => array( 'fontSize' => '2rem' ) ) ) ),
			'a color property that is not supported' => array( array( 'button' => array( 'color' => array( 'other' => '#000000' ) ) ) ),
			'a color of another element'             => array( array( 'caption' => array( 'color' => array( 'text' => '#000000' ) ) ) ),
			'a color of the cite element'            => array( array( 'cite' => array( 'color' => array( 'text' => '#000000' ) ) ) ),
			'hover color of a button'                => array( array( 'button' => array( ':hover' => array( 'color' => array( 'text' => '#000000' ) ) ) ) ),
			'background of a link hover'             => array( array( 'link' => array( ':hover' => array( 'color' => array( 'background' => '#000000' ) ) ) ) ),
			'heading level that does not exist'      => array( array( 'h7' => array( 'color' => array( 'text' => '#000000' ) ) ) ),
			'empty color'                            => array( array( 'link' => array( 'color' => array() ) ) ),
		);
	}

	/**
	 * Tests that a color that is null does not add the class name.
	 *
	 * @ticket 66510
	 */
	public function test_should_return_false_when_the_color_is_null() {
		$elements = array( 'link' => array( 'color' => array( 'text' => null ) ) );

		$this->assertFalse( wp_should_add_elements_class_name( $this->get_block( $elements ), array() ) );
	}

	/**
	 * Tests that the elements that are skipped in the options are ignored.
	 *
	 * @ticket 66510
	 *
	 * @dataProvider data_skipped_elements
	 *
	 * @param array $elements Styles of the elements.
	 * @param array $options  Options of the block support.
	 * @param bool  $expected Whether the class name should be added.
	 */
	public function test_should_ignore_the_elements_that_are_skipped( $elements, $options, $expected ) {
		$this->assertSame( $expected, wp_should_add_elements_class_name( $this->get_block( $elements ), $options ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_skipped_elements() {
		$button  = array( 'button' => array( 'color' => array( 'text' => '#111111' ) ) );
		$link    = array( 'link' => array( 'color' => array( 'text' => '#222222' ) ) );
		$heading = array( 'h2' => array( 'color' => array( 'background' => '#333333' ) ) );

		return array(
			'skipped button'                    => array( $button, array( 'button' => array( 'skip' => true ) ), false ),
			'skipped link'                      => array( $link, array( 'link' => array( 'skip' => true ) ), false ),
			'skipped heading'                   => array( $heading, array( 'heading' => array( 'skip' => true ) ), false ),
			'button is not skipped'             => array( $button, array( 'button' => array( 'skip' => false ) ), true ),
			'another element is skipped'        => array( $button, array( 'link' => array( 'skip' => true ) ), true ),
			'options without a skip'            => array( $link, array( 'link' => array() ), true ),
			'a skipped element and another one' => array( array_merge( $button, $link ), array( 'button' => array( 'skip' => true ) ), true ),
			'all the elements are skipped'      => array(
				array_merge( $button, $link, $heading ),
				array(
					'button'  => array( 'skip' => true ),
					'link'    => array( 'skip' => true ),
					'heading' => array( 'skip' => true ),
				),
				false,
			),
		);
	}
}
