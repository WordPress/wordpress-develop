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
}
