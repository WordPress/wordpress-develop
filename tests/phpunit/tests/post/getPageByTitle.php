<?php

/**
 * @group post
 * @covers ::get_page_by_title
 */
class Tests_Post_GetPageByTitle extends WP_UnitTestCase {

	/**
	 * Generate shared fixtures.
	 *
	 * These are not used in the tests but are rather used to populate the
	 * posts table and ensure that the tests return the correct post object
	 * by design rather than through chance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Fill the database with some pages.
		$factory->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		// Fill the database with some attachments.
		$factory->post->create_many(
			2,
			array(
				'post_type' => 'attachment',
			)
		);

		// Fill the database with some test post types.
		register_post_type( 'wptests_pt' );
		$factory->post->create_many(
			2,
			array(
				'post_type' => 'wptests_pt',
			)
		);
	}

	/**
	 * @ticket 36905
	 */
	public function test_get_page_by_title_priority() {
		$attachment = self::factory()->post->create_and_get(
			array(
				'post_title' => 'some-other-page',
				'post_type'  => 'attachment',
			)
		);
		$page       = self::factory()->post->create_and_get(
			array(
				'post_title' => 'some-page',
				'post_type'  => 'page',
			)
		);

		$this->assertEquals( $page, get_page_by_title( 'some-page' ), 'should return a post of the requested type before returning an attachment.' );

		$this->assertEquals( $attachment, get_page_by_title( 'some-other-page', OBJECT, 'attachment' ), "will still select an attachment when a post of the requested type doesn't exist." );
	}

