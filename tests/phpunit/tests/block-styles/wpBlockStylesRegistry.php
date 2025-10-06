<?php
/**
 * Unit tests for WP_Block_Styles_Registry::is_registered().
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.9.0
 *
 * @group block-styles
 *
 * @covers WP_Block_Styles_Registry::is_registered
 */

class Tests_Block_Styles_WPBlockStylesRegistryTest extends WP_UnitTestCase {

	/**
	 * WP_Block_Styles_Registry instance.
	 *
	 * @var WP_Block_Styles_Registry
	 */
	protected $registry;

	public function setUp(): void {
		parent::setUp();
		$this->registry = WP_Block_Styles_Registry::get_instance();
	}

	public function tearDown(): void {
		$this->registry = null;
		parent::tearDown();
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_unregistered_style() {
		$this->assertFalse(
			$this->registry->is_registered( 'core/paragraph', 'fancy-style' ),
			'Unregistered style should return false.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_true_for_registered_style() {
		$block_name = 'core/paragraph';
		$style_name = 'fancy-style';

		$this->registry->register(
			$block_name,
			array(
				'name'  => $style_name,
				'label' => 'Fancy Style',
			)
		);

		$this->assertTrue(
			$this->registry->is_registered( $block_name, $style_name ),
			'Registered style should return true.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_wrong_block() {
		$block_name = 'core/paragraph';
		$style_name = 'fancy-style';

		$this->registry->register(
			$block_name,
			array(
				'name'  => $style_name,
				'label' => 'Fancy Style',
			)
		);

		$this->assertFalse(
			$this->registry->is_registered( 'core/image', $style_name ),
			'Style registered for another block should return false.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_wrong_style_name() {
		$block_name = 'core/paragraph';
		$style_name = 'fancy-style';

		$this->registry->register(
			$block_name,
			array(
				'name'  => $style_name,
				'label' => 'Fancy Style',
			)
		);

		$this->assertFalse(
			$this->registry->is_registered( $block_name, 'other-style' ),
			'Non-existent style name should return false.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_empty_block_name() {
		$style_name = 'fancy-style';
		$this->assertFalse(
			$this->registry->is_registered( '', $style_name ),
			'Empty block name should return false.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_empty_style_name() {
		$block_name = 'core/paragraph';
		$this->assertFalse(
			$this->registry->is_registered( $block_name, '' ),
			'Empty style name should return false.'
		);
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_both_empty_params() {
		$this->assertFalse(
			$this->registry->is_registered( '', '' ),
			'Both empty block and style name should return false.'
		);
	}
}
