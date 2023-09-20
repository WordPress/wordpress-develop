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
		$registry    = WP_Block_Type_Registry::get_instance();
		$block_names = array(
			'tests/injected-one',
			'tests/injected-two',
		);
		foreach ( $block_names as $block_name ) {
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

	/**
	 * @ticket 59385
	 *
	 * @covers ::insert_inner_block
	 *
	 * @dataProvider data_insert_inner_block
	 *
	 * @param string $block_index     Block index to insert the block at.
	 * @param string $expected_markup Expected markup after the block is inserted.
	 */
	public function test_insert_inner_block( $block_index, $expected_markup ) {
		$original_markup = <<<HTML
<!-- wp:tests/group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
			<p>Foo</p>
		<!-- /wp:paragraph -->
	</div>
<!-- /wp:tests/group -->
HTML;

		$inserted_block = array(
			'blockName'    => 'tests/hooked-block',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$expected = parse_blocks( $expected_markup )[0];
		$block    = parse_blocks( $original_markup )[0];
		insert_inner_block( $block, $block_index, 1, $inserted_block );
		$this->assertSame( $expected, $block );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_insert_inner_block() {
		$expected_before_markup = <<<HTML
<!-- wp:tests/group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:tests/hooked-block /--><!-- wp:paragraph -->
			<p>Foo</p>
		<!-- /wp:paragraph -->
	</div>
<!-- /wp:tests/group -->
HTML;

		$expected_after_markup = <<<HTML
<!-- wp:tests/group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
			<p>Foo</p>
		<!-- /wp:paragraph --><!-- wp:tests/hooked-block /-->
	</div>
<!-- /wp:tests/group -->
HTML;

		return array(
			'insert before given block' => array(
				'block_index'     => 0,
				'expected_markup' => $expected_before_markup,
			),
			'insert after given block'  => array(
				'block_index'     => 1,
				'expected_markup' => $expected_after_markup,
			),
		);
	}

	/**
	 * @ticket 59385
	 *
	 * @covers ::prepend_inner_block
	 */
	public function test_prepend_inner_block() {
		$original_markup = <<<HTML
<!-- wp:tests/group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
			<p>Foo</p>
		<!-- /wp:paragraph -->
	</div>
<!-- /wp:tests/group -->
HTML;

		$inserted_block = array(
			'blockName'    => 'tests/hooked-block',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$expected_markup = <<<HTML
<!-- wp:tests/group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:tests/hooked-block /--><!-- wp:paragraph -->
			<p>Foo</p>
		<!-- /wp:paragraph -->
	</div>
<!-- /wp:tests/group -->
HTML;

		$expected = parse_blocks( $expected_markup )[0];
		$block    = parse_blocks( $original_markup )[0];
		prepend_inner_block( $block, $inserted_block );
		$this->assertSame( $expected, $block );
	}

	/**
	 * @ticket 59385
	 *
	 * @covers ::append_inner_block
	 */
	public function test_append_inner_block() {
		$original_markup = <<<HTML
<!-- wp:tests/group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
			<p>Foo</p>
		<!-- /wp:paragraph -->
	</div>
<!-- /wp:tests/group -->
HTML;

		$inserted_block = array(
			'blockName'    => 'tests/hooked-block',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$expected_markup = <<<HTML
<!-- wp:tests/group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
			<p>Foo</p>
		<!-- /wp:paragraph --><!-- wp:tests/hooked-block /-->
	</div>
<!-- /wp:tests/group -->
HTML;

		$expected = parse_blocks( $expected_markup )[0];
		$block    = parse_blocks( $original_markup )[0];
		append_inner_block( $block, $inserted_block );
		$this->assertSame( $expected, $block );
	}
}
