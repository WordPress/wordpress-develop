<?php

/**
 * @group comment
 *
 * @covers WP_Comment::get_instance
 */
class Tests_Comment_WpComment extends WP_UnitTestCase {
	protected static int $comment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		// Ensure that there is a comment with ID 1.
		$comment_1 = WP_Comment::get_instance( 1 );
		if ( ! $comment_1 ) {
			$wpdb->insert(
				$wpdb->comments,
				array(
					'comment_ID' => 1,
				)
			);

			clean_comment_cache( 1 );
		}

		self::$comment_id = $factory->comment->create();
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_work_for_numeric_string() {
		$found = WP_Comment::get_instance( (string) self::$comment_id );

		$this->assertSame( (string) self::$comment_id, $found->comment_ID );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_negative_number() {
		$found = WP_Comment::get_instance( -self::$comment_id );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_non_numeric_string() {
		$found = WP_Comment::get_instance( 'abc' );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_succeed_for_float_that_is_equal_to_post_id() {
		$found = WP_Comment::get_instance( 1.0 );

		$this->assertSame( '1', $found->comment_ID );
	}

	/**
	 * Tests that a cached value which cannot be used as a comment is treated as a cache miss.
	 *
	 * @ticket 65962
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss( $cache_value ): void {
		wp_cache_set( self::$comment_id, $cache_value, 'comment' );

		$num_queries = get_num_queries();

		$comment = WP_Comment::get_instance( self::$comment_id );

		$this->assertInstanceOf( WP_Comment::class, $comment, 'A comment object was not returned.' );
		$this->assertSame( (string) self::$comment_id, $comment->comment_ID, 'The wrong comment was returned.' );
		$this->assertSame( $num_queries + 1, get_num_queries(), 'The comment was not fetched from the database.' );
	}

	/**
	 * Tests that the refetched comment replaces the poisoned cache value.
	 *
	 * Otherwise the poisoned value survives and every subsequent lookup queries the database again.
	 *
	 * @ticket 65962
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_replaces_a_poisoned_cache_value( $cache_value ): void {
		wp_cache_set( self::$comment_id, $cache_value, 'comment' );

		// Prime the object cache, replacing the poisoned value.
		WP_Comment::get_instance( self::$comment_id );

		$num_queries = get_num_queries();

		$comment = WP_Comment::get_instance( self::$comment_id );

		$this->assertInstanceOf( WP_Comment::class, $comment, 'A comment object was not returned.' );
		$this->assertSame( (string) self::$comment_id, $comment->comment_ID, 'The wrong comment was returned.' );
		$this->assertSame( $num_queries, get_num_queries(), 'The database was queried again.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ mixed }>
	 */
	public function data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss(): array {
		return array(
			'true'                            => array( true ),
			'a non-numeric string'            => array( 'not-a-comment' ),
			'an empty array'                  => array( array() ),
			'an array of comment data'        => array(
				array(
					'comment_ID'      => '1',
					'comment_content' => 'Hello world.',
				),
			),
			'an object without comment_ID'    => array(
				(object) array(
					'comment_content' => 'Hello world.',
				),
			),
			'a WP_Comment without comment_ID' => array( new WP_Comment( new stdClass() ) ),
		);
	}

	/**
	 * @ticket 64898
	 *
	 * @covers WP_Comment::get_children
	 */
	public function test_get_children_should_return_count_without_storing_it_in_the_children_cache(): void {
		$post_id  = self::factory()->post->create();
		$parent   = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		$children = self::factory()->comment->create_many(
			2,
			array(
				'comment_post_ID' => $post_id,
				'comment_parent'  => $parent,
			)
		);

		$this->assertIsInt( $parent );
		$comment = get_comment( $parent );
		$this->assertInstanceOf( WP_Comment::class, $comment );

		$count = $comment->get_children( array( 'count' => true ) );
		$this->assertSame( 2, $count, 'Expected the number of direct children.' );

		$found = $comment->get_children();
		$this->assertContainsOnlyInstancesOf( 'WP_Comment', $found, 'Expected WP_Comment objects from a subsequent default query.' );

		$found_ids = array();
		foreach ( $found as $child ) {
			$found_ids[] = (int) $child->comment_ID;
		}
		$this->assertSameSets( $children, $found_ids, 'Expected the children cache to be unaffected by the count query.' );
	}

	/**
	 * @ticket 64898
	 *
	 * @covers WP_Comment::get_children
	 */
	public function test_get_children_should_return_ids_without_storing_them_in_the_children_cache(): void {
		$post_id  = self::factory()->post->create();
		$parent   = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		$children = self::factory()->comment->create_many(
			2,
			array(
				'comment_post_ID' => $post_id,
				'comment_parent'  => $parent,
			)
		);

		$this->assertIsInt( $parent );
		$comment = get_comment( $parent );
		$this->assertInstanceOf( WP_Comment::class, $comment );

		$ids = $comment->get_children( array( 'fields' => 'ids' ) );
		$this->assertSameSets( $children, $ids, 'Expected the IDs of the direct children.' );

		$found = $comment->get_children();
		$this->assertContainsOnlyInstancesOf( 'WP_Comment', $found, 'Expected WP_Comment objects from a subsequent default query.' );

		$found_ids = array();
		foreach ( $found as $child ) {
			$found_ids[] = (int) $child->comment_ID;
		}
		$this->assertSameSets( $children, $found_ids, 'Expected the children cache to be unaffected by the IDs query.' );
	}

	/**
	 * @ticket 64898
	 *
	 * @covers WP_Comment::__isset
	 * @covers WP_Comment::__get
	 */
	public function test_post_fields_should_be_null_when_the_comments_post_is_deleted(): void {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->assertIsInt( $post_id );
		$this->assertIsInt( $comment_id );
		$comment = get_comment( $comment_id );
		$this->assertInstanceOf( WP_Comment::class, $comment );

		wp_delete_post( $post_id, true );

		$this->assertFalse( isset( $comment->post_title ), 'Expected __isset() to return false when the post is deleted.' );
		$this->assertNull( $comment->post_title, 'Expected __get() to return null when the post is deleted.' );
	}

	/**
	 * @ticket 64898
	 *
	 * @covers WP_Comment::__isset
	 * @covers WP_Comment::__get
	 */
	public function test_post_fields_should_be_null_for_an_unattached_comment(): void {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => 0 ) );

		$this->assertIsInt( $post_id );
		$this->assertIsInt( $comment_id );
		$comment = get_comment( $comment_id );
		$this->assertInstanceOf( WP_Comment::class, $comment );

		$GLOBALS['post'] = get_post( $post_id );

		$is_set = isset( $comment->post_title );
		$title  = $comment->post_title;

		unset( $GLOBALS['post'] );

		$this->assertFalse( $is_set, 'Expected __isset() to return false for an unattached comment.' );
		$this->assertNull( $title, 'Expected __get() not to read the field from the global post.' );
	}
}
