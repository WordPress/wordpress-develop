<?php
/**
 * WP_Block_Type_Registry Tests
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Tests for WP_Block_Type_Registry
 *
 * @since 5.0.0
 *
 * @group blocks
 */
class WP_Test_Block_Type_Registry extends WP_UnitTestCase {

	/**
	 * Fake block type registry.
	 *
	 * @since 5.0.0
	 * @var WP_Block_Type_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 *
	 * @since 5.0.0
	 */
	public function setUp() {
		parent::setUp();

		$this->registry = new WP_Block_Type_Registry();
	}

	/**
	 * Tear down each test method.
	 *
	 * @since 5.0.0
	 */
	public function tearDown() {
		parent::tearDown();

		$this->registry = null;
	}

	/**
	 * Should reject numbers
	 *
	 * @ticket 45097
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::register
	 */
	public function test_invalid_non_string_names() {
		$result = $this->registry->register( 1, array() );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject blocks without a namespace
	 *
	 * @ticket 45097
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::register
	 */
	public function test_invalid_names_without_namespace() {
		$result = $this->registry->register( 'paragraph', array() );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject blocks with invalid characters
	 *
	 * @ticket 45097
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::register
	 */
	public function test_invalid_characters() {
		$result = $this->registry->register( 'still/_doing_it_wrong', array() );
		$this->assertFalse( $result );
	}

	/**
	 * Should reject blocks with uppercase characters
	 *
	 * @ticket 45097
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::register
	 */
	public function test_uppercase_characters() {
		$result = $this->registry->register( 'Core/Paragraph', array() );
		$this->assertFalse( $result );
	}

	/**
	 * Should accept valid block names
	 *
	 * @ticket 45097
	 */
	public function test_register_block_type() {
		$name     = 'core/paragraph';
		$settings = array(
			'icon' => 'editor-paragraph',
		);

		$block_type = $this->registry->register( $name, $settings );
		$this->assertSame( $name, $block_type->name );
		$this->assertSame( $settings['icon'], $block_type->icon );
		$this->assertSame( $block_type, $this->registry->get_registered( $name ) );
	}

	/**
	 * Should fail to re-register the same block
	 *
	 * @ticket 45097
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::register
	 */
	public function test_register_block_type_twice() {
		$name     = 'core/paragraph';
		$settings = array(
			'icon' => 'editor-paragraph',
		);

		$result = $this->registry->register( $name, $settings );
		$this->assertNotFalse( $result );
		$result = $this->registry->register( $name, $settings );
		$this->assertFalse( $result );
	}

	/**
	 * Should accept a WP_Block_Type instance
	 *
	 * @ticket 45097
	 */
	public function test_register_block_type_instance() {
		$block_type = new WP_Fake_Block_Type( 'core/fake' );

		$result = $this->registry->register( $block_type );
		$this->assertSame( $block_type, $result );
	}

	/**
	 * Unregistering should fail if a block is not registered
	 *
	 * @ticket 45097
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::unregister
	 */
	public function test_unregister_not_registered_block() {
		$result = $this->registry->unregister( 'core/unregistered' );
		$this->assertFalse( $result );
	}

	/**
	 * Should unregister existing blocks
	 *
	 * @ticket 45097
	 */
	public function test_unregister_block_type() {
		$name     = 'core/paragraph';
		$settings = array(
			'icon' => 'editor-paragraph',
		);

		$this->registry->register( $name, $settings );
		$block_type = $this->registry->unregister( $name );
		$this->assertSame( $name, $block_type->name );
		$this->assertSame( $settings['icon'], $block_type->icon );
		$this->assertFalse( $this->registry->is_registered( $name ) );
	}

	/**
	 * @ticket 45097
	 */
	public function test_get_all_registered() {
		$names    = array( 'core/paragraph', 'core/image', 'core/blockquote' );
		$settings = array(
			'icon' => 'random',
		);

		foreach ( $names as $name ) {
			$this->registry->register( $name, $settings );
		}

		$registered = $this->registry->get_all_registered();
		$this->assertSameSets( $names, array_keys( $registered ) );
	}
}
