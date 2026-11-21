<?php
/**
 * Tests for the apply_block_hooks_to_content_from_post_object function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.8.0
 *
 * @group blocks
 * @group block-hooks
 *
 * @covers ::apply_block_hooks_to_content_from_post_object
 */
class Tests_Blocks_ApplyBlockHooksToContentFromPostObject extends WP_UnitTestCase {
	/**
	 * Post object.
	 *
	 * @var WP_Post
	 */
	protected static $post;

	/**
	 * Post object.
	 *
	 * @var WP_Post
	 */
	protected static $post_with_ignored_hooked_block;

	/**
	 * Post object.
	 *
	 * @var WP_Post
	 */
	protected static $post_with_non_block_content;

	/**
	 *
	 * Set up.
	 *
	 * @ticket 62716
	 */
	public static function wpSetUpBeforeClass() {
		self::$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Test Post',
				'post_content' => '<!-- wp:heading {"level":1} --><h1>Hello World!</h1><!-- /wp:heading -->',
			)
		);

		self::$post_with_ignored_hooked_block = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Test Post',
				'post_content' => '<!-- wp:heading {"level":1} --><h1>Hello World!</h1><!-- /wp:heading -->',
				'meta_input'   => array(
					'_wp_ignored_hooked_blocks' => '["tests/hooked-block-first-child"]',
				),
			)
		);

		self::$post_with_non_block_content = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Test Post',
				'post_content' => '<h1>Hello World!</h1>',
			)
		);

		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'core/heading' => 'after',
				),
			)
		);

		register_block_type(
			'tests/hooked-block-first-child',
			array(
				'block_hooks' => array(
					'core/post-content' => 'first_child',
				),
			)
		);

		register_block_type(
			'tests/hooked-block-after-post-content',
			array(
				'block_hooks' => array(
					'core/post-content' => 'after',
				),
			)
		);

		register_block_type( 'tests/dynamically-hooked-block-before-post-content' );
	}

	/**
	 * Tear down.
	 *
	 * @ticket 62716
	 */
	public static function wpTearDownAfterClass() {
		$registry = WP_Block_Type_Registry::get_instance();

		$registry->unregister( 'tests/hooked-block' );
		$registry->unregister( 'tests/hooked-block-first-child' );
		$registry->unregister( 'tests/hooked-block-after-post-content' );
		$registry->unregister( 'tests/dynamically-hooked-block-before-post-content' );
	}

	/**
	 * @ticket 62716
	 */
	public function test_apply_block_hooks_to_content_from_post_object_inserts_hooked_block() {
		$expected = '<!-- wp:tests/hooked-block-first-child /-->' .
			self::$post->post_content .
			'<!-- wp:tests/hooked-block /-->';
		$actual   = apply_block_hooks_to_content_from_post_object(
			self::$post->post_content,
			self::$post,
			'insert_hooked_blocks'
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 65008
	 */
	public function test_apply_block_hooks_to_content_from_post_object_sets_ignored_hooked_blocks() {
		$ignored_hooked_blocks_at_root = array();

		$expected = '<!-- wp:tests/hooked-block-first-child /-->' .
			'<!-- wp:heading {"level":1,"metadata":{"ignoredHookedBlocks":["tests/hooked-block"]}} -->' .
			'<h1>Hello World!</h1>' .
			'<!-- /wp:heading -->' .
			'<!-- wp:tests/hooked-block /-->';
		$actual   = apply_block_hooks_to_content_from_post_object(
			self::$post->post_content,
			self::$post,
			'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata',
			$ignored_hooked_blocks_at_root
		);
		$this->assertSame( $expected, $actual, "Markup wasn't updated correctly." );
		$this->assertSame(
			array( 'tests/hooked-block-first-child' ),
			$ignored_hooked_blocks_at_root,
			"Hooked block added at 'first_child' position wasn't added to ignoredHookedBlocks metadata."
		);
	}

	/**
	 * @ticket 62716
	 * @ticket 65008
	 */
	public function test_apply_block_hooks_to_content_from_post_object_respects_ignored_hooked_blocks_post_meta() {
		$ignored_hooked_blocks_at_root = array();

		$expected = '<!-- wp:heading {"level":1,"metadata":{"ignoredHookedBlocks":["tests/hooked-block"]}} -->' .
			'<h1>Hello World!</h1>' .
			'<!-- /wp:heading -->' .
			'<!-- wp:tests/hooked-block /-->';
		$actual   = apply_block_hooks_to_content_from_post_object(
			self::$post_with_ignored_hooked_block->post_content,
			self::$post_with_ignored_hooked_block,
			'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata',
			$ignored_hooked_blocks_at_root
		);
		$this->assertSame( $expected, $actual );
		$this->assertSame(
			array( 'tests/hooked-block-first-child' ),
			$ignored_hooked_blocks_at_root,
			"Pre-existing ignored hooked block at root level wasn't reflected in metadata."
		);
	}

	/**
	 * @ticket 63287
	 * @ticket 65008
	 */
	public function test_apply_block_hooks_to_content_from_post_object_does_not_insert_hooked_block_before_container_block() {
		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type ) {
			if ( 'core/post-content' === $anchor_block_type && 'before' === $relative_position ) {
				$hooked_block_types[] = 'tests/dynamically-hooked-block-before-post-content';
			}

			return $hooked_block_types;
		};

		$ignored_hooked_blocks_at_root = array();

		$expected = '<!-- wp:tests/hooked-block-first-child /-->' .
			'<!-- wp:heading {"level":1,"metadata":{"ignoredHookedBlocks":["tests/hooked-block"]}} -->' .
			'<h1>Hello World!</h1>' .
			'<!-- /wp:heading -->' .
			'<!-- wp:tests/hooked-block /-->';

		add_filter( 'hooked_block_types', $filter, 10, 3 );
		$actual = apply_block_hooks_to_content_from_post_object(
			self::$post->post_content,
			self::$post,
			'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata',
			$ignored_hooked_blocks_at_root
		);
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame( $expected, $actual, "Hooked block added before 'core/post-content' block shouldn't be inserted." );
		$this->assertSame(
			array( 'tests/hooked-block-first-child' ),
			$ignored_hooked_blocks_at_root,
			"ignoredHookedBlocks metadata wasn't set correctly."
		);
	}

	/**
	 * @ticket 62716
	 * @ticket 65008
	 */
	public function test_apply_block_hooks_to_content_from_post_object_inserts_hooked_block_if_content_contains_no_blocks() {
		$ignored_hooked_blocks_at_root = array();

		$expected = '<!-- wp:tests/hooked-block-first-child /-->' . self::$post_with_non_block_content->post_content;
		$actual   = apply_block_hooks_to_content_from_post_object(
			self::$post_with_non_block_content->post_content,
			self::$post_with_non_block_content,
			'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata',
			$ignored_hooked_blocks_at_root
		);
		$this->assertSame( $expected, $actual, "Markup wasn't updated correctly." );
		$this->assertSame(
			array( 'tests/hooked-block-first-child' ),
			$ignored_hooked_blocks_at_root,
			"Hooked block added at 'first_child' position wasn't added to ignoredHookedBlocks metadata."
		);
	}
}
