<?php
/**
 * Tests for WP_Block_Bindings_Processor.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Blocks_wpBlockBindingsProcessor extends WP_UnitTestCase {
	public function test_set_attribute_and_replace_rich_text() {
		$figure_opener = '<figure class="wp-block-image">';
		$img           = '<img src="breakfast.jpg" alt="" class="wp-image-1"/>';
		$figure_closer = '</figure>';
		$processor     = WP_Block_Bindings_Processor::create_fragment(
			$figure_opener .
			$img .
			'<figcaption class="wp-element-caption">Breakfast at a <em>café</em> in Berlin</figcaption>' .
			$figure_closer
		);

		$processor->next_tag( array( 'tag_name' => 'figure' ) );
		$processor->add_class( 'size-large' );

		$processor->next_tag( array( 'tag_name' => 'figcaption' ) );

		$this->assertTrue( $processor->replace_rich_text( '<strong>New</strong> image caption' ) );
		$this->assertEquals(
			'<figure class="wp-block-image size-large">' .
			$img .
			'<figcaption class="wp-element-caption"><strong>New</strong> image caption</figcaption>' .
			$figure_closer,
			$processor->build()
		);
	}
}
