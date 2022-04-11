<?php
/**
 * Comment Template block tests
 *
 * @package WordPress
 * @subpackage Block Library
 * @since 6.0.0
 */

/**
 * Tests for the Comment Template block.
 *
 * @since 6.0.0
 *
 * @group block-library
 */
class Test_Block_Library_CommentTemplate extends WP_UnitTestCase {

	private static $custom_post;
	private static $comment_ids;
	private static $per_page = 5;

	public function set_up() {
		parent::set_up();

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', self::$per_page );
		update_option( 'comment_order', 'ASC' );

		self::$custom_post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'dogs',
				'post_status'  => 'publish',
				'post_name'    => 'metaldog',
				'post_title'   => 'Metal Dog',
				'post_content' => 'Metal Dog content',
				'post_excerpt' => 'Metal Dog',
			)
		);

		self::$comment_ids = self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			1,
			array(
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_content'      => 'Hello world',
			)
		);
	}

	function test_build_comment_query_vars_from_block_with_context() {
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		$this->assertEquals(
			array(
				'orderby'                   => 'comment_date_gmt',
				'order'                     => 'ASC',
				'status'                    => 'approve',
				'no_found_rows'             => false,
				'update_comment_meta_cache' => false,
				'post_id'                   => self::$custom_post->ID,
				'hierarchical'              => 'threaded',
				'number'                    => 5,
				'paged'                     => 1,
			),
			build_comment_query_vars_from_block( $block )
		);
	}

	function test_build_comment_query_vars_from_block_no_context() {
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block( $parsed_blocks[0] );

		$this->assertEquals(
			array(
				'orderby'                   => 'comment_date_gmt',
				'order'                     => 'ASC',
				'status'                    => 'approve',
				'no_found_rows'             => false,
				'update_comment_meta_cache' => false,
				'hierarchical'              => 'threaded',
				'number'                    => 5,
				'paged'                     => 1,
			),
			build_comment_query_vars_from_block( $block )
		);
	}

	/**
	 * Test rendering a single comment
	 */
	function test_rendering_comment_template() {
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		// Here we use the function prefixed with 'gutenberg_*' because it's added
		// in the build step.
		$this->assertEquals(
			'<ol ><li><div class="wp-block-comment-author-name">Test</div><div class="wp-block-comment-content">Hello world</div></li></ol>',
			render_block_core_comment_template( null, null, $block )
		);
	}

	/**
	 * Test rendering 3 nested comments:
	 *
	 * └─ comment 1
	 *    └─ comment 2
	 *       └─ comment 3
	 */
	function test_rendering_comment_template_nested() {
		$nested_comment_ids = self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			1,
			array(
				'comment_parent'       => self::$comment_ids[0],
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_content'      => 'Hello world',
			)
		);

		self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			1,
			array(
				'comment_parent'       => $nested_comment_ids[0],
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_content'      => 'Hello world',
			)
		);

		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		$this->assertEquals(
			'<ol ><li><div class="wp-block-comment-author-name">Test</div><div class="wp-block-comment-content">Hello world</div><ol><li><div class="wp-block-comment-author-name">Test</div><div class="wp-block-comment-content">Hello world</div><ol><li><div class="wp-block-comment-author-name">Test</div><div class="wp-block-comment-content">Hello world</div></li></ol></li></ol></li></ol>',
			render_block_core_comment_template( null, null, $block )
		);
	}
	/**
	 * Test that both "Older Comments" and "Newer Comments" are displayed in the correct order
	 * inside the Comment Query Loop when we enable pagination on Discussion Settings.
	 * In order to do that, it should exist a query var 'cpage' set with the $comment_args['paged'] value.
	 */
	function test_build_comment_query_vars_from_block_sets_cpage_var() {

		// This could be any number, we set a fixed one instead of a random for better performance.
		$comment_query_max_num_pages = 5;
		// We substract 1 because we created 1 comment at the beggining.
		$post_comments_numbers = ( self::$per_page * $comment_query_max_num_pages ) - 1;
		self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			$post_comments_numbers,
			array(
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_content'      => 'Hello world',
			)
		);
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block  = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId'           => self::$custom_post->ID,
				'comments/inherit' => true,
			)
		);
		$actual = build_comment_query_vars_from_block( $block );
		$this->assertEquals( $comment_query_max_num_pages, $actual['paged'] );
		$this->assertEquals( $comment_query_max_num_pages, get_query_var( 'cpage' ) );
	}
}
