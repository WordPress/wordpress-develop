<?php
/**
 * Tests for WP_Block_Metadata_Registry integration with register_block_type_from_metadata().
 *
 * @group blocks
 */
class Tests_Blocks_RegisterBlockTypeFromMetadataWithRegistry extends WP_UnitTestCase {
	private $temp_manifest_file;

	public function set_up() {
		parent::set_up();
		$this->temp_manifest_file = wp_tempnam( 'block-metadata-manifest' );
	}

	public function tear_down() {
		$this->unregister_test_blocks();
		unlink( $this->temp_manifest_file );
		parent::tear_down();
	}

	public function test_register_block_type_from_metadata_with_registry() {
		$plugin_path     = WP_PLUGIN_DIR . '/test-plugin';
		$block_json_path = $plugin_path . '/blocks/test-block/block.json';

		// Create a manifest file with metadata for our test block
		$manifest_data = array(
			'test-block' => array(
				'name'        => 'test-suite/test-block',
				'title'       => 'Custom Test Block',
				'category'    => 'widgets',
				'icon'        => 'smiley',
				'description' => 'A test block registered via WP_Block_Metadata_Registry',
				'supports'    => array( 'html' => false ),
				'textdomain'  => 'test-plugin',
			),
		);
		file_put_contents( $this->temp_manifest_file, '<?php return ' . var_export( $manifest_data, true ) . ';' );

		// Register the collection
		WP_Block_Metadata_Registry::register_collection( $plugin_path . '/blocks', $this->temp_manifest_file );

		// Attempt to register the block
		$registered_block = register_block_type_from_metadata( $block_json_path );

		// Assert that the block was registered successfully
		$this->assertInstanceOf( 'WP_Block_Type', $registered_block );
		$this->assertEquals( 'test-suite/test-block', $registered_block->name );
		$this->assertEquals( 'Custom Test Block', $registered_block->title );
		$this->assertEquals( 'widgets', $registered_block->category );
		$this->assertEquals( 'smiley', $registered_block->icon );
		$this->assertEquals( 'A test block registered via WP_Block_Metadata_Registry', $registered_block->description );
		$this->assertEquals( array( 'html' => false ), $registered_block->supports );
	}

	public function test_register_block_type_from_metadata_with_registry_and_override() {
		$plugin_path     = WP_PLUGIN_DIR . '/test-plugin-2';
		$block_json_path = $plugin_path . '/blocks/test-block/block.json';

		// Create a manifest file with metadata for our test block
		$manifest_data = array(
			'test-block' => array(
				'name'        => 'test-suite/test-block',
				'title'       => 'Custom Test Block',
				'category'    => 'widgets',
				'icon'        => 'smiley',
				'description' => 'A test block registered via WP_Block_Metadata_Registry',
				'supports'    => array( 'html' => false ),
			),
		);
		file_put_contents( $this->temp_manifest_file, '<?php return ' . var_export( $manifest_data, true ) . ';' );

		// Register the collection
		WP_Block_Metadata_Registry::register_collection( $plugin_path . '/blocks', $this->temp_manifest_file );

		// Attempt to register the block with some overrides
		$registered_block = register_block_type_from_metadata(
			$block_json_path,
			array(
				'title'    => 'Overridden Title',
				'supports' => array( 'html' => true ),
			)
		);

		// Assert that the block was registered successfully with overrides
		$this->assertInstanceOf( 'WP_Block_Type', $registered_block );
		$this->assertEquals( 'test-suite/test-block', $registered_block->name );
		$this->assertEquals( 'Overridden Title', $registered_block->title );
		$this->assertEquals( 'widgets', $registered_block->category );
		$this->assertEquals( 'smiley', $registered_block->icon );
		$this->assertEquals( 'A test block registered via WP_Block_Metadata_Registry', $registered_block->description );
		$this->assertEquals( array( 'html' => true ), $registered_block->supports );
	}

	private function unregister_test_blocks() {
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_name = 'test-suite/test-block';

		if ( $registry->is_registered( $block_name ) ) {
			$registry->unregister( $block_name );
		}
	}
}
