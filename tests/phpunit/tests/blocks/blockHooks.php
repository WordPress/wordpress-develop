<?php
/**
 * Tests for block hooks feature functions.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.4.0
 *
 * @group blocks
 */
class Tests_Blocks_BlockHooks extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 *
	 * @since 6.4.0
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array( 'tests/my-block', 'tests/my-container-block' ) as $block_name ) {
			if ( $registry->is_registered( $block_name ) ) {
				$registry->unregister( $block_name );
			}
		}

		parent::tear_down();
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::get_hooked_blocks
	 */
	public function test_get_hooked_blocks_no_match_found() {
		$result = get_hooked_blocks( 'tests/no-hooked-blocks' );

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::get_hooked_blocks
	 *
	 * @dataProvider data_block_hooked_blocks
	 *
	 * @param string $block_name Block name.
	 * @param array  $expected   Expected hooked blocks.
	 */
	public function test_get_hooked_blocks_matches_found( $block_name, $expected ) {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'tests/hooked-before' => 'before',
					'tests/hooked-after'  => 'after',
				),
			)
		);
		register_block_type(
			'tests/my-container-block',
			array(
				'block_hooks' => array(
					'tests/hooked-before'      => 'before',
					'tests/hooked-after'       => 'after',
					'tests/hooked-first-child' => 'first_child',
					'tests/hooked-last-child'  => 'last_child',
				),
			)
		);

		$this->assertSame(
			$expected,
			get_hooked_blocks( $block_name )
		);
	}

	/**
	 * Data provider for hooked blocks.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string Block name.
	 *         @type array  Expected hooked blocks.
	 *     }
	 * }
	 */
	public function data_block_hooked_blocks() {
		return array(
			'block hooked at the before position'      => array(
				'tests/hooked-before',
				array(
					'tests/my-block'           => 'before',
					'tests/my-container-block' => 'before',
				),
			),
			'block hooked at the after position'       => array(
				'tests/hooked-after',
				array(
					'tests/my-block'           => 'after',
					'tests/my-container-block' => 'after',
				),
			),
			'block hooked at the first child position' => array(
				'tests/hooked-first-child',
				array(
					'tests/my-container-block' => 'first_child',
				),
			),
			'block hooked at the last child position'  => array(
				'tests/hooked-last-child',
				array(
					'tests/my-container-block' => 'last_child',
				),
			),
		);
	}
}
