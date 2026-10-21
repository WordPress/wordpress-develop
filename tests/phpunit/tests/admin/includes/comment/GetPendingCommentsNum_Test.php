<?php

/**
 * @group admin
 * @group comment
 *
 * @covers ::get_pending_comments_num
 */
class Admin_Includes_Comment_GetPendingCommentsNum_Test extends WP_UnitTestCase {

	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	public static $post_ids = array();

	/**
	 * Creates the posts and comments for the tests.
	 *
	 * Post 0 gets two pending and one approved comment, post 1 gets one pending
	 * comment, post 2 gets none.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many( 3 );

		$factory->comment->create_post_comments(
			self::$post_ids[0],
			2,
			array( 'comment_approved' => '0' )
		);
		$factory->comment->create(
			array(
				'comment_post_ID'  => self::$post_ids[0],
				'comment_approved' => '1',
			)
		);
		$factory->comment->create(
			array(
				'comment_post_ID'  => self::$post_ids[1],
				'comment_approved' => '0',
			)
		);
	}

	/**
	 * @ticket 17020
	 */
	public function test_should_return_int_for_single_post_id() {
		$this->assertSame( 2, get_pending_comments_num( self::$post_ids[0] ) );
		$this->assertSame( 0, get_pending_comments_num( self::$post_ids[2] ) );
	}

	/**
	 * @ticket 17020
	 */
	public function test_should_return_counts_keyed_by_post_id_for_array() {
		$expected = array(
			self::$post_ids[0] => 2,
			self::$post_ids[1] => 1,
			self::$post_ids[2] => 0,
		);

		$this->assertSame( $expected, get_pending_comments_num( self::$post_ids ) );
	}

	/**
	 * @ticket 17020
	 */
	public function test_should_return_empty_array_for_empty_array() {
		$filter = new MockAction();
		add_filter( 'get_pending_comments_num_query', array( $filter, 'filter' ) );

		$this->assertSame( array(), get_pending_comments_num( array() ), 'An empty array should be returned.' );
		$this->assertSame( 0, $filter->get_call_count(), 'No query should be run.' );
	}

	/**
	 * @ticket 17020
	 */
	public function test_query_filter_receives_query_and_post_ids() {
		$filter = new MockAction();
		add_filter( 'get_pending_comments_num_query', array( $filter, 'filter' ), 10, 2 );

		get_pending_comments_num( self::$post_ids );

		$this->assertSame( 1, $filter->get_call_count(), 'The filter should run once.' );

		$args = $filter->get_args()[0];
		$this->assertIsString( $args[0], 'The query should be passed as a string.' );
		$this->assertStringContainsString( "comment_approved = '0'", $args[0], 'The query should count pending comments.' );
		$this->assertSame( self::$post_ids, $args[1], 'The post IDs should be passed to the filter.' );
	}

	/**
	 * @ticket 17020
	 */
	public function test_query_filter_should_change_result() {
		// Count approved comments instead of pending ones.
		$callback = static function ( $query ) {
			return str_replace( "comment_approved = '0'", "comment_approved = '1'", $query );
		};
		add_filter( 'get_pending_comments_num_query', $callback );

		$this->assertSame( 1, get_pending_comments_num( self::$post_ids[0] ) );
		$this->assertSame( 0, get_pending_comments_num( self::$post_ids[1] ) );
	}
}
