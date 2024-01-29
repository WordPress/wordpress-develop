<?php
/**
 * Tests for WP_Block_Bindings_Registry.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 *
 * @coversDefaultClass WP_Block_Bindings_Registry
 */
class Tests_Blocks_wpBlockBindingsRegistry extends WP_UnitTestCase {

	const TEST_SOURCE_NAME       = 'test/source';
	const TEST_SOURCE_PROPERTIES = array(
		'label' => 'Test source',
	);

	/**
	 * Fake block bindings registry.
	 *
	 * @since 6.5.0
	 * @var WP_Block_Bindings_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 *
	 * @since 6.5.0
	 */
	public function set_up() {
		parent::set_up();

		$this->registry = new WP_Block_Bindings_Registry();
	}

	/**
	 * Tear down each test method.
	 *
	 * @since 6.5.0
	 */
	public function tear_down() {
		$this->registry = null;

		parent::tear_down();
	}

	/**
	 * Should reject numbers as block binding source name.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_non_string_names() {
		$result = $this->registry->register( 1, self::TEST_SOURCE_PROPERTIES );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject block binding source name without a namespace.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_names_without_namespace() {
		$result = $this->registry->register( 'post-meta', self::TEST_SOURCE_PROPERTIES );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject block binding source name with invalid characters.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_characters() {
		$result = $this->registry->register( 'still/_doing_it_wrong', array() );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject block binding source name with uppercase characters.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_uppercase_characters() {
		$result = $this->registry->register( 'Core/PostMeta', self::TEST_SOURCE_PROPERTIES );
		$this->assertFalse( $result );
	}

	/**
	 * Should accept valid block binding source.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 */
	public function test_register_block_binding_source() {
		$result = $this->registry->register( self::TEST_SOURCE_NAME, self::TEST_SOURCE_PROPERTIES );
		$this->assertSame(
			array_merge(
				array( 'name' => self::TEST_SOURCE_NAME ),
				self::TEST_SOURCE_PROPERTIES
			),
			$result
		);
	}

	/**
	 * Unregistering should fail if a block binding source is not registered.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::unregister
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::unregister
	 */
	public function test_unregister_not_registered_block() {
		$result = $this->registry->unregister( 'test/unregistered' );
		$this->assertFalse( $result );
	}

	/**
	 * Should unregister existing block binding source.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 * @covers WP_Block_Bindings_Registry::unregister
	 */
	public function test_unregister_block_source() {
		$this->registry->register( self::TEST_SOURCE_NAME, self::TEST_SOURCE_PROPERTIES );

		$result = $this->registry->unregister( self::TEST_SOURCE_NAME );
		$this->assertSame(
			array_merge(
				array( 'name' => self::TEST_SOURCE_NAME ),
				self::TEST_SOURCE_PROPERTIES
			),
			$result
		);
	}

	/**
	 * Should find all registered sources.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 * @covers WP_Block_Bindings_Registry::get_all_registered
	 */
	public function test_get_all_registered() {
		$source_one_name       = 'test/source-one';
		$source_one_properties = self::TEST_SOURCE_PROPERTIES;
		$this->registry->register( $source_one_name, $source_one_properties );

		$source_two_name       = 'test/source-two';
		$source_two_properties = self::TEST_SOURCE_PROPERTIES;
		$this->registry->register( $source_two_name, $source_two_properties );

		$source_three_name       = 'test/source-three';
		$source_three_properties = self::TEST_SOURCE_PROPERTIES;
		$this->registry->register( $source_three_name, $source_three_properties );

		$expected = array(
			$source_one_name   => array_merge( array( 'name' => $source_one_name ), $source_one_properties ),
			$source_two_name   => array_merge( array( 'name' => $source_two_name ), $source_two_properties ),
			$source_three_name => array_merge( array( 'name' => $source_three_name ), $source_three_properties ),
		);

		$registered = $this->registry->get_all_registered();
		$this->assertSame( $expected, $registered );
	}

	/**
	 * Should not find source that's not registered.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 * @covers WP_Block_Bindings_Registry::get_registered
	 */
	public function test_get_registered_rejects_unknown_source_name() {
		$this->registry->register( self::TEST_SOURCE_NAME, self::TEST_SOURCE_PROPERTIES );

		$source = $this->registry->get_registered( 'test/unknown-source' );
		$this->assertNull( $source );
	}

	/**
	 * Should find registered block binding source by name.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 * @covers WP_Block_Bindings_Registry::get_registered
	 */
	public function test_get_registered() {
		$source_one_name       = 'test/source-one';
		$source_one_properties = self::TEST_SOURCE_PROPERTIES;
		$this->registry->register( $source_one_name, $source_one_properties );

		$source_two_name       = 'test/source-two';
		$source_two_properties = self::TEST_SOURCE_PROPERTIES;
		$this->registry->register( $source_two_name, $source_two_properties );

		$source_three_name       = 'test/source-three';
		$source_three_properties = self::TEST_SOURCE_PROPERTIES;
		$this->registry->register( $source_three_name, $source_three_properties );

		$result = $this->registry->get_registered( 'test/source-two' );
		$this->assertSame(
			array_merge(
				array( 'name' => $source_two_name ),
				$source_two_properties
			),
			$result
		);
	}

	/**
	 * Should return false for source that's not registered.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::is_registered
	 */
	public function test_is_registered_for_unknown_source() {
		$result = $this->registry->is_registered( 'test/one' );
		$this->assertFalse( $result );
	}

	/**
	 * Should return true if source is registered.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 * @covers WP_Block_Bindings_Registry::is_registered
	 */
	public function test_is_registered_for_known_source() {
		$this->registry->register( self::TEST_SOURCE_NAME, self::TEST_SOURCE_PROPERTIES );

		$result = $this->registry->is_registered( self::TEST_SOURCE_NAME );
		$this->assertTrue( $result );
	}
}
