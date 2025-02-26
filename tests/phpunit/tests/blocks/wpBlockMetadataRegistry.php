<?php

/**
 * Tests for WP_Block_Metadata_Registry class.
 *
 * @group blocks
 */
class Tests_Blocks_WpBlockMetadataRegistry extends WP_UnitTestCase {

	private $temp_manifest_file;

	public function set_up() {
		parent::set_up();
		$this->temp_manifest_file = wp_tempnam( 'block-metadata-manifest' );
	}

	public function tear_down() {
		unlink( $this->temp_manifest_file );
		parent::tear_down();
	}

	public function test_register_collection_and_get_metadata() {
		$path          = WP_PLUGIN_DIR . '/test/path';
		$manifest_data = array(
			'test-block' => array(
				'name'  => 'test-block',
				'title' => 'Test Block',
			),
		);

		file_put_contents( $this->temp_manifest_file, '<?php return ' . var_export( $manifest_data, true ) . ';' );

		WP_Block_Metadata_Registry::register_collection( $path, $this->temp_manifest_file );

		$retrieved_metadata = WP_Block_Metadata_Registry::get_metadata( $path . '/test-block' );
		$this->assertEquals( $manifest_data['test-block'], $retrieved_metadata );
	}

	public function test_get_nonexistent_metadata() {
		$path               = WP_PLUGIN_DIR . '/nonexistent/path';
		$retrieved_metadata = WP_Block_Metadata_Registry::get_metadata( $path . '/nonexistent-block' );
		$this->assertNull( $retrieved_metadata );
	}

	public function test_has_metadata() {
			$path          = WP_PLUGIN_DIR . '/another/test/path';
			$manifest_data = array(
				'existing-block' => array(
					'name'  => 'existing-block',
					'title' => 'Existing Block',
				),
			);

			file_put_contents( $this->temp_manifest_file, '<?php return ' . var_export( $manifest_data, true ) . ';' );

			WP_Block_Metadata_Registry::register_collection( $path, $this->temp_manifest_file );

			$this->assertTrue( WP_Block_Metadata_Registry::has_metadata( $path . '/existing-block' ) );
			$this->assertFalse( WP_Block_Metadata_Registry::has_metadata( $path . '/non-existing-block' ) );
	}

	public function test_register_collection_with_core_path() {
		$core_path = ABSPATH . WPINC . '/blocks';
		$result    = WP_Block_Metadata_Registry::register_collection( $core_path, $this->temp_manifest_file );
		$this->assertTrue( $result, 'Core path should be registered successfully' );
	}

	public function test_register_collection_with_valid_plugin_path() {
		$plugin_path = WP_PLUGIN_DIR . '/my-plugin/blocks';
		$result      = WP_Block_Metadata_Registry::register_collection( $plugin_path, $this->temp_manifest_file );
		$this->assertTrue( $result, 'Valid plugin path should be registered successfully' );
	}

	public function test_register_collection_with_invalid_plugin_path() {
		$invalid_plugin_path = WP_PLUGIN_DIR;

		$this->setExpectedIncorrectUsage( 'WP_Block_Metadata_Registry::register_collection' );

		$result = WP_Block_Metadata_Registry::register_collection( $invalid_plugin_path, $this->temp_manifest_file );
		$this->assertFalse( $result, 'Invalid plugin path should not be registered' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_valid_muplugin_path() {
		$plugin_path = WPMU_PLUGIN_DIR . '/my-plugin/blocks';
		$result      = WP_Block_Metadata_Registry::register_collection( $plugin_path, $this->temp_manifest_file );
		$this->assertTrue( $result, 'Valid must-use plugin path should be registered successfully' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_invalid_muplugin_path() {
		$invalid_plugin_path = WPMU_PLUGIN_DIR;

		$this->setExpectedIncorrectUsage( 'WP_Block_Metadata_Registry::register_collection' );

		$result = WP_Block_Metadata_Registry::register_collection( $invalid_plugin_path, $this->temp_manifest_file );
		$this->assertFalse( $result, 'Invalid must-use plugin path should not be registered' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_valid_theme_path() {
		$theme_path = WP_CONTENT_DIR . '/themes/my-theme/blocks';
		$result     = WP_Block_Metadata_Registry::register_collection( $theme_path, $this->temp_manifest_file );
		$this->assertTrue( $result, 'Valid theme path should be registered successfully' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_invalid_theme_path() {
		$invalid_theme_path = WP_CONTENT_DIR . '/themes';

		$this->setExpectedIncorrectUsage( 'WP_Block_Metadata_Registry::register_collection' );

		$result = WP_Block_Metadata_Registry::register_collection( $invalid_theme_path, $this->temp_manifest_file );
		$this->assertFalse( $result, 'Invalid theme path should not be registered' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_arbitrary_path() {
		$arbitrary_path = '/var/arbitrary/path';
		$result         = WP_Block_Metadata_Registry::register_collection( $arbitrary_path, $this->temp_manifest_file );
		$this->assertTrue( $result, 'Arbitrary path should be registered successfully' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_arbitrary_path_and_collection_roots_filter() {
		$arbitrary_path = '/var/arbitrary/path';
		add_filter(
			'wp_allowed_block_metadata_collection_roots',
			static function ( $paths ) use ( $arbitrary_path ) {
				$paths[] = $arbitrary_path;
				return $paths;
			}
		);

		$this->setExpectedIncorrectUsage( 'WP_Block_Metadata_Registry::register_collection' );

		$result = WP_Block_Metadata_Registry::register_collection( $arbitrary_path, $this->temp_manifest_file );
		$this->assertFalse( $result, 'Arbitrary path should not be registered if it matches a collection root' );

		$result = WP_Block_Metadata_Registry::register_collection( dirname( $arbitrary_path ), $this->temp_manifest_file );
		$this->assertFalse( $result, 'Arbitrary path should not be registered if it is a parent directory of a collection root' );

		$result = WP_Block_Metadata_Registry::register_collection( $arbitrary_path . '/my-plugin/blocks', $this->temp_manifest_file );
		$this->assertTrue( $result, 'Arbitrary path should be registered successfully if it is within a collection root' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_wp_content_parent_directory_path() {
		$invalid_path = dirname( WP_CONTENT_DIR );

		$this->setExpectedIncorrectUsage( 'WP_Block_Metadata_Registry::register_collection' );

		$result = WP_Block_Metadata_Registry::register_collection( $invalid_path, $this->temp_manifest_file );
		$this->assertFalse( $result, 'Invalid path (parent directory of "wp-content") should not be registered' );
	}

	/**
	 * @ticket 62140
	 */
	public function test_register_collection_with_wp_includes_parent_directory_path() {
		$invalid_path = ABSPATH;

		$this->setExpectedIncorrectUsage( 'WP_Block_Metadata_Registry::register_collection' );

		$result = WP_Block_Metadata_Registry::register_collection( $invalid_path, $this->temp_manifest_file );
		$this->assertFalse( $result, 'Invalid path (parent directory of "wp-includes") should not be registered' );
	}

	public function test_register_collection_with_non_existent_manifest() {
		$non_existent_manifest = '/path/that/does/not/exist/block-manifest.php';

		$this->setExpectedIncorrectUsage( 'WP_Block_Metadata_Registry::register_collection' );

		$result = WP_Block_Metadata_Registry::register_collection( '/var/arbitrary/path', $non_existent_manifest );
		$this->assertFalse( $result, 'Non-existent manifest should not be registered' );
	}
}
