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
 */
class Tests_Blocks_wpBlockBindingsRegistry extends WP_UnitTestCase {

	public static $test_source_name       = 'test/source';
	public static $test_source_properties = array();

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

		self::$test_source_properties = array(
			'label'              => 'Test source',
			'get_value_callback' => function () {
				return 'test-value';
			},
			'uses_context'       => array( 'sourceContext' ),
		);
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
		$result = $this->registry->register( 1, self::$test_source_properties );
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
		$result = $this->registry->register( 'post-meta', self::$test_source_properties );
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
		$result = $this->registry->register( 'Core/PostMeta', self::$test_source_properties );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject block bindings registration without a label.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_missing_label() {

		// Remove the label from the properties.
		unset( self::$test_source_properties['label'] );

		$result = $this->registry->register( self::$test_source_name, self::$test_source_properties );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject block bindings registration without a get_value_callback.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_missing_get_value_callback() {

		// Remove the get_value_callback from the properties.
		unset( self::$test_source_properties['get_value_callback'] );

		$result = $this->registry->register( self::$test_source_name, self::$test_source_properties );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject block bindings registration if `get_value_callback` is not a callable.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_incorrect_callback_type() {

		self::$test_source_properties['get_value_callback'] = 'not-a-callback';

		$result = $this->registry->register( self::$test_source_name, self::$test_source_properties );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject block bindings registration if `uses_context` is not an array.
	 *
	 * @ticket 60525
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Bindings_Registry::register
	 */
	public function test_register_invalid_string_uses_context() {

		self::$test_source_properties['uses_context'] = 'not-an-array';

		$result = $this->registry->register( self::$test_source_name, self::$test_source_properties );
		$this->assertFalse( $result );
	}

	/**
	 * Should accept valid block binding source.
	 *
	 * @ticket 60282
	 *
	 * @covers WP_Block_Bindings_Registry::register
	 * @covers WP_Block_Bindings_Source::__construct
	 */
	public function test_register_block_binding_source() {
		$result = $this->registry->register( self::$test_source_name, self::$test_source_properties );
		$this->assertEquals(
			new WP_Block_Bindings_Source(
				self::$test_source_name,
				self::$test_source_properties
			),
			$result
		);
		$this->assertSame( 'test/source', $result->name );
		$this->assertSame( 'Test source', $result->label );
		$this->assertSame(
			'test-value',
			$result->get_value( array(), null, '' )
		);
		$this->assertEquals( array( 'sourceContext' ), $result->uses_context );
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
	 * @covers WP_Block_Bindings_Source::__construct
	 */
	public function test_unregister_block_source() {
		$this->registry->register( self::$test_source_name, self::$test_source_properties );

		$result = $this->registry->unregister( self::$test_source_name );
		$this->assertEquals(
			new WP_Block_Bindings_Source(
				self::$test_source_name,
				self::$test_source_properties
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
	 * @covers WP_Block_Bindings_Source::__construct
	 */
	public function test_get_all_registered() {
		$source_one_name       = 'test/source-one';
		$source_one_properties = self::$test_source_properties;
		$this->registry->register( $source_one_name, $source_one_properties );

		$source_two_name       = 'test/source-two';
		$source_two_properties = self::$test_source_properties;
		$this->registry->register( $source_two_name, $source_two_properties );

		$source_three_name       = 'test/source-three';
		$source_three_properties = self::$test_source_properties;
		$this->registry->register( $source_three_name, $source_three_properties );

		$expected = array(
			$source_one_name   => new WP_Block_Bindings_Source( $source_one_name, $source_one_properties ),
			$source_two_name   => new WP_Block_Bindings_Source( $source_two_name, $source_two_properties ),
			$source_three_name => new WP_Block_Bindings_Source( $source_three_name, $source_three_properties ),
		);

		$registered = $this->registry->get_all_registered();
		$this->assertEquals( $expected, $registered );
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
		$this->registry->register( self::$test_source_name, self::$test_source_properties );

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
	 * @covers WP_Block_Bindings_Source::__construct
	 */
	public function test_get_registered() {
		$source_one_name       = 'test/source-one';
		$source_one_properties = self::$test_source_properties;
		$this->registry->register( $source_one_name, $source_one_properties );

		$source_two_name       = 'test/source-two';
		$source_two_properties = self::$test_source_properties;
		$this->registry->register( $source_two_name, $source_two_properties );

		$source_three_name       = 'test/source-three';
		$source_three_properties = self::$test_source_properties;
		$this->registry->register( $source_three_name, $source_three_properties );

		$expected = new WP_Block_Bindings_Source( $source_two_name, $source_two_properties );
		$result   = $this->registry->get_registered( 'test/source-two' );

		$this->assertEquals(
			$expected,
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
		$this->registry->register( self::$test_source_name, self::$test_source_properties );

		$result = $this->registry->is_registered( self::$test_source_name );
		$this->assertTrue( $result );
	}
}
