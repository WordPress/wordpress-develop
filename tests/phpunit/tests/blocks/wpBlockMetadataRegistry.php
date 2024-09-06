<?php

/**
 * Tests for WP_Block_Metadata_Registry class.
 *
 * @group blocks
 */
class Tests_Blocks_WpBlockMetadataRegistry extends WP_UnitTestCase {
	/**
	 * @var WP_Block_Metadata_Registry
	 */
	private $registry;

	public function set_up() {
		parent::set_up();
		$this->registry = WP_Block_Metadata_Registry::get_instance();
	}

	public function test_register_and_get_metadata() {
		$namespace = 'test-namespace';
		$source    = 'test-source';
		$metadata  = array(
			'name'  => 'test-block',
			'title' => 'Test Block',
		);

		$this->registry->register( $namespace, $source, $metadata );

		$retrieved_metadata = $this->registry->get_metadata( $namespace, $source );
		$this->assertEquals( $metadata, $retrieved_metadata );
	}

	public function test_get_nonexistent_metadata() {
		$retrieved_metadata = $this->registry->get_metadata( 'nonexistent', 'nonexistent' );
		$this->assertNull( $retrieved_metadata );
	}

	public function test_singleton_instance() {
		$instance1 = WP_Block_Metadata_Registry::get_instance();
		$instance2 = WP_Block_Metadata_Registry::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}
}
