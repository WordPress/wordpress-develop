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

	/**
	 * Test that WP_Query::get() returns the value as passed on the `pre_get_posts` hook.
	 *
	 * @ticket 63255
	 * @dataProvider data_pre_get_posts_includes_unmodified_query_vars
	 *
	 * @param string $query_var      The query variable.
	 * @param mixed  $query_var_value The value to set for the query variable.
	 */
	public function test_pre_get_posts_includes_unmodified_query_vars( $query_var, $query_var_value ) {
		$number_action_runs = 0;

		/*
		 * MockAction can not be used here because `$query` is an object and therefore
		 * is passed by reference so will be modified by the time `MockAction::get_args()`
		 * is called.
		 */
		add_action(
			'pre_get_posts',
			function ( $query ) use ( $query_var, $query_var_value, &$number_action_runs ) {
				++$number_action_runs;
				$this->assertSame( $query_var_value, $query->get( $query_var ), 'The pre_get_posts filter should return an unmodified query var.' );
			}
		);

		new WP_Query(
			array(
				$query_var            => $query_var_value,
				'ignore_sticky_posts' => true, // Ensures the sticky posts WP_Query does not run.
			)
		);

		// Ensure the action was called.
		$this->assertSame( 1, $number_action_runs, 'The pre_get_posts action is expected to be called exactly once' );
	}

	/**
	 * Data provider for test_pre_get_posts_includes_unmodified_query_vars.
	 *
	 * @return array[] Data provider.
	 */
	public function data_pre_get_posts_includes_unmodified_query_vars() {
		return array(
			'post type, string'                 => array( 'post_type', 'post' ),
			'post type, string[] DESC'          => array( 'post_type', array( 'post', 'page' ) ),
			'post type, string[] ASC'           => array( 'post_type', array( 'page', 'post' ) ),
			'post type, string[] duplicate'     => array( 'post_type', array( 'post', 'post' ) ),
			'post status, string'               => array( 'post_status', 'publish' ),
			'post status, string[] DESC'        => array( 'post_status', array( 'publish', 'draft' ) ),
			'post status, string[] ASC'         => array( 'post_status', array( 'draft', 'publish' ) ),
			'post status, string[] duplicate'   => array( 'post_status', array( 'draft', 'draft' ) ),

			'post_name__in, string'             => array( 'post_name__in', 'elphaba' ),
			'post_name__in, string[] DESC'      => array( 'post_name__in', array( 'the-wizard-of-oz', 'glinda', 'doctor-dillamond', 'elphaba' ) ),
			'post_name__in, string[] ASC'       => array( 'post_name__in', array( 'elphaba', 'doctor-dillamond', 'glinda', 'the-wizard-of-oz' ) ),
			'post_name__in, string[] duplicate' => array( 'post_name__in', array( 'elphaba', 'doctor-dillamond', 'elphaba', 'doctor-dillamond' ) ),

			'cat, comma-separated string ASC'   => array( 'cat', '1,2' ),
			'cat, comma-separated string DESC'  => array( 'cat', '2,1' ),

			'category__in, int[] ASC'           => array( 'category__in', array( 1, 2 ) ),
			'category__in, int[] DESC'          => array( 'category__in', array( 2, 1 ) ),

			'category__not_in, int[] ASC'       => array( 'category__not_in', array( 1, 2 ) ),
			'category__not_in, int[] DESC'      => array( 'category__not_in', array( 2, 1 ) ),

			'category__and, int[] ASC'          => array( 'category__in', array( 1, 2 ) ),
			'category__and, int[] DESC'         => array( 'category__in', array( 2, 1 ) ),

			'post id, int'                      => array( 'p', 1 ),
			'page_id, int'                      => array( 'page_id', 1 ),
			'attachment_id, int'                => array( 'page_id', 1 ),
			'offset, string'                    => array( 'offset', '5' ),
			'offset, int'                       => array( 'offset', 5 ),

			'post__in, string[] ASC'            => array( 'post__in', array( '1', '2' ) ),
			'post__in, string[] DESC'           => array( 'post__in', array( '2', '1' ) ),
			'post__in, int[] ASC'               => array( 'post__in', array( 1, 2 ) ),
			'post__in, int[] DESC'              => array( 'post__in', array( 2, 1 ) ),
			'post__in, int[] duplicate'         => array( 'post__in', array( 1, 1 ) ),

			'post__not_in, string[] ASC'        => array( 'post__not_in', array( '1', '2' ) ),
			'post__not_in, string[] DESC'       => array( 'post__not_in', array( '2', '1' ) ),
			'post__not_in, int[] ASC'           => array( 'post__not_in', array( 1, 2 ) ),
			'post__not_in, int[] DESC'          => array( 'post__not_in', array( 2, 1 ) ),
			'post__not_in, int[] duplicate'     => array( 'post__not_in', array( 1, 1 ) ),

			'author__in, string[] ASC'          => array( 'author__in', array( '1', '2' ) ),
			'author__in, string[] DESC'         => array( 'author__in', array( '2', '1' ) ),
			'author__in, int[] ASC'             => array( 'author__in', array( 1, 2 ) ),
			'author__in, int[] DESC'            => array( 'author__in', array( 2, 1 ) ),
			'author__in, int[] duplicate'       => array( 'author__in', array( 1, 1 ) ),

			'author__not_in, string[] ASC'      => array( 'author__not_in', array( '1', '2' ) ),
			'author__not_in, string[] DESC'     => array( 'author__not_in', array( '2', '1' ) ),
			'author__not_in, int[] ASC'         => array( 'author__not_in', array( 1, 2 ) ),
			'author__not_in, int[] DESC'        => array( 'author__not_in', array( 2, 1 ) ),
			'author__not_in, int[] duplicate'   => array( 'author__not_in', array( 1, 1 ) ),

			'tag_slug__in, string[] ASC'        => array( 'tag_slug__in', array( 'bobby', 'hans', 'herman', 'victor' ) ),
			'tag_slug__in, string[] DESC'       => array( 'tag_slug__in', array( 'victor', 'herman', 'hans', 'bobby' ) ),

			'tag__in, int[] ASC'                => array( 'tag__in', array( 1, 2 ) ),
			'tag__in, int[] DESC'               => array( 'tag__in', array( 2, 1 ) ),

			'tag__not_in, int[] ASC'            => array( 'tag__not_in', array( 1, 2 ) ),
			'tag__not_in, int[] DESC'           => array( 'tag__not_in', array( 2, 1 ) ),

			'tag__and, int[] ASC'               => array( 'tag__and', array( 1, 2 ) ),
			'tag__and, int[] DESC'              => array( 'tag__and', array( 2, 1 ) ),

			'tag_slug__and, string[] ASC'       => array( 'tag_slug__and', array( 'bobby', 'hans', 'herman', 'victor' ) ),
			'tag_slug__and, string[] DESC'      => array( 'tag_slug__and', array( 'victor', 'herman', 'hans', 'bobby' ) ),
		);
	}
}
