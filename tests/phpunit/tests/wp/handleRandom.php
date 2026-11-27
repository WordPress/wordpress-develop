<?php

/**
 * @group wp
 * @group query
 *
 * @covers WP::handle_random
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
	 * Counts the queries looking up random post IDs recorded by a `query` filter.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param MockAction $queries Mock recording the `query` filter.
	 * @return int Number of queries looking up random post IDs.
	 */
	protected function count_sample_queries( MockAction $queries ): int {
		global $wpdb;

		$sample_queries = preg_grep( "/{$wpdb->posts}\.ID IN \(/", array_column( $queries->get_args(), 0 ) );

		return is_array( $sample_queries ) ? count( $sample_queries ) : 0;
	}

	/**
	 * Counts the queries selecting a pool of randomly ordered posts recorded by a
	 * `query` filter.
	 *
	 * @param MockAction $queries Mock recording the `query` filter.
	 * @return int Number of queries selecting a pool of posts.
	 */
	protected function count_pool_queries( MockAction $queries ): int {
		$pool_queries = preg_grep( '/ORDER BY RAND\(\) LIMIT 1000$/', array_column( $queries->get_args(), 0 ) );

		return is_array( $pool_queries ) ? count( $pool_queries ) : 0;
	}

	/**
	 * Makes eligible posts too sparse among the post IDs to be found by looking up
	 * random post IDs, so a pool of posts is selected.
	 *
	 * With random post IDs of up to 4 billion, the probability of one of them being a
	 * post is less than 1 in a million.
	 */
	protected function make_posts_sparse(): void {
		wp_cache_set( 'max_post_id', 4000000000, 'random-content' );
	}

	/**
	 * Ensure posts selected by looking up random post IDs are selected again for each
	 * request, without selecting or caching a pool of posts.
	 *
	 * @ticket 66654
	 */
	public function test_random_ids_are_looked_up_for_each_request(): void {
		$post_ids = get_posts(
			array(
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ) use ( $post_ids ): void {
				$query->set( 'random_sample_ids', $post_ids );
			},
			30
		);

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );
		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 2, $this->count_sample_queries( $queries ), 'The random post IDs should be looked up for each request.' );
		$this->assertSame( 0, $this->count_pool_queries( $queries ), 'A pool of posts should not be selected when a random post ID is eligible.' );
		$this->assertContains( $GLOBALS['wp_the_query']->post->ID, $post_ids, 'One of the posts looked up should be selected.' );
	}

	/**
	 * Ensure the pool of posts is cached between requests.
	 *
	 * @ticket 66654
	 */
	public function test_pool_is_cached_between_requests(): void {
		$this->make_posts_sparse();

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 3, $this->count_sample_queries( $queries ), 'Random post IDs should be looked up three times by the first request.' );
		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'The pool should be selected by the first request.' );

		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 3, $this->count_sample_queries( $queries ), 'Random post IDs should not be looked up while the pool is cached.' );
		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'The pool should be reused by the following request.' );
		$this->assertInstanceOf( WP_Post::class, $GLOBALS['wp_the_query']->post, 'The request served from the cached pool should select a post.' );
	}

	/**
	 * Ensure different posts are selected from the cached pool.
	 *
	 * @ticket 66654
	 */
	public function test_selection_from_the_cached_pool_varies_between_requests(): void {
		$this->make_posts_sparse();
		$this->handle_random( array( 'random' => '' ) );

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$seen = array();
		for ( $i = 0; $i < 30; $i++ ) {
			$this->handle_random( array( 'random' => '' ) );
			$seen[ $GLOBALS['wp_the_query']->post->ID ] = true;
		}

		$this->assertSame( 0, $this->count_sample_queries( $queries ) + $this->count_pool_queries( $queries ), 'All requests should select from the cached pool.' );
		// With 5 posts the probability of a single post being selected 30 times in a row is 1 in 5^29.
		$this->assertGreaterThan( 1, count( $seen ), 'Requests served from the cached pool should select different posts.' );
	}

	/**
	 * Ensure each archive has its own pool.
	 *
	 * @ticket 66654
	 */
	public function test_archives_do_not_share_a_pool(): void {
		$author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create( array( 'post_author' => $author ) );

		$this->make_posts_sparse();
		$this->handle_random( array( 'random' => '' ) );

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random(
			array(
				'random' => '',
				'author' => (string) $author,
			)
		);

		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'Each archive should select its own pool.' );
		$this->assertSame( $post_id, $GLOBALS['wp_the_query']->post->ID, 'The author archive should not select from the pool of the blog home.' );
	}

	/**
	 * Ensure posts that are no longer eligible are not selected from the cached pool.
	 *
	 * @ticket 66654
	 *
	 * @dataProvider data_changes_making_a_post_ineligible
	 *
	 * @param callable $change Callback making the post with the given ID ineligible.
	 *
	 * @phpstan-param callable( int ): mixed $change
	 */
	public function test_posts_that_are_no_longer_eligible_are_not_selected_from_the_cached_pool( callable $change ): void {
		$this->make_posts_sparse();
		$this->handle_random( array( 'random' => '' ) );

		$post_ids = get_posts(
			array(
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
		$eligible = array_pop( $post_ids );

		foreach ( $post_ids as $post_id ) {
			$change( $post_id );
		}

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		for ( $i = 0; $i < 10; $i++ ) {
			$this->handle_random( array( 'random' => '' ) );

			$this->assertSame( $eligible, $GLOBALS['wp_the_query']->post->ID, 'Only the post that is still eligible should be selected.' );
		}

		$this->assertSame( 0, $this->count_sample_queries( $queries ) + $this->count_pool_queries( $queries ), 'The cached pool should be reused while it contains an eligible post.' );
	}

	/**
	 * Data provider for {@see self::test_posts_that_are_no_longer_eligible_are_not_selected_from_the_cached_pool()}.
	 *
	 * @return array<non-falsy-string, array{ 0: callable( int ): mixed }>
	 */
	public static function data_changes_making_a_post_ineligible(): array {
		return array(
			'trashed'            => array( 'wp_trash_post' ),
			'deleted'            => array(
				static function ( int $post_id ) {
					return wp_delete_post( $post_id, true );
				},
			),
			'unpublished'        => array(
				static function ( int $post_id ) {
					return wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						)
					);
				},
			),
			'password protected' => array(
				static function ( int $post_id ) {
					return wp_update_post(
						array(
							'ID'            => $post_id,
							'post_password' => 'password',
						)
					);
				},
			),
			'not randomable'     => array(
				static function ( int $post_id ) {
					return set_post_type( $post_id, 'page' );
				},
			),
		);
	}

	/**
	 * Ensure a new pool is selected once no post in the cached pool is eligible.
	 *
	 * @ticket 66654
	 */
	public function test_new_pool_is_selected_when_no_post_in_the_cached_pool_is_eligible(): void {
		$this->make_posts_sparse();
		$this->handle_random( array( 'random' => '' ) );

		foreach ( get_posts(
			array(
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		) as $post_id ) {
			wp_trash_post( $post_id );
		}

		$post_id = self::factory()->post->create();

		$this->handle_random( array( 'random' => '' ) );

		$this->assertFalse( $GLOBALS['wp_the_query']->is_404(), 'A stale pool should not be handled as a 404.' );
		$this->assertSame( $post_id, $GLOBALS['wp_the_query']->post->ID, 'A new pool should be selected when the cached pool is stale.' );
	}

	/**
	 * Gets the pool queries recorded by a `query` filter.
	 *
	 * @param MockAction $queries Mock recording the `query` filter.
	 * @return string[] Queries selecting a pool of posts.
	 */
	protected function get_pool_queries( MockAction $queries ): array {
		$pool_queries = preg_grep( '/ORDER BY RAND\(\) LIMIT 1000$/', array_column( $queries->get_args(), 0 ) );

		return is_array( $pool_queries ) ? array_values( $pool_queries ) : array();
	}

	/**
	 * Gets the cache key of the pool selected by the last random content request.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return string Cache key in the `random-content` group.
	 */
	protected function get_pool_cache_key(): string {
		global $wpdb;

		$query = $GLOBALS['wp_the_query'];

		return 'pool:' . md5(
			str_replace(
				_wp_random_content_sample_clause( $query->get( 'random_sample_ids' ) ),
				'',
				$wpdb->remove_placeholder_escape( $query->request )
			)
		);
	}

	/**
	 * Makes the pool selected by the last random content request more than 10 minutes old.
	 *
	 * @return string Cache key of the pool.
	 */
	protected function expire_pool(): string {
		$cache_key = $this->get_pool_cache_key();
		$pool      = wp_cache_get( $cache_key, 'random-content' );

		$this->assertIsArray( $pool, 'The pool should be cached.' );

		$pool['expires'] = time() - 1;
		wp_cache_set( $cache_key, $pool, 'random-content' );

		return $cache_key;
	}

	/**
	 * Password protects every post.
	 */
	protected function password_protect_posts(): void {
		foreach ( get_posts(
			array(
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		) as $post_id ) {
			wp_update_post(
				array(
					'ID'            => $post_id,
					'post_password' => 'secret',
				)
			);
		}
	}

	/**
	 * Ensure the pool is selected without filtering by the post password, which is not
	 * indexed, and password protected posts are not selected from it.
	 *
	 * @ticket 66654
	 */
	public function test_pool_is_selected_without_the_password_and_skips_protected_posts(): void {
		$this->password_protect_posts();
		$post_id = self::factory()->post->create();

		$this->make_posts_sparse();

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );

		$pool_queries = $this->get_pool_queries( $queries );

		$this->assertCount( 1, $pool_queries, 'A pool of posts should be selected.' );
		$this->assertStringNotContainsString( 'post_password', $pool_queries[0], 'The pool should be selected without filtering by the post password.' );
		$this->assertSame( $post_id, $GLOBALS['wp_the_query']->post->ID, 'Password protected posts should not be selected from the pool.' );

		// The password protected posts in the cached pool are skipped too.
		$this->handle_random( array( 'random' => '' ) );

		$this->assertCount( 1, $this->get_pool_queries( $queries ), 'The pool should be reused by the following request.' );
		$this->assertSame( $post_id, $GLOBALS['wp_the_query']->post->ID, 'Password protected posts should not be selected from the cached pool.' );
	}

	/**
	 * Ensure an archive whose posts are all password protected is cached as an archive
	 * without eligible posts.
	 *
	 * @ticket 66654
	 */
	public function test_pool_of_password_protected_posts_is_cached_as_empty(): void {
		$this->password_protect_posts();
		$this->make_posts_sparse();

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'The pool should be selected by the first request.' );
		$this->assertTrue( $GLOBALS['wp_the_query']->is_404(), 'The request should be handled as a 404.' );

		$sample_queries = $this->count_sample_queries( $queries );

		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( $sample_queries, $this->count_sample_queries( $queries ), 'Random post IDs should not be looked up while the empty pool is cached.' );
		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'The archive should not be queried again.' );
		$this->assertTrue( $GLOBALS['wp_the_query']->is_404(), 'The following request should be handled as a 404.' );
	}

	/**
	 * Ensure a pool that is more than 10 minutes old is selected again.
	 *
	 * @ticket 66654
	 */
	public function test_expired_pool_is_selected_again(): void {
		$this->make_posts_sparse();

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );
		$cache_key = $this->expire_pool();
		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 2, $this->count_pool_queries( $queries ), 'The expired pool should be selected again.' );
		$this->assertInstanceOf( WP_Post::class, $GLOBALS['wp_the_query']->post, 'The request should select a post.' );

		$pool = wp_cache_get( $cache_key, 'random-content' );

		$this->assertGreaterThan( time(), $pool['expires'], 'The new pool should be cached.' );
		$this->assertFalse( wp_cache_get( $cache_key . ':lock', 'random-content' ), 'The lock should be released once the pool is selected.' );

		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 2, $this->count_pool_queries( $queries ), 'The new pool should be reused by the following request.' );
	}

	/**
	 * Ensure requests select from the expired pool while another request is selecting
	 * the next pool, rather than each reading every eligible post.
	 *
	 * @ticket 66654
	 */
	public function test_expired_pool_is_used_while_another_request_selects_the_pool(): void {
		$this->make_posts_sparse();

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );
		$cache_key = $this->expire_pool();

		// Another request is selecting the pool.
		wp_cache_add( $cache_key . ':lock', 1, 'random-content' );

		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'The pool should not be selected while another request is selecting it.' );
		$this->assertFalse( $GLOBALS['wp_the_query']->is_404(), 'The request should not be handled as a 404.' );
		$this->assertInstanceOf( WP_Post::class, $GLOBALS['wp_the_query']->post, 'A post should be selected from the expired pool.' );
		$this->assertSame( 1, wp_cache_get( $cache_key . ':lock', 'random-content' ), 'The lock of the other request should not be released.' );
	}

	/**
	 * Ensure a request selects a pool while another request is selecting it if there
	 * is no earlier pool to select from.
	 *
	 * @ticket 66654
	 */
	public function test_pool_is_selected_when_locked_without_an_earlier_pool(): void {
		$this->make_posts_sparse();
		$this->handle_random( array( 'random' => '' ) );

		$cache_key = $this->get_pool_cache_key();

		wp_cache_delete( $cache_key, 'random-content' );
		wp_cache_add( $cache_key . ':lock', 1, 'random-content' );

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );

		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'A pool should be selected.' );
		$this->assertInstanceOf( WP_Post::class, $GLOBALS['wp_the_query']->post, 'The request should select a post.' );
		$this->assertSame( 1, wp_cache_get( $cache_key . ':lock', 'random-content' ), 'The lock of the other request should not be released.' );
	}

	/**
	 * Ensure the empty pool of an archive without eligible posts is cached, so the
	 * archive is not queried for every request.
	 *
	 * @ticket 66654
	 */
	public function test_empty_pool_is_cached_between_requests(): void {
		$author     = self::factory()->user->create( array( 'role' => 'author' ) );
		$query_vars = array(
			'random' => '',
			'author' => (string) $author,
		);

		$this->make_posts_sparse();

		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( $query_vars );

		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'The pool should be selected by the first request.' );
		$this->assertTrue( $GLOBALS['wp_the_query']->is_404(), 'The request for an empty archive should be handled as a 404.' );

		$sample_queries = $this->count_sample_queries( $queries );

		$this->handle_random( $query_vars );

		$this->assertSame( $sample_queries, $this->count_sample_queries( $queries ), 'Random post IDs should not be looked up while the empty pool is cached.' );
		$this->assertSame( 1, $this->count_pool_queries( $queries ), 'The empty pool should be reused by the following request.' );
		$this->assertTrue( $GLOBALS['wp_the_query']->is_404(), 'The request served from the empty pool should be handled as a 404.' );

		// The empty pool does not prevent other archives selecting posts.
		$this->handle_random( array( 'random' => '' ) );

		$this->assertInstanceOf( WP_Post::class, $GLOBALS['wp_the_query']->post, 'Another archive should select a post.' );
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
