<?php

/**
 * @group query
 * @group post
 */
class Tests_Post_Query extends WP_UnitTestCase {

	/**
	 * Temporary storage for a post ID for tests using filter callbacks.
	 *
	 * Used in the `test_posts_pre_query_filter_should_respect_set_found_posts()` method.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		unset( $this->post_id );

		parent::tear_down();
	}

	/**
	 * @group taxonomy
	 */
	public function test_category__and_var() {
		$q = new WP_Query();

		$term_id  = self::factory()->category->create(
			array(
				'slug' => 'woo',
				'name' => 'WOO!',
			)
		);
		$term_id2 = self::factory()->category->create(
			array(
				'slug' => 'hoo',
				'name' => 'HOO!',
			)
		);
		$post_id  = self::factory()->post->create();

		wp_set_post_categories( $post_id, $term_id );

		$posts = $q->query( array( 'category__and' => array( $term_id ) ) );

		$this->assertEmpty( $q->get( 'category__and' ) );
		$this->assertCount( 0, $q->get( 'category__and' ) );
		$this->assertNotEmpty( $q->get( 'category__in' ) );
		$this->assertCount( 1, $q->get( 'category__in' ) );

		$this->assertNotEmpty( $posts );
		$this->assertSame( array( $post_id ), wp_list_pluck( $posts, 'ID' ) );

		$posts2 = $q->query( array( 'category__and' => array( $term_id, $term_id2 ) ) );
		$this->assertNotEmpty( $q->get( 'category__and' ) );
		$this->assertCount( 2, $q->get( 'category__and' ) );
		$this->assertEmpty( $q->get( 'category__in' ) );
		$this->assertCount( 0, $q->get( 'category__in' ) );

		$this->assertEmpty( $posts2 );
	}

	/**
	 * @ticket 28099
	 * @group taxonomy
	 */
	public function test_empty_category__in() {
		$cat_id  = self::factory()->category->create();
		$post_id = self::factory()->post->create();
		wp_set_post_categories( $post_id, $cat_id );

		$q1 = get_posts( array( 'category__in' => array( $cat_id ) ) );
		$this->assertNotEmpty( $q1 );
		$q2 = get_posts( array( 'category__in' => array() ) );
		$this->assertNotEmpty( $q2 );

		$tag    = wp_insert_term( 'woo', 'post_tag' );
		$tag_id = $tag['term_id'];
		$slug   = get_tag( $tag_id )->slug;
		wp_set_post_tags( $post_id, $slug );

		$q3 = get_posts( array( 'tag__in' => array( $tag_id ) ) );
		$this->assertNotEmpty( $q3 );
		$q4 = get_posts( array( 'tag__in' => array() ) );
		$this->assertNotEmpty( $q4 );

		$q5 = get_posts( array( 'tag_slug__in' => array( $slug ) ) );
		$this->assertNotEmpty( $q5 );
		$q6 = get_posts( array( 'tag_slug__in' => array() ) );
		$this->assertNotEmpty( $q6 );
	}

