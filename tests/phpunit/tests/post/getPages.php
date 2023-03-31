<?php

/**
 * @group post
 *
 * @covers ::get_pages
 */
class Tests_Post_GetPages extends WP_UnitTestCase {
	/**
	 * @ticket 23167
	 */
	public function test_get_pages_cache() {
		global $wpdb;

		self::factory()->post->create_many( 3, array( 'post_type' => 'page' ) );
		wp_cache_delete( 'last_changed', 'posts' );
		$this->assertFalse( wp_cache_get( 'last_changed', 'posts' ) );

		$pages = get_pages();
		$this->assertCount( 3, $pages );
		$time1 = wp_cache_get( 'last_changed', 'posts' );
		$this->assertNotEmpty( $time1 );
		$num_queries = $wpdb->num_queries;
		foreach ( $pages as $page ) {
			$this->assertInstanceOf( 'WP_Post', $page );
		}

		// Again. num_queries and last_changed should remain the same.
		$pages = get_pages();
		$this->assertCount( 3, $pages );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );
		foreach ( $pages as $page ) {
			$this->assertInstanceOf( 'WP_Post', $page );
		}

		// Again with different args. last_changed should not increment because of
		// different args to get_pages(). num_queries should bump by 1.
		$pages = get_pages( array( 'number' => 2 ) );
		$this->assertCount( 2, $pages );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );
		foreach ( $pages as $page ) {
			$this->assertInstanceOf( 'WP_Post', $page );
		}

		$num_queries = $wpdb->num_queries;

		// Again. num_queries and last_changed should remain the same.
		$pages = get_pages( array( 'number' => 2 ) );
		$this->assertCount( 2, $pages );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );
		foreach ( $pages as $page ) {
			$this->assertInstanceOf( 'WP_Post', $page );
		}

		// Do the first query again. The interim queries should not affect it.
		$pages = get_pages();
		$this->assertCount( 3, $pages );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );
		foreach ( $pages as $page ) {
			$this->assertInstanceOf( 'WP_Post', $page );
		}

		// Force last_changed to increment.
		clean_post_cache( $pages[0]->ID );
		$this->assertNotEquals( $time1, $time2 = wp_cache_get( 'last_changed', 'posts' ) );
		get_post( $pages[0]->ID );
		$num_queries = $wpdb->num_queries;

		// last_changed bumped so num_queries should increment.
		$pages = get_pages( array( 'number' => 2 ) );
		$this->assertCount( 2, $pages );
		$this->assertSame( $time2, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );
		foreach ( $pages as $page ) {
			$this->assertInstanceOf( 'WP_Post', $page );
		}

		$last_changed = wp_cache_get( 'last_changed', 'posts' );

		// This should bump last_changed.
		wp_delete_post( $pages[0]->ID );
		$old_changed_float = $this->_microtime_to_float( $last_changed );
		$new_changed_float = $this->_microtime_to_float( wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertGreaterThan( $old_changed_float, $new_changed_float );

		$num_queries  = $wpdb->num_queries;
		$last_changed = wp_cache_get( 'last_changed', 'posts' );

		// num_queries should bump after wp_delete_post() bumps last_changed.
		$pages = get_pages();
		$this->assertCount( 2, $pages );
		$this->assertSame( $last_changed, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );
		foreach ( $pages as $page ) {
			$this->assertInstanceOf( 'WP_Post', $page );
		}
	}

	/**
	 * @ticket 43514
	 */
	public function test_get_pages_cache_empty() {
		global $wpdb;

		wp_cache_delete( 'last_changed', 'posts' );
		$this->assertFalse( wp_cache_get( 'last_changed', 'posts' ) );

		$num_queries = $wpdb->num_queries;

		$pages = get_pages(); // Database gets queried.

		$this->assertSame( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		$pages = get_pages(); // Database should not get queried.

		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 40669
	 */
	public function test_get_pages_cache_should_be_invalidated_by_add_post_meta() {
		$posts = self::factory()->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		add_post_meta( $posts[0], 'foo', 'bar' );

		$cached = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$cached_ids = wp_list_pluck( $cached, 'ID' );
		$this->assertSameSets( array( $posts[0] ), $cached_ids );

		add_post_meta( $posts[1], 'foo', 'bar' );

		$found = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$found_ids = wp_list_pluck( $found, 'ID' );
		$this->assertSameSets( $posts, $found_ids );
	}

	/**
	 * @ticket 40669
	 */
	public function test_get_pages_cache_should_be_invalidated_by_update_post_meta() {
		$posts = self::factory()->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		add_post_meta( $posts[0], 'foo', 'bar' );
		add_post_meta( $posts[1], 'foo', 'bar' );

		$cached = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$cached_ids = wp_list_pluck( $cached, 'ID' );
		$this->assertSameSets( $posts, $cached_ids );

		update_post_meta( $posts[1], 'foo', 'baz' );

		$found = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$found_ids = wp_list_pluck( $found, 'ID' );
		$this->assertSameSets( array( $posts[0] ), $found_ids );
	}

	/**
	 * @ticket 40669
	 */
	public function test_get_pages_cache_should_be_invalidated_by_delete_post_meta() {
		$posts = self::factory()->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		add_post_meta( $posts[0], 'foo', 'bar' );
		add_post_meta( $posts[1], 'foo', 'bar' );

		$cached = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$cached_ids = wp_list_pluck( $cached, 'ID' );
		$this->assertSameSets( $posts, $cached_ids );

		delete_post_meta( $posts[1], 'foo' );

		$found = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$found_ids = wp_list_pluck( $found, 'ID' );
		$this->assertSameSets( array( $posts[0] ), $found_ids );
	}

	/**
	 * @ticket 40669
	 */
	public function test_get_pages_cache_should_be_invalidated_by_delete_post_meta_by_key() {
		$posts = self::factory()->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		add_post_meta( $posts[0], 'foo', 'bar' );
		add_post_meta( $posts[1], 'foo', 'bar' );

		$cached = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$cached_ids = wp_list_pluck( $cached, 'ID' );
		$this->assertSameSets( $posts, $cached_ids );

		delete_post_meta_by_key( 'foo' );

		$found = get_pages(
			array(
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$found_ids = wp_list_pluck( $found, 'ID' );
		$this->assertSameSets( array(), $found_ids );
	}

	/**
	 * @ticket 20376
	 */
	public function test_get_pages_meta() {
		$posts = self::factory()->post->create_many( 3, array( 'post_type' => 'page' ) );
		add_post_meta( $posts[0], 'some-meta-key', '0' );
		add_post_meta( $posts[1], 'some-meta-key', '' );
		add_post_meta( $posts[2], 'some-meta-key', '1' );

		$this->assertSame(
			1,
			count(
				get_pages(
					array(
						'meta_key'   => 'some-meta-key',
						'meta_value' => '0',
					)
				)
			)
		);
		$this->assertSame(
			1,
			count(
				get_pages(
					array(
						'meta_key'   => 'some-meta-key',
						'meta_value' => '1',
					)
				)
			)
		);
		$this->assertCount( 3, get_pages( array( 'meta_key' => 'some-meta-key' ) ) );
	}

	/**
	 * @ticket 22074
	 */
	public function test_get_pages_include_exclude() {
		$page_ids = array();

		foreach ( range( 1, 20 ) as $i ) {
			$page_ids[] = self::factory()->post->create( array( 'post_type' => 'page' ) );
		}

		$inc = array_slice( $page_ids, 0, 10 );
		sort( $inc );
		$exc = array_slice( $page_ids, 10 );
		sort( $exc );

		$include    = get_pages( array( 'include' => $inc ) );
		$inc_result = wp_list_pluck( $include, 'ID' );
		sort( $inc_result );
		$this->assertSame( $inc, $inc_result );

		$exclude    = get_pages( array( 'exclude' => $exc ) );
		$exc_result = wp_list_pluck( $exclude, 'ID' );
		sort( $exc_result );
		$this->assertSame( $inc, $exc_result );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_include_ignores_meta_key() {
		$posts = self::factory()->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		$pages = get_pages(
			array(
				'include'    => $posts,
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$page_ids = wp_list_pluck( $pages, 'ID' );
		$this->assertSameSets( $posts, $page_ids );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_include_ignores_exclude() {
		$includes = self::factory()->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		$excludes = self::factory()->post->create_many(
			2,
			array(
				'post_type' => 'page',
			)
		);

		$pages = get_pages(
			array(
				'include' => $includes,
				'exclude' => $excludes,
			)
		);

		$page_ids = wp_list_pluck( $pages, 'ID' );
		$this->assertSameSets( $includes, $page_ids );
	}

	public function test_get_pages_exclude_tree() {
		$post_id1 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$post_id2 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $post_id1,
			)
		);
		$post_id3 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$post_id4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $post_id3,
			)
		);

		$all = get_pages();

		$this->assertCount( 4, $all );

		$exclude1 = get_pages( "exclude_tree=$post_id1" );
		$this->assertCount( 2, $exclude1 );

		$exclude2 = get_pages( array( 'exclude_tree' => $post_id1 ) );
		$this->assertCount( 2, $exclude2 );

		$exclude3 = get_pages( array( 'exclude_tree' => array( $post_id1 ) ) );
		$this->assertCount( 2, $exclude3 );

		$exclude4 = get_pages( array( 'exclude_tree' => array( $post_id1, $post_id2 ) ) );
		$this->assertCount( 2, $exclude4 );

		$exclude5 = get_pages( array( 'exclude_tree' => array( $post_id1, $post_id3 ) ) );
		$this->assertCount( 0, $exclude5 );

		$post_id5 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$post_id6 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $post_id5,
			)
		);

		$exclude6 = get_pages( array( 'exclude_tree' => array( $post_id1, $post_id3 ) ) );
		$this->assertCount( 2, $exclude6 );
	}

	/**
	 * @ticket 9470
	 */
	public function test_get_pages_parent() {
		$page_id1 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_id2 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_id1,
			)
		);
		$page_id3 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_id2,
			)
		);
		$page_id4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_id1,
			)
		);

		$pages = get_pages(
			array(
				'parent'       => 0,
				'hierarchical' => false,
			)
		);
		$this->assertSameSets( array( $page_id1 ), wp_list_pluck( $pages, 'ID' ) );

		$pages = get_pages(
			array(
				'parent'       => $page_id1,
				'hierarchical' => false,
			)
		);
		$this->assertSameSets( array( $page_id2, $page_id4 ), wp_list_pluck( $pages, 'ID' ) );

		$pages = get_pages(
			array(
				'parent'       => array( $page_id1, $page_id2 ),
				'hierarchical' => false,
			)
		);
		$this->assertSameSets( array( $page_id2, $page_id3, $page_id4 ), wp_list_pluck( $pages, 'ID' ) );

		$pages = get_pages( array( 'parent' => 0 ) );
		$this->assertSameSets( array( $page_id1 ), wp_list_pluck( $pages, 'ID' ) );

		$pages = get_pages( array( 'parent' => $page_id1 ) );
		$this->assertSameSets( array( $page_id2, $page_id4 ), wp_list_pluck( $pages, 'ID' ) );

		$pages = get_pages( array( 'parent' => array( $page_id1, $page_id2 ) ) );
		$this->assertSameSets( array( $page_id2, $page_id3, $page_id4 ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 22208
	 */
	public function test_get_children_fields_ids() {
		$post_id   = self::factory()->post->create();
		$child_ids = self::factory()->post->create_many( 5, array( 'post_parent' => $post_id ) );

		$post_ids = get_children(
			array(
				'fields'      => 'ids',
				'post_parent' => $post_id,
			)
		);
		$this->assertSameSets( $child_ids, $post_ids );
	}

	/**
	 * @ticket 25750
	 */
	public function test_get_pages_hierarchical_and_no_parent() {
		global $wpdb;
		$page_1 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_2 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);
		$page_3 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);
		$page_4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_2,
			)
		);

		$pages              = get_pages(); // Defaults: hierarchical = true, parent = -1.
		$pages_default_args = get_pages(
			array(
				'hierarchical' => true,
				'parent'       => -1,
			)
		);
		// Confirm the defaults.
		$this->assertEqualSets( $pages, $pages_default_args );

		/*
		 * Here's the tree we are testing:
		 *
		 * page 1
		 * - page 2
		 * -- page 4
		 * - page 3
		 *
		 * If hierarchical => true works, the order will be 1,2,4,3.
		 * If it doesn't, they will be in the creation order, 1,2,3,4.
		 */

		$this->assertSameSets( array( $page_1, $page_2, $page_4, $page_3 ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 18701
	 */
	public function test_get_pages_hierarchical_empty_child_of() {
		$page_1 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_2 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_3 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);
		$page_4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);

		$pages        = get_pages(); // Defaults: hierarchical = true, child_of = '', parent = -1.
		$default_args = get_pages(
			array(
				'hierarchical' => true,
				'child_of'     => '',
			)
		);

		$this->assertEqualSets( $pages, $default_args );

		/*
		 * Page tree:
		 *
		 * page 1 (parent 0)
		 * – page 3 (parent 1)
		 * – page 4 (parent 1)
		 * page 2 (parent 0)
		 *
		 * With default arguments, if child_of is empty (normalized to 0), only pages with a matching
		 * post_parent will be returned, in the order they were created: 1, 2.
		 */

		$found_pages = wp_list_filter( $pages, array( 'post_parent' => 0 ) );

		$this->assertSameSets( array( $page_1, $page_2 ), wp_list_pluck( $found_pages, 'ID' ) );
	}

	/**
	 * @ticket 18701
	 */
	public function test_get_pages_non_hierarchical_empty_child_of() {
		$page_1 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_2 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_3 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);
		$page_4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);

		$pages = get_pages( array( 'hierarchical' => false ) ); // child_of = '', parent = -1.

		/*
		 * Page tree:
		 *
		 * page 1 (parent 0)
		 * – page 3 (parent 1)
		 * – page 4 (parent 1)
		 * page 2 (parent 0)
		 *
		 * If hierarchical is false and child_of is empty (normalized to 0), pages will be returned
		 * in order of creation: 1, 2, 3, 4, regardless of parent.
		 */

		$this->assertSameSets( array( $page_1, $page_2, $page_3, $page_4 ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 18701
	 */
	public function test_get_pages_hierarchical_non_empty_child_of() {
		$page_1 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_2 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_3 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);
		$page_4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_3,
			)
		);
		$page_5 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);

		$pages = get_pages( array( 'child_of' => $page_1 ) ); // Defaults: hierarchical = true, parent = -1.

		/*
		 * Page tree:
		 *
		 * page 1 (parent 0)
		 * – page 3 (parent 1)
		 * –– page 4 (parent 3)
		 * – page 5 (parent 1)
		 * page 2 (parent 0)
		 *
		 * If hierarchical is true (default), and child_of is not empty, pages will be returned
		 * hierarchically in order of creation: 3, 4, 5.
		 */

		$this->assertSameSets( array( $page_3, $page_4, $page_5 ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 18701
	 */
	public function test_get_pages_non_hierarchical_non_empty_child_of() {
		$page_1 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_2 = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_3 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);
		$page_4 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_3,
			)
		);
		$page_5 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_1,
			)
		);

		$pages = get_pages(
			array(
				'hierarchical' => false,
				'child_of'     => $page_1,
			)
		);

		/*
		 * Page tree:
		 *
		 * page 1 (parent 0)
		 * – page 3 (parent 1)
		 * –– page 4 (parent 3)
		 * – page 5 (parent 1)
		 * page 2 (parent 0)
		 *
		 * If hierarchical is false, and child_of is not empty, pages will (apparently) be returned
		 * hierarchically anyway in order of creation: 3, 4, 5.
		 */
		$this->assertSameSets( array( $page_3, $page_4, $page_5 ), wp_list_pluck( $pages, 'ID' ) );

		// How it should work.
		$found_pages = wp_list_filter( $pages, array( 'post_parent' => $page_1 ) );
		$this->assertSameSets( array( $page_3, $page_5 ), wp_list_pluck( $found_pages, 'ID' ) );

	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_post_type() {
		register_post_type( 'wptests_pt', array( 'hierarchical' => true ) );
		$posts = self::factory()->post->create_many( 2, array( 'post_type' => 'wptests_pt' ) );
		$pages = get_pages(
			array(
				'post_type' => 'wptests_pt',
			)
		);
		$this->assertSameSets( $posts, wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_post_status() {
		register_post_status(
			'foo',
			array(
				'public' => true,
			)
		);

		$posts = self::factory()->post->create_many(
			2,
			array(
				'post_type'   => 'page',
				'post_status' => 'foo',
			)
		);
		$pages = get_pages(
			array(
				'post_status' => 'foo',
			)
		);

		$this->assertSameSets( $posts, wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_offset() {
		$posts = self::factory()->post->create_many( 4, array( 'post_type' => 'page' ) );
		$pages = get_pages(
			array(
				'offset' => 2,
				'number' => 2,
			)
		);

		$this->assertSameSets( array( $posts[2], $posts[3] ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_author() {
		$author_1 = self::factory()->user->create(
			array(
				'user_login' => 'author1',
				'role'       => 'author',
			)
		);
		$posts    = self::factory()->post->create_many(
			2,
			array(
				'post_type'   => 'page',
				'post_author' => $author_1,
			)
		);
		$pages    = get_pages(
			array(
				'authors' => $author_1,
			)
		);

		$this->assertSameSets( $posts, wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_multiple_authors() {
		$author_1 = self::factory()->user->create(
			array(
				'user_login' => 'author1',
				'role'       => 'author',
			)
		);
		$post_1   = self::factory()->post->create(
			array(
				'post_title'  => 'Page 1',
				'post_type'   => 'page',
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
				'post_title'  => 'Page 2',
				'post_type'   => 'page',
				'post_author' => $author_2,
				'post_date'   => '2007-01-01 00:00:00',
			)
		);
		$pages    = get_pages(
			array(
				'authors' => "{$author_1}, {$author_2}",
			)
		);

		$this->assertSameSets( array( $post_1, $post_2 ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_multiple_authors_by_user_login() {
		$author_1 = self::factory()->user->create(
			array(
				'user_login' => 'author1',
				'role'       => 'author',
			)
		);
		$post_1   = self::factory()->post->create(
			array(
				'post_title'  => 'Page 1',
				'post_type'   => 'page',
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
				'post_title'  => 'Page 2',
				'post_type'   => 'page',
				'post_author' => $author_2,
				'post_date'   => '2007-01-01 00:00:00',
			)
		);
		$pages    = get_pages(
			array(
				'authors' => 'author1, author2',
			)
		);

		$this->assertSameSets( array( $post_1, $post_2 ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_orderby() {
		global $wpdb;
		// 'rand' is a valid value.
		get_pages( array( 'sort_column' => 'rand' ) );
		$this->assertStringContainsString(
			'ORDER BY RAND()',
			$wpdb->last_query,
			'Check that ORDER is random.'
		);

		// This isn't allowed.
		get_pages( array( 'sort_order' => 'rand' ) );
		$this->assertStringContainsString(
			'ORDER BY',
			$wpdb->last_query,
			'Check that ORDER BY is present.'
		);
		$this->assertStringNotContainsString(
			'RAND()',
			$wpdb->last_query,
			'Check that ORDER is not random.'
		);
		$this->assertStringContainsString(
			'DESC',
			$wpdb->last_query,
			'Check that DESC is not present.'
		);

		// 'none' is a valid value.
		get_pages( array( 'sort_column' => 'none' ) );
		$this->assertStringNotContainsString(
			'ORDER BY',
			$wpdb->last_query,
			'Check that ORDER BY is not present.'
		);
		$this->assertStringNotContainsString(
			'DESC',
			$wpdb->last_query,
			'Check that DESC is not present.'
		);
		$this->assertStringNotContainsString(
			'ASC',
			$wpdb->last_query,
			'Check that ASC is not present.'
		);

		// False is a valid value.
		get_pages( array( 'sort_column' => false ) );
		$this->assertStringContainsString(
			'ORDER BY',
			$wpdb->last_query,
			'Check that ORDER BY is present if sort_column is false.'
		);

		// Empty array() is a valid value.
		get_pages( array( 'sort_column' => array() ) );
		$this->assertStringContainsString(
			'ORDER BY',
			$wpdb->last_query,
			'Check that ORDER BY is present if sort_column is an empty array.'
		);
	}

	/**
	 * @ticket 12821
	 */
	public function test_get_pages_order() {
		global $wpdb;

		get_pages(
			array(
				'sort_column' => 'post_type',
			)
		);
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_type ASC",
			$wpdb->last_query,
			'Check that ORDER is post type.'
		);

		get_pages(
			array(
				'sort_column' => 'title',
				'sort_order'  => 'foo',
			)
		);
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_title DESC",
			$wpdb->last_query,
			'Check that ORDER is default.'
		);

		get_pages(
			array(
				'sort_order'  => 'asc',
				'sort_column' => 'date',
			)
		);
		$this->assertStringContainsString(
			"ORDER BY $wpdb->posts.post_date ASC",
			$wpdb->last_query,
			'Check that ORDER is post date.'
		);
	}
}
