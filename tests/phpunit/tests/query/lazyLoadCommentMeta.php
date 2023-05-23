<?php

/**
 * @group comments
 * @group meta
 */
class Tests_Lazy_Load_Comment_Meta extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	protected static $post_id;

	/**
	 * @var array
	 */
	protected static $comment_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {

		self::$post_id     = $factory->post->create();
		self::$comment_ids = $factory->comment->create_post_comments( self::$post_id, 11 );
	}

	/**
	 * @ticket 57901
	 *
	 * @covers ::wp_queue_comments_for_comment_meta_lazyload
	 */
	public function test_wp_queue_comments_for_comment_meta_lazyload() {
		$filter = new MockAction();
		add_filter( 'update_comment_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		$comments   = array_map( 'get_comment', self::$comment_ids );
		$comment_id = reset( self::$comment_ids );
		wp_queue_comments_for_comment_meta_lazyload( $comments );
		get_comment_meta( $comment_id );

		$args             = $filter->get_args();
		$first            = reset( $args );
		$comment_meta_ids = end( $first );
		$this->assertSameSets( self::$comment_ids, $comment_meta_ids );
	}

	/**
	 * @ticket 57901
	 *
	 * @covers ::wp_queue_comments_for_comment_meta_lazyload
	 */
	public function test_wp_queue_comments_for_comment_meta_lazyload_new_comment() {
		$filter = new MockAction();
		add_filter( 'update_comment_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		$comments   = array_map( 'get_comment', self::$comment_ids );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);
		wp_queue_comments_for_comment_meta_lazyload( $comments );
		get_comment_meta( $comment_id );

		$args             = $filter->get_args();
		$first            = reset( $args );
		$comment_meta_ids = end( $first );
		$this->assertContains( $comment_id, $comment_meta_ids );
	}
}
