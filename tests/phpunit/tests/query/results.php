<?php

/**
 * Test various query vars and make sure the WP_Query class selects the correct posts.
 * We're testing against a known data set, so we can check that specific posts are included in the output.
 *
 * @group query
 *
 * @covers WP_Query::query
 */
class Tests_Query_Results extends WP_UnitTestCase {
	protected $q;

	public static $cat_ids  = array();
	public static $tag_ids  = array();
	public static $post_ids = array();

	public static $parent_one;
	public static $parent_two;
	public static $parent_three;
	public static $child_one;
	public static $child_two;
	public static $child_three;
	public static $child_four;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$cat_a           = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'cat-a',
			)
		);
		self::$cat_ids[] = $cat_a;
		$cat_b           = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'cat-b',
			)
		);
		self::$cat_ids[] = $cat_b;
		$cat_c           = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'cat-c',
			)
		);
		self::$cat_ids[] = $cat_c;

		$tag_a           = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'tag-a',
			)
		);
		self::$tag_ids[] = $tag_a;
		$tag_b           = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'tag-b',
			)
		);
		self::$tag_ids[] = $tag_b;
		$tag_c           = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'tag-c',
			)
		);
		self::$tag_ids[] = $tag_c;
		$tag_nun         = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'tag-נ',
			)
		);
		self::$tag_ids[] = $tag_nun;

		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tag-נ',
				'tags_input' => array( 'tag-נ' ),
				'post_date'  => '2008-11-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title'    => 'cats-a-b-c',
				'post_date'     => '2008-12-01 00:00:00',
				'post_category' => array( $cat_a, $cat_b, $cat_c ),
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title'    => 'cats-a-and-b',
				'post_date'     => '2009-01-01 00:00:00',
				'post_category' => array( $cat_a, $cat_b ),
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title'    => 'cats-b-and-c',
				'post_date'     => '2009-02-01 00:00:00',
				'post_category' => array( $cat_b, $cat_c ),
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title'    => 'cats-a-and-c',
				'post_date'     => '2009-03-01 00:00:00',
				'post_category' => array( $cat_a, $cat_c ),
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title'    => 'cat-a',
				'post_date'     => '2009-04-01 00:00:00',
				'post_category' => array( $cat_a ),
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title'    => 'cat-b',
				'post_date'     => '2009-05-01 00:00:00',
				'post_category' => array( $cat_b ),
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title'    => 'cat-c',
				'post_date'     => '2009-06-01 00:00:00',
				'post_category' => array( $cat_c ),
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'lorem-ipsum',
				'post_date'  => '2009-07-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'comment-test',
				'post_date'  => '2009-08-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'one-trackback',
				'post_date'  => '2009-09-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'many-trackbacks',
				'post_date'  => '2009-10-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'no-comments',
				'post_date'  => '2009-10-15 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'one-comment',
				'post_date'  => '2009-11-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'contributor-post-approved',
				'post_date'  => '2009-12-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'embedded-video',
				'post_date'  => '2010-01-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'simple-markup-test',
				'post_date'  => '2010-02-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'raw-html-code',
				'post_date'  => '2010-03-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tags-a-b-c',
				'tags_input' => array( 'tag-a', 'tag-b', 'tag-c' ),
				'post_date'  => '2010-04-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tag-a',
				'tags_input' => array( 'tag-a' ),
				'post_date'  => '2010-05-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tag-b',
				'tags_input' => array( 'tag-b' ),
				'post_date'  => '2010-06-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tag-c',
				'tags_input' => array( 'tag-c' ),
				'post_date'  => '2010-07-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tags-a-and-b',
				'tags_input' => array( 'tag-a', 'tag-b' ),
				'post_date'  => '2010-08-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tags-b-and-c',
				'tags_input' => array( 'tag-b', 'tag-c' ),
				'post_date'  => '2010-09-01 00:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'tags-a-and-c',
				'tags_input' => array( 'tag-a', 'tag-c' ),
				'post_date'  => '2010-10-01 00:00:00',
			)
		);

		self::$parent_one   = $factory->post->create(
			array(
				'post_title' => 'parent-one',
				'post_date'  => '2007-01-01 00:00:00',
			)
		);
		self::$post_ids[]   = self::$parent_one;
		self::$parent_two   = $factory->post->create(
			array(
				'post_title' => 'parent-two',
				'post_date'  => '2007-01-01 00:00:00',
			)
		);
		self::$post_ids[]   = self::$parent_two;
		self::$parent_three = $factory->post->create(
			array(
				'post_title' => 'parent-three',
				'post_date'  => '2007-01-01 00:00:00',
			)
		);
		self::$post_ids[]   = self::$parent_three;
		self::$child_one    = $factory->post->create(
			array(
				'post_title'  => 'child-one',
				'post_parent' => self::$parent_one,
				'post_date'   => '2007-01-01 00:00:01',
			)
		);
		self::$post_ids[]   = self::$child_one;
		self::$child_two    = $factory->post->create(
			array(
				'post_title'  => 'child-two',
				'post_parent' => self::$parent_one,
				'post_date'   => '2007-01-01 00:00:02',
			)
		);
		self::$post_ids[]   = self::$child_two;
		self::$child_three  = $factory->post->create(
			array(
				'post_title'  => 'child-three',
				'post_parent' => self::$parent_two,
				'post_date'   => '2007-01-01 00:00:03',
			)
		);
		self::$post_ids[]   = self::$child_three;
		self::$child_four   = $factory->post->create(
			array(
				'post_title'  => 'child-four',
				'post_parent' => self::$parent_two,
				'post_date'   => '2007-01-01 00:00:04',
			)
		);
		self::$post_ids[]   = self::$child_four;
	}

	public function set_up() {
		parent::set_up();

		unset( $this->q );
		$this->q = new WP_Query();
	}

	public function test_query_default() {
		$posts = $this->q->query( '' );

		// The output should be the most recent 10 posts as listed here.
		$expected = array(
			0 => 'tags-a-and-c',
			1 => 'tags-b-and-c',
			2 => 'tags-a-and-b',
			3 => 'tag-c',
			4 => 'tag-b',
			5 => 'tag-a',
			6 => 'tags-a-b-c',
			7 => 'raw-html-code',
			8 => 'simple-markup-test',
			9 => 'embedded-video',
		);

		$this->assertSame( $expected, wp_list_pluck( $posts, 'post_name' ) );
	}

	public function test_query_tag_a() {
		$posts = $this->q->query( 'tag=tag-a' );

		// There are 4 posts with Tag A.
		$this->assertCount( 4, $posts );
		$this->assertSame( 'tags-a-and-c', $posts[0]->post_name );
		$this->assertSame( 'tags-a-and-b', $posts[1]->post_name );
		$this->assertSame( 'tag-a', $posts[2]->post_name );
		$this->assertSame( 'tags-a-b-c', $posts[3]->post_name );
	}

	public function test_query_tag_b() {
		$posts = $this->q->query( 'tag=tag-b' );

		// There are 4 posts with Tag A.
		$this->assertCount( 4, $posts );
		$this->assertSame( 'tags-b-and-c', $posts[0]->post_name );
		$this->assertSame( 'tags-a-and-b', $posts[1]->post_name );
		$this->assertSame( 'tag-b', $posts[2]->post_name );
		$this->assertSame( 'tags-a-b-c', $posts[3]->post_name );
	}

	/**
	 * @ticket 21779
	 */
	public function test_query_tag_nun() {
		$posts = $this->q->query( 'tag=tag-נ' );

		// There is 1 post with Tag נ.
		$this->assertCount( 1, $posts );
		$this->assertSame( 'tag-%d7%a0', $posts[0]->post_name );
	}

	public function test_query_tag_id() {
		$tag   = tag_exists( 'tag-a' );
		$posts = $this->q->query( 'tag_id=' . $tag['term_id'] );

		// There are 4 posts with Tag A.
		$this->assertCount( 4, $posts );
		$this->assertSame( 'tags-a-and-c', $posts[0]->post_name );
		$this->assertSame( 'tags-a-and-b', $posts[1]->post_name );
		$this->assertSame( 'tag-a', $posts[2]->post_name );
		$this->assertSame( 'tags-a-b-c', $posts[3]->post_name );
	}

	public function test_query_tag_slug__in() {
		$posts = $this->q->query( 'tag_slug__in[]=tag-b&tag_slug__in[]=tag-c' );

		// There are 4 posts with either Tag B or Tag C.
		$this->assertCount( 6, $posts );
		$this->assertSame( 'tags-a-and-c', $posts[0]->post_name );
		$this->assertSame( 'tags-b-and-c', $posts[1]->post_name );
		$this->assertSame( 'tags-a-and-b', $posts[2]->post_name );
		$this->assertSame( 'tag-c', $posts[3]->post_name );
		$this->assertSame( 'tag-b', $posts[4]->post_name );
		$this->assertSame( 'tags-a-b-c', $posts[5]->post_name );
	}


	public function test_query_tag__in() {
		$tag_a = tag_exists( 'tag-a' );
		$tag_b = tag_exists( 'tag-b' );
		$posts = $this->q->query( 'tag__in[]=' . $tag_a['term_id'] . '&tag__in[]=' . $tag_b['term_id'] );

		// There are 6 posts with either Tag A or Tag B.
		$this->assertCount( 6, $posts );
		$this->assertSame( 'tags-a-and-c', $posts[0]->post_name );
		$this->assertSame( 'tags-b-and-c', $posts[1]->post_name );
		$this->assertSame( 'tags-a-and-b', $posts[2]->post_name );
		$this->assertSame( 'tag-b', $posts[3]->post_name );
		$this->assertSame( 'tag-a', $posts[4]->post_name );
		$this->assertSame( 'tags-a-b-c', $posts[5]->post_name );
	}

	public function test_query_tag__not_in() {
		$tag_a = tag_exists( 'tag-a' );
		$posts = $this->q->query( 'tag__not_in[]=' . $tag_a['term_id'] );

		// The most recent 10 posts with Tag A excluded
		// (note the different between this and test_query_default).
		$expected = array(
			0 => 'tags-b-and-c',
			1 => 'tag-c',
			2 => 'tag-b',
			3 => 'raw-html-code',
			4 => 'simple-markup-test',
			5 => 'embedded-video',
			6 => 'contributor-post-approved',
			7 => 'one-comment',
			8 => 'no-comments',
			9 => 'many-trackbacks',
		);

		$this->assertSame( $expected, wp_list_pluck( $posts, 'post_name' ) );
	}

	public function test_query_tag__in_but__not_in() {
		$tag_a = tag_exists( 'tag-a' );
		$tag_b = tag_exists( 'tag-b' );
		$posts = $this->q->query( 'tag__in[]=' . $tag_a['term_id'] . '&tag__not_in[]=' . $tag_b['term_id'] );

		// There are 4 posts with Tag A, only 2 when we exclude Tag B.
		$this->assertCount( 2, $posts );
		$this->assertSame( 'tags-a-and-c', $posts[0]->post_name );
		$this->assertSame( 'tag-a', $posts[1]->post_name );
	}



	public function test_query_category_name() {
		$posts = $this->q->query( 'category_name=cat-a' );

		// There are 4 posts with Cat A, we'll check for them by name.
		$this->assertCount( 4, $posts );
		$this->assertSame( 'cat-a', $posts[0]->post_name );
		$this->assertSame( 'cats-a-and-c', $posts[1]->post_name );
		$this->assertSame( 'cats-a-and-b', $posts[2]->post_name );
		$this->assertSame( 'cats-a-b-c', $posts[3]->post_name );
	}

	public function test_query_cat() {
		$cat   = category_exists( 'cat-b' );
		$posts = $this->q->query( "cat=$cat" );

		// There are 4 posts with Cat B.
		$this->assertCount( 4, $posts );
		$this->assertSame( 'cat-b', $posts[0]->post_name );
		$this->assertSame( 'cats-b-and-c', $posts[1]->post_name );
		$this->assertSame( 'cats-a-and-b', $posts[2]->post_name );
		$this->assertSame( 'cats-a-b-c', $posts[3]->post_name );
	}

	public function test_query_posts_per_page() {
		$posts = $this->q->query( 'posts_per_page=5' );

		$expected = array(
			0 => 'tags-a-and-c',
			1 => 'tags-b-and-c',
			2 => 'tags-a-and-b',
			3 => 'tag-c',
			4 => 'tag-b',
		);

		$this->assertCount( 5, $posts );
		$this->assertSame( $expected, wp_list_pluck( $posts, 'post_name' ) );
	}

	public function test_query_offset() {
		$posts = $this->q->query( 'offset=2' );

		$expected = array(
			0 => 'tags-a-and-b',
			1 => 'tag-c',
			2 => 'tag-b',
			3 => 'tag-a',
			4 => 'tags-a-b-c',
			5 => 'raw-html-code',
			6 => 'simple-markup-test',
			7 => 'embedded-video',
			8 => 'contributor-post-approved',
			9 => 'one-comment',
		);

		$this->assertCount( 10, $posts );
		$this->assertSame( $expected, wp_list_pluck( $posts, 'post_name' ) );
	}

	public function test_query_paged() {
		$posts = $this->q->query( 'paged=2' );

		$expected = array(
			0 => 'contributor-post-approved',
			1 => 'one-comment',
			2 => 'no-comments',
			3 => 'many-trackbacks',
			4 => 'one-trackback',
			5 => 'comment-test',
			6 => 'lorem-ipsum',
			7 => 'cat-c',
			8 => 'cat-b',
			9 => 'cat-a',
		);

		$this->assertCount( 10, $posts );
		$this->assertTrue( $this->q->is_paged() );
		$this->assertSame( $expected, wp_list_pluck( $posts, 'post_name' ) );
	}

	public function test_query_paged_and_posts_per_page() {
		$posts = $this->q->query( 'paged=4&posts_per_page=4' );

		$expected = array(
			0 => 'no-comments',
			1 => 'many-trackbacks',
			2 => 'one-trackback',
			3 => 'comment-test',
		);

		$this->assertCount( 4, $posts );
		$this->assertTrue( $this->q->is_paged() );
		$this->assertSame( $expected, wp_list_pluck( $posts, 'post_name' ) );
	}

	/**
	 * @ticket 11056
	 */
	public function test_query_post_parent__in() {
		// Query for first parent's children.
		$posts = $this->q->query(
			array(
				'post_parent__in' => array( self::$parent_one ),
				'orderby'         => 'date',
				'order'           => 'asc',
			)
		);

		$this->assertSame(
			array(
				'child-one',
				'child-two',
			),
			wp_list_pluck( $posts, 'post_title' )
		);

		// Second parent's children.
		$posts = $this->q->query(
			array(
				'post_parent__in' => array( self::$parent_two ),
				'orderby'         => 'date',
				'order'           => 'asc',
			)
		);

		$this->assertSame(
			array(
				'child-three',
				'child-four',
			),
			wp_list_pluck( $posts, 'post_title' )
		);

		// Both first and second parent's children.
		$posts = $this->q->query(
			array(
				'post_parent__in' => array( self::$parent_one, self::$parent_two ),
				'orderby'         => 'date',
				'order'           => 'asc',
			)
		);

		$this->assertSame(
			array(
				'child-one',
				'child-two',
				'child-three',
				'child-four',
			),
			wp_list_pluck( $posts, 'post_title' )
		);

		// Third parent's children.
		$posts = $this->q->query(
			array(
				'post_parent__in' => array( self::$parent_three ),
			)
		);

		$this->assertSame( array(), wp_list_pluck( $posts, 'post_title' ) );
	}

	/**
	 * @ticket 11056
	 */
	public function test_query_orderby_post_parent__in() {
		$posts = $this->q->query(
			array(
				'post_parent__in' => array( self::$parent_two, self::$parent_one ),
				'orderby'         => 'post_parent__in',
				'order'           => 'asc',
			)
		);

		$this->assertSame(
			array(
				'child-three',
				'child-four',
				'child-one',
				'child-two',
			),
			wp_list_pluck( $posts, 'post_title' )
		);
	}

	/**
	 * @ticket 39055
	 */
	public function test_query_orderby_post_parent__in_with_order_desc() {
		$post_parent__in_array   = array( self::$parent_two, self::$parent_one );
		$expected_returned_array = array( 'child-three', 'child-four', 'child-one', 'child-two' );

		$posts = $this->q->query(
			array(
				'post_parent__in' => $post_parent__in_array,
				'orderby'         => 'post_parent__in',
				'order'           => 'desc',
			)
		);

		// 'order=desc' does not influence the order of returned results (returns same order as 'order=asc').
		$this->assertSame( $expected_returned_array, wp_list_pluck( $posts, 'post_title' ) );
	}

	/**
	 * @ticket 39055
	 */
	public function test_query_orderby_post__in_with_no_order_specified() {
		$post__in_array          = array( self::$post_ids[2], self::$post_ids[0], self::$post_ids[1] );
		$expected_returned_array = array( self::$post_ids[2], self::$post_ids[0], self::$post_ids[1] );

		$q = new WP_Query(
			array(
				'post__in' => $post__in_array,
				'orderby'  => 'post__in',
				'fields'   => 'ids',
			)
		);

		// Expect post IDs in the same order as post__in array when no 'order' param is passed in.
		$this->assertSame( $expected_returned_array, $q->posts );
	}

	/**
	 * @ticket 39055
	 */
	public function test_query_orderby_post__in_with_order_asc() {
		$post__in_array          = array( self::$post_ids[2], self::$post_ids[0], self::$post_ids[1] );
		$expected_returned_array = array( self::$post_ids[2], self::$post_ids[0], self::$post_ids[1] );

		$q = new WP_Query(
			array(
				'post__in' => $post__in_array,
				'orderby'  => 'post__in',
				'order'    => 'asc',
				'fields'   => 'ids',
			)
		);

		// Expect post IDs in the same order as post__in array when order=asc is passed in.
		$this->assertSame( $expected_returned_array, $q->posts );
	}

	/**
	 * @ticket 39055
	 */
	public function test_query_orderby_post__in_with_order_desc() {
		$post__in_array          = array( self::$post_ids[1], self::$post_ids[2], self::$post_ids[0] );
		$expected_returned_array = array( self::$post_ids[1], self::$post_ids[2], self::$post_ids[0] );

		$q = new WP_Query(
			array(
				'post__in' => $post__in_array,
				'orderby'  => 'post__in',
				'order'    => 'desc',
				'fields'   => 'ids',
			)
		);

		// Note that results are returned in the order specified in the post__in array.
		// 'order=desc' does not influence the order of returned results.
		$this->assertSame( $expected_returned_array, $q->posts );
	}

	/**
	 * @ticket 39055
	 */
	public function test_query_orderby_post_name__in_with_order_asc() {
		$post_name__in_array = array( 'parent-two', 'parent-one', 'parent-three' );

		$q = new WP_Query(
			array(
				'post_name__in' => $post_name__in_array,
				'orderby'       => 'post_name__in',
				'order'         => 'asc',
			)
		);

		$this->assertSame( $post_name__in_array, array_unique( wp_list_pluck( $q->posts, 'post_title' ) ) );
	}

	/**
	 * @ticket 39055
	 */
	public function test_query_orderby_post_name__in_with_order_desc() {
		$post_name__in_array = array( 'parent-two', 'parent-one', 'parent-three' );

		$q = new WP_Query(
			array(
				'post_name__in' => $post_name__in_array,
				'orderby'       => 'post_name__in',
				'order'         => 'desc',
			)
		);

		// 'order=desc' does not influence the order of returned results (returns same order as 'order=asc').
		$this->assertSame( $post_name__in_array, array_unique( wp_list_pluck( $q->posts, 'post_title' ) ) );
	}

	/**
	 * @ticket 27252
	 * @ticket 31194
	 */
	public function test_query_fields_integers() {

		$parents = array(
			(int) self::$parent_one,
			(int) self::$parent_two,
		);
		$posts1  = $this->q->query(
			array(
				'post__in' => $parents,
				'fields'   => 'ids',
				'orderby'  => 'post__in',
			)
		);

		$this->assertSame( $parents, $posts1 );
		$this->assertSame( $parents, $this->q->posts );

		$children = array(
			(int) self::$child_one => (int) self::$parent_one,
			(int) self::$child_two => (int) self::$parent_one,
		);

		$posts2 = $this->q->query(
			array(
				'post__in' => array_keys( $children ),
				'fields'   => 'id=>parent',
				'orderby'  => 'post__in',
			)
		);

		$this->assertSame( $children, $posts2 );

		foreach ( $this->q->posts as $post ) {
			$this->assertIsInt( $post->ID );
			$this->assertIsInt( $post->post_parent );
		}
	}

	/**
	 * @ticket 28099
	 */
	public function test_empty_post__in() {
		$posts1 = $this->q->query( array() );
		$this->assertNotEmpty( $posts1 );
		$posts2 = $this->q->query( array( 'post__in' => array() ) );
		$this->assertNotEmpty( $posts2 );
		$posts3 = $this->q->query( array( 'post_parent__in' => array() ) );
		$this->assertNotEmpty( $posts3 );
	}

	/**
	 * @ticket 19198
	 */
	public function test_exclude_from_search_empty() {
		global $wp_post_types;
		foreach ( array_keys( $wp_post_types ) as $slug ) {
			$wp_post_types[ $slug ]->exclude_from_search = true;
		}

		$posts = $this->q->query( array( 'post_type' => 'any' ) );

		$this->assertEmpty( $posts );
		$this->assertMatchesRegularExpression( '#AND 1=0#', $this->q->request );

		foreach ( array_keys( $wp_post_types ) as $slug ) {
			$wp_post_types[ $slug ]->exclude_from_search = false;
		}

		$posts2 = $this->q->query( array( 'post_type' => 'any' ) );

		$this->assertNotEmpty( $posts2 );
		$this->assertDoesNotMatchRegularExpression( '#AND 1=0#', $this->q->request );
	}

	/**
	 * @ticket 16854
	 */
	public function test_query_author_vars() {
		$author_1 = self::factory()->user->create(
			array(
				'user_login' => 'author1',
				'role'       => 'author',
			)
		);
		$post_1   = self::factory()->post->create(
			array(
				'post_title'  => 'Post 1',
				'post_author' => $author_1,
				'post_date'   => '2007-01-01 00:00:00',
			)
		);

		$author_2 = self::factory()->user->create(
			array(
				'user_login' => 'author2',
				'role'       => 'author',
			)
		);
		$post_2   = self::factory()->post->create(
			array(
				'post_title'  => 'Post 2',
				'post_author' => $author_2,
				'post_date'   => '2007-01-01 00:00:00',
			)
		);

		$author_3 = self::factory()->user->create(
			array(
				'user_login' => 'author3',
				'role'       => 'author',
			)
		);
		$post_3   = self::factory()->post->create(
			array(
				'post_title'  => 'Post 3',
				'post_author' => $author_3,
				'post_date'   => '2007-01-01 00:00:00',
			)
		);

		$author_4 = self::factory()->user->create(
			array(
				'user_login' => 'author4',
				'role'       => 'author',
			)
		);
		$post_4   = self::factory()->post->create(
			array(
				'post_title'  => 'Post 4',
				'post_author' => $author_4,
				'post_date'   => '2007-01-01 00:00:00',
			)
		);

		$posts      = $this->q->query(
			array(
				'author'   => '',
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1, $author_2, $author_3, $author_4 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => 0,
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1, $author_2, $author_3, $author_4 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => '0',
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1, $author_2, $author_3, $author_4 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => $author_1,
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => "$author_1",
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => "{$author_1},{$author_2}",
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1, $author_2 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => "-{$author_1},{$author_2}",
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_2, $author_3, $author_4 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => "{$author_1},-{$author_2}",
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1, $author_3, $author_4 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author'   => "-{$author_1},-{$author_2}",
				'post__in' => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_3, $author_4 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author__in' => array( $author_1, $author_2 ),
				'post__in'   => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1, $author_2 ), $author_ids );

		$posts = $this->q->query( array( 'author__in' => array() ) );
		$this->assertNotEmpty( $posts );

		$posts      = $this->q->query(
			array(
				'author__not_in' => array( $author_1, $author_2 ),
				'post__in'       => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_3, $author_4 ), $author_ids );

		$posts      = $this->q->query(
			array(
				'author_name' => 'author1',
				'post__in'    => array( $post_1, $post_2, $post_3, $post_4 ),
			)
		);
		$author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
		$this->assertEqualSets( array( $author_1 ), $author_ids );
	}

	/**
	 * @ticket 10935
	 */
	public function test_query_is_date() {
		$this->q->query(
			array(
				'year'     => '2007',
				'monthnum' => '01',
				'day'      => '01',
			)
		);

		$this->assertTrue( $this->q->is_date );
		$this->assertTrue( $this->q->is_day );
		$this->assertFalse( $this->q->is_month );
		$this->assertFalse( $this->q->is_year );

		$this->q->query(
			array(
				'year'     => '2007',
				'monthnum' => '01',
			)
		);

		$this->assertTrue( $this->q->is_date );
		$this->assertFalse( $this->q->is_day );
		$this->assertTrue( $this->q->is_month );
		$this->assertFalse( $this->q->is_year );

		$this->q->query(
			array(
				'year' => '2007',
			)
		);

		$this->assertTrue( $this->q->is_date );
		$this->assertFalse( $this->q->is_day );
		$this->assertFalse( $this->q->is_month );
		$this->assertTrue( $this->q->is_year );
	}

	/**
	 * @ticket 10935
	 * @expectedIncorrectUsage WP_Date_Query
	 */
	public function test_query_is_date_with_bad_date() {
		$this->q->query(
			array(
				'year'     => '2007',
				'monthnum' => '01',
				'day'      => '50',
			)
		);

		$this->assertTrue( $this->q->is_404 );
		$this->assertFalse( $this->q->is_date );
		$this->assertFalse( $this->q->is_day );
		$this->assertFalse( $this->q->is_month );
		$this->assertFalse( $this->q->is_year );
	}

	public function test_perm_with_status_array() {
		global $wpdb;
		$this->q->query(
			array(
				'perm'        => 'readable',
				'post_status' => array( 'publish', 'private' ),
			)
		);
		$this->assertTrue( $this->q->have_posts() );
		$this->assertStringContainsString(
			"(({$wpdb->posts}.post_status = 'publish') OR ({$wpdb->posts}.post_author = 0 AND ({$wpdb->posts}.post_status = 'private')))",
			$this->q->request
		);
		$this->assertStringNotContainsString( "({$wpdb->posts}.post_status = 'publish') AND", $this->q->request );
	}

	/**
	 * @ticket 20308
	 */
	public function test_post_password() {
		$one   = (string) self::factory()->post->create( array( 'post_password' => '' ) );
		$two   = (string) self::factory()->post->create( array( 'post_password' => 'burrito' ) );
		$three = (string) self::factory()->post->create( array( 'post_password' => 'burrito' ) );

		$args = array(
			'post__in' => array( $one, $two, $three ),
			'fields'   => 'ids',
		);

		$result1 = $this->q->query( array_merge( $args, array( 'has_password' => true ) ) );
		$this->assertEqualSets( array( $two, $three ), $result1 );
		$result2 = $this->q->query( array_merge( $args, array( 'has_password' => false ) ) );
		$this->assertEquals( array( $one ), $result2 );

		// This is equivalent to not passing it at all.
		$result3 = $this->q->query( array_merge( $args, array( 'has_password' => null ) ) );
		$this->assertEqualSets( array( $one, $two, $three ), $result3 );

		// If both arguments are passed, only post_password is considered.
		$result4 = $this->q->query(
			array_merge(
				$args,
				array(
					'has_password'  => true,
					'post_password' => '',
				)
			)
		);
		$this->assertEquals( array( $one ), $result4 );
		$result5 = $this->q->query(
			array_merge(
				$args,
				array(
					'has_password'  => false,
					'post_password' => '',
				)
			)
		);
		$this->assertEquals( array( $one ), $result5 );
		$result6 = $this->q->query(
			array_merge(
				$args,
				array(
					'has_password'  => null,
					'post_password' => '',
				)
			)
		);
		$this->assertEquals( array( $one ), $result6 );

		$result7 = $this->q->query(
			array_merge(
				$args,
				array(
					'has_password'  => true,
					'post_password' => 'burrito',
				)
			)
		);
		$this->assertEqualSets( array( $two, $three ), $result7 );
		$result8 = $this->q->query(
			array_merge(
				$args,
				array(
					'has_password'  => false,
					'post_password' => 'burrito',
				)
			)
		);
		$this->assertEqualSets( array( $two, $three ), $result8 );
		$result9 = $this->q->query(
			array_merge(
				$args,
				array(
					'has_password'  => null,
					'post_password' => 'burrito',
				)
			)
		);
		$this->assertEqualSets( array( $two, $three ), $result9 );

		$result10 = $this->q->query( array_merge( $args, array( 'post_password' => '' ) ) );
		$this->assertEquals( array( $one ), $result10 );
		$result11 = $this->q->query( array_merge( $args, array( 'post_password' => 'burrito' ) ) );
		$this->assertEqualSets( array( $two, $three ), $result11 );
	}

	/**
	 * @ticket 28611
	 */
	public function test_duplicate_slug_in_hierarchical_post_type() {
		register_post_type( 'handbook', array( 'hierarchical' => true ) );

		$post_1 = self::factory()->post->create(
			array(
				'post_title' => 'Getting Started',
				'post_type'  => 'handbook',
			)
		);
		$post_2 = self::factory()->post->create(
			array(
				'post_title' => 'Contributing to the WordPress Codex',
				'post_type'  => 'handbook',
			)
		);
		$post_3 = self::factory()->post->create(
			array(
				'post_title'  => 'Getting Started',
				'post_parent' => $post_2,
				'post_type'   => 'handbook',
			)
		);

		$result = $this->q->query(
			array(
				'handbook'  => 'getting-started',
				'post_type' => 'handbook',
			)
		);
		$this->assertCount( 1, $result );
	}

	/**
	 * @ticket 29615
	 */
	public function test_child_post_in_hierarchical_post_type_with_default_permalinks() {
		register_post_type( 'handbook', array( 'hierarchical' => true ) );

		$post_1 = self::factory()->post->create(
			array(
				'post_title' => 'Contributing to the WordPress Codex',
				'post_type'  => 'handbook',
			)
		);
		$post_2 = self::factory()->post->create(
			array(
				'post_title'  => 'Getting Started',
				'post_parent' => $post_1,
				'post_type'   => 'handbook',
			)
		);

		$this->assertStringContainsString( 'contributing-to-the-wordpress-codex/getting-started', get_permalink( $post_2 ) );

		$result = $this->q->query(
			array(
				'handbook'  => 'contributing-to-the-wordpress-codex/getting-started',
				'post_type' => 'handbook',
			)
		);
		$this->assertCount( 1, $result );
	}

	public function test_title() {
		$title   = 'Tacos are Cool';
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$result1 = $this->q->query(
			array(
				'title'  => $title,
				'fields' => 'ids',
			)
		);
		$this->assertCount( 1, $result1 );
		$this->assertContains( $post_id, $result1 );

		$result2 = $this->q->query(
			array(
				'title'  => 'Tacos',
				'fields' => 'ids',
			)
		);
		$this->assertCount( 0, $result2 );
	}

	/**
	 * @ticket 15610
	 */
	public function test_main_comments_feed_includes_attachment_comments() {
		$attachment_id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		$comment_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $attachment_id,
				'comment_approved' => '1',
			)
		);

		$this->q->query(
			array(
				'withcomments' => 1,
				'feed'         => 'feed',
			)
		);

		$this->assertTrue( $this->q->have_comments() );

		$feed_comment = $this->q->next_comment();
		$this->assertEquals( $comment_id, $feed_comment->comment_ID );
	}
}
