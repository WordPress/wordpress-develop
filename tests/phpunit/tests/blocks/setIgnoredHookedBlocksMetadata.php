<?php
/**
 * Tests for the set_ignored_hooked_blocks_metadata function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.5.0
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_SetIgnoredHookedBlocksMetadata extends WP_UnitTestCase {
	/**
	 * @ticket 60506
	 *
	 * @covers ::set_ignored_hooked_blocks_metadata
	 */
	public function test_set_ignored_hooked_blocks_metadata() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
		);

		$hooked_blocks = array(
			'tests/anchor-block' => array(
				'after' => array( 'tests/hooked-block' ),
			)
		);

		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		$this->assertSame( $anchor_block[ 'attrs' ][ 'metadata' ][ 'ignoredHookedBlocks' ], array( 'tests/hooked-block' ) );
	}

	/**
	 * @ticket 60506
	 *
	 * @covers ::set_ignored_hooked_blocks_metadata
	 */
	public function test_set_ignored_hooked_blocks_metadata_retains_existing_items() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(
				'metadata' => array(
					'ignoredHookedBlocks' => array( 'tests/other-ignored-block' ),
				),
			),
		);

		$hooked_blocks = array(
			'tests/anchor-block' => array(
				'after' => array( 'tests/hooked-block' ),
			)
		);

		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		$this->assertSame(
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks'],
			array( 'tests/other-ignored-block', 'tests/hooked-block' )
		);
	}

	/**
	 * @ticket 60506
	 *
	 * @covers ::set_ignored_hooked_blocks_metadata
	 */
	public function test_set_ignored_hooked_blocks_metadata_for_block_added_by_filter() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(),
		);

		$hooked_blocks = array();

		$filter = function( $hooked_block_types, $relative_position, $anchor_block_type ) {
			if ( 'tests/anchor-block' === $anchor_block_type && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/hooked-block-added-by-filter';
			}

			return $hooked_block_types;
		};

		add_filter( 'hooked_block_types', $filter, 10, 3 );
		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame(
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks'],
			array( 'tests/hooked-block-added-by-filter' )
		);
	}
}
