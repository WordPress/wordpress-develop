<?php

/**
 * Tests for WP_Block_Metadata_Registry class.
 *
 * @group blocks
 */
class Tests_Blocks_WpBlockMetadataRegistry extends WP_UnitTestCase {
	public function test_register_and_get_metadata() {
		$source   = 'test-source';
		$metadata = array(
			'name'  => 'test-block',
			'title' => 'Test Block',
		);

		WP_Block_Metadata_Registry::register( $source, $metadata );

		$retrieved_metadata = WP_Block_Metadata_Registry::get_metadata( $source );
		$this->assertEquals( $metadata, $retrieved_metadata );
	}

	public function test_get_nonexistent_metadata() {
		$retrieved_metadata = WP_Block_Metadata_Registry::get_metadata( 'nonexistent', 'nonexistent' );
		$this->assertNull( $retrieved_metadata );
	}
}
