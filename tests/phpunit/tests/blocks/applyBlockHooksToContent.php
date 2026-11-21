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
}
