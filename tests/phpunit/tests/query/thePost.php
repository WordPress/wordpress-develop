<?php

/**
 * @group query
 * @covers WP_Query::the_post
 */
class Tests_Query_ThePost extends WP_UnitTestCase {

	/**
	 * Author IDs created for shared fixtures.
	 *
	 * @var int[]
	 */
	public static $author_ids = array();

	/**
	 * Post parent ID created for shared fixtures.
	 *
	 * @var int
	 */
	public static $page_parent_id = 0;

	/**
	 * Post child IDs created for shared fixtures.
	 *
	 * @var int[]
	 */
	public static $page_child_ids = array();

	/**
	 * Create the shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_ids     = $factory->user->create_many( 5, array( 'role' => 'author' ) );
		self::$page_parent_id = $factory->post->create( array( 'post_type' => 'page' ) );

		// Create child pages.
		foreach ( self::$author_ids as $author_id ) {
			self::$page_child_ids[] = $factory->post->create(
				array(
					'post_type'   => 'page',
					'post_parent' => self::$page_parent_id,
					'post_author' => $author_id,
				)
			);
		}
	}

	/**
	 * Ensure custom 'fields' values are respected.
	 *
	 * @ticket 56992
	 */
	public function test_wp_query_respects_custom_fields_values() {
		global $wpdb;
		add_filter(
			'posts_fields',
			function ( $fields, $query ) {
				global $wpdb;

				if ( $query->get( 'fields' ) === 'custom' ) {
					$fields = "$wpdb->posts.ID,$wpdb->posts.post_author";
				}

				return $fields;
			},
			10,
			2
		);

		$query = new WP_Query(
			array(
				'fields'    => 'custom',
				'post_type' => 'page',
				'post__in'  => self::$page_child_ids,
			)
		);

		$this->assertNotEmpty( $query->posts, 'The query is expected to return results' );
		$this->assertSame( $query->get( 'fields' ), 'custom', 'The WP_Query class is expected to use the custom fields value' );
		$this->assertStringContainsString( "$wpdb->posts.ID,$wpdb->posts.post_author", $query->request, 'The database query is expected to use the custom fields value' );
	}

	/**
	 * Ensure custom 'fields' populates the global post in the loop.
	 *
	 * @ticket 56992
	 */
	public function test_wp_query_with_custom_fields_value_populates_the_global_post() {
		global $wpdb;
		add_filter(
			'posts_fields',
			function ( $fields, $query ) {
				global $wpdb;

				if ( $query->get( 'fields' ) === 'custom' ) {
					$fields = "$wpdb->posts.ID,$wpdb->posts.post_author";
				}

				return $fields;
			},
			10,
			2
		);

		$query = new WP_Query(
			array(
				'fields'    => 'custom',
				'post_type' => 'page',
				'post__in'  => self::$page_child_ids,
				'orderby'   => 'id',
				'order'     => 'ASC',
			)
		);

		$query->the_post();

		// Get the global post and specific post.
		$global_post   = get_post();
		$specific_post = get_post( self::$page_child_ids[0], ARRAY_A );

		$this->assertSameSetsWithIndex( $specific_post, $global_post->to_array(), 'The global post is expected to be fully populated.' );

		$this->assertNotEmpty( get_the_title(), 'The title is expected to be populated.' );
		$this->assertNotEmpty( get_the_content(), 'The content is expected to be populated.' );
		$this->assertNotEmpty( get_the_excerpt(), 'The excerpt is expected to be populated.' );
	}

	/**
	 * Ensure that a secondary loop populates the global post completely regardless of the fields parameter.
	 *
	 * @ticket 56992
	 *
	 * @dataProvider data_the_loop_fields
	 *
	 * @param string $fields Fields parameter for use in the query.
	 */
	public function test_the_loop_populates_the_global_post_completely( $fields ) {
		$query = new WP_Query(
			array(
				'fields'    => $fields,
				'post_type' => 'page',
				'page_id'   => self::$page_child_ids[0],
			)
		);

		$this->assertNotEmpty( $query->posts, 'The query is expected to return results' );

		// Start the loop.
		$query->the_post();

		// Get the global post and specific post.
		$global_post   = get_post();
		$specific_post = get_post( self::$page_child_ids[0], ARRAY_A );

		$this->assertSameSetsWithIndex( $specific_post, $global_post->to_array(), 'The global post is expected to be fully populated.' );

		$this->assertNotEmpty( get_the_title(), 'The title is expected to be populated.' );
		$this->assertNotEmpty( get_the_content(), 'The content is expected to be populated.' );
		$this->assertNotEmpty( get_the_excerpt(), 'The excerpt is expected to be populated.' );
	}

