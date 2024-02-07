<?php
/**
 * Tests for Block Bindings integration with block rendering.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 */
class WP_Block_Bindings_Render extends WP_UnitTestCase {

	const SOURCE_NAME  = 'test/source';
	const SOURCE_LABEL = array(
		'label' => 'Test source',
	);

	/**
	 * Tear down after each test.
	 *
	 * @since 6.5.0
	 */
	public function tear_down() {
		foreach ( get_all_registered_block_bindings_sources() as $source_name => $source_properties ) {
			if ( str_starts_with( $source_name, 'test/' ) ) {
				unregister_block_bindings_source( $source_name );
			}
		}

		parent::tear_down();
	}

	/**
	 * Test if the block content is updated with the value returned by the source.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_update_block_with_value_from_source() {
		$get_value_callback = function () {
			return 'test source value';
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source"}}}} --><p>This should not appear</p><!-- /wp:paragraph -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );

		$expected = '<p>test source value</p>';
		$result   = $block->render();

		$this->assertEquals( $expected, $result, 'The block content should be updated with the value returned by the source.' );
	}

	/**
	 * Test passing arguments to the source.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_passing_arguments_to_source() {
		$get_value_callback = function ( $source_args, $block_instance, $attribute_name ) {
			$value = $source_args['key'];
			return "The attribute name is '$attribute_name' and its binding has argument 'key' with value '$value'.";
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$value         = 'test';
		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source", "args": {"key": "$value"}}}}} --><p>This should not appear</p><!-- /wp:paragraph -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );

		$expected = "<p>The attribute name is 'content' and its binding has argument 'key' with value '$value'.</p>";
		$result   = $block->render();

		$this->assertEquals( $expected, $result, 'The block content should be updated with the value returned by the source.' );
	}
}
