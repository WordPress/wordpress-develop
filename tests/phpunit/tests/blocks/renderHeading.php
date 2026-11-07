<?php
/**
 * Tests for the class that the Heading block adds on the server.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::block_core_heading_render
 */
class Tests_Blocks_RenderHeading extends WP_UnitTestCase {

	/**
	 * Tests the class that is added to the heading.
	 *
	 * @ticket 66596
	 *
	 * @dataProvider data_content
	 *
	 * @param string $content  The content of the block.
	 * @param string $expected The expected output.
	 */
	public function test_should_add_the_class_to_the_first_heading( $content, $expected ) {
		$this->assertSame( $expected, block_core_heading_render( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content() {
		return array(
			'h1'                     => array( '<h1>Title</h1>', '<h1 class="wp-block-heading">Title</h1>' ),
			'h2'                     => array( '<h2>Title</h2>', '<h2 class="wp-block-heading">Title</h2>' ),
			'h3'                     => array( '<h3>Title</h3>', '<h3 class="wp-block-heading">Title</h3>' ),
			'h4'                     => array( '<h4>Title</h4>', '<h4 class="wp-block-heading">Title</h4>' ),
			'h5'                     => array( '<h5>Title</h5>', '<h5 class="wp-block-heading">Title</h5>' ),
			'h6'                     => array( '<h6>Title</h6>', '<h6 class="wp-block-heading">Title</h6>' ),
			'uppercase tag'          => array( '<H2>Title</H2>', '<H2 class="wp-block-heading">Title</H2>' ),
			'existing class'         => array( '<h2 class="has-text-align-center">Title</h2>', '<h2 class="has-text-align-center wp-block-heading">Title</h2>' ),
			'class already there'    => array( '<h2 class="wp-block-heading">Title</h2>', '<h2 class="wp-block-heading">Title</h2>' ),
			'other attributes'       => array( '<h2 id="top" style="color:red">Title</h2>', '<h2 class="wp-block-heading" id="top" style="color:red">Title</h2>' ),
			'only the first heading' => array( '<h2>One</h2><h3>Two</h3>', '<h2 class="wp-block-heading">One</h2><h3>Two</h3>' ),
			'after other elements'   => array( '<div><span>Text</span><h4>Title</h4></div>', '<div><span>Text</span><h4 class="wp-block-heading">Title</h4></div>' ),
			'no heading'             => array( '<p>Text</p>', '<p>Text</p>' ),
			'not a heading level'    => array( '<h7>Title</h7>', '<h7>Title</h7>' ),
			'text only'              => array( 'Just text', 'Just text' ),
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
		$this->assertSame( $content, block_core_heading_render( array(), $content ) );
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
	 * Tests the class of the heading when the block is rendered.
	 *
	 * @ticket 66596
	 */
	public function test_should_add_the_class_when_the_block_is_rendered() {
		$this->assertSame(
			'<h2 class="wp-block-heading">Old heading</h2>',
			do_blocks( '<!-- wp:heading --><h2>Old heading</h2><!-- /wp:heading -->' )
		);
	}
}
