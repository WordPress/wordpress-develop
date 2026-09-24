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
	 */
	private static function create_block_template_object() {
		$template              = new WP_Block_Template();
		$template->type        = 'wp_template';
		$template->theme       = 'test-theme';
		$template->slug        = 'single';
		$template->id          = $template->theme . '//' . $template->slug;
		$template->title       = 'Single';
		$template->content     = '<!-- wp:tests/anchor-block /-->';
		$template->description = 'Description of my template';

		return $template;
	}

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
			),
		);

		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		$this->assertSame( array( 'tests/hooked-block' ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
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
			),
		);

		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		$this->assertSame(
			array( 'tests/other-ignored-block', 'tests/hooked-block' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks']
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

		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type ) {
			if ( 'tests/anchor-block' === $anchor_block_type && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/hooked-block-added-by-filter';
			}

			return $hooked_block_types;
		};

		add_filter( 'hooked_block_types', $filter, 10, 3 );
		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame(
			array( 'tests/hooked-block-added-by-filter' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks']
		);
	}

	/**
	 * @ticket 60506
	 *
	 * @covers ::set_ignored_hooked_blocks_metadata
	 */
	public function test_set_ignored_hooked_blocks_metadata_for_block_added_by_context_aware_filter() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(),
		);

		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type, $context ) {
			if (
				! $context instanceof WP_Block_Template ||
				! property_exists( $context, 'slug' ) ||
				'single' !== $context->slug
			) {
				return $hooked_block_types;
			}

			if ( 'tests/anchor-block' === $anchor_block_type && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/hooked-block-added-by-filter';
			}

			return $hooked_block_types;
		};

		$template = self::create_block_template_object();

		add_filter( 'hooked_block_types', $filter, 10, 4 );
		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', array(), $template );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame(
			array( 'tests/hooked-block-added-by-filter' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks']
		);
	}

	/**
	 * @ticket 60580
	 *
	 * @covers ::set_ignored_hooked_blocks_metadata
	 */
	public function test_set_ignored_hooked_blocks_metadata_for_block_suppressed_by_filter() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(),
		);

		$hooked_blocks = array(
			'tests/anchor-block' => array(
				'after' => array( 'tests/hooked-block', 'tests/hooked-block-suppressed-by-filter' ),
			),
		);

		$filter = function ( $parsed_hooked_block, $hooked_block_type, $relative_position, $parsed_anchor_block ) {
			if (
				'tests/hooked-block-suppressed-by-filter' === $hooked_block_type &&
				'after' === $relative_position &&
				'tests/anchor-block' === $parsed_anchor_block['blockName']
			) {
				return null;
			}

			return $parsed_hooked_block;
		};

		add_filter( 'hooked_block', $filter, 10, 4 );
		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		remove_filter( 'hooked_block', $filter );

		$this->assertSame( array( 'tests/hooked-block' ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
	}
}
