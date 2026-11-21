<?php
/**
 * Tests for WP_Block_Styles_Registry.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.6.0
 *
 * @group blocks
 * @coversDefaultClass WP_Block_Styles_Registry
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
		$style_properties = array( 'name' => 'fancy' );
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
		$style_properties = array( 'name' => 'plain' );
		$result           = $this->registry->register( $names, $style_properties );
		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( 'core/paragraph', 'plain' ) );
		$this->assertTrue( $this->registry->is_registered( 'core/group', 'plain' ) );
	}

	/**
	 * Should accept valid string style label. The registered style should have the same label.
	 *
	 * @ticket 52592
	 *
	 * @covers ::register
	 * @covers ::is_registered
	 * @covers ::get_registered_styles_for_block
	 */
	public function test_register_block_style_with_label() {
		$name             = 'core/paragraph';
		$style_properties = array(
			'name'  => 'fancy',
			'label' => 'Fancy',
		);
		$result           = $this->registry->register( $name, $style_properties );

		$this->assertTrue( $result, 'The block style should be registered when the label is a valid string.' );
		$this->assertTrue(
			$this->registry->is_registered( $name, 'fancy' ),
			'The block type should have the block style registered when the label is valid.'
		);
		$this->assertSame(
			$style_properties['label'],
			$this->registry->get_registered_styles_for_block( $name )['fancy']['label'],
			'The registered block style should have the same label.'
		);
	}

	/**
	 * Should register the block style when `label` is missing, using `name` as the label.
	 *
	 * @ticket 52592
	 *
	 * @covers ::register
	 * @covers ::is_registered
	 * @covers ::get_registered_styles_for_block
	 */
	public function test_register_block_style_without_label() {
		$name             = 'core/paragraph';
		$style_properties = array(
			'name' => 'fancy',
		);
		$result           = $this->registry->register( $name, $style_properties );

		$this->assertTrue( $result, 'The block style should be registered when the label is missing.' );
		$this->assertTrue(
			$this->registry->is_registered( $name, 'fancy' ),
			'The block type should have the block style registered when the label is missing.'
		);
		$this->assertSame(
			$style_properties['name'],
			$this->registry->get_registered_styles_for_block( $name )['fancy']['label'],
			'The registered block style label should be the same as the name.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_null_block_name() {
		$style_name = 'fancy-style';
		$this->assertFalse(
			$this->registry->is_registered( null, $style_name ),
			'Empty block name should return false.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_null_style_name() {
		$block_name = 'core/paragraph';
		$this->assertFalse(
			$this->registry->is_registered( $block_name, null ),
			'Empty style name should return false.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_both_null_params() {
		$this->assertFalse(
			$this->registry->is_registered( null, null ),
			'Both empty block and style name should return false.'
		);
	}
}
