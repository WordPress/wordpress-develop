<?php
/**
 * Tests for WP_Block_Styles_Registry.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.6.0
 *
 * @group blocks
 */
class Tests_Blocks_wpBlockStylesRegistry extends WP_UnitTestCase {

	/**
	 * Fake block styles registry.
	 *
	 * @since 6.6.0
	 * @var WP_Block_Styles_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 *
	 * @since 6.6.0
	 */
	public function set_up() {
		parent::set_up();

		$this->registry = new WP_Block_Styles_Registry();
	}

	/**
	 * Tear down each test method.
	 *
	 * @since 6.6.0
	 */
	public function tear_down() {
		$this->registry = null;

		parent::tear_down();
	}

	/**
	 * Should accept valid string block type name.
	 *
	 * @ticket 61274
	 */
	public function test_register_block_style_with_string_block_name() {
		$name             = 'core/paragraph';
		$style_properties = array(
			'name'  => 'fancy',
			'label' => 'Fancy',
		);
		$result           = $this->registry->register( $name, $style_properties );
		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( 'core/paragraph', 'fancy' ) );
	}

	/**
	 * Should accept valid array of block type names.
	 *
	 * @ticket 61274
	 */
	public function test_register_block_style_with_array_of_block_names() {
		$names            = array( 'core/paragraph', 'core/group' );
		$style_properties = array(
			'name'  => 'plain',
			'label' => 'Plain',
		);
		$result           = $this->registry->register( $names, $style_properties );
		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( 'core/paragraph', 'plain' ) );
		$this->assertTrue( $this->registry->is_registered( 'core/group', 'plain' ) );
	}

	/**
	 * Should accept valid string style label.
	 * The registered style should have the same label.
	 */
	public function test_register_block_style_with_string_style_label() {
		$name             = 'core/paragraph';
		$style_properties = array(
			'name'  => 'fancy',
			'label' => 'Fancy',
		);
		$result           = $this->registry->register( $name, $style_properties );
		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( 'core/paragraph', 'fancy' ) );
		$this->assertEquals( $style_properties['label'], $this->registry->get_registered_styles_for_block( 'core/paragraph' )['fancy']['label'] );
	}
}
