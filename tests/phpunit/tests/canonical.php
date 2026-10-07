<?php
/**
 * Tests Canonical redirections.
 *
 * In the process of doing so, it also tests WP, WP_Rewrite and WP_Query, A fail here may show a bug in any one of these areas.
 *
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical extends WP_Canonical_UnitTestCase {

	public static $private_cpt_post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Set up fixtures in WP_Canonical_UnitTestCase.
		parent::wpSetUpBeforeClass( $factory );

		self::set_up_custom_post_types();
		self::$private_cpt_post = $factory->post->create(
			array(
				'post_type'  => 'wp_tests_private',
				'post_title' => 'private-cpt-post',
			)
		);
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$author_id );
		self::set_up_custom_post_types();

		update_option( 'wp_attachment_pages_enabled', 1 );
	}

	/**
	 * Register custom post type for tests.
	 *
	 * Register non publicly queryable post type with public set to true.
	 *
	 * These arguments are intentionally contradictory for the test associated
	 * with ticket #59795.
	 */
	public static function set_up_custom_post_types() {
		register_post_type(
			'wp_tests_private',
			array(
				'public'             => true,
				'publicly_queryable' => false,
			)
		);
	}

	/**
	 * @dataProvider data_canonical
	 */
	public function test_canonical( $test_url, $expected, $ticket = 0, $expected_doing_it_wrong = array() ) {

		if ( false !== strpos( $test_url, '%d' ) ) {
			if ( false !== strpos( $test_url, '/?author=%d' ) ) {
				$test_url = sprintf( $test_url, self::$author_id );
			}
			if ( false !== strpos( $test_url, '?cat=%d' ) ) {
				$test_url = sprintf( $test_url, self::$terms[ $expected['url'] ] );
			}
		}

		$this->assertCanonical( $test_url, $expected, $ticket, $expected_doing_it_wrong );
	}

	public function data_canonical() {
		/*
		 * Data format:
		 * [0]: Test URL.
		 * [1]: Expected results: Any of the following can be used.
		 *      array( 'url': expected redirection location, 'qv': expected query vars to be set via the rewrite AND $_GET );
		 *      array( expected query vars to be set, same as 'qv' above )
		 *      (string) expected redirect location
		 * [2]: (optional) The ticket the test refers to, Can be skipped if unknown.
		 * [3]: (optional) Array of class/function names expected to throw `_doing_it_wrong()` notices.
		 */

		// Please Note: A few test cases are commented out below, look at the test case following it.
		// In most cases it's simply showing 2 options for the "proper" redirect.
		return array(
			// Categories.
			array( '?cat=%d', array( 'url' => '/category/parent/' ), 15256 ),
			array( '?cat=%d', array( 'url' => '/category/parent/child-1/' ), 15256 ),
			array( '?cat=%d', array( 'url' => '/category/parent/child-1/child-2/' ) ), // No children.
			array(
				'/category/uncategorized/',
				array(
					'url' => '/category/uncategorized/',
					'qv'  => array( 'category_name' => 'uncategorized' ),
				),
			),
			array(
				'/category/uncategorized/page/2/',
				array(
					'url' => '/category/uncategorized/page/2/',
					'qv'  => array(
						'category_name' => 'uncategorized',
						'paged'         => 2,
					),
				),
			),
			array(
				'/category/uncategorized/?paged=2',
				array(
					'url' => '/category/uncategorized/page/2/',
					'qv'  => array(
						'category_name' => 'uncategorized',
						'paged'         => 2,
					),
				),
			),
			array(
				'/category/uncategorized/?paged=2&category_name=uncategorized',
				array(
					'url' => '/category/uncategorized/page/2/',
					'qv'  => array(
						'category_name' => 'uncategorized',
						'paged'         => 2,
					),
				),
				17174,
			),

			// Categories & intersections with other vars.
			array(
				'/category/uncategorized/?tag=post-formats',
				array(
					'url' => '/category/uncategorized/?tag=post-formats',
					'qv'  => array(
						'category_name' => 'uncategorized',
						'tag'           => 'post-formats',
					),
				),
			),
			array(
				'/?category_name=cat-a,cat-b',
				array(
					'url' => '/?category_name=cat-a,cat-b',
					'qv'  => array( 'category_name' => 'cat-a,cat-b' ),
				),
			),

			// Taxonomies with extra query vars.
			array( '/category/cat-a/page/1/?test=one%20two', '/category/cat-a/?test=one%20two', 18086 ), // Extra query vars should stay encoded.

			// Post formats.
			array( '/?post_format=audio', '/type/audio/', 20902 ),
			array( '/?post_format=audio&test=one', '/type/audio/?test=one', 20902 ), // Extra query vars should be kept.
			array( '/type/audio/', '/type/audio/', 20902 ), // No redirect.
			array( '/?post_format=video', '/?post_format=video', 20902 ), // A format without posts is a 404: no redirect.

			// Categories with dates.
			array(
				'/2008/04/?cat=1',
				array(
					'url' => '/2008/04/?cat=1',
					'qv'  => array(
						'cat'      => '1',
						'year'     => '2008',
						'monthnum' => '04',
					),
				),
				17661,
			),
			/*
			array(
				'/2008/?category_name=cat-a',
					array(
						'url' => '/2008/?category_name=cat-a',
						'qv'  => array(
							'category_name' => 'cat-a',
							'year'          => '2008'
						)
					)
			),
			*/

			// Pages.
			array( '/child-page-1/', '/parent-page/child-page-1/' ),
			array( '/?page_id=144', '/parent-page/child-page-1/' ),
			array( '/?pagename=sample-page', '/sample-page/', 20902 ),
			array( '/?pagename=sample-page&test=one', '/sample-page/?test=one', 20902 ), // Extra query vars should be kept.
			array( '/?pagename=parent/child1/grandchild', '/parent/child1/grandchild/', 20902 ), // Hierarchical page path.
			array( '/?pagename=does-not-exist', '/?pagename=does-not-exist', 20902 ), // A page that does not exist is a 404: no redirect.
			array( '/?pagename=sample-page&feed=rss2', '/sample-page/feed/', 20902 ),
			array( '/?pagename=sample-page&paged=2', '/page/2/?pagename=sample-page', 20902 ), // Paging does not apply to a page: unchanged, the page name is not dropped.
			array( '/abo', '/about/' ),
			array( '/parent/child1/grandchild/', '/parent/child1/grandchild/' ),
			array( '/parent/child2/grandchild/', '/parent/child2/grandchild/' ),

			// Posts.
			array( '?p=587', '/2008/06/02/post-format-test-audio/' ),
			array( '/?name=images-test', '/2008/09/03/images-test/' ),
			// Incomplete slug should resolve and remove the ?name= parameter.
			array( '/?name=images-te', '/2008/09/03/images-test/', 20374 ),
			// Page slug should resolve to post slug and remove the ?pagename= parameter.
			array( '/?pagename=images-test', '/2008/09/03/images-test/', 20374 ),

			array( '/2008/06/02/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),
			array( '/2008/06/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),
			array( '/2008/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),
			array( '/2010/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ), // A year the post is not in.
			array( '/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),

			// Pagination.
			array(
				'/2008/09/03/multipage-post-test/3/',
				array(
					'url' => '/2008/09/03/multipage-post-test/3/',
					'qv'  => array(
						'name'     => 'multipage-post-test',
						'year'     => '2008',
						'monthnum' => '09',
						'day'      => '03',
						'page'     => '3',
					),
				),
			),
			array( '/2008/09/03/multipage-post-test/?page=3', '/2008/09/03/multipage-post-test/3/' ),
			array( '/2008/09/03/multipage-post-te?page=3', '/2008/09/03/multipage-post-test/3/' ),

			array( '/2008/09/03/non-paged-post-test/3/', '/2008/09/03/non-paged-post-test/' ),
			array( '/2008/09/03/non-paged-post-test/?page=3', '/2008/09/03/non-paged-post-test/' ),

			// Comments.
			array( '/2008/03/03/comment-test/?cpage=2', '/2008/03/03/comment-test/comment-page-2/' ),

			// Attachments.
			array( '/?attachment_id=611', '/2008/06/10/post-format-test-gallery/canola2/' ),
			array( '/2008/06/10/post-format-test-gallery/?attachment_id=611', '/2008/06/10/post-format-test-gallery/canola2/' ),

			// Dates.
			array( '/?m=2008', '/2008/' ),
			array( '/?m=200809', '/2008/09/' ),
			array( '/?m=20080905', '/2008/09/05/' ),

			array( '/2008/?day=05', '/2008/?day=05' ), // No redirect.
			array( '/2008/09/?day=05', '/2008/09/05/' ),
			array( '/2008/?monthnum=9', '/2008/09/' ),

			array( '/?year=2008', '/2008/' ),

			array( '/2012/13/', '/2012/' ),
			array( '/2012/11/51/', '/2012/11/', 0, array( 'WP_Date_Query' ) ),

			// Authors.
			array( '/?author=%d', '/author/canonical-author/' ),
			array( '/?author_name=canonical-author', '/author/canonical-author/', 20902 ),
			array( '/?author_name=canonical-author&test=one', '/author/canonical-author/?test=one', 20902 ), // Extra query vars should be kept.
			array( '/?author_name=does-not-exist', '/?author_name=does-not-exist', 20902 ), // No such author: no redirect.
			// Paging and feeds should be added to the author URL, not replace it.
			array( '/?author_name=canonical-author&paged=1', '/author/canonical-author/', 20902 ),
			array( '/?author_name=canonical-author&paged=2', '/author/canonical-author/page/2/', 20902 ),
			array( '/?author_name=canonical-author&feed=rss2', '/author/canonical-author/feed/', 20902 ),
			array( '/?author=%d&paged=2', '/author/canonical-author/page/2/', 20902 ),
			// array( '/?author=%d&year=2008', '/2008/?author=3'),
			// array( '/author/canonical-author/?year=2008', '/2008/?author=3'), // Either or, see previous testcase.
			array( '/author/canonical-author/?author[1]=hello', '/author/canonical-author/?author[1]=hello', 60059 ),

			// Feeds.
			array( '/?feed=atom', '/feed/atom/' ),
			array( '/?feed=rss2', '/feed/' ),
			array( '/?feed=comments-rss2', '/comments/feed/' ),
			array( '/?feed=comments-atom', '/comments/feed/atom/' ),

			// Feeds (per-post).
			array( '/2008/03/03/comment-test/?feed=comments-atom', '/2008/03/03/comment-test/feed/atom/' ),
			array( '/?p=149&feed=comments-atom', '/2008/03/03/comment-test/feed/atom/' ),

			// Index.
			array( '/?paged=1', '/' ),
			array( '/page/1/', '/' ),
			array( '/page1/', '/' ),
			array( '/?paged=2', '/page/2/' ),
			array( '/page2/', '/page/2/' ),

			// Misc.
			array( '/2008%20', '/2008' ),
			array( '//2008////', '/2008/' ),

			// @todo Endpoints (feeds, trackbacks, etc). More fuzzed mixed query variables, comment paging, Home page (static).
		);
	}

	/**
	 * A `?author_name=` request for a user without published posts should not be redirected,
	 * the same as a `?author=` request for that user.
	 *
	 * @ticket 20902
	 *
	 * @dataProvider data_author_query_vars_without_published_posts
	 *
	 * @param string $query_var The author query var to test, either `author` or `author_name`.
	 */
	public function test_author_query_var_without_published_posts_is_not_redirected( $query_var ) {
		$user_id = self::factory()->user->create( array( 'user_login' => 'author-without-posts' ) );

		$test_url = ( 'author' === $query_var ) ? "/?author={$user_id}" : '/?author_name=author-without-posts';

		$this->assertCanonical( $test_url, $test_url, 20902 );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_author_query_vars_without_published_posts() {
		return array(
			'author'      => array( 'author' ),
			'author_name' => array( 'author_name' ),
		);
	}

	/**
	 * Paging and feeds of a `?post_format=` request should be added to the post format URL.
	 *
	 * @ticket 20902
	 */
	public function test_post_format_query_var_with_paging_and_feed() {
		// Together with the audio post from the fixtures, this gives two pages of audio posts.
		$post_ids = self::factory()->post->create_many( 5, array( 'post_date' => '2008-07-01 00:00:00' ) );

		foreach ( $post_ids as $post_id ) {
			set_post_format( $post_id, 'audio' );
		}

		$this->assertCanonical( '/?post_format=audio&paged=1', '/type/audio/', 20902 );
		$this->assertCanonical( '/?post_format=audio&paged=2', '/type/audio/page/2/', 20902 );
		$this->assertCanonical( '/?post_format=audio&paged=2&test=one', '/type/audio/page/2/?test=one', 20902 );
		$this->assertCanonical( '/?post_format=audio&feed=rss2', '/type/audio/feed/', 20902 );
	}

	/**
	 * @ticket 16557
	 */
	public function test_do_redirect_guess_404_permalink() {
		// Test disable do_redirect_guess_404_permalink().
		add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
		$this->go_to( '/child-page-1' );
		$this->assertFalse( redirect_guess_404_permalink() );
	}

	/**
	 * @ticket 16557
	 */
	public function test_pre_redirect_guess_404_permalink() {
		// Test short-circuit filter.
		add_filter(
			'pre_redirect_guess_404_permalink',
			static function () {
				return 'wp';
			}
		);
		$this->go_to( '/child-page-1' );
		$this->assertSame( 'wp', redirect_guess_404_permalink() );
	}

	/**
	 * @ticket 16557
	 */
	public function test_strict_redirect_guess_404_permalink() {
		$post = self::factory()->post->create(
			array(
				'post_title' => 'strict-redirect-guess-404-permalink',
			)
		);

		$this->go_to( 'strict-redirect' );

		// Test default 'non-strict' redirect guess.
		$this->assertSame( get_permalink( $post ), redirect_guess_404_permalink() );

		// Test 'strict' redirect guess.
		add_filter( 'strict_redirect_guess_404_permalink', '__return_true' );
		$this->assertFalse( redirect_guess_404_permalink() );
	}

	/**
	 * Ensure public posts with custom public statuses are guessed.
	 *
	 * @ticket 47911
	 * @dataProvider data_redirect_guess_404_permalink_with_custom_statuses
	 *
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_redirect_guess_404_permalink_with_custom_statuses( $status_args, $redirects ) {
		register_post_status( 'custom', $status_args );

		$post = self::factory()->post->create(
			array(
				'post_title'  => 'custom-status-public-guess-404-permalink',
				'post_status' => 'custom',
			)
		);

		$this->go_to( 'custom-status-public-guess-404-permalink' );

		$expected = $redirects ? get_permalink( $post ) : false;

		$this->assertSame( $expected, redirect_guess_404_permalink() );
	}

	/**
	 * Data provider for test_redirect_guess_404_permalink_with_custom_statuses().
	 *
	 * return array[] {
	 *    array Arguments used to register custom status
	 *    bool  Whether the 404 link is expected to redirect
	 * }
	 */
	public function data_redirect_guess_404_permalink_with_custom_statuses() {
		return array(
			'public status'                      => array(
				'status_args' => array( 'public' => true ),
				'redirects'   => true,
			),
			'private status'                     => array(
				'status_args' => array( 'public' => false ),
				'redirects'   => false,
			),
			'internal status'                    => array(
				'status_args' => array( 'internal' => true ),
				'redirects'   => false,
			),
			'protected status'                   => array(
				'status_args' => array( 'protected' => true ),
				'redirects'   => false,
			),
			'protected status flagged as public' => array(
				'status_args' => array(
					'protected' => true,
					'public'    => true,
				),
				'redirects'   => false,
			),
		);
	}

	/**
	 * Ensure multiple post types do not throw a notice.
	 *
	 * @ticket 43056
	 * @ticket 59795
	 *
	 * @dataProvider data_redirect_guess_404_permalink_post_types
	 */
	public function test_redirect_guess_404_permalink_post_types( $original_url, $expected ) {
		$this->assertCanonical( $original_url, $expected );
	}

	/**
	 * Data provider for test_redirect_guess_404_permalink_post_types().
	 *
	 * In the original URLs the post names are intentionally misspelled
	 * to test the redirection.
	 *
	 * Please do not correct the apparent typos.
	 *
	 * @return array[]
	 */
	public function data_redirect_guess_404_permalink_post_types() {
		return array(
			'single string formatted post type'    => array(
				'original_url' => '/?name=sample-pag&post_type=page',
				'expected'     => '/sample-page/',
			),
			'single array formatted post type'     => array(
				'original_url' => '/?name=sample-pag&post_type[]=page',
				'expected'     => '/sample-page/',
			),
			'multiple array formatted post type'   => array(
				'original_url' => '/?name=sample-pag&post_type[]=page&post_type[]=post',
				'expected'     => '/sample-page/',
			),
			'do not redirect to private post type' => array(
				'original_url' => '/?name=private-cpt-po&post_type[]=wp_tests_private',
				'expected'     => '/?name=private-cpt-po&post_type[]=wp_tests_private',
			),
		);
	}

	/**
	 * @ticket 64250
	 *
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_redirect_guess_404_permalink_cache() {
		$post = self::factory()->post->create(
			array(
				'post_title' => 'redirect-guess-404-permalink-cache',
			)
		);

		$this->go_to( 'redirect-guess-404-permalink-cach' );

		$first_run = redirect_guess_404_permalink();
		$this->assertSame( get_permalink( $post ), $first_run, 'Did not guess the correct permalink on first run.' );

		$start_num_queries = get_num_queries();
		$second_run        = redirect_guess_404_permalink();
		$this->assertSame( $first_run, $second_run, 'Result changed between cached and uncached run.' );
		$this->assertSame( 0, get_num_queries() - $start_num_queries, 'A cached lookup performed an additional database query.' );
	}

	/**
	 * @ticket 64250
	 *
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_redirect_guess_404_permalink_cache_misses_are_cached() {
		$this->go_to( 'redirect-guess-404-permalink-no-such-post' );

		$this->assertFalse( redirect_guess_404_permalink(), 'Expected no match for a nonexistent slug.' );

		$start_num_queries = get_num_queries();
		$this->assertFalse( redirect_guess_404_permalink(), 'Expected no match for a nonexistent slug on second run.' );
		$this->assertSame( 0, get_num_queries() - $start_num_queries, 'A cached "not found" result performed an additional database query.' );
	}

	/**
	 * @ticket 64250
	 *
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_redirect_guess_404_permalink_cache_invalidated_on_new_matching_post() {
		$this->go_to( 'redirect-guess-404-permalink-new-post' );

		// Prime a "not found" cache entry.
		$this->assertFalse( redirect_guess_404_permalink(), 'Expected no match before the matching post exists.' );

		$post = self::factory()->post->create(
			array(
				'post_title' => 'redirect-guess-404-permalink-new-post',
			)
		);

		$start_num_queries = get_num_queries();
		$this->assertSame( get_permalink( $post ), redirect_guess_404_permalink(), 'Newly created matching post was not found after cache invalidation.' );
		$this->assertSame( 1, get_num_queries() - $start_num_queries, 'Expected exactly one new query after the posts cache was invalidated.' );
	}

	/**
	 * @ticket 64250
	 *
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_redirect_guess_404_permalink_cache_invalidated_on_post_delete() {
		$post = self::factory()->post->create(
			array(
				'post_title' => 'redirect-guess-404-permalink-delete-me',
			)
		);

		$this->go_to( 'redirect-guess-404-permalink-delete-m' );

		$this->assertSame( get_permalink( $post ), redirect_guess_404_permalink(), 'Did not guess the correct permalink before deletion.' );

		wp_delete_post( $post, true );

		$start_num_queries = get_num_queries();
		$this->assertFalse( redirect_guess_404_permalink(), 'Deleted post should no longer be guessed after cache invalidation.' );
		$this->assertSame( 1, get_num_queries() - $start_num_queries, 'Expected exactly one new query after the posts cache was invalidated by deletion.' );
	}

	/**
	 * @ticket 64250
	 *
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_redirect_guess_404_permalink_cache_keys_do_not_collide() {
		$post_post = self::factory()->post->create(
			array(
				'post_title' => 'redirect-guess-collision',
				'post_type'  => 'post',
			)
		);
		$page_post = self::factory()->post->create(
			array(
				'post_title' => 'redirect-guess-collision',
				'post_type'  => 'page',
			)
		);

		$this->go_to( '/?name=redirect-guess-collisio&post_type=post' );
		$this->assertSame( get_permalink( $post_post ), redirect_guess_404_permalink(), 'Setting post type to post did not return the post permalink.' );

		// Re-run without navigating away (go_to() flushes the object cache) to confirm the cached result is reused.
		$start_num_queries = get_num_queries();
		$this->assertSame( get_permalink( $post_post ), redirect_guess_404_permalink(), 'Result changed between cached and uncached run.' );
		$this->assertSame( 0, get_num_queries() - $start_num_queries, 'A cached lookup performed an additional database query.' );

		$this->go_to( '/?name=redirect-guess-collisio&post_type=page' );
		$this->assertSame( get_permalink( $page_post ), redirect_guess_404_permalink(), 'Setting post type to page did not return the page permalink.' );
	}

	/**
	 * @ticket 43745
	 */
	public function test_utf8_query_keys_canonical() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $p );

		$this->go_to( get_permalink( $p ) );

		$redirect = redirect_canonical( add_query_arg( '%D0%BA%D0%BE%D0%BA%D0%BE%D0%BA%D0%BE', 1, site_url( '/' ) ), false );

		delete_option( 'page_on_front' );

		$this->assertNull( $redirect );
	}

	/**
	 * Ensure NOT EXISTS queries do not trigger not-countable or undefined array key errors.
	 *
	 * @ticket 55955
	 */
	public function test_feed_canonical_with_not_exists_query() {
		// Set a NOT EXISTS tax_query on the global query.
		$global_query        = $GLOBALS['wp_query'];
		$GLOBALS['wp_query'] = new WP_Query(
			array(
				'post_type' => 'post',
				'tax_query' => array(
					array(
						'taxonomy' => 'post_format',
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);

		$redirect = redirect_canonical( get_term_feed_link( self::$terms['/category/parent/'] ), false );

		// Restore original global.
		$GLOBALS['wp_query'] = $global_query;

		$this->assertNull( $redirect );
	}

	/**
	 * Test canonical redirects for attachment pages when the option is disabled.
	 *
	 * @ticket 57913
	 * @ticket 59866
	 *
	 * @dataProvider data_canonical_attachment_page_redirect_with_option_disabled
	 */
	public function test_canonical_attachment_page_redirect_with_option_disabled( $expected, $user = null, $parent_post_status = '' ) {
		update_option( 'wp_attachment_pages_enabled', 0 );

		if ( '' !== $parent_post_status ) {
			$parent_post_id = self::factory()->post->create(
				array(
					'post_status' => $parent_post_status,
				)
			);
		} else {
			$parent_post_id = 0;
		}

		$filename = DIR_TESTDATA . '/images/test-image.jpg';
		$contents = file_get_contents( $filename );
		$upload   = wp_upload_bits( wp_basename( $filename ), null, $contents );

		$attachment_id   = $this->_make_attachment( $upload, $parent_post_id );
		$attachment_url  = wp_get_attachment_url( $attachment_id );
		$attachment_page = get_permalink( $attachment_id );

		// Set as anonymous/logged out user.
		if ( null !== $user ) {
			wp_set_current_user( $user );
		}

		$this->go_to( $attachment_page );

		$url = redirect_canonical( $attachment_page, false );
		if ( is_string( $expected ) ) {
			$expected = str_replace( '%%attachment_url%%', $attachment_url, $expected );
		}

		$this->assertSame( $expected, $url );
	}

	/**
	 * Data provider for test_canonical_attachment_page_redirect_with_option_disabled().
	 *
	 * @return array[]
	 */
	public function data_canonical_attachment_page_redirect_with_option_disabled() {
		return array(
			'logged out user, no parent'      => array(
				'%%attachment_url%%',
				0,
			),
			'logged in user, no parent'       => array(
				'%%attachment_url%%',
			),
			'logged out user, private parent' => array(
				null,
				0,
				'private',
			),
			'logged in user, private parent'  => array(
				'%%attachment_url%%',
				null,
				'private',
			),
			'logged out user, public parent'  => array(
				'%%attachment_url%%',
				0,
				'publish',
			),
			'logged in user, public parent'   => array(
				'%%attachment_url%%',
				null,
				'publish',
			),
		);
	}
}
