<?php
/**
 * Tests for the server-side rendering of the Accordion block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_accordion
 */
class Tests_Blocks_RenderAccordion extends WP_UnitTestCase {

	/**
	 * Tests that the directives for the interactivity are added with the autoclose setting.
	 *
	 * @ticket 66575
	 *
	 * @dataProvider data_autoclose
	 *
	 * @param bool   $autoclose Whether the block closes the other items.
	 * @param string $expected  The expected value in the context.
	 */
	public function test_should_add_the_directives_of_the_interactivity( $autoclose, $expected ) {
		$output    = render_block_core_accordion( array( 'autoclose' => $autoclose ), '<div class="wp-block-accordion"><p>Items</p></div>' );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'wp-block-accordion' ) ) );
		$this->assertSame( 'core/accordion', $processor->get_attribute( 'data-wp-interactive' ) );
		$this->assertSame( '{ "autoclose": ' . $expected . ', "accordionItems": [] }', $processor->get_attribute( 'data-wp-context' ) );
		$this->assertStringContainsString( '<p>Items</p></div>', $output, 'The inner content should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_autoclose() {
		return array(
			'autoclose'    => array( true, 'true' ),
			'no autoclose' => array( false, 'false' ),
		);
	}

	/**
	 * Tests that the context is valid JSON.
	 *
	 * @ticket 66575
	 */
	public function test_should_add_a_context_that_is_valid_json() {
		$output    = render_block_core_accordion( array( 'autoclose' => true ), '<div class="wp-block-accordion"></div>' );
		$processor = new WP_HTML_Tag_Processor( $output );
		$processor->next_tag();

		$this->assertSame(
			array(
				'autoclose'      => true,
				'accordionItems' => array(),
			),
			json_decode( $processor->get_attribute( 'data-wp-context' ), true )
		);
	}

	/**
	 * Tests that the content that is empty is returned.
	 *
	 * @ticket 66575
	 *
	 * @dataProvider data_empty_content
	 *
	 * @param string|null $content The content.
	 */
	public function test_should_return_empty_content( $content ) {
		$this->assertSame( $content, render_block_core_accordion( array( 'autoclose' => true ), $content ) );
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
			'null'         => array( null ),
		);
	}

	/**
	 * Tests that the content without the class of the block is not changed.
	 *
	 * @ticket 66575
	 */
	public function test_should_not_change_content_without_the_class_of_the_block() {
		$content = '<div class="other"><p>Items</p></div>';

		$this->assertSame( $content, render_block_core_accordion( array( 'autoclose' => true ), $content ) );
	}

	/**
	 * Tests that only the first element with the class of the block is changed.
	 *
	 * @ticket 66575
	 */
	public function test_should_change_only_the_first_element_with_the_class_of_the_block() {
		$output = render_block_core_accordion( array( 'autoclose' => false ), '<div class="wp-block-accordion"><div class="wp-block-accordion"></div></div>' );

		$this->assertSame( 1, substr_count( $output, 'data-wp-interactive' ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66575
	 *
	 * @covers ::register_block_core_accordion
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/accordion' );

		if ( $original_block ) {
			unregister_block_type( 'core/accordion' );
		}

		try {
			register_block_core_accordion();
			$block_type = $registry->get_registered( 'core/accordion' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_accordion', $block_type->render_callback );
		} finally {
			if ( $registry->is_registered( 'core/accordion' ) ) {
				unregister_block_type( 'core/accordion' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
