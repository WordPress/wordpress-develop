<?php

/**
 * @group wp
 * @group query
 *
 * @covers WP::handle_random
 * @covers ::wp_random_content_posts_pre_query
 */
class Tests_WP_HandleRandom extends WP_UnitTestCase {

	/**
	 * Message of the exception thrown in place of the `exit` following a redirect.
	 *
	 * @var string
	 */
	const REDIRECT_EXCEPTION_MESSAGE = 'Random content redirect.';

	/**
	 * Redirect location captured from the `wp_redirect` filter.
	 *
	 * @var string|null
	 */
	protected $redirect_location;

	/**
	 * Creates shared post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$factory->post->create_many( 5 );
	}

	/**
	 * Captures redirects before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->redirect_location = null;

		// Capture redirects and end the request before `exit` ends the test run.
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ) );
	}

	/**
	 * Captures the redirect and ends the request in place of the `exit` that follows it.
	 *
	 * @throws WP_Tests_Exception Always, caught by {@see self::handle_random()}.
	 *
	 * @param string $location Redirect location.
	 * @return never
	 */
	public function filter_wp_redirect( string $location ) {
		$this->redirect_location = $location;

		throw new WP_Tests_Exception( self::REDIRECT_EXCEPTION_MESSAGE );
	}

	/**
	 * Handles a random content request with a new main query.
	 *
	 * Unlike WP_UnitTestCase_Base::go_to(), the request is handled without flushing the
	 * cache or running the rest of WP::main().
	 *
	 * @param array<string, string> $query_vars Query variables for the request.
	 * @return bool|null Whether the main query was run for a random content request,
	 *                   or null if the request was redirected.
	 */
	protected function handle_random( array $query_vars ): ?bool {
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];

		$wp             = new WP();
		$wp->query_vars = $query_vars;

