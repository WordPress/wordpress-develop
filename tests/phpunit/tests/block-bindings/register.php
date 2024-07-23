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

	public static $test_source_name       = 'test/source';
	public static $test_source_properties = array();

	/**
	 * Set up before each test.
	 *
	 * @since 6.5.0
	 */
	public function set_up() {
		parent::set_up();

		self::$test_source_properties = array(
			'label'              => 'Test source',
			'get_value_callback' => function () {
				return 'test-value';
			},
		);
	}

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
	 * Should find all registered sources.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 * @covers ::get_all_registered_block_bindings_sources
	 * @covers ::get_block_bindings_source
	 * @covers WP_Block_Bindings_Source::__construct
	 */
	public function test_get_all_registered() {
		$source_one_name       = 'test/source-one';
		$source_one_properties = self::$test_source_properties;
		register_block_bindings_source( $source_one_name, $source_one_properties );

		$source_two_name       = 'test/source-two';
		$source_two_properties = self::$test_source_properties;
		register_block_bindings_source( $source_two_name, $source_two_properties );

		$source_three_name       = 'test/source-three';
		$source_three_properties = self::$test_source_properties;
		register_block_bindings_source( $source_three_name, $source_three_properties );

		$expected = array(
			$source_one_name         => new WP_Block_Bindings_Source( $source_one_name, $source_one_properties ),
			$source_two_name         => new WP_Block_Bindings_Source( $source_two_name, $source_two_properties ),
			$source_three_name       => new WP_Block_Bindings_Source( $source_three_name, $source_three_properties ),
			'core/post-meta'         => get_block_bindings_source( 'core/post-meta' ),
			'core/pattern-overrides' => get_block_bindings_source( 'core/pattern-overrides' ),
		);

		$registered = get_all_registered_block_bindings_sources();
		$this->assertEquals( $expected, $registered );
	}

	/**
	 * Should unregister existing block binding source.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 * @covers ::unregister_block_bindings_source
	 * @covers WP_Block_Bindings_Source::__construct
	 */
	public function test_unregister_block_source() {
		register_block_bindings_source( self::$test_source_name, self::$test_source_properties );

		$result = unregister_block_bindings_source( self::$test_source_name );
		$this->assertEquals(
			new WP_Block_Bindings_Source(
				self::$test_source_name,
				self::$test_source_properties
			),
			$result
		);
	}
}