	/**
	 * @ticket 36905
	 */
	public function test_should_match_top_level_page() {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'foo',
			)
		);

		$found = get_page_by_title( 'foo' );

		$this->assertSame( $page, $found->ID );
	}

	/**
	 * @ticket 36905
	 * @ticket 56609
	 */
	public function test_should_be_case_insensitive_match() {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Foo',
			)
		);

		$found = get_page_by_title( 'foo' );

		$this->assertSame( $page, $found->ID );
	}

	/**
	 * Test the oldest published post is matched first.
	 *
	 * Per the docs: in case of more than one post having the same title,
	 * it will check the oldest publication date, not the smallest ID.
	 *
	 * @ticket 36905
	 * @ticket 56609
	 */
	public function test_should_match_oldest_published_date_when_titles_match() {
		self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'foo',
			)
		);

		$old_page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'foo',
				'post_date'  => '1984-01-11 05:00:00',
			)
		);

		$found = get_page_by_title( 'foo' );

		$this->assertSame( $old_page, $found->ID );
	}

	/**
	 * @ticket 36905
	 */
	public function test_inherit() {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'foo',
				'post_status' => 'inherit',
			)
		);

		$found = get_page_by_title( 'foo' );

		$this->assertSame( $page, $found->ID );
	}

	/**
	 * @ticket 36905
	 */
	public function test_should_obey_post_type() {
		register_post_type( 'wptests_pt' );

		$page = self::factory()->post->create(
			array(
				'post_type'  => 'wptests_pt',
				'post_title' => 'foo',
			)
		);

		$found = get_page_by_title( 'foo' );
		$this->assertNull( $found, 'Should return null, as post type does not match' );

		$found = get_page_by_title( 'foo', OBJECT, 'wptests_pt' );
		$this->assertSame( $page, $found->ID, 'Should return find post, as post type does do match' );
	}


	/**
	 * @ticket 36905
	 */
	public function test_should_hit_cache() {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'foo',
			)
		);

		// Prime cache.
		$found = get_page_by_title( 'foo' );
		$this->assertSame( $page, $found->ID, 'Should return find page.' );

		$num_queries = get_num_queries();

		$found = get_page_by_title( 'foo' );
		$this->assertSame( $page, $found->ID, 'Should return find page on second run.' );
		$this->assertSame( $num_queries, get_num_queries(), 'Should not result in another database query.' );
	}

	/**
	 * @ticket 36905
	 */
	public function test_bad_title_should_be_cached() {
		// Prime cache.
		$found = get_page_by_title( 'foo' );
		$this->assertNull( $found, 'Should return not find a page.' );

		$num_queries = get_num_queries();

		$found = get_page_by_title( 'foo' );
		$this->assertNull( $found, 'Should return not find a page on second run.' );
		$this->assertSame( $num_queries, get_num_queries(), 'Should not result in another database query.' );
	}

	/**
	 * @ticket 36905
	 */
	public function test_bad_title_served_from_cache_should_not_fall_back_on_current_post() {
		global $post;

		// Fake the global.
		$post = self::factory()->post->create_and_get();

		// Prime cache.
		$found = get_page_by_title( 'foo' );
		$this->assertNull( $found, 'Should return not find a page.' );

		$num_queries = get_num_queries();

		$found = get_page_by_title( 'foo' );
		$this->assertNull( $found, 'Should return not find a page on second run.' );
		$this->assertSame( $num_queries, get_num_queries(), 'Should not result in another database query.' );
	}

	/**
	 * @ticket 36905
	 */
	public function test_cache_should_not_match_post_in_different_post_type_with_same_title() {
		register_post_type( 'wptests_pt' );

		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'foo',
			)
		);

		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'wptests_pt',
				'post_title' => 'foo',
			)
		);

		// Prime cache for the page.
		$found = get_page_by_title( 'foo' );
		$this->assertSame( $p1, $found->ID, 'Should find a page.' );

		$num_queries = get_num_queries();

		$found = get_page_by_title( 'foo', OBJECT, 'wptests_pt' );
		$this->assertSame( $p2, $found->ID, 'Should find a post with post type wptests_pt.' );
		++$num_queries;
		$this->assertSame( $num_queries, get_num_queries(), 'Should result in another database query.' );
	}

	/**
	 * @ticket 36905
	 */
	public function test_cache_should_be_invalidated_when_post_title_is_edited() {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'foo',
			)
		);

		// Prime cache.
		$found = get_page_by_title( 'foo' );
		$this->assertSame( $page, $found->ID, 'Should find a page.' );

		wp_update_post(
			array(
				'ID'         => $page,
				'post_title' => 'bar',
			)
		);

		$num_queries = get_num_queries();

		$found = get_page_by_title( 'bar' );
		$this->assertSame( $page, $found->ID, 'Should find a page with the new title.' );
		++$num_queries;
		$this->assertSame( $num_queries, get_num_queries(), 'Should result in another database query.' );
	}

	/**
	 * @ticket 36905
	 */
	public function test_output_param_should_be_obeyed_for_cached_value() {
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'foo',
			)
		);

		// Prime cache.
		$found = get_page_by_title( 'foo' );

		$num_queries = get_num_queries();
		$this->assertSame( $page, $found->ID, 'Should find a page.' );

		$object = get_page_by_title( 'foo', OBJECT );
		$this->assertIsObject( $object, 'Should be an object.' );
		$this->assertSame( $page, $object->ID, 'Should match post id.' );
		$this->assertSame( $num_queries, get_num_queries(), 'Should not result in another database query.' );

		$array_n = get_page_by_title( 'foo', ARRAY_N );
		++$num_queries; // Add one database query for loading of post metadata.
		$this->assertIsArray( $array_n, 'Should be numbric array.' );
		$this->assertSame( $page, $array_n[0], 'Should match post id.' );
		$this->assertSame( $num_queries, get_num_queries(), 'Should not result in another database query.' );

		$array_a = get_page_by_title( 'foo', ARRAY_A );
		$this->assertIsArray( $array_a, 'Should be associative array.' );
		$this->assertSame( $page, $array_a['ID'], 'Should match post id.' );
		$this->assertSame( $num_queries, get_num_queries(), 'Should not result in another database query.' );
	}

	/**
	 * Ensure get_page_by_title() only runs the query once.
	 *
	 * @ticket 56721
	 * @covers ::get_page_by_title
	 */
	public function test_should_not_run_query_more_than_once() {
		$page = self::factory()->post->create_and_get(
			array(
				'post_title' => 'some-page',
				'post_type'  => 'page',
			)
		);

		// Use the `pre_get_posts` hook to ensure the query is only run once.
		$ma = new MockAction();
		add_action( 'pre_get_posts', array( $ma, 'action' ) );

		get_page_by_title( 'some-page' );
		$this->assertSame( 1, $ma->get_call_count(), 'Query does not run exactly once.' );
	}

	/**
	 * @ticket 56991
	 * @dataProvider data_should_return_same_result_as_legacy_query_single_post_type
	 */
	public function test_should_return_same_result_as_legacy_query_single_post_type( $post_to_trash ) {
		global $wpdb;
		$page_title = "Ticket number 56991";

		$pages = self::factory()->post->create_many(
			5,
			array(
				'post_title' => $page_title,
				'post_type'  => 'page',
			)
		);

		// Trash one of the pages.
		wp_delete_post( $pages[ $post_to_trash ] );

		$legacy_query_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s",
				$page_title,
				'page'
			)
		);

		$get_page_by_title_post_id = get_page_by_title( $page_title, OBJECT, 'page' )->ID;

		$this->assertSame( (int) $legacy_query_post_id, (int) $get_page_by_title_post_id, 'Legacy query and get_page_by_title() should return the same post ID.' );
	}

	public function data_should_return_same_result_as_legacy_query_single_post_type() {
		$range = range( 0, 4 );
		return array_map(
			function( $post_to_trash ) {
				return array( $post_to_trash );
			},
			$range
		);
	}

	/**
	 * @ticket 56991
	 * @dataProvider data_should_return_same_result_as_legacy_query_multiple_post_types
	 */
	public function test_should_return_same_result_as_legacy_query_multiple_post_types( $post_to_trash ) {
		global $wpdb;
		register_post_type( 'wptest' );

		$page_title = "Ticket number 56991";

		$pages = self::factory()->post->create_many(
			5,
			array(
				'post_title' => $page_title,
				'post_type'  => 'page',
			)
		);

		$cpts = self::factory()->post->create_many(
			5,
			array(
				'post_title' => $page_title,
				'post_type'  => 'wptest',
			)
		);

		$all_posts = array_merge( $pages, $cpts );

		// Trash one of the post objects.
		wp_delete_post( $all_posts[ $post_to_trash ] );

		$legacy_query_post_id_1 = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type IN ( %s, %s )",
				$page_title,
				'page',
				'wptest'
			)
		);

		$legacy_query_post_id_2 = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type IN ( %s, %s )",
				$page_title,
				'wptest',
				'page'
			)
		);

		$get_page_by_title_post_id_1 = get_page_by_title( $page_title, OBJECT, array( 'page', 'wptest' ) )->ID;
		$get_page_by_title_post_id_2 = get_page_by_title( $page_title, OBJECT, array( 'wptest', 'page' ) )->ID;

		$this->assertSame( (int) $legacy_query_post_id_1, (int) $get_page_by_title_post_id_1, 'Legacy query 1 and get_page_by_title() 1 should return the same post ID.' );
		$this->assertSame( (int) $legacy_query_post_id_1, (int) $get_page_by_title_post_id_2, 'Legacy query 1 and get_page_by_title() 2 should return the same post ID.' );
		$this->assertSame( (int) $legacy_query_post_id_2, (int) $get_page_by_title_post_id_1, 'Legacy query 2 and get_page_by_title() 1 should return the same post ID.' );
		$this->assertSame( (int) $legacy_query_post_id_2, (int) $get_page_by_title_post_id_2, 'Legacy query 2 and get_page_by_title() 2 should return the same post ID.' );
	}

	public function data_should_return_same_result_as_legacy_query_multiple_post_types() {
		$range = range( 0, 9 );
		return array_map(
			function( $post_to_trash ) {
				return array( $post_to_trash );
			},
			$range
		);
	}
}
