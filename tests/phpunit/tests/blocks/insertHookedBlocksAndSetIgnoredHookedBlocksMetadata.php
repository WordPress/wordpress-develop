<?php
/**
 * Tests for the insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.6.0
 *
 * @group blocks
 * @group block-hooks
 * @covers ::insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata
 */
class Tests_Blocks_InsertHookedBlocksAndSetIgnoredHookedBlocksMetadata extends WP_UnitTestCase {
	const ANCHOR_BLOCK_TYPE       = 'tests/anchor-block';
	const HOOKED_BLOCK_TYPE       = 'tests/hooked-block';
	const OTHER_HOOKED_BLOCK_TYPE = 'tests/other-hooked-block';

	const HOOKED_BLOCKS = array(
		self::ANCHOR_BLOCK_TYPE => array(
			'after'  => array( self::HOOKED_BLOCK_TYPE ),
			'before' => array( self::OTHER_HOOKED_BLOCK_TYPE ),
		),
	);

	/**
	 * @ticket 59574
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
	 * @ticket 59574
	 */
	public function test_insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata_returns_correct_markup_and_sets_metadata() {
		$anchor_block = array(
			'blockName' => self::ANCHOR_BLOCK_TYPE,
		);

		$actual = insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata( $anchor_block, 'after', self::HOOKED_BLOCKS, array() );
		$this->assertSame(
			'<!-- wp:' . self::HOOKED_BLOCK_TYPE . ' /-->',
			$actual,
			"Markup for hooked block wasn't generated correctly."
		);
		$this->assertSame(
			array( 'tests/hooked-block' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks'],
			"Block wasn't added to ignoredHookedBlocks metadata."
		);
	}

	/**
	 * @ticket 59574
	 */
	public function test_insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata_if_block_is_ignored() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(
				'metadata' => array(
					'ignoredHookedBlocks' => array( self::HOOKED_BLOCK_TYPE ),
				),
			),
		);

		$actual = insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata( $anchor_block, 'after', self::HOOKED_BLOCKS, array() );
		$this->assertSame(
			'',
			$actual,
			"No markup should've been generated for ignored hooked block."
		);
		$this->assertSame(
			array( 'tests/hooked-block' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks'],
			"ignoredHookedBlocks metadata shouldn't have been modified."
		);
	}

	/**
	 * @ticket 59574
	 */
	public function test_insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata_if_other_block_is_ignored() {
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

		$actual = insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, array() );
		$this->assertSame(
			'<!-- wp:' . self::HOOKED_BLOCK_TYPE . ' /-->',
			$actual,
			"Markup for newly hooked block should've been generated."
		);
		$this->assertSame(
			array( 'tests/other-ignored-block', 'tests/hooked-block' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks']
		);
	}

	/**
	 * @ticket 59574
	 */
	public function test_insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata_filter_can_suppress_hooked_block() {
		$anchor_block = array(
			'blockName'    => self::ANCHOR_BLOCK_TYPE,
			'attrs'        => array(
				'layout' => array(
					'type' => 'flex',
				),
			),
			'innerContent' => array(),
		);

		$filter = function ( $parsed_hooked_block, $hooked_block_type, $relative_position, $parsed_anchor_block ) {
			// Is the hooked block adjacent to the anchor block?
			if ( 'before' !== $relative_position && 'after' !== $relative_position ) {
				return $parsed_hooked_block;
			}

			if (
				isset( $parsed_anchor_block['attrs']['layout']['type'] ) &&
				'flex' === $parsed_anchor_block['attrs']['layout']['type']
			) {
				return null;
			}

			return $parsed_hooked_block;
		};
		add_filter( 'hooked_block_' . self::HOOKED_BLOCK_TYPE, $filter, 10, 4 );
		$actual = insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata( $anchor_block, 'after', self::HOOKED_BLOCKS, array() );
		remove_filter( 'hooked_block_' . self::HOOKED_BLOCK_TYPE, $filter );

		$this->assertSame( '', $actual, "No markup should've been generated for hooked block suppressed by filter." );
		$this->assertSame(
			array(),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks'],
			"No block should've been added to ignoredHookedBlocks metadata."
		);
	}

	/**
	 * @ticket 59574
	 */
	public function test_insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata_added_by_context_aware_filter() {
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
		$actual = insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata( $anchor_block, 'after', array(), $template );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame(
			'<!-- wp:tests/hooked-block-added-by-filter /-->',
			$actual,
			"Markup for hooked block added by filter wasn't generated correctly."
		);
		$this->assertSame(
			array( 'tests/hooked-block-added-by-filter' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks'],
			"Block added by filter wasn't added to ignoredHookedBlocks metadata."
		);
	}

	/**
	 * @ticket 59574
	 */
	public function test_insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata_for_block_suppressed_by_filter() {
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
		$actual = insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		remove_filter( 'hooked_block', $filter );

		$this->assertSame(
			'<!-- wp:tests/hooked-block /-->',
			$actual,
			"Markup for hooked block wasn't generated correctly."
		);
		$this->assertSame(
			array( 'tests/hooked-block' ),
			$anchor_block['attrs']['metadata']['ignoredHookedBlocks'],
			"ignoredHookedBlocks metadata wasn't set correctly."
		);
	}
}
