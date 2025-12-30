<?php
/**
 * Tests for get_block_wrapper_attributes function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 7.0.0
 *
 * @group blocks
 * @covers ::get_block_wrapper_attributes
 */
class Tests_Blocks_GetBlockWrapperAttributes extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 *
	 * @since 7.0.0
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( 'core/example' ) ) {
			$registry->unregister( 'core/example' );
		}
		if ( $registry->is_registered( 'core/example' ) ) {
			$registry->unregister( 'core/example' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 64452
	 */
	public function test_preserves_zero_values() {
		WP_Block_Supports::init();
		register_block_type(
			'core/example',
			array(
				'supports' => array(
					'customClassName' => true,
					'ariaLabel'       => true,
				),
			)
		);
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/example',
			'attrs'     => array(
				'className' => '0',
				'ariaLabel' => 0,
			),
		);

		$result = get_block_wrapper_attributes();
		$this->assertSame( 'class="0 wp-block-example" aria-label="0"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_preserves_zero_values_from_extra_attributes() {
		WP_Block_Supports::init();
		register_block_type( 'core/example' );
		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/example' );

		$result = get_block_wrapper_attributes(
			array(
				'class' => '0',
				'aria-label' => 0,
				'data-foo' => 0,
				'data-var' => '0',
			)
		);
		$this->assertSame( 'class="0 wp-block-example" aria-label="0" data-foo="0" data-var="0"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_excludes_falsy_values_except_zero() {
		WP_Block_Supports::init();
		register_block_type(
			'core/example',
			array(
				'supports' => array(
					'customClassName' => true,
					'ariaLabel'       => true,
				),
			)
		);
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/example',
			'attrs'     => array(
				'className' => false,
				'ariaLabel' => null,
			),
		);

		$result = get_block_wrapper_attributes();
		$this->assertSame( 'class="wp-block-example"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_excludes_falsy_values_except_zero_from_extra_attributes() {
		WP_Block_Supports::init();
		register_block_type( 'core/example' );
		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/example' );

		$result = get_block_wrapper_attributes(
			array(
				'class'      => false,
				'aria-label' => null,
				'data-var'   => false,
				'data-baz'   => null,
			)
		);
		$this->assertSame( 'class="wp-block-example"', $result );
	}
}
