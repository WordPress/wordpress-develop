<?php
/**
 * Tests for Block Bindings API helper functions.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Block_Bindings_Register extends WP_UnitTestCase {

	const TEST_SOURCE_NAME       = 'test/source';
	const TEST_SOURCE_PROPERTIES = array(
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
			unregister_block_bindings_source( $source_name );
		}

		parent::tear_down();
	}

	/**
	 * Should find all registered sources.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 * @covers ::get_all_registered_block_bindings_sources
	 */
	public function test_get_all_registered() {
		$source_one_name       = 'test/source-one';
		$source_one_properties = self::TEST_SOURCE_PROPERTIES;
		register_block_bindings_source( $source_one_name, $source_one_properties );

		$source_two_name       = 'test/source-two';
		$source_two_properties = self::TEST_SOURCE_PROPERTIES;
		register_block_bindings_source( $source_two_name, $source_two_properties );

		$source_three_name       = 'test/source-three';
		$source_three_properties = self::TEST_SOURCE_PROPERTIES;
		register_block_bindings_source( $source_three_name, $source_three_properties );

		$expected = array(
			$source_one_name   => array_merge( array( 'name' => $source_one_name ), $source_one_properties ),
			$source_two_name   => array_merge( array( 'name' => $source_two_name ), $source_two_properties ),
			$source_three_name => array_merge( array( 'name' => $source_three_name ), $source_three_properties ),
		);

		$registered = get_all_registered_block_bindings_sources();
		$this->assertSame( $expected, $registered );
	}

	/**
	 * Should unregister existing block binding source.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 * @covers ::unregister_block_bindings_source
	 */
	public function test_unregister_block_source() {
		register_block_bindings_source( self::TEST_SOURCE_NAME, self::TEST_SOURCE_PROPERTIES );

		$result = unregister_block_bindings_source( self::TEST_SOURCE_NAME );
		$this->assertSame(
			array_merge(
				array( 'name' => self::TEST_SOURCE_NAME ),
				self::TEST_SOURCE_PROPERTIES
			),
			$result
		);
	}
}