	/**
	 * Ensure that a secondary loop primes the post cache completely regardless of the fields parameter.
	 *
	 * @ticket 56992
	 *
	 * @dataProvider data_the_loop_fields
	 *
	 * @param string $fields           Fields parameter for use in the query.
	 * @param int    $expected_queries Expected number of queries when starting the loop.
	 */
	public function test_the_loop_primes_the_post_cache( $fields, $expected_queries ) {
		$query = new WP_Query(
			array(
				'fields'    => $fields,
				'post_type' => 'page',
				'post__in'  => self::$page_child_ids,
			)
		);

		// Start the loop.
		$start_queries = get_num_queries();
		$query->the_post();
		$end_queries = get_num_queries();
		/*
		 * Querying complete posts: 2 queries.
		 * 1. User meta data.
		 * 2. User data.
		 *
		 * Querying partial posts: 4 queries.
		 * 1. Post objects
		 * 2. Post meta data.
		 * 3. User meta data.
		 * 4. User data.
		 */
		$this->assertSame( $expected_queries, $end_queries - $start_queries, "Starting the loop should make $expected_queries db queries." );

		// Complete the loop.
		$start_queries = get_num_queries();
		while ( $query->have_posts() ) {
			$query->the_post();
		}
		$end_queries = get_num_queries();

		$this->assertSame( 0, $end_queries - $start_queries, 'The cache is expected to be primed by the loop.' );
	}

	/**
	 * Ensure that a secondary loop primes the author cache completely regardless of the fields parameter.
	 *
	 * @ticket 56992
	 *
	 * @dataProvider data_the_loop_fields
	 *
	 * @param string $fields           Fields parameter for use in the query.
	 * @param int    $expected_queries Expected number of queries when starting the loop.
	 */
	public function test_the_loop_primes_the_author_cache( $fields, $expected_queries ) {
		$query = new WP_Query(
			array(
				'fields'    => $fields,
				'post_type' => 'page',
				'post__in'  => self::$page_child_ids,
			)
		);

		// Start the loop.
		$start_queries = get_num_queries();
		$query->the_post();
		$end_queries = get_num_queries();
		/*
		 * Querying complete posts: 2 queries.
		 * 1. User meta data.
		 * 2. User data.
		 *
		 * Querying partial posts: 4 queries.
		 * 1. Post objects
		 * 2. Post meta data.
		 * 3. User meta data.
		 * 4. User data.
		 */
		$this->assertSame( $expected_queries, $end_queries - $start_queries, "Starting the loop should make $expected_queries db queries." );

		// Complete the loop.
		$start_queries = get_num_queries();
		while ( $query->have_posts() ) {
			$query->the_post();
			get_the_author();
		}
		$end_queries = get_num_queries();

		$this->assertSame( 0, $end_queries - $start_queries, 'The cache is expected to be primed by the loop.' );
	}

	/**
	 * Data provider for:
	 * - test_the_loop_populates_the_global_post_completely,
	 * - test_the_loop_primes_the_post_cache, and,
	 * - test_the_loop_primes_the_author_cache.
	 *
	 * @return array[]
	 */
	public function data_the_loop_fields() {
		return array(
			'all fields'                => array( 'all', 2 ),
			'all fields (empty fields)' => array( '', 2 ),
			'post IDs'                  => array( 'ids', 4 ),
			'post ids and parent'       => array( 'id=>parent', 4 ),
		);
	}

