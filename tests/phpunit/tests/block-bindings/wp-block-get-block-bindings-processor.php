<?php
/**
 * Tests for WP_Block::get_block_bindings_processor.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.9.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Blocks_GetBlockBindingsProcessor extends WP_UnitTestCase {

	private static $get_block_bindings_processor_method;

	public static function wpSetupBeforeClass() {
		self::$get_block_bindings_processor_method = new ReflectionMethod( 'WP_Block', 'get_block_bindings_processor' );
		if ( PHP_VERSION_ID < 80100 ) {
			self::$get_block_bindings_processor_method->setAccessible( true );
		}
	}

	/**
	 * @ticket 63840
	 */
	public function test_replace_rich_text() {
		$button_wrapper_opener = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">';
		$button_wrapper_closer = '</a></div>';

		$processor = self::$get_block_bindings_processor_method->invoke(
			null,
			$button_wrapper_opener . 'This should not appear' . $button_wrapper_closer
		);
		$processor->next_tag( array( 'tag_name' => 'a' ) );

		$this->assertTrue( $processor->replace_rich_text( 'The hardest button to button' ) );
		$this->assertEquals(
			$button_wrapper_opener . 'The hardest button to button' . $button_wrapper_closer,
			$processor->get_updated_html()
		);
	}

	/**
	 * @ticket 63840
	 */
	public function test_set_attribute_and_replace_rich_text() {
		$figure_opener = '<figure class="wp-block-image">';
		$img           = '<img src="breakfast.jpg" alt="" class="wp-image-1"/>';
		$figure_closer = '</figure>';
		$processor     = self::$get_block_bindings_processor_method->invoke(
			null,
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
			$processor->get_updated_html()
		);
	}

	/**
	 * @ticket 63840
	 */
	public function test_replace_rich_text_and_seek() {
		$figure_opener = '<figure class="wp-block-image">';
		$img           = '<img src="breakfast.jpg" alt="" class="wp-image-1"/>';
		$figure_closer = '</figure>';
		$processor     = self::$get_block_bindings_processor_method->invoke(
			null,
			$figure_opener .
			$img .
			'<figcaption class="wp-element-caption">Breakfast at a <em>café</em> in Berlin</figcaption>' .
			$figure_closer
		);

		$processor->next_tag( array( 'tag_name' => 'img' ) );
		$processor->set_bookmark( 'image' );

		$processor->next_tag( array( 'tag_name' => 'figcaption' ) );

		$this->assertTrue( $processor->replace_rich_text( '<strong>New</strong> image caption' ) );

		$processor->seek( 'image' );
		$processor->add_class( 'extra-img-class' );

		$this->assertEquals(
			$figure_opener .
			'<img src="breakfast.jpg" alt="" class="wp-image-1 extra-img-class"/>' .
			'<figcaption class="wp-element-caption"><strong>New</strong> image caption</figcaption>' .
			$figure_closer,
			$processor->get_updated_html()
		);
	}
}
