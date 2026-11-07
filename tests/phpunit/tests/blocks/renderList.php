<?php
/**
 * Tests for the class that the List block adds on the server.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::block_core_list_render
 */
class Tests_Blocks_RenderList extends WP_UnitTestCase {

	/**
	 * Tests the class that is added to the list.
	 *
	 * @ticket 66596
	 *
	 * @dataProvider data_content
	 *
	 * @param string $content  The content of the block.
	 * @param string $expected The expected output.
	 */
	public function test_should_add_the_class_to_the_first_list( $content, $expected ) {
		$this->assertSame( $expected, block_core_list_render( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content() {
		return array(
			'unordered list'      => array( '<ul><li>One</li></ul>', '<ul class="wp-block-list"><li>One</li></ul>' ),
			'ordered list'        => array( '<ol><li>One</li></ol>', '<ol class="wp-block-list"><li>One</li></ol>' ),
			'uppercase tag'       => array( '<UL><li>One</li></UL>', '<UL class="wp-block-list"><li>One</li></UL>' ),
			'existing class'      => array( '<ul class="has-large-font-size"><li>One</li></ul>', '<ul class="has-large-font-size wp-block-list"><li>One</li></ul>' ),
			'class already there' => array( '<ul class="wp-block-list"><li>One</li></ul>', '<ul class="wp-block-list"><li>One</li></ul>' ),
			'other attributes'    => array( '<ol start="3" reversed><li>One</li></ol>', '<ol class="wp-block-list" start="3" reversed><li>One</li></ol>' ),
			'nested list'         => array( '<ul><li>One<ul><li>Two</li></ul></li></ul>', '<ul class="wp-block-list"><li>One<ul><li>Two</li></ul></li></ul>' ),
			'after other element' => array( '<div><ol><li>One</li></ol></div>', '<div><ol class="wp-block-list"><li>One</li></ol></div>' ),
			'two lists'           => array( '<ul><li>One</li></ul><ol><li>Two</li></ol>', '<ul class="wp-block-list"><li>One</li></ul><ol><li>Two</li></ol>' ),
			'no list'             => array( '<p>Text</p>', '<p>Text</p>' ),
			'list item only'      => array( '<li>One</li>', '<li>One</li>' ),
			'definition list'     => array( '<dl><dt>Term</dt></dl>', '<dl><dt>Term</dt></dl>' ),
			'text only'           => array( 'Just text', 'Just text' ),
		);
	}

	/**
	 * Tests that empty content is returned as it is.
	 *
	 * @ticket 66596
	 *
	 * @dataProvider data_empty_content
	 *
	 * @param string $content The empty content.
	 */
	public function test_should_return_empty_content_as_it_is( $content ) {
		$this->assertSame( $content, block_core_list_render( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_content() {
		return array(
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
		);
	}

	/**
	 * Tests the class of the list when the block is rendered.
	 *
	 * @ticket 66596
	 */
	public function test_should_add_the_class_when_the_block_is_rendered() {
		$this->assertSame(
			'<ul class="wp-block-list"><li>Old list</li></ul>',
			do_blocks( '<!-- wp:list --><ul><li>Old list</li></ul><!-- /wp:list -->' )
		);
	}
}
