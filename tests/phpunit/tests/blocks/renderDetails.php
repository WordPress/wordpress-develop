<?php
/**
 * Tests for the server-side rendering of the Details block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::block_core_details_set_img_fetchpriority_low
 */
class Tests_Blocks_RenderDetails extends WP_UnitTestCase {

	/**
	 * Tests that the images of a closed block get a low fetch priority.
	 *
	 * @ticket 66574
	 */
	public function test_should_set_low_fetchpriority_on_images_of_a_closed_block() {
		$content = '<details class="wp-block-details"><summary>Title</summary><img src="a.jpg" alt=""><p>Text</p><img src="b.jpg" alt="" fetchpriority="high"></details>';

		$output = block_core_details_set_img_fetchpriority_low( $content, array( 'attrs' => array() ) );

		$this->assertSame( 2, substr_count( $output, 'fetchpriority="low"' ), 'Both images should have a low fetch priority.' );
		$this->assertStringNotContainsString( 'fetchpriority="high"', $output, 'An existing priority should be replaced.' );
		$this->assertStringContainsString( '<summary>Title</summary>', $output, 'The rest of the markup should not change.' );
	}

	/**
	 * Tests that a block that is closed by the attribute is handled like a closed block.
	 *
	 * @ticket 66574
	 *
	 * @dataProvider data_closed_attributes
	 *
	 * @param array $attrs The attributes of the block.
	 */
	public function test_should_set_low_fetchpriority_when_the_block_is_not_open( $attrs ) {
		$output = block_core_details_set_img_fetchpriority_low( '<details><img src="a.jpg" alt=""></details>', array( 'attrs' => $attrs ) );

		$this->assertStringContainsString( 'fetchpriority="low"', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_closed_attributes() {
		return array(
			'no attributes'     => array( array() ),
			'show content off'  => array( array( 'showContent' => false ) ),
			'show content null' => array( array( 'showContent' => null ) ),
		);
	}

	/**
	 * Tests that the content of an open block is not changed.
	 *
	 * @ticket 66574
	 */
	public function test_should_not_change_the_content_of_an_open_block() {
		$content = '<details open><img src="a.jpg" alt="" fetchpriority="high"></details>';

		$this->assertSame(
			$content,
			block_core_details_set_img_fetchpriority_low( $content, array( 'attrs' => array( 'showContent' => true ) ) )
		);
	}

	/**
	 * Tests that content without an image is not changed.
	 *
	 * @ticket 66574
	 */
	public function test_should_not_change_content_without_images() {
		$content = '<details class="wp-block-details"><summary>Title</summary><p>Text</p></details>';

		$this->assertSame( $content, block_core_details_set_img_fetchpriority_low( $content, array( 'attrs' => array() ) ) );
	}

	/**
	 * Tests that the content that is not a string is replaced with an empty string.
	 *
	 * @ticket 66574
	 *
	 * @dataProvider data_content_that_is_not_a_string
	 *
	 * @param mixed $content The content.
	 */
	public function test_should_return_an_empty_string_for_content_that_is_not_a_string( $content ) {
		$this->assertSame( '', block_core_details_set_img_fetchpriority_low( $content, array( 'attrs' => array( 'showContent' => true ) ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_that_is_not_a_string() {
		return array(
			'null'  => array( null ),
			'false' => array( false ),
			'array' => array( array( '<img>' ) ),
		);
	}

	/**
	 * Tests the filter of the rendered block.
	 *
	 * @ticket 66574
	 */
	public function test_should_be_a_filter_of_the_rendered_block() {
		$this->assertSame( 10, has_filter( 'render_block_core/details', 'block_core_details_set_img_fetchpriority_low' ) );

		$output = render_block(
			array(
				'blockName'    => 'core/details',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '<details class="wp-block-details"><summary>Title</summary><img src="a.jpg" alt=""></details>',
				'innerContent' => array( '<details class="wp-block-details"><summary>Title</summary><img src="a.jpg" alt=""></details>' ),
			)
		);

		$this->assertStringContainsString( 'fetchpriority="low"', $output );
	}

	/**
	 * Tests that the block is registered.
	 *
	 * @ticket 66574
	 *
	 * @covers ::register_block_core_details
	 */
	public function test_should_register_the_block() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/details' );

		if ( $original_block ) {
			unregister_block_type( 'core/details' );
		}

		try {
			register_block_core_details();

			$this->assertInstanceOf( WP_Block_Type::class, $registry->get_registered( 'core/details' ) );
		} finally {
			if ( $registry->is_registered( 'core/details' ) ) {
				unregister_block_type( 'core/details' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
