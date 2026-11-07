<?php
/**
 * Tests for the class that the Paragraph block adds on the server.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::block_core_paragraph_add_class
 */
class Tests_Blocks_RenderParagraph extends WP_UnitTestCase {

	/**
	 * Tests the class that is added to the paragraph.
	 *
	 * @ticket 66596
	 *
	 * @dataProvider data_content
	 *
	 * @param string $content  The content of the block.
	 * @param string $expected The expected output.
	 */
	public function test_should_add_the_class_to_the_first_paragraph( $content, $expected ) {
		$this->assertSame( $expected, block_core_paragraph_add_class( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content() {
		return array(
			'paragraph'           => array( '<p>Text</p>', '<p class="wp-block-paragraph">Text</p>' ),
			'uppercase tag'       => array( '<P>Text</P>', '<P class="wp-block-paragraph">Text</P>' ),
			'existing class'      => array( '<p class="has-drop-cap">Text</p>', '<p class="has-drop-cap wp-block-paragraph">Text</p>' ),
			'class already there' => array( '<p class="wp-block-paragraph">Text</p>', '<p class="wp-block-paragraph">Text</p>' ),
			'other attributes'    => array( '<p style="color:red" dir="rtl">Text</p>', '<p class="wp-block-paragraph" style="color:red" dir="rtl">Text</p>' ),
			'only the first one'  => array( '<p>One</p><p>Two</p>', '<p class="wp-block-paragraph">One</p><p>Two</p>' ),
			'after other element' => array( '<div><p>Text</p></div>', '<div><p class="wp-block-paragraph">Text</p></div>' ),
			'no paragraph'        => array( '<div>Text</div>', '<div>Text</div>' ),
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
		$this->assertSame( $content, block_core_paragraph_add_class( $content ) );
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
	 * Tests the class of the paragraph when the block is rendered.
	 *
	 * @ticket 66596
	 */
	public function test_should_add_the_class_when_the_block_is_rendered() {
		$this->assertSame(
			'<p class="wp-block-paragraph">Old paragraph</p>',
			do_blocks( '<!-- wp:paragraph --><p>Old paragraph</p><!-- /wp:paragraph -->' )
		);
	}

	/**
	 * Tests that the function is hooked to the render filter of the block.
	 *
	 * @ticket 66596
	 */
	public function test_should_be_hooked_to_the_render_filter_of_the_block() {
		$this->assertSame( 10, has_filter( 'render_block_core/paragraph', 'block_core_paragraph_add_class' ) );
	}
}
