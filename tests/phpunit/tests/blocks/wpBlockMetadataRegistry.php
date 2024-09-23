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
		$path          = '/test/path';
		$manifest_data = array(
			'test-block' => array(
				'name'  => 'test-block',
				'title' => 'Test Block',
			),
		);

		file_put_contents( $this->temp_manifest_file, '<?php return ' . var_export( $manifest_data, true ) . ';' );

		WP_Block_Metadata_Registry::register_collection( $path, $this->temp_manifest_file );

		$retrieved_metadata = WP_Block_Metadata_Registry::get_metadata( $path, 'test-block' );
		$this->assertEquals( $manifest_data['test-block'], $retrieved_metadata );
	}

	public function test_get_nonexistent_metadata() {
		$path               = '/nonexistent/path';
		$retrieved_metadata = WP_Block_Metadata_Registry::get_metadata( $path, 'nonexistent-block' );
		$this->assertNull( $retrieved_metadata );
	}

	public function test_has_metadata() {
		$path          = '/another/test/path';
		$manifest_data = array(
			'existing-block' => array(
				'name'  => 'existing-block',
				'title' => 'Existing Block',
			),
		);

		file_put_contents( $this->temp_manifest_file, '<?php return ' . var_export( $manifest_data, true ) . ';' );

		WP_Block_Metadata_Registry::register_collection( $path, $this->temp_manifest_file );

		$this->assertTrue( WP_Block_Metadata_Registry::has_metadata( $path, 'existing-block' ) );
		$this->assertFalse( WP_Block_Metadata_Registry::has_metadata( $path, 'non-existing-block' ) );
	}
}