	/**
	 * Ensure draft content is shown for post previews and permalinks for logged in users.
	 *
	 * @ticket 56992
	 */
	public function test_post_preview_links_draft_posts() {
		$user_id = self::$author_ids[0];
		wp_set_current_user( $user_id );
		$draft_post = $this->factory()->post->create(
			array(
				'post_status'  => 'draft',
				'post_author'  => $user_id,
				'post_content' => 'ticket 56992',
			)
		);

		// Ensure the global post is populated with the draft content for the preview link.
		$this->go_to( get_preview_post_link( $draft_post ) );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertSame( 'ticket 56992', get_the_content(), 'Preview link should show draft content to logged in user' );

		// Ensure the global post is populated with the draft content for the permalink.
		$this->go_to( get_permalink( $draft_post ) );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertSame( 'ticket 56992', get_the_content(), 'Permalink should show draft content to logged in user' );

		// Ensure the global post is not populated with the draft content for the preview link when logged out.
		wp_set_current_user( 0 );
		$this->go_to( get_preview_post_link( $draft_post ) );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertEmpty( get_the_content(), 'Preview link should not show draft content to logged out users' );

		// Ensure the global post is not populated with the draft content for the permalink when logged out.
		$this->go_to( get_permalink( $draft_post ) );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertEmpty( get_the_content(), 'Permalink should not show draft content to logged out users' );
	}

	/**
	 * Ensure autosave content is shown for post previews.
	 *
	 * @ticket 56992
	 */
	public function test_post_preview_links_autosaves() {
		$user_id = self::$author_ids[0];
		wp_set_current_user( $user_id );
		$published_post = $this->factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_content' => 'ticket 56992',
			)
		);

		// Create an autosave for the published post.
		$autosave                 = get_post( $published_post, ARRAY_A );
		$autosave['post_ID']      = $published_post;
		$autosave['post_content'] = 'ticket 56992 edited';
		wp_create_post_autosave( $autosave );

		// Set up the preview $_GET parameters.
		$nonce                       = wp_create_nonce( 'post_preview_' . $published_post );
		$query_args['preview_id']    = $published_post;
		$query_args['preview_nonce'] = $nonce;
		$post_preview_link           = get_preview_post_link( $published_post, $query_args );

		/*
		 * Set up the GET parameters for the preview link.
		 *
		 * _show_post_preview() checks the $_GET super global for preview
		 * and nonce parameters. It needs to run prior to the global query
		 * being set up in WP_Query (via $this->go_to()), so the preview
		 * parameters are created here to ensure _show_post_preview()
		 * runs correctly.
		 */
		$_GET['preview_id']    = $published_post;
		$_GET['preview_nonce'] = $nonce;
		_show_post_preview();

		// Ensure the global post is populated with the autosave content for the preview link.
		$this->go_to( $post_preview_link );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertSame( 'ticket 56992 edited', get_the_content(), 'Preview link should show autosave content to logged in user' );

		// Ensure the global post is populated with the published content for the permalink.
		$this->go_to( get_permalink( $published_post ) );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertSame( 'ticket 56992', get_the_content(), 'Permalink should show published content to logged in user' );

		wp_set_current_user( 0 );

		// New user, new nonce; set up the preview $_GET parameters.
		$nonce                       = wp_create_nonce( 'post_preview_' . $published_post );
		$query_args['preview_id']    = $published_post;
		$query_args['preview_nonce'] = $nonce;
		$post_preview_link           = get_preview_post_link( $published_post, $query_args );

		/*
		 * Set up the GET parameters for the preview link.
		 *
		 * _show_post_preview() checks the $_GET super global for preview
		 * and nonce parameters. It needs to run prior to the global query
		 * being set up in WP_Query (via $this->go_to()), so the preview
		 * parameters are created here to ensure _show_post_preview()
		 * runs correctly.
		 */
		$_GET['preview_id']    = $published_post;
		$_GET['preview_nonce'] = $nonce;
		_show_post_preview();

		// Ensure the global post is not populated with the draft content for the preview link when logged out.
		$this->go_to( $post_preview_link );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertSame( 'ticket 56992', get_the_content(), 'Preview link should show published content to logged out users' );

		// Ensure the global post is not populated with the draft content for the permalink when logged out.
		$this->go_to( get_permalink( $published_post ) );
		if ( have_posts() ) {
			the_post();
		}
		$this->assertSame( 'ticket 56992', get_the_content(), 'Permalink should show published content to logged out users' );
	}
}
