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
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 */
	public function test_get_hooked_blocks_no_match_found() {
		$result = get_hooked_blocks( 'tests/no-hooked-blocks' );

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 */
	public function test_get_hooked_blocks_matches_found() {
		register_block_type(
			'tests/injected-one',
			array(
				'block_hooks' => array(
					'tests/hooked-at-before' => 'before',
					'tests/hooked-at-after'  => 'after',
				),
			)
		);
		register_block_type(
			'tests/injected-two',
			array(
				'block_hooks' => array(
					'tests/hooked-at-before'      => 'before',
					'tests/hooked-at-after'       => 'after',
					'tests/hooked-at-first-child' => 'first_child',
					'tests/hooked-at-last-child'  => 'last_child',
				),
			)
		);

		$this->assertSame(
			array(
				'tests/injected-one' => 'before',
				'tests/injected-two' => 'before',
			),
			get_hooked_blocks( 'tests/hooked-at-before' ),
			'block hooked at the before position'
		);
		$this->assertSame(
			array(
				'tests/injected-one' => 'after',
				'tests/injected-two' => 'after',
			),
			get_hooked_blocks( 'tests/hooked-at-after' ),
			'block hooked at the after position'
		);
		$this->assertSame(
			array(
				'tests/injected-two' => 'first_child',
			),
			get_hooked_blocks( 'tests/hooked-at-first-child' ),
			'block hooked at the first child position'
		);
		$this->assertSame(
			array(
				'tests/injected-two' => 'last_child',
			),
			get_hooked_blocks( 'tests/hooked-at-last-child' ),
			'block hooked at the last child position'
		);
	}
}
