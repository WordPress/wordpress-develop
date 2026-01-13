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
	 * @ticket 62716
	 */
	public function test_apply_block_hooks_to_content_from_post_object_respects_ignored_hooked_blocks_post_meta() {
		$expected = self::$post_with_ignored_hooked_block->post_content . '<!-- wp:tests/hooked-block /-->';
		$actual   = apply_block_hooks_to_content_from_post_object(
			self::$post_with_ignored_hooked_block->post_content,
			self::$post_with_ignored_hooked_block,
			'insert_hooked_blocks'
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 63287
	 */
	public function test_apply_block_hooks_to_content_from_post_object_does_not_insert_hooked_block_before_container_block() {
		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type ) {
			if ( 'core/post-content' === $anchor_block_type && 'before' === $relative_position ) {
				$hooked_block_types[] = 'tests/dynamically-hooked-block-before-post-content';
			}

			return $hooked_block_types;
		};

		$expected = '<!-- wp:tests/hooked-block-first-child /-->' .
			self::$post->post_content .
			'<!-- wp:tests/hooked-block /-->';

		add_filter( 'hooked_block_types', $filter, 10, 3 );
		$actual = apply_block_hooks_to_content_from_post_object(
			self::$post->post_content,
			self::$post,
			'insert_hooked_blocks'
		);
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 62716
	 */
	public function test_apply_block_hooks_to_content_from_post_object_inserts_hooked_block_if_content_contains_no_blocks() {
		$expected = '<!-- wp:tests/hooked-block-first-child /-->' . self::$post_with_non_block_content->post_content;
		$actual   = apply_block_hooks_to_content_from_post_object(
			self::$post_with_non_block_content->post_content,
			self::$post_with_non_block_content,
			'insert_hooked_blocks'
		);
		$this->assertSame( $expected, $actual );
	}
}
