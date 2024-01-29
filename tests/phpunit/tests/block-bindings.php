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
	 * Set up before each test.
	 *
	 * @since 6.5.0
	 */
	public function set_up() {
		foreach ( get_all_registered_block_bindings_sources() as $source_name => $source_properties ) {
			unregister_block_bindings_source( $source_name );
		}

		parent::set_up();
	}

	/**
	* Test if the block content is updated with the value returned by the source.
	*
	* @since 6.5.0
	*
	* @covers WP_Block_Bindings_Registry::
	*/
	public function test_replace_html_for_paragraph_content() {
		$source_name        = 'test/source';
		$label              = 'Test Source';
		$get_value_callback = function () {
			return 'test source value';
		};

		register_block_bindings_source(
			$source_name,
			array(
				'label'              => $label,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":{"name":"test/source","attributes":{"value":"text_custom_field"}}}}}} --><p>This should not appear</p><!-- /wp:paragraph -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );

		$block = new WP_Block( $parsed_blocks[0] );

		$expected = '<p>test source value</p>';
		$result   = $block->render();

		// Check if the block content was updated correctly.
		$this->assertEquals( $expected, $result, 'The block content should be updated with the value returned by the source.' );
	}
}
