<?php
/**
 * Unit tests covering WP_Block_Bindings_Registry functionality.
 *
 * @group block-bindings
 *
 * @covers WP_Block_Bindings_Registry
 *
 * @since 6.5.0
 * @package WordPress
 */
class WP_Block_Bindings_Registry_Test extends WP_UnitTestCase {

	/**
	* Test
	*
	* @covers WP_Block_Bindings_Registry::
	*/
	public function test_replace_html_for_paragraph_content() {
		$wp_block_bindings = WP_Block_Bindings_Registry::get_instance();

		$source_name        = 'test_source';
		$label              = 'Test Source';
		$get_value_callback = function () {
			return 'test source value';
		};

		$wp_block_bindings->register(
			$source_name,
			array(
				'label'              => $label,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":{"name":"test_source","attributes":{"value":"text_custom_field"}}}}}} --><p>This should not appear</p><!-- /wp:paragraph -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );

		$block = new WP_Block( $parsed_blocks[0] );

		$expected = '<p>test source value</p>';
		$result   = $block->render();

		// Check if the block content was updated correctly.
		$this->assertEquals( $expected, $result, 'The block content should be updated with the value returned by the source.' );
	}
}