		try {
			return $wp->handle_random();
		} catch ( WP_Tests_Exception $e ) {
			if ( self::REDIRECT_EXCEPTION_MESSAGE !== $e->getMessage() ) {
				throw $e;
			}

			return null;
		}
	}

	/**
	 * Ensure requests without the random query variable are not handled.
	 *
	 * @ticket 64498
	 *
	 * @dataProvider data_requests_without_the_random_query_var
	 *
	 * @param array<string, string> $query_vars Query variables for the request.
	 */
	public function test_requests_without_the_random_query_var_are_not_handled( array $query_vars ): void {
		$handled = $this->handle_random( $query_vars );

		$this->assertFalse( $handled, 'Requests without the random query variable should not be handled.' );
		$this->assertNull( $GLOBALS['wp_the_query']->request, 'The main query should be left for WP::main() to run.' );
		$this->assertNull( $this->redirect_location, 'The request should not redirect.' );
	}

	/**
	 * Data provider for {@see self::test_requests_without_the_random_query_var_are_not_handled()}.
	 *
	 * @return array<non-falsy-string, array{ 0: array<non-falsy-string, non-falsy-string> }>
	 */
	public static function data_requests_without_the_random_query_var(): array {
		return array(
			'no query vars'    => array( array() ),
			'other query vars' => array( array( 'post_type' => 'post' ) ),
		);
	}

	/**
	 * Ensure random content requests are not handled when the feature is disabled.
	 *
	 * @ticket 64498
	 */
	public function test_random_content_requests_are_not_handled_when_disabled(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$handled = $this->handle_random( array( 'random' => '' ) );

		$this->assertFalse( $handled, 'Random content requests should not be handled when the feature is disabled.' );
		$this->assertNull( $GLOBALS['wp_the_query']->request, 'The main query should be left for WP::main() to run.' );
		$this->assertNull( $this->redirect_location, 'The request should not redirect.' );
	}

	/**
	 * Ensure the pool of randomly ordered posts is served from the cache.
	 *
	 * @ticket 64498
	 * @ticket 66654
	 */
	public function test_random_post_pool_is_served_from_the_cache(): void {
		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		for ( $i = 0; $i < 2; $i++ ) {
			$this->handle_random( array( 'random' => '' ) );
			$this->assertInstanceOf( WP_Post::class, $GLOBALS['wp_the_query']->post, "Request {$i} should select a post." );
		}

		$random_queries = preg_grep( '/RAND\(\)/', array_column( $queries->get_args(), 0 ) );

		$this->assertCount( 1, $random_queries, 'The random query should only be made by the first request.' );
	}

	/**
	 * Ensure each request selects a post from the cached pool at random.
	 *
	 * @ticket 66654
	 */
	public function test_posts_are_selected_at_random_from_the_cached_pool(): void {
		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$selected = array();
		for ( $i = 0; $i < 30; $i++ ) {
			$this->handle_random( array( 'random' => '' ) );
			$selected[ $GLOBALS['wp_the_query']->post->ID ] = true;
		}

		$random_queries = preg_grep( '/RAND\(\)/', array_column( $queries->get_args(), 0 ) );

		$this->assertCount( 1, $random_queries, 'The pool should be selected by a single random query.' );
		// With 5 posts the probability of a single post being selected 30 times in a row is about 1 in 10^20.
		$this->assertGreaterThan( 1, count( $selected ), 'Requests using the cached pool should select different posts.' );
	}

	/**
	 * Ensure the cached pool is invalidated when a post is published.
	 *
	 * @ticket 66654
	 */
	public function test_cached_pool_is_invalidated_when_a_post_is_published(): void {
		$this->handle_random( array( 'random' => '' ) );

		self::factory()->post->create();

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );

		$random_queries = preg_grep( '/RAND\(\)/', array_column( $queries->get_args(), 0 ) );

		$this->assertCount( 1, $random_queries, 'The random query should be made again after a post is published.' );
	}

	/**
	 * Ensure the cached pool for a taxonomy archive is invalidated when the terms of a post change.
	 *
	 * Changing the terms of a post updates the last changed time of terms, but not posts.
	 *
	 * @ticket 66654
	 */
	public function test_cached_taxonomy_archive_pool_is_invalidated_when_post_terms_change(): void {
		$category = self::factory()->category->create( array( 'slug' => 'pool' ) );
		$post_ids = self::factory()->post->create_many( 2 );
		wp_set_object_terms( $post_ids[0], array( $category ), 'category' );

		$this->handle_random(
			array(
				'random'        => '',
				'category_name' => 'pool',
			)
		);

		$this->assertSame( $post_ids[0], $GLOBALS['wp_the_query']->post->ID, 'The only post in the category should be selected.' );

		wp_set_object_terms( $post_ids[0], array(), 'category' );
		wp_set_object_terms( $post_ids[1], array( $category ), 'category' );

		$this->handle_random(
			array(
				'random'        => '',
				'category_name' => 'pool',
			)
		);

		$this->assertSame( $post_ids[1], $GLOBALS['wp_the_query']->post->ID, 'The post added to the category should be selected after the terms change.' );
	}

	/**
	 * Ensure the cached pool for an archive not filtered by taxonomy is kept when terms change.
	 *
	 * @ticket 66654
	 */
	public function test_cached_pool_without_taxonomy_query_is_kept_when_terms_change(): void {
		$this->handle_random( array( 'random' => '' ) );

		wp_set_object_terms( $GLOBALS['wp_the_query']->post->ID, array( self::factory()->category->create() ), 'category' );

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );

		$random_queries = preg_grep( '/RAND\(\)/', array_column( $queries->get_args(), 0 ) );

		$this->assertCount( 0, $random_queries, 'The cached pool should be used after terms change.' );
	}

	/**
	 * Ensure random content requests for different archives use different pools.
	 *
	 * @ticket 66654
	 */
	public function test_cached_pool_is_not_shared_between_archives(): void {
		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );
		// The shared post fixtures are in the default category.
		$this->handle_random(
			array(
				'random'        => '',
				'category_name' => 'uncategorized',
			)
		);

		$random_queries = preg_grep( '/RAND\(\)/', array_column( $queries->get_args(), 0 ) );

		$this->assertCount( 2, $random_queries, 'Each archive should have its own pool.' );
	}

	/**
	 * Ensure only the selected post is loaded from the pool.
	 *
	 * @ticket 66654
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_only_the_selected_post_is_loaded_from_the_pool(): void {
		global $wpdb;

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );

		$executed      = array_column( $queries->get_args(), 0 );
		$random_query  = array_values( preg_grep( '/RAND\(\)/', $executed ) );
		$posts_queries = array_values( preg_grep( "/^\s*SELECT\s+(\*|{$wpdb->posts}\.\*)\s+FROM\s+{$wpdb->posts}\s+WHERE\s+ID/", $executed ) );

		$this->assertCount( 1, $random_query, 'The pool should be selected by a single random query.' );
		$this->assertMatchesRegularExpression( "/^\s*SELECT\s+{$wpdb->posts}\.ID\s+FROM/", $random_query[0], 'The pool query should select post IDs only.' );
		$this->assertMatchesRegularExpression( '/LIMIT 0, 50\s*$/', $random_query[0], 'The pool query should select up to 50 posts.' );
		$this->assertCount( 1, $posts_queries, "Only the selected post should be loaded:\n" . implode( "\n", $executed ) );
		$this->assertStringContainsString( (string) $GLOBALS['wp_the_query']->post->ID, $posts_queries[0], 'The selected post should be loaded.' );
	}

	/**
	 * Ensure the main query runs unchanged for requests with the random query variable
	 * that are not random content requests.
	 *
	 * The main query must not be run again by WP::main() and any 404 is left to
	 * WP::handle_404().
	 *
	 * @ticket 64498
	 *
	 * @dataProvider data_requests_that_are_not_random_content_requests
	 *
	 * @param array<string, string> $query_vars     Query variables for the request.
	 * @param string                $request_method HTTP request method.
	 */
	public function test_main_query_runs_unchanged_for_requests_that_are_not_random_content_requests( array $query_vars, string $request_method ): void {
		$_SERVER['REQUEST_METHOD'] = $request_method;

		$handled = $this->handle_random( $query_vars );

		$request = $GLOBALS['wp_the_query']->request;

		$this->assertTrue( $handled, 'The main query should be reported as run.' );
		$this->assertIsString( $request, 'The main query was not run.' );
		$this->assertStringNotContainsString( 'RAND()', $request, 'The main query should not be randomized.' );
		$this->assertNull( $this->redirect_location, 'The request should not redirect.' );
		$this->assertFalse( is_404(), 'The request should not be set to a 404.' );
	}

	/**
	 * Data provider for {@see self::test_main_query_runs_unchanged_for_requests_that_are_not_random_content_requests()}.
	 *
	 * @return array<non-falsy-string, array{ 0: array<non-falsy-string, string>, 1: non-falsy-string }>
	 */
	public static function data_requests_that_are_not_random_content_requests(): array {
		return array(
			'post request'                 => array( array( 'random' => '' ), 'POST' ),
			'non-randomable without posts' => array(
				array(
					'random'    => '',
					'post_type' => 'page',
				),
				'GET',
			),
		);
	}

	/**
	 * Ensure the request is redirected to the selected post's permalink on the
	 * `template_redirect` action, ending the request.
	 *
	 * @ticket 64498
	 * @ticket 66625
	 *
	 * @dataProvider data_permalinks
	 *
	 * @param string $permalink Permalink of the selected post.
	 */
	public function test_request_is_redirected_to_the_permalink_of_the_selected_post( string $permalink ): void {
		add_filter(
			'post_link',
			static function () use ( $permalink ): string {
				return $permalink;
			}
		);

		$handled = $this->handle_random( array( 'random' => '' ) );

		$this->assertTrue( $handled, 'The main query should be reported as run.' );
		$this->assertNull( $this->redirect_location, 'The request should not redirect before the template_redirect action.' );

		try {
			do_action( 'template_redirect' );
			$this->fail( 'The request should end with the redirect.' );
		} catch ( WP_Tests_Exception $e ) {
			$this->assertSame( self::REDIRECT_EXCEPTION_MESSAGE, $e->getMessage(), 'An unexpected exception was thrown.' );
		}

		$this->assertSame( $permalink, $this->redirect_location, 'The request should redirect to the permalink of the selected post.' );
	}

	/**
	 * Data provider for {@see self::test_request_is_redirected_to_the_permalink_of_the_selected_post()}.
	 *
	 * @return array<non-falsy-string, array{ 0: non-falsy-string }>
	 */
	public static function data_permalinks(): array {
		return array(
			'permalink on this site'    => array( 'http://' . WP_TESTS_DOMAIN . '/post/' ),
			'permalink on another site' => array( 'https://elsewhere.example/post/' ),
		);
	}
}
