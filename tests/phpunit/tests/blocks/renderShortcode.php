<?php
/**
 * Tests for the server-side rendering of the Shortcode block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group shortcode
 */
class Tests_Blocks_RenderShortcode extends WP_UnitTestCase {

	/**
	 * Tests that the content is formatted with paragraphs.
	 *
	 * @ticket 66523
	 *
	 * @covers ::render_block_core_shortcode
	 *
	 * @dataProvider data_contents
	 *
	 * @param string $content  Content of the block.
	 * @param string $expected Expected output.
	 */
	public function test_should_format_the_content_with_paragraphs( $content, $expected ) {
		$this->assertSame( $expected, render_block_core_shortcode( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contents() {
		return array(
			'an empty string' => array( '', '' ),
			'a line'          => array( 'Hello', "<p>Hello</p>\n" ),
			'a shortcode'     => array( '[gallery ids="1,2"]', "<p>[gallery ids=\"1,2\"]</p>\n" ),
			'two paragraphs'  => array( "One\n\nTwo", "<p>One</p>\n<p>Two</p>\n" ),
			'a line break'    => array( "One\nTwo", "<p>One<br />\nTwo</p>\n" ),
			'a block element' => array( '<div>Hello</div>', "<div>Hello</div>\n" ),
		);
	}

	/**
	 * Tests that the attributes do not change the output.
	 *
	 * @ticket 66523
	 *
	 * @covers ::render_block_core_shortcode
	 */
	public function test_should_ignore_the_attributes() {
		$this->assertSame(
			render_block_core_shortcode( array(), 'Hello' ),
			render_block_core_shortcode( array( 'text' => 'Other' ), 'Hello' )
		);
	}

	/**
	 * Tests that the output is the one of wpautop().
	 *
	 * @ticket 66523
	 *
	 * @covers ::render_block_core_shortcode
	 */
	public function test_should_return_the_output_of_wpautop() {
		$content = "<ul>\n<li>One</li>\n</ul>\nText after the list\n\n<p>Paragraph</p>";

		$this->assertSame( wpautop( $content ), render_block_core_shortcode( array(), $content ) );
	}

	/**
	 * Tests that the shortcodes are not run by the callback.
	 *
	 * @ticket 66523
	 *
	 * @covers ::render_block_core_shortcode
	 */
	public function test_should_not_run_the_shortcodes() {
		add_shortcode(
			'wp_test_block_shortcode',
			static function () {
				return 'RUN';
			}
		);

		$output = render_block_core_shortcode( array(), '[wp_test_block_shortcode]' );

		remove_shortcode( 'wp_test_block_shortcode' );

		$this->assertSame( "<p>[wp_test_block_shortcode]</p>\n", $output );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66523
	 *
	 * @covers ::register_block_core_shortcode
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/shortcode' );

		if ( $was_registered ) {
			unregister_block_type( 'core/shortcode' );
		}

		register_block_core_shortcode();
		$block_type = $registry->get_registered( 'core/shortcode' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/shortcode' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_shortcode', $block_type->render_callback );
	}
}
