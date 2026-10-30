<?php

/**
 * @group wp
 * @group query
 *
 * @covers WP::handle_random
 */
class Tests_WP_HandleRandom extends WP_UnitTestCase {

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

		// Capture redirects and prevent `exit` from ending the test run.
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ) );
	}

	/**
	 * Captures the redirect and prevents it from being sent.
	 *
	 * @param string $location Redirect location.
	 * @return false
	 */
	public function filter_wp_redirect( string $location ) {
		$this->redirect_location = $location;

		return false;
	}

	/**
	 * Handles a random content request with a new main query.
	 *
	 * Unlike WP_UnitTestCase_Base::go_to(), the request is handled without flushing the
	 * cache or running the rest of WP::main().
	 *
	 * @param array<string, string> $query_vars Query variables for the request.
	 * @return bool Whether the main query was run for a random content request.
	 */
	protected function handle_random( array $query_vars ): bool {
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];

		$wp             = new WP();
		$wp->query_vars = $query_vars;

		return $wp->handle_random();
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
	 * Ensure random content queries are not served from the query cache.
	 *
	 * If the ID query were cached, the same post would be selected until the cache is
	 * invalidated.
	 *
	 * @ticket 64498
	 */
	public function test_random_content_queries_are_not_cached(): void {
		$queries = new MockAction();
		add_filter( 'query', array( $queries, 'filter' ) );

		$this->handle_random( array( 'random' => '' ) );
		$this->handle_random( array( 'random' => '' ) );

		$random_queries = preg_grep( '/RAND\(\)/', array_column( $queries->get_args(), 0 ) );

		$this->assertCount( 2, $random_queries, 'The random ID query should run for each request rather than reuse a cached result.' );
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
	 * Ensure the request continues with the selected post when it cannot be redirected to.
	 *
	 * @ticket 64498
	 *
	 * @dataProvider data_permalinks_that_cannot_be_redirected_to
	 *
	 * @param string $permalink Permalink of the selected post.
	 */
	public function test_request_continues_with_the_selected_post_when_it_is_not_redirected( string $permalink ): void {
		add_filter(
			'post_link',
			static function () use ( $permalink ): string {
				return $permalink;
			}
		);

		$handled = $this->handle_random( array( 'random' => '' ) );

		$this->assertTrue( $handled, 'The main query should be reported as run.' );
		$this->assertNull( $this->redirect_location, 'The request should not redirect.' );
		$this->assertInstanceOf( WP_Post::class, $GLOBALS['wp_the_query']->post, 'The selected post should remain in the main query.' );
		$this->assertFalse( is_404(), 'The request should not be set to a 404.' );
	}

	/**
	 * Data provider for {@see self::test_request_continues_with_the_selected_post_when_it_is_not_redirected()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public static function data_permalinks_that_cannot_be_redirected_to(): array {
		return array(
			'empty permalink'    => array( '' ),
			'off-site permalink' => array( 'https://elsewhere.example/post/' ),
		);
	}
}
