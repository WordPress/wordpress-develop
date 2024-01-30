<?php
/**
 * Unit tests covering the Block Bindings public API.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 *
 * @covers register_block_bindings_source
 */
class WP_Block_Bindings_Test extends WP_UnitTestCase {

	const SOURCE_NAME  = 'test/source';
	const SOURCE_LABEL = array(
		'label' => 'Test source',
	);

	/**
	 * Set up before each test.
	 *
	 * @since 6.5.0
	 */
	public function set_up() {
		foreach ( get_all_registered_block_bindings_sources() as $source_name => $source_properties ) {
			if ( str_starts_with( $source_name, 'test/' ) ) {
				unregister_block_bindings_source( $source_name );
			}
		}

		parent::set_up();
	}

	/**
	 * Test if the block content is updated with the value returned by the source.
	 *
	 * @ticket 60282
	 *
	 * @covers register_block_bindings_source
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

		// Check if the block content was updated correctly.
		$this->assertEquals( $expected, $result, 'The block content should be updated with the value returned by the source.' );
	}

	/**
	 * Test passing arguments to the source.
	 *
	 * @ticket 60282
	 *
	 * @covers register_block_bindings_source
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

		$key = 'test';

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source", "args": {"key": "$key"}}}}} --><p>This should not appear</p><!-- /wp:paragraph -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );

		$expected = "<p>The attribute name is 'content' and its binding has argument 'key' with value 'test'.</p>";
		$result   = $block->render();

		// Check if the block content was updated correctly.
		$this->assertEquals( $expected, $result, 'The block content should be updated with the value returned by the source.' );
	}
}
