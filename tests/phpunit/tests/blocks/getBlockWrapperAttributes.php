<?php
/**
 * Tests for get_block_wrapper_attributes function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 7.1.0
 *
 * @group blocks
 * @covers ::get_block_wrapper_attributes
 */
class Tests_Blocks_GetBlockWrapperAttributes extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 *
	 * @since 7.1.0
	 */
	public function tear_down(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( 'core/example' ) ) {
			$registry->unregister( 'core/example' );
		}

		parent::tear_down();
	}

	/**
	 * The string '0' is preserved for block support attributes.
	 *
	 * @ticket 64452
	 */
	public function test_preserves_string_zero_values(): void {
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
				'ariaLabel' => '0',
			),
		);

		$result = get_block_wrapper_attributes();
		$this->assertSame( 'class="0 wp-block-example" aria-label="0"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_preserves_string_zero_values_from_extra_attributes(): void {
		WP_Block_Supports::init();
		register_block_type( 'core/example' );
		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/example' );

		$result = get_block_wrapper_attributes(
			array(
				'class'      => '0',
				'id'         => '0',
				'aria-label' => '0',
				'data-foo'   => '0',
				'data-var'   => '0',
			)
		);
		$this->assertSame( 'class="0 wp-block-example" id="0" aria-label="0" data-foo="0" data-var="0"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_preserves_numeric_values(): void {
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
				'className' => 5,
				'ariaLabel' => 42,
			),
		);

		$result = get_block_wrapper_attributes();
		$this->assertSame( 'class="5 wp-block-example" aria-label="42"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_preserves_numeric_values_from_extra_attributes(): void {
		WP_Block_Supports::init();
		register_block_type( 'core/example' );
		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/example' );

		$result = get_block_wrapper_attributes(
			array(
				'class'      => 5,
				'id'         => 7,
				'aria-label' => 42,
				'data-foo'   => 1.5,
			)
		);
		$this->assertSame( 'class="5 wp-block-example" id="7" aria-label="42" data-foo="1.5"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_excludes_non_scalar_values(): void {
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
				'className' => true,
				'ariaLabel' => array( 'x' ),
			),
		);

		$result = get_block_wrapper_attributes();
		$this->assertSame( 'class="wp-block-example"', $result );
	}

	/**
	 * @ticket 64452
	 */
	public function test_excludes_non_scalar_values_from_extra_attributes(): void {
		WP_Block_Supports::init();
		register_block_type( 'core/example' );
		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/example' );

		$result = get_block_wrapper_attributes(
			array(
				'class'      => true,
				'id'         => false,
				'aria-label' => null,
				'data-foo'   => array( 'x' ),
				'data-bar'   => true,
			)
		);
		$this->assertSame( 'class="wp-block-example"', $result );
	}
}