	/**
	 * @ticket 22448
	 */
	public function test_the_posts_filter() {
		// Create posts and clear their caches.
		$post_ids = self::factory()->post->create_many( 4 );
		foreach ( $post_ids as $post_id ) {
			clean_post_cache( $post_id );
		}

		add_filter( 'the_posts', array( $this, 'the_posts_filter' ) );

		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 3,
			)
		);

		// Fourth post added in filter.
		$this->assertCount( 4, $query->posts );
		$this->assertSame( 4, $query->post_count );

		foreach ( $query->posts as $post ) {

			// Posts are WP_Post objects.
			$this->assertInstanceOf( 'WP_Post', $post );

			// Filters are raw.
			$this->assertSame( 'raw', $post->filter );

			// Custom data added in the_posts filter is preserved.
			$this->assertSame( array( $post->ID, 'custom data' ), $post->custom_data );
		}

		remove_filter( 'the_posts', array( $this, 'the_posts_filter' ) );
	}

	/**
	 * Use with the_posts filter, appends a post and adds some custom data.
	 */
	public function the_posts_filter( $posts ) {
		$posts[] = clone $posts[0];

		// Add some custom data to each post.
		foreach ( $posts as $key => $post ) {
			$posts[ $key ]->custom_data = array( $post->ID, 'custom data' );
		}

		return $posts;
	}

	public function test_post__in_ordering() {
		$post_id1 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'menu_order' => 1,
			)
		);
		$post_id2 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'menu_order' => 2,
			)
		);
		$post_id3 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $post_id2,
				'menu_order'  => 3,
			)
		);
		$post_id4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $post_id2,
				'menu_order'  => 4,
			)
		);
		$post_id5 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'menu_order' => 5,
			)
		);

		$ordered = array( $post_id2, $post_id4, $post_id3, $post_id1, $post_id5 );

		$q = new WP_Query(
			array(
				'post_type' => 'any',
				'post__in'  => $ordered,
				'orderby'   => 'post__in',
			)
		);
		$this->assertSame( $ordered, wp_list_pluck( $q->posts, 'ID' ) );
	}

	/**
	 * @ticket 38034
	 */
	public function test_orderby_post__in_array() {
		$posts = self::factory()->post->create_many( 4 );

		$ordered = array( $posts[2], $posts[0], $posts[3] );

		$q = new WP_Query(
			array(
				'post_type' => 'any',
				'post__in'  => $ordered,
				'orderby'   => array( 'post__in' => 'ASC' ),
			)
		);
		$this->assertSame( $ordered, wp_list_pluck( $q->posts, 'ID' ) );
	}

	/**
	 * @ticket 38034
	 */
	public function test_orderby_post__in_array_with_implied_order() {
		$posts = self::factory()->post->create_many( 4 );

		$ordered = array( $posts[2], $posts[0], $posts[3] );

		$q = new WP_Query(
			array(
				'post_type' => 'any',
				'post__in'  => $ordered,
				'orderby'   => 'post__in',
			)
		);
		$this->assertSame( $ordered, wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_post__in_attachment_ordering() {
		$post_id    = self::factory()->post->create();
		$att_ids    = array();
		$file       = DIR_TESTDATA . '/images/canola.jpg';
		$att_ids[1] = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'menu_order'     => 1,
			)
		);
		$att_ids[2] = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'menu_order'     => 2,
			)
		);
		$att_ids[3] = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'menu_order'     => 3,
			)
		);
		$att_ids[4] = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'menu_order'     => 4,
			)
		);
		$att_ids[5] = self::factory()->attachment->create_object(
			$file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'menu_order'     => 5,
			)
		);

		$ordered = array( $att_ids[5], $att_ids[1], $att_ids[4], $att_ids[3], $att_ids[2] );

		$attached = new WP_Query(
			array(
				'post__in'       => $ordered,
				'post_type'      => 'attachment',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => '-1',
				'orderby'        => 'post__in',
			)
		);
		$this->assertSame( $ordered, wp_list_pluck( $attached->posts, 'ID' ) );
	}

	/**
	 * @ticket 36515
	 */
	public function test_post_name__in_ordering() {
		$post_id1 = self::factory()->post->create(
			array(
				'post_name' => 'id-1',
				'post_type' => 'page',
			)
		);
		$post_id2 = self::factory()->post->create(
			array(
				'post_name' => 'id-2',
				'post_type' => 'page',
			)
		);
		$post_id3 = self::factory()->post->create(
			array(
				'post_name'   => 'id-3',
				'post_type'   => 'page',
				'post_parent' => $post_id2,
			)
		);

		$ordered = array( 'id-2', 'id-3', 'id-1' );

		$q = new WP_Query(
			array(
				'post_type'     => 'any',
				'post_name__in' => $ordered,
				'orderby'       => 'post_name__in',
			)
		);

		$this->assertSame( $ordered, wp_list_pluck( $q->posts, 'post_name' ) );
	}

	public function test_post_status() {
		$statuses1 = get_post_stati();
		$this->assertContains( 'auto-draft', $statuses1 );

		$statuses2 = get_post_stati( array( 'exclude_from_search' => true ) );
		$this->assertContains( 'auto-draft', $statuses2 );

		$statuses3 = get_post_stati( array( 'exclude_from_search' => false ) );
		$this->assertNotContains( 'auto-draft', $statuses3 );

		$q1 = new WP_Query( array( 'post_status' => 'any' ) );
		$this->assertStringContainsString( "post_status <> 'auto-draft'", $q1->request );

		$q2 = new WP_Query( array( 'post_status' => 'any, auto-draft' ) );
		$this->assertStringNotContainsString( "post_status <> 'auto-draft'", $q2->request );

		$q3 = new WP_Query( array( 'post_status' => array( 'any', 'auto-draft' ) ) );
		$this->assertStringNotContainsString( "post_status <> 'auto-draft'", $q3->request );
	}

	/**
	 * @ticket 17065
	 */
	public function test_orderby_array() {
		global $wpdb;

		$q1 = new WP_Query(
			array(
				'orderby' => array(
					'type' => 'DESC',
					'name' => 'ASC',
				),
			)
		);
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_type DESC, $wpdb->posts.post_name ASC",
			$q1->request
		);

		$q2 = new WP_Query( array( 'orderby' => array() ) );
		$this->assertStringNotContainsString( 'ORDER BY', $q2->request );
		$this->assertStringNotContainsString( 'ORDER', $q2->request );

		$q3 = new WP_Query( array( 'post_type' => 'post' ) );
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_date DESC",
			$q3->request
		);

		$q4 = new WP_Query( array( 'post_type' => 'post' ) );
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_date DESC",
			$q4->request
		);
	}

	/**
	 * @ticket 17065
	 */
	public function test_order() {
		global $wpdb;

		$q1 = new WP_Query(
			array(
				'orderby' => array(
					'post_type' => 'foo',
				),
			)
		);
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_type DESC",
			$q1->request
		);

		$q2 = new WP_Query(
			array(
				'orderby' => 'title',
				'order'   => 'foo',
			)
		);
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_title DESC",
			$q2->request
		);

		$q3 = new WP_Query(
			array(
				'order' => 'asc',
			)
		);
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_date ASC",
			$q3->request
		);
	}

	/**
	 * @ticket 29629
	 */
	public function test_orderby() {
		// 'rand' is a valid value.
		$q = new WP_Query( array( 'orderby' => 'rand' ) );
		$this->assertStringContainsString( 'ORDER BY RAND()', $q->request );
		$this->assertStringNotContainsString( 'ASC', $q->request );
		$this->assertStringNotContainsString( 'DESC', $q->request );

		// This isn't allowed.
		$q2 = new WP_Query( array( 'order' => 'rand' ) );
		$this->assertStringContainsString( 'ORDER BY', $q2->request );
		$this->assertStringNotContainsString( 'RAND()', $q2->request );
		$this->assertStringContainsString( 'DESC', $q2->request );

		// 'none' is a valid value.
		$q3 = new WP_Query( array( 'orderby' => 'none' ) );
		$this->assertStringNotContainsString( 'ORDER BY', $q3->request );
		$this->assertStringNotContainsString( 'DESC', $q3->request );
		$this->assertStringNotContainsString( 'ASC', $q3->request );

		// False is a valid value.
		$q4 = new WP_Query( array( 'orderby' => false ) );
		$this->assertStringNotContainsString( 'ORDER BY', $q4->request );
		$this->assertStringNotContainsString( 'DESC', $q4->request );
		$this->assertStringNotContainsString( 'ASC', $q4->request );

		// Empty array() is a valid value.
		$q5 = new WP_Query( array( 'orderby' => array() ) );
		$this->assertStringNotContainsString( 'ORDER BY', $q5->request );
		$this->assertStringNotContainsString( 'DESC', $q5->request );
		$this->assertStringNotContainsString( 'ASC', $q5->request );
	}

	/**
	 * @ticket 35692
	 */
	public function test_orderby_rand_with_seed() {
		$q = new WP_Query(
			array(
				'orderby' => 'RAND(5)',
			)
		);

		$this->assertStringContainsString( 'ORDER BY RAND(5)', $q->request );
	}

	/**
	 * @ticket 35692
	 */
	public function test_orderby_rand_should_ignore_invalid_seed() {
		$q = new WP_Query(
			array(
				'orderby' => 'RAND(foo)',
			)
		);

		$this->assertStringNotContainsString( 'ORDER BY RAND', $q->request );
	}

	/**
	 * @ticket 35692
	 */
	public function test_orderby_rand_with_seed_should_be_case_insensitive() {
		$q = new WP_Query(
			array(
				'orderby' => 'rand(5)',
			)
		);

		$this->assertStringContainsString( 'ORDER BY RAND(5)', $q->request );
	}

	/**
	 * Tests the post_name__in attribute of WP_Query.
	 *
	 * @ticket 33065
	 */
	public function test_post_name__in() {
		$q = new WP_Query();

		$post_ids[0] = self::factory()->post->create(
			array(
				'post_title' => 'woo',
				'post_date'  => '2015-07-23 00:00:00',
			)
		);
		$post_ids[1] = self::factory()->post->create(
			array(
				'post_title' => 'hoo',
				'post_date'  => '2015-07-23 00:00:00',
			)
		);
		$post_ids[2] = self::factory()->post->create(
			array(
				'post_title' => 'test',
				'post_date'  => '2015-07-23 00:00:00',
			)
		);
		$post_ids[3] = self::factory()->post->create(
			array(
				'post_title' => 'me',
				'post_date'  => '2015-07-23 00:00:00',
			)
		);

		$requested = array( $post_ids[0], $post_ids[3] );
		$q->query(
			array(
				'post_name__in' => array( 'woo', 'me' ),
				'fields'        => 'ids',
			)
		);
		$actual_posts = $q->get_posts();
		$this->assertSameSets( $requested, $actual_posts );

		$requested = array( $post_ids[1], $post_ids[2] );
		$q->query(
			array(
				'post_name__in' => array( 'hoo', 'test' ),
				'fields'        => 'ids',
			)
		);
		$actual_posts = $q->get_posts();
		$this->assertSameSets( $requested, $actual_posts );
	}

	/**
	 * @ticket 36687
	 */
	public function test_posts_pre_query_filter_should_bypass_database_query() {
		add_filter( 'posts_pre_query', array( __CLASS__, 'filter_posts_pre_query' ) );

		$num_queries = get_num_queries();
		$q           = new WP_Query(
			array(
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		);

		remove_filter( 'posts_pre_query', array( __CLASS__, 'filter_posts_pre_query' ) );

		$this->assertSame( $num_queries, get_num_queries() );
		$this->assertSame( array( 12345 ), $q->posts );
	}

	public static function filter_posts_pre_query( $posts ) {
		return array( 12345 );
	}

	/**
	 * @ticket 36687
	 */
	public function test_posts_pre_query_filter_should_respect_set_found_posts() {
		global $wpdb;

		$this->post_id = self::factory()->post->create();

		// Prevent the DB query.
		add_filter( 'posts_request', '__return_empty_string' );
		add_filter( 'found_posts_query', '__return_empty_string' );

		// Add the post and found_posts.
		add_filter( 'the_posts', array( $this, 'filter_the_posts' ) );
		add_filter( 'found_posts', array( $this, 'filter_found_posts' ) );

		$q = new WP_Query( array( 'suppress_filters' => false ) );

		remove_filter( 'posts_request', '__return_empty_string' );
		remove_filter( 'found_posts_query', '__return_empty_string' );
		remove_filter( 'the_posts', array( $this, 'filter_the_posts' ) );
		remove_filter( 'found_posts', array( $this, 'filter_found_posts' ) );

		$this->assertSame( array( $this->post_id ), wp_list_pluck( $q->posts, 'ID' ) );
		$this->assertSame( 1, $q->found_posts );
	}

	public function filter_the_posts() {
		return array( get_post( $this->post_id ) );
	}

	public function filter_found_posts( $posts ) {
		return 1;
	}

	/**
	 * @ticket 36687
	 */
	public function test_set_found_posts_fields_ids() {
		register_post_type( 'wptests_pt' );

		$posts = self::factory()->post->create_many( 2, array( 'post_type' => 'wptests_pt' ) );

		foreach ( $posts as $p ) {
			clean_post_cache( $p );
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'wptests_pt',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$this->assertSame( 2, $q->found_posts );
		$this->assertEquals( 2, $q->max_num_pages );
	}

	/**
	 * @ticket 36687
	 */
	public function test_set_found_posts_fields_idparent() {
		register_post_type( 'wptests_pt' );

		$posts = self::factory()->post->create_many( 2, array( 'post_type' => 'wptests_pt' ) );
		foreach ( $posts as $p ) {
			clean_post_cache( $p );
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'wptests_pt',
				'posts_per_page' => 1,
				'fields'         => 'id=>parent',
			)
		);

		$this->assertSame( 2, $q->found_posts );
		$this->assertEquals( 2, $q->max_num_pages );
	}

	/**
	 * @ticket 36687
	 */
	public function test_set_found_posts_fields_split_the_query() {
		register_post_type( 'wptests_pt' );

		$posts = self::factory()->post->create_many( 2, array( 'post_type' => 'wptests_pt' ) );
		foreach ( $posts as $p ) {
			clean_post_cache( $p );
		}

		add_filter( 'split_the_query', '__return_true' );

		$q = new WP_Query(
			array(
				'post_type'      => 'wptests_pt',
				'posts_per_page' => 1,
			)
		);

		remove_filter( 'split_the_query', '__return_true' );

		$this->assertSame( 2, $q->found_posts );
		$this->assertEquals( 2, $q->max_num_pages );
	}

	/**
	 * @ticket 36687
	 */
	public function test_set_found_posts_fields_not_split_the_query() {
		register_post_type( 'wptests_pt' );

		$posts = self::factory()->post->create_many( 2, array( 'post_type' => 'wptests_pt' ) );
		foreach ( $posts as $p ) {
			clean_post_cache( $p );
		}

		// ! $split_the_query
		add_filter( 'split_the_query', '__return_false' );

		$q = new WP_Query(
			array(
				'post_type'      => 'wptests_pt',
				'posts_per_page' => 1,
			)
		);

		remove_filter( 'split_the_query', '__return_false' );

		$this->assertSame( 2, $q->found_posts );
		$this->assertEquals( 2, $q->max_num_pages );
	}

	/**
	 * @ticket 42860
	 *
	 * @dataProvider data_set_found_posts_not_posts_as_an_array
	 */
	public function test_set_found_posts_not_posts_as_an_array( $posts, $expected ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'wptests_pt',
				'posts_per_page' => 1,
			)
		);

		$q->posts = $posts;

		$methd = new ReflectionMethod( 'WP_Query', 'set_found_posts' );
		$methd->setAccessible( true );
		$methd->invoke( $q, array( 'no_found_rows' => false ), array() );

		$this->assertSame( $expected, $q->found_posts );
	}

	public function data_set_found_posts_not_posts_as_an_array() {
		// Count return 0 for null, but 1 for other data you may not expect.
		return array(
			array( null, 0 ),
			array( '', 1 ),
			array( "To life, to life, l'chaim", 1 ),
			array( false, 1 ),
		);
	}

	/**
	 * @ticket 42469
	 */
	public function test_found_posts_should_be_integer_not_string() {
		self::factory()->post->create();

		$q = new WP_Query(
			array(
				'posts_per_page' => 1,
			)
		);

		$this->assertIsInt( $q->found_posts );
	}

	/**
	 * @ticket 42469
	 */
	public function test_found_posts_should_be_integer_even_if_found_posts_filter_returns_string_value() {
		self::factory()->post->create();

		add_filter( 'found_posts', '__return_empty_string' );

		$q = new WP_Query(
			array(
				'posts_per_page' => 1,
			)
		);

		remove_filter( 'found_posts', '__return_empty_string' );

		$this->assertIsInt( $q->found_posts );
	}

	/**
	 * @ticket 47280
	 */
	public function test_found_posts_are_correct_for_empty_query() {
		self::factory()->post->create_many( 12 );

		$q = new WP_Query();

		$this->assertSame( 0, $q->post_count, 'Post count is expected to be zero' );
		$this->assertSame( 0, $q->found_posts, 'Total found posts is expected to be zero' );
		$this->assertSame( 0, $q->max_num_pages, 'Number of pages is expected to be zero' );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_query_with_no_results( $fields ) {
		$q = new WP_Query(
			array(
				'fields'       => $fields,
				'posts_status' => 'draft',
			)
		);

		$this->assertSame( 0, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 0, $q->found_posts, self::get_count_message( $q ) );
		$this->assertSame( 0, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_basic_query( $fields ) {
		self::factory()->post->create_many( 5 );

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_query_with_no_limit( $fields ) {
		self::factory()->post->create_many( 5 );

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => -1,
			)
		);

		$this->assertSame( 5, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		// You would expect this to be 1 but historically it's 0 for posts without paging.
		$this->assertSame( 0, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_query_with_no_paging( $fields ) {
		self::factory()->post->create_many( 5 );

		$q = new WP_Query(
			array(
				'fields'   => $fields,
				'nopaging' => true,
			)
		);

		$this->assertSame( 5, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		// You would expect this to be 1 but historically it's 0 for posts without paging.
		$this->assertSame( 0, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_query_with_no_found_rows( $fields ) {
		self::factory()->post->create_many( 5 );

		$q = new WP_Query(
			array(
				'fields'        => $fields,
				'no_found_rows' => true,
			)
		);

		$this->assertSame( 5, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 0, $q->found_posts, self::get_count_message( $q ) );
		$this->assertSame( 0, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_paged_query( $fields ) {
		self::factory()->post->create_many( 5 );

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
				'paged'          => 3,
			)
		);

		$this->assertSame( 1, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_author_queries( $fields ) {
		$author = self::factory()->user->create();
		self::factory()->post->create_many( 5 );
		self::factory()->post->create_many(
			5,
			array(
				'post_author' => $author,
			)
		);

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
				'author'         => $author,
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * A query for the following triggers an additional LEFT JOIN on `wp_posts`:
	 *
	 *  - Custom taxonomy query
	 *  - `post_status` specified
	 *  - `post_type` not specified
	 *
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_query_that_performs_post_status_join( $fields ) {
		$taxonomy = 'post_status_join_tax';
		register_taxonomy( $taxonomy, 'post' );
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => $taxonomy,
			)
		);
		self::factory()->post->create_many( 5 );
		$ids = self::factory()->post->create_many( 5 );

		foreach ( $ids as $id ) {
			wp_set_post_terms( $id, $term->slug, $taxonomy );
		}

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
				'taxonomy'       => $taxonomy,
				'term'           => $term->slug,
				'post_status'    => 'publish',
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_tax_queries( $fields ) {
		$term = self::factory()->term->create_and_get();
		self::factory()->post->create_many( 5 );
		$ids = self::factory()->post->create_many( 5 );

		foreach ( $ids as $id ) {
			wp_set_post_terms( $id, $term->slug );
		}

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
				'tag'            => $term->slug,
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_meta_queries( $fields ) {
		self::factory()->post->create_many( 5 );
		$ids = self::factory()->post->create_many( 5 );

		foreach ( $ids as $id ) {
			add_post_meta( $id, 'my_meta', 'foo' );
		}

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
				'meta_key'       => 'my_meta',
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_multiple_meta_queries( $fields ) {
		self::factory()->post->create_many( 5 );
		$ids = self::factory()->post->create_many( 5 );

		foreach ( $ids as $id ) {
			add_post_meta( $id, 'field_1', 'foo' );
			add_post_meta( $id, 'field_2', 'bar' );
		}

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
				'meta_query'     => array(
					array(
						'key' => 'field_1',
					),
					array(
						'key' => 'field_2',
					),
				),
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_OR_meta_queries( $fields ) {
		self::factory()->post->create_many( 5 );
		$ids = self::factory()->post->create_many( 5 );

		// Add a mixture of meta values so all 5 posts match the meta query.
		add_post_meta( $ids[0], 'field_1', 'foo' );
		add_post_meta( $ids[0], 'field_2', 'bar' );
		add_post_meta( $ids[1], 'field_1', 'foo' );
		add_post_meta( $ids[2], 'field_1', 'foo' );
		add_post_meta( $ids[3], 'field_2', 'bar' );
		add_post_meta( $ids[4], 'field_2', 'bar' );

		/*
		 * This query results in a `GROUP BY wp_posts.ID` clause, which means the
		 * count query must count `DISTINCT wp_posts.ID` to eliminate duplicates.
		 */
		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key' => 'field_1',
					),
					array(
						'key' => 'field_2',
					),
				),
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_for_group_by_queries( $fields ) {
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();

		self::factory()->post->create_many(
			5,
			array(
				'post_author' => $user1,
			)
		);
		self::factory()->post->create_many(
			5,
			array(
				'post_author' => $user2,
			)
		);

		/**
		 * Adds a GROUP BY clause to the query.
		 */
		add_filter(
			'posts_groupby_request',
			function() {
				return "{$GLOBALS['wpdb']->posts}.post_author";
			}
		);

		/*
		 * This query results in a `GROUP BY wp_posts.post_author` clause, which means the
		 * count query must count `DISTINCT wp_posts.post_author` to eliminate duplicates.
		 */
		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
			)
		);

		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		// There is a total of two distinct authors in the results.
		$this->assertSame( 2, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 1, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * @ticket 47280
	 * @group ms-required
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` argument for `WP_Query`.
	 */
	public function test_found_posts_are_correct_when_switching_between_sites( $fields ) {
		$blog = self::factory()->blog->create();
		self::factory()->post->create_many( 5 );

		$q = new WP_Query(
			array(
				'fields'         => $fields,
				'posts_per_page' => 2,
			)
		);

		// Switch to another site.
		switch_to_blog( $blog );

		/*
		 * Count the posts from the original site. This works because the SQL query
		 * and its table names has already been formed during the original query.
		 */
		$post_count    = $q->post_count;
		$found_posts   = $q->found_posts;
		$max_num_pages = $q->max_num_pages;

		// Switch back.
		restore_current_blog();

		$this->assertSame( 2, $post_count, self::get_count_message( $q ) );
		$this->assertSame( 5, $found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 3, $max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * Ensures the count is correct when the query includes an `SQL_CALC_FOUND_ROWS` modifier.
	 *
	 * @ticket 47280
	 *
	 * @expectedDeprecated The posts_request filter
	 */
	public function test_posts_are_counted_with_select_found_rows_when_query_includes_sql_calc_found_rows_modifier() {
		// Create five published posts.
		self::factory()->post->create_many( 5 );
		// Create ten draft posts.
		self::factory()->post->create_many(
			10,
			array(
				'post_status' => 'draft',
			)
		);

		add_filter(
			'posts_request',
			static function( $request ) {
				global $wpdb;

				return "
					SELECT SQL_CALC_FOUND_ROWS {$wpdb->posts}.ID
					FROM {$wpdb->posts}
					WHERE 1=1
					AND {$wpdb->posts}.post_type = 'post'
					AND {$wpdb->posts}.post_status = 'draft'
					ORDER BY {$wpdb->posts}.post_date
					DESC LIMIT 0, 2
				";
			}
		);

		$q = new WP_Query(
			array(
				'posts_per_page' => 2,
			)
		);

		// These results should now reflect the results for draft posts, as set by the filter.
		$this->assertStringContainsString( 'SELECT FOUND_ROWS()', $q->count_request, self::get_count_message( $q ) );
		$this->assertSame( 2, $q->post_count, self::get_count_message( $q ) );
		$this->assertSame( 10, $q->found_posts, self::get_count_message( $q ) );
		$this->assertEquals( 5, $q->max_num_pages, self::get_count_message( $q ) );
	}

	/**
	 * Data provider for tests which need to run once for each possible value of the fields argument.
	 *
	 * @return array[] Test data.
	 */
	public function data_fields() {
		return array(
			'posts'   => array(
				'',
			),
			'ids'     => array(
				'ids',
			),
			'parents' => array(
				'id=>parent',
			),
		);
	}

	/**
	 * Helper method which returns a readable representation of the SQL queries performed.
	 *
	 * @param WP_Query $query The current query instance.
	 * @return string The formatted message.
	 */
	protected static function get_count_message( WP_Query $query ) {
		global $wpdb;

		return sprintf(
			"Request SQL:\n%s\nCount SQL:\n\n%s\n",
			self::format_sql( $wpdb->remove_placeholder_escape( $query->request ) ),
			self::format_sql( $wpdb->remove_placeholder_escape( $query->count_request ) )
		);
	}

	/**
	 * Applies some basic formatting to an SQL query to make it more readable during a test failure.
	 *
	 * @param string $sql The SQL query to be formatted.
	 * @return string     The formatted SQL query.
	 */
	protected static function format_sql( $sql ) {
		$sql = preg_replace(
			'# (FROM|INNER JOIN|LEFT JOIN|ON|WHERE|AND|GROUP BY|ORDER BY|LIMIT) #',
			"\n\$1 ",
			$sql
		);
		$sql = preg_replace( '#^\t+#m', '', $sql );

		return $sql;
	}

}
