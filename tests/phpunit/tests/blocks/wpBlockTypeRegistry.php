<?php
/**
 * Tests for WP_Block_Type_Registry.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 *
 * @group blocks
 *
 * @coversDefaultClass WP_Block_Type_Registry
 */
class Tests_Blocks_wpBlockTypeRegistry extends WP_UnitTestCase {

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
	public function set_up() {
		parent::set_up();

		$this->registry = new WP_Block_Type_Registry();
	}

	/**
	 * Tear down each test method.
	 *
	 * @since 5.0.0
	 */
	public function tear_down() {
		$this->registry = null;

		parent::tear_down();
	}

	/**
	 * Should reject invalid block names.
	 *
	 * @ticket 45097
	 *
	 * @covers ::register
	 *
	 * @dataProvider data_invalid_block_names
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::register
	 */
	public function test_invalid_block_names( $name ) {
		$result = $this->registry->register( $name, array() );
		$this->assertFalse( $result );
	}

	/**
	 * Data provider for test_invalid_block_names().
	 *
	 * @return array<string, array{ 0: mixed }>
	 */
	public function data_invalid_block_names(): array {
		return array(
			'non-string name'      => array( 1 ),
			'no namespace'         => array( 'paragraph' ),
			'invalid characters'   => array( 'still/_doing_it_wrong' ),
			'uppercase characters' => array( 'Core/Paragraph' ),
		);
	}

	/**
	 * Should accept valid block names.
	 *
	 * @ticket 45097
	 *
	 * @covers ::register
	 * @covers ::get_registered
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
	 * Should fail to re-register the same block.
	 *
	 * @ticket 45097
	 *
	 * @covers ::register
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
	 * Should accept a WP_Block_Type instance.
	 *
	 * @ticket 45097
	 *
	 * @covers ::register
	 */
	public function test_register_block_type_instance() {
		$block_type = new WP_Fake_Block_Type( 'core/fake' );

		$result = $this->registry->register( $block_type );
		$this->assertSame( $block_type, $result );
	}

	/**
	 * Unregistering should fail if a block is not registered.
	 *
	 * @ticket 45097
	 *
	 * @covers ::unregister
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::unregister
	 */
	public function test_unregister_not_registered_block() {
		$result = $this->registry->unregister( 'core/unregistered' );
		$this->assertFalse( $result );
	}

	/**
	 * Should unregister existing blocks.
	 *
	 * @ticket 45097
	 *
	 * @covers ::unregister
	 * @covers ::is_registered
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
	 *
	 * @covers ::get_all_registered
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

	/**
	 * @covers ::get_change_count
	 */
	public function test_change_count_starts_at_zero() {
		$this->assertSame( 0, $this->registry->get_change_count() );
	}

	/**
	 * @covers ::get_change_count
	 */
	public function test_change_count_increments_on_register_and_unregister() {
		$this->registry->register( 'core/paragraph', array() );
		$this->assertSame( 1, $this->registry->get_change_count(), 'Registering a block type should increment the change count' );

		$this->registry->register( 'core/image', array() );
		$this->assertSame( 2, $this->registry->get_change_count(), 'Registering a second block type should increment the change count' );

		$this->registry->unregister( 'core/paragraph' );
		$this->assertSame( 3, $this->registry->get_change_count(), 'Unregistering a block type should increment the change count' );
	}

	/**
	 * @covers ::get_change_count
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::register
	 */
	public function test_change_count_unchanged_on_failed_register() {
		$this->registry->register( 'core/paragraph', array() );

		$this->assertFalse( $this->registry->register( 'core/paragraph', array() ), 'Registering a block type twice should fail' );
		$this->assertFalse( $this->registry->register( 'paragraph', array() ), 'Registering an invalid block name should fail' );
		$this->assertSame( 1, $this->registry->get_change_count(), 'A failed registration should not increment the change count' );
	}

	/**
	 * @covers ::get_change_count
	 *
	 * @expectedIncorrectUsage WP_Block_Type_Registry::unregister
	 */
	public function test_change_count_unchanged_on_failed_unregister() {
		$this->assertFalse( $this->registry->unregister( 'core/unregistered' ), 'Unregistering an unknown block type should fail' );
		$this->assertSame( 0, $this->registry->get_change_count(), 'A failed unregistration should not increment the change count' );
	}
}
