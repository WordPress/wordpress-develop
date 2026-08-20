<?php
/**
 * Tests for the apply_block_hooks_to_content function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.7.0
 *
 * @group blocks
 * @group block-hooks
 *
 * @covers ::apply_block_hooks_to_content
 */
class Tests_Blocks_ApplyBlockHooksToContent extends WP_UnitTestCase {
	/**
	 * Set up.
	 *
	 * @ticket 61902.
	 * @ticket 63287.
	 */
	public static function wpSetUpBeforeClass() {
		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'core/post-content' => 'after',
				),
			)
		);

		register_block_type(
			'tests/hooked-block-with-multiple-false',
			array(
				'block_hooks' => array(
					'tests/other-anchor-block' => 'after',
				),
				'supports'    => array(
					'multiple' => false,
				),
			)
		);

		register_block_type(
			'tests/dynamically-hooked-block-with-multiple-false',
			array(
				'supports' => array(
					'multiple' => false,
				),
			)
		);
	}

	/**
	 * Tear down.
	 *
	 * @ticket 61902.
	 */
	public static function wpTearDownAfterClass() {
		$registry = WP_Block_Type_Registry::get_instance();

		$registry->unregister( 'tests/hooked-block' );
		$registry->unregister( 'tests/hooked-block-with-multiple-false' );
		$registry->unregister( 'tests/dynamically-hooked-block-with-multiple-false' );
	}

	/**
	 * @ticket 61902
	 */
	public function test_apply_block_hooks_to_content_sets_theme_attribute_on_template_part_block() {
		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:template-part /-->';

		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		$this->assertSame(
			sprintf( '<!-- wp:template-part {"theme":"%s"} /-->', get_stylesheet() ),
			$actual
		);
	}

	/**
	 * @ticket 61902
	 * @ticket 63287
	 */
	public function test_apply_block_hooks_to_content_inserts_hooked_block() {
		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:post-content /-->';

		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		$this->assertSame(
			'<!-- wp:post-content /--><!-- wp:tests/hooked-block /-->',
			$actual
		);
	}

	/**
	 * @ticket 61074
	 * @ticket 63287
	 */
	public function test_apply_block_hooks_to_content_with_context_set_to_null() {
		$content = '<!-- wp:post-content /-->';

		/*
		 * apply_block_hooks_to_content() will fall back to the global $post object (via get_post())
		 * if the $context parameter is null. However, we'd also like to ensure that the function
		 * works as expected even when get_post() returns null.
		 */
		$this->assertNull( get_post() );

		$actual = apply_block_hooks_to_content( $content, null, 'insert_hooked_blocks' );
		$this->assertSame(
			'<!-- wp:post-content /--><!-- wp:tests/hooked-block /-->',
			$actual
		);
	}

	/**
	 * @ticket 61902
	 */
	public function test_apply_block_hooks_to_content_respect_multiple_false() {
		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:tests/hooked-block-with-multiple-false /--><!-- wp:tests/other-anchor-block /-->';

		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		$this->assertSame(
			'<!-- wp:tests/hooked-block-with-multiple-false /--><!-- wp:tests/other-anchor-block /-->',
			$actual
		);
	}

	/**
	 * @ticket 61902
	 */
	public function test_apply_block_hooks_to_content_respect_multiple_false_after_inserting_once() {
		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:tests/other-anchor-block /--><!-- wp:tests/other-block /--><!-- wp:tests/other-anchor-block /-->';

		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		$this->assertSame(
			'<!-- wp:tests/other-anchor-block /--><!-- wp:tests/hooked-block-with-multiple-false /--><!-- wp:tests/other-block /--><!-- wp:tests/other-anchor-block /-->',
			$actual
		);
	}

	/**
	 * @ticket 61902
	 */
	public function test_apply_block_hooks_to_content_respect_multiple_false_with_filter() {
		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type ) {
			if ( 'tests/yet-another-anchor-block' === $anchor_block_type && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/dynamically-hooked-block-with-multiple-false';
			}

			return $hooked_block_types;
		};

		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:tests/dynamically-hooked-block-with-multiple-false /--><!-- wp:tests/yet-another-anchor-block /-->';

		add_filter( 'hooked_block_types', $filter, 10, 3 );
		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame(
			'<!-- wp:tests/dynamically-hooked-block-with-multiple-false /--><!-- wp:tests/yet-another-anchor-block /-->',
			$actual
		);
	}

	/**
	 * @ticket 61902
	 */
	public function test_apply_block_hooks_to_content_respect_multiple_false_after_inserting_once_with_filter() {
		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type ) {
			if ( 'tests/yet-another-anchor-block' === $anchor_block_type && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/dynamically-hooked-block-with-multiple-false';
			}

			return $hooked_block_types;
		};

		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:tests/yet-another-anchor-block /--><!-- wp:tests/other-block /--><!-- wp:tests/yet-another-anchor-block /-->';

		add_filter( 'hooked_block_types', $filter, 10, 3 );
		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame(
			'<!-- wp:tests/yet-another-anchor-block /--><!-- wp:tests/dynamically-hooked-block-with-multiple-false /--><!-- wp:tests/other-block /--><!-- wp:tests/yet-another-anchor-block /-->',
			$actual
		);
	}

	/**
	 * An empty object attribute on the anchor block must survive hooked block insertion.
	 *
	 * The Block Hooks algorithm parses stored markup and writes it back, which is what
	 * turned `{}` into `[]` before empty-object preservation existed.
	 *
	 * @ticket 63325
	 */
	public function test_empty_object_attribute_survives_hooked_block_insertion() {
		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:post-content {"layout":{"columns":{}}} /-->';

		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );

		$this->assertSame(
			'<!-- wp:post-content {"layout":{"columns":{}}} /--><!-- wp:tests/hooked-block /-->',
			$actual
		);
	}

	/**
	 * An empty `metadata` object must not break the algorithm that writes to it.
	 *
	 * `set_ignored_hooked_blocks_metadata()` and `insert_hooked_blocks()` index into
	 * `attrs.metadata.ignoredHookedBlocks`. In PHP every array offset against an object
	 * is fatal -- including inside isset() and ?? -- so an anchor block carrying
	 * `"metadata":{}` would take down the whole request without the array casts there.
	 *
	 * @ticket 63325
	 *
	 * @dataProvider data_object_shaped_metadata
	 *
	 * @param string $attributes Anchor block attribute JSON.
	 */
	public function test_object_shaped_metadata_does_not_fatal( $attributes ) {
		$context          = new WP_Block_Template();
		$context->content = "<!-- wp:post-content $attributes /-->";

		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );

		$this->assertStringContainsString( '<!-- wp:tests/hooked-block /-->', $actual );
	}

	/**
	 * Same shapes, through the visitor that records ignored hooked blocks.
	 *
	 * @ticket 63325
	 *
	 * @dataProvider data_object_shaped_metadata
	 *
	 * @param string $attributes Anchor block attribute JSON.
	 */
	public function test_object_shaped_metadata_does_not_fatal_when_setting_metadata( $attributes ) {
		$context          = new WP_Block_Template();
		$context->content = "<!-- wp:post-content $attributes /-->";

		$actual = apply_block_hooks_to_content( $context->content, $context, 'set_ignored_hooked_blocks_metadata' );

		$this->assertStringContainsString( '"ignoredHookedBlocks":["tests/hooked-block"]', $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_object_shaped_metadata() {
		return array(
			'empty metadata object'             => array( '{"metadata":{}}' ),
			'empty ignoredHookedBlocks object'  => array( '{"metadata":{"ignoredHookedBlocks":{}}}' ),
			'empty object beside real metadata' => array( '{"metadata":{"name":"Test","extra":{}}}' ),
		);
	}

	/**
	 * Filters must keep receiving the historical all-array attribute shape.
	 *
	 * The preserved empty object stays in the block that gets serialized, but the copy
	 * handed to `hooked_block` is converted, so existing callbacks that walk attributes
	 * with array access are unaffected.
	 *
	 * @ticket 63325
	 */
	public function test_hooked_block_filter_receives_array_shaped_attributes() {
		$anchor_attrs = null;

		$filter = static function ( $parsed_hooked_block, $hooked_block_type, $relative_position, $anchor_block ) use ( &$anchor_attrs ) {
			$anchor_attrs = $anchor_block['attrs'];

			return $parsed_hooked_block;
		};

		$context          = new WP_Block_Template();
		$context->content = '<!-- wp:post-content {"layout":{"columns":{}}} /-->';

		add_filter( 'hooked_block', $filter, 10, 4 );
		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		remove_filter( 'hooked_block', $filter, 10 );

		$this->assertIsArray( $anchor_attrs['layout']['columns'], 'The filter should not see a preserved object.' );
		$this->assertSame( array(), $anchor_attrs['layout']['columns'] );

		// The serialized markup still carries the empty object.
		$this->assertStringContainsString( '"columns":{}', $actual );
	}

	/**
	 * A hooked block added purely by filter must still be inserted.
	 *
	 * `insert_hooked_blocks()` returns early when no hooked block types remain, which is what
	 * lets it skip normalizing the anchor block's attributes for the majority of blocks. That
	 * check has to run after the `hooked_block_types` filter: an anchor with nothing registered
	 * statically starts with an empty list, and a plugin is entitled to fill it.
	 *
	 * @ticket 63325
	 */
	public function test_filter_can_add_hooked_block_to_anchor_with_no_registered_hooks() {
		$anchor_block_type = 'tests/anchor-block-without-registered-hooks';

		// Nothing is hooked to this anchor, so the list is empty until the filter runs.
		$this->assertSame(
			array(),
			get_hooked_blocks()[ $anchor_block_type ] ?? array(),
			'The anchor block should have no statically registered hooked blocks.'
		);

		$filter = static function ( $hooked_block_types, $relative_position, $anchor ) use ( $anchor_block_type ) {
			if ( $anchor_block_type === $anchor && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/hooked-block';
			}

			return $hooked_block_types;
		};

		$context          = new WP_Block_Template();
		$context->content = "<!-- wp:$anchor_block_type /-->";

		add_filter( 'hooked_block_types', $filter, 10, 3 );
		$actual = apply_block_hooks_to_content( $context->content, $context, 'insert_hooked_blocks' );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame(
			"<!-- wp:$anchor_block_type /--><!-- wp:tests/hooked-block /-->",
			$actual
		);
	}

	/**
	 * An anchor block with no hooked blocks must serialize back unchanged.
	 *
	 * Companion to the test above: this is the path that now returns before normalizing the
	 * anchor block, and a preserved empty object still has to survive it.
	 *
	 * @ticket 63325
	 */
	public function test_anchor_block_without_hooked_blocks_round_trips() {
		$content = '<!-- wp:tests/anchor-block-without-registered-hooks {"layout":{"columns":{}}} /-->';

		$context          = new WP_Block_Template();
		$context->content = $content;

		$this->assertSame(
			$content,
			apply_block_hooks_to_content( $content, $context, 'insert_hooked_blocks' )
		);
	}
}
