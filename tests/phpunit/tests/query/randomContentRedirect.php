<?php

/**
 * Tests for random content redirects.
 *
 * @group query
 * @group rewrite
 * @ticket 64498
 *
 * @covers ::wp_random_content_redirect
 * @covers WP::handle_random
 * @covers ::wp_random_content_pre_get_posts
 * @covers ::wp_random_content_posts_orderby
 * @covers ::wp_random_content_post_limits
 * @covers ::wp_is_random_content_query
 * @covers ::wp_get_random_content_post_types
 * @covers ::wp_random_content_flush_rewrite_rules_for_post
 * @covers ::wp_random_content_headers
 * @covers ::wp_random_content_split_the_query
 * @covers ::wp_is_random_content_redirect_enabled
 */
class Tests_Query_RandomContentRedirect extends WP_UnitTestCase {

	/**
	 * Published post in the "arts" category, tagged "Impressionism".
	 *
	 * @var int
	 */
	protected static $arts_post;

	/**
	 * Published post in the "sport" category, written by the editor.
	 *
	 * @var int
	 */
	protected static $sport_post;

	/**
	 * Term ID of the "arts" category.
	 *
	 * @var int
	 */
	protected static $arts_category;

	/**
	 * Term ID of the "sport" category.
	 *
	 * @var int
	 */
	protected static $sport_category;

	/**
	 * Term ID of the empty "empty" category.
	 *
	 * @var int
	 */
	protected static $empty_category;

	/**
	 * User ID of the editor.
	 *
	 * @var int
	 */
	protected static $editor;

	/**
	 * User ID of an administrator.
	 *
	 * @var int
	 */
	protected static $admin;

	/**
	 * Redirect location captured from the `wp_redirect` filter.
	 *
	 * @var string|null
	 */
	protected $redirect_location;

	/**
	 * Redirect status captured from the `wp_redirect` filter.
	 *
	 * @var int|null
	 */
	protected $redirect_status;

	/**
	 * Database queries made between the request being parsed and the redirect.
	 *
	 * @var string[]
	 */
	protected $queries = array();

	/**
	 * Whether database queries are being recorded.
	 *
	 * @var bool
	 */
	protected $recording_queries = false;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$editor = $factory->user->create(
			array(
				'role'          => 'editor',
				'user_nicename' => 'random-editor',
			)
		);
		self::$admin  = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$arts_category  = $factory->category->create( array( 'slug' => 'arts' ) );
		self::$sport_category = $factory->category->create( array( 'slug' => 'sport' ) );
		self::$empty_category = $factory->category->create( array( 'slug' => 'empty' ) );

		self::$arts_post = $factory->post->create(
			array(
				'post_title'    => 'Cassatt',
				'post_name'     => 'cassatt',
				'post_date'     => '1844-05-22 00:00:00',
				'post_author'   => self::$admin,
				'post_category' => array( self::$arts_category ),
				'tags_input'    => array( 'Impressionism' ),
			)
		);

		self::$sport_post = $factory->post->create(
			array(
				'post_title'    => 'Baseball',
				'post_name'     => 'baseball',
				'post_date'     => '2005-10-27 04:01:00',
				'post_author'   => self::$editor,
				'post_category' => array( self::$sport_category ),
			)
		);
	}

	public function set_up(): void {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
		// Register the taxonomy rewrite rules for the permalink structure.
		create_initial_taxonomies();
		flush_rewrite_rules();

		$this->redirect_location = null;
		$this->redirect_status   = null;
		$this->queries           = array();
		$this->recording_queries = false;

		// Capture redirects and prevent `exit` from ending the test run.
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ), 10, 2 );
	}

	/**
	 * Captures the redirect and prevents it from being sent.
	 *
	 * @param string $location Redirect location.
	 * @param int    $status   Redirect status.
	 * @return false
	 */
	public function filter_wp_redirect( $location, $status ) {
		$this->redirect_location = $location;
		$this->redirect_status   = $status;
		$this->recording_queries = false;

		return false;
	}

	/**
	 * Starts recording database queries once the request has been parsed.
	 */
	public function start_recording_queries(): void {
		$this->recording_queries = true;
	}

	/**
	 * Records database queries while recording is active.
	 *
	 * @param string $query Database query.
	 * @return string Unmodified database query.
	 */
	public function record_query( $query ) {
		if ( $this->recording_queries ) {
			$this->queries[] = $query;
		}

		return $query;
	}

	/**
	 * Requests a URL and records the queries made after the request is parsed.
	 *
	 * @param string $url URL to request.
	 */
	protected function go_to_and_record_queries( $url ): void {
		add_action( 'parse_request', array( $this, 'start_recording_queries' ), PHP_INT_MAX );
		add_filter( 'query', array( $this, 'record_query' ) );

		$this->go_to( $url );

		$this->recording_queries = false;
	}

	/**
	 * Gets the main query.
	 *
	 * The global is read when called as WP_UnitTestCase_Base::go_to() replaces it.
	 *
	 * @global WP_Query $wp_query WordPress Query object.
	 *
	 * @return WP_Query The main query.
	 */
	protected function get_main_query(): WP_Query {
		global $wp_query;

		return $wp_query;
	}

	/**
	 * Requests a URL and gets the headers passed through the `wp_headers` filter.
	 *
	 * @param string $url URL to request.
	 * @return array<mixed> Headers to be sent.
	 */
	protected function go_to_and_get_headers( string $url ): array {
		$headers = new MockAction();
		add_filter( 'wp_headers', array( $headers, 'filter' ), 1000 );

		$this->go_to( $url );

		$sent = $headers->get_args()[0][0] ?? null;
		$this->assertIsArray( $sent, 'The wp_headers filter did not receive an array of headers.' );

		return $sent;
	}

	/**
	 * Creates a published post.
	 *
	 * @param array<string, mixed> $args Optional. Post arguments.
	 * @return int Post ID.
	 */
	protected function create_post( array $args = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_status' => 'publish',
					'post_date'   => '2016-12-04 00:00:00',
				),
				$args
			)
		);
	}

	/**
	 * Asserts that the request redirected to one of the given posts.
	 *
	 * @param int[]  $post_ids Allowed post IDs.
	 * @param string $message  Message to display on failure.

	 *
	 * @phpstan-assert !null $this->redirect_location
	 */
	protected function assertRedirectedToOneOf( array $post_ids, string $message ): void {
		$this->assertNotNull( $this->redirect_location, "{$message} The request did not redirect." );

		$permalinks = array_map( 'get_permalink', $post_ids );
		$this->assertContains( $this->redirect_location, $permalinks, "{$message} The request redirected to an unexpected location." );
	}

	/**
	 * Asserts that the request did not redirect.
	 *
	 * @param string $message Message to display on failure.
	 */
	protected function assertNotRedirected( string $message ): void {
		$this->assertNull( $this->redirect_location, $message );
	}

	/**
	 * Asserts that the query does not select posts in a random order.
	 *
	 * @param WP_Query $query   The query to check.
	 * @param string   $message Message to display on failure.
	 */
	protected function assertQueryIsNotRandomized( WP_Query $query, string $message ): void {
		$this->assertIsString( $query->request, "{$message} The query was not run." );
		$this->assertStringNotContainsString( 'RAND()', $query->request, $message );
	}

	/*
	 * Redirect targets.
	 */

	/**
	 * @dataProvider data_random_urls
	 *
	 * @param string $path Random content request path.
	 */
	public function test_random_url_redirects_to_a_published_post( $path ): void {
		$this->go_to( home_url( $path ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The random URL should redirect to a published post.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_random_urls(): array {
		return array(
			'pretty permalink'         => array( '/random/' ),
			'pretty without slash'     => array( '/random' ),
			'query string flag'        => array( '/?random' ),
			'query string with value'  => array( '/?random=1' ),
			'query string array value' => array( '/?random[]=1' ),
		);
	}

	public function test_random_redirect_uses_a_temporary_redirect(): void {
		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( 302, $this->redirect_status, 'Random redirects must not be permanent.' );
	}

	public function test_random_redirect_location_is_the_permalink(): void {
		$this->go_to( home_url( '/random/category/arts/' ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'The redirect location should be the permalink of the selected post.' );
	}

	public function test_random_query_string_redirects_with_plain_permalinks(): void {
		$this->set_permalink_structure( '' );

		$this->go_to( home_url( '/?random' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The random query string should redirect with plain permalinks.' );
		$this->assertStringContainsString( '?p=', $this->redirect_location, 'The redirect should use the plain permalink.' );
	}

	public function test_random_redirect_with_pathinfo_permalinks(): void {
		$this->set_permalink_structure( '/index.php/%postname%/' );

		$this->go_to( home_url( '/index.php/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The random URL should redirect with PATHINFO permalinks.' );
	}

	public function test_random_category_url_only_selects_posts_in_the_category(): void {
		$this->go_to( home_url( '/random/category/sport/' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'The random category URL should only select posts in the category.' );
	}

	public function test_random_child_category_url_selects_posts_in_the_child_category(): void {
		$child   = self::factory()->category->create(
			array(
				'slug'   => 'local',
				'parent' => self::$arts_category,
			)
		);
		$post_id = $this->create_post( array( 'post_category' => array( $child ) ) );

		$this->go_to( home_url( '/random/category/arts/local/' ) );

		$this->assertSame( get_permalink( $post_id ), $this->redirect_location, 'The random child category URL should select posts in the child category.' );
	}

	public function test_random_category_url_includes_posts_in_child_categories(): void {
		$child      = self::factory()->category->create(
			array(
				'slug'   => 'politics',
				'parent' => self::$sport_category,
			)
		);
		$child_post = $this->create_post( array( 'post_category' => array( $child ) ) );

		$this->go_to( home_url( '/random/category/sport/' ) );

		$this->assertRedirectedToOneOf( array( self::$sport_post, $child_post ), 'Category archives include child categories.' );
	}

	public function test_random_cat_query_string_only_selects_posts_in_the_category(): void {
		$this->go_to( add_query_arg( 'cat', self::$arts_category, home_url( '/?random' ) ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'The random cat query string should only select posts in the category.' );
	}

	public function test_random_tag_url_only_selects_posts_with_the_tag(): void {
		$this->go_to( home_url( '/random/tag/impressionism/' ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'The random tag URL should only select posts with the tag.' );
	}

	public function test_random_combined_taxonomy_query_selects_posts_matching_all_terms(): void {
		$impressionist_sport = $this->create_post(
			array(
				'post_title'    => 'Caillebotte',
				'post_category' => array( self::$sport_category ),
				'tags_input'    => array( 'Impressionism' ),
			)
		);

		$this->go_to( home_url( '/?random&category_name=sport&tag=impressionism' ) );

		$this->assertSame( get_permalink( $impressionist_sport ), $this->redirect_location, 'The combined taxonomy query should only select posts matching all terms.' );
	}

	public function test_random_author_url_only_selects_posts_by_the_author(): void {
		$this->go_to( home_url( '/random/author/random-editor/' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'The random author URL should only select posts by the author.' );
	}

	public function test_random_date_archive_only_selects_posts_from_the_date(): void {
		$this->go_to( home_url( '/1844/?random' ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'The random date archive should only select posts from the date.' );
	}

	public function test_random_search_only_selects_matching_posts(): void {
		$this->go_to( home_url( '/?s=Baseball&random' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'The random search should only select matching posts.' );
	}

	public function test_random_post_type_archive_url_only_selects_posts_of_the_type(): void {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => 'books',
			)
		);
		flush_rewrite_rules();

		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/random/books/' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location, 'The random post type archive URL should only select posts of the type.' );
	}

	public function test_random_post_type_query_string_selects_posts_of_the_type(): void {
		register_post_type( 'wptests_book', array( 'public' => true ) );

		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/?random&post_type=wptests_book' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location, 'The random post_type query string should only select posts of the type.' );
	}

	/*
	 * Randomable post types.
	 */

	public function test_no_redirect_for_pages(): void {
		$this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/?random&post_type=page' ) );

		$this->assertNotRedirected( 'Pages should not be randomable by default.' );
	}

	public function test_main_query_is_unchanged_for_pages(): void {
		$this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/?random&post_type=page' ) );

		$this->assertQueryIsNotRandomized( $this->get_main_query(), 'Requests for non-randomable post types should not be randomized.' );
	}

	public function test_pages_redirect_when_made_randomable(): void {
		add_filter( 'register_post_type_args', array( $this, 'filter_make_pages_randomable' ), 10, 2 );
		create_initial_post_types();

		$page = $this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/?random&post_type=page' ) );

		$this->assertSame( get_permalink( $page ), $this->redirect_location, 'Pages should redirect once they are made randomable.' );
	}

	/**
	 * Makes the page post type randomable.
	 *
	 * @param array<string, mixed> $args      Post type registration arguments.
	 * @param string               $post_type Post type name.
	 * @return array<string, mixed> Modified post type registration arguments.
	 */
	public function filter_make_pages_randomable( array $args, string $post_type ): array {
		if ( 'page' === $post_type ) {
			$args['randomable'] = true;
		}

		return $args;
	}

	public function test_no_redirect_for_post_type_that_is_not_randomable(): void {
		register_post_type(
			'wptests_book',
			array(
				'public'     => true,
				'randomable' => false,
			)
		);

		$this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/?random&post_type=wptests_book' ) );

		$this->assertNotRedirected( 'Post types registered as not randomable should not redirect.' );
	}

	public function test_no_redirect_for_post_type_archive_that_is_not_randomable(): void {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => 'books',
				'randomable'  => false,
			)
		);
		flush_rewrite_rules();

		$this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/books/?random' ) );

		$this->assertNotRedirected( 'Post type archives registered as not randomable should not redirect.' );
		$this->assertTrue( is_post_type_archive( 'wptests_book' ), 'The archive should be displayed.' );
	}

	public function test_random_redirect_with_multiple_post_types_only_selects_randomable_types(): void {
		register_post_type( 'wptests_book', array( 'public' => true ) );

		$this->create_post( array( 'post_type' => 'page' ) );
		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/?random&post_type[]=page&post_type[]=wptests_book' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location, 'Only randomable post types should be selected from multiple post types.' );
	}

	public function test_random_search_only_selects_randomable_post_types(): void {
		$this->create_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Baseball page',
			)
		);

		$this->go_to( home_url( '/?s=Baseball&random' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'Pages should not be selected from search results.' );
	}

	public function test_random_search_includes_randomable_custom_post_types(): void {
		register_post_type( 'wptests_book', array( 'public' => true ) );

		$book = $this->create_post(
			array(
				'post_type'  => 'wptests_book',
				'post_title' => 'Unique needle',
			)
		);

		$this->go_to( home_url( '/?s=needle&random' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location, 'Randomable custom post types should be selected from search results.' );
	}

	public function test_random_custom_taxonomy_archive_only_selects_randomable_post_types(): void {
		register_post_type( 'wptests_book', array( 'public' => true ) );
		register_taxonomy(
			'wptests_genre',
			array( 'page', 'wptests_book' ),
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);
		flush_rewrite_rules();

		$page = $this->create_post( array( 'post_type' => 'page' ) );
		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );
		wp_set_object_terms( $page, 'sci-fi', 'wptests_genre' );
		wp_set_object_terms( $book, 'sci-fi', 'wptests_genre' );

		$this->go_to( home_url( '/random/genre/sci-fi/' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location, 'Only randomable post types should be selected from a custom taxonomy archive.' );
	}

	public function test_no_redirect_for_taxonomy_used_only_by_pages(): void {
		register_taxonomy(
			'wptests_genre',
			'page',
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);
		flush_rewrite_rules();

		$page = $this->create_post( array( 'post_type' => 'page' ) );
		wp_set_object_terms( $page, 'sci-fi', 'wptests_genre' );

		$this->go_to( home_url( '/genre/sci-fi/?random' ) );

		$this->assertNotRedirected( 'Taxonomies used only by pages should not redirect.' );
		$this->assertTrue( is_tax( 'wptests_genre' ), 'The term archive should be displayed.' );
	}

	/**
	 * @dataProvider data_wp_get_random_content_post_types
	 *
	 * @param string   $url      URL to request.
	 * @param string[] $expected Expected post types.
	 */
	public function test_wp_get_random_content_post_types( $url, $expected ): void {
		register_post_type( 'wptests_book', array( 'public' => true ) );
		register_post_type(
			'wptests_hidden',
			array(
				'public'     => true,
				'randomable' => false,
			)
		);
		register_taxonomy( 'wptests_genre', array( 'post', 'page', 'wptests_book' ), array( 'public' => true ) );
		wp_set_object_terms( self::$arts_post, 'sci-fi', 'wptests_genre' );

		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
		$this->go_to( home_url( $url ) );
		remove_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$actual = wp_get_random_content_post_types( $this->get_main_query() );
		sort( $actual );

		$this->assertSame( $expected, $actual, 'The eligible post types do not match the expected post types for the request.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string[]}>
	 */
	public function data_wp_get_random_content_post_types(): array {
		return array(
			'home'                 => array( '/?random', array( 'post' ) ),
			'category'             => array( '/?random&category_name=arts', array( 'post' ) ),
			'search'               => array( '/?random&s=post', array( 'post', 'wptests_book' ) ),
			'any'                  => array( '/?random&post_type=any', array( 'post', 'wptests_book' ) ),
			'custom taxonomy'      => array( '/?random&wptests_genre=sci-fi', array( 'post', 'wptests_book' ) ),
			'randomable post type' => array( '/?random&post_type=wptests_book', array( 'wptests_book' ) ),
			'non-randomable type'  => array( '/?random&post_type=wptests_hidden', array() ),
			'page'                 => array( '/?random&post_type=page', array() ),
			'attachment'           => array( '/?random&post_type=attachment', array() ),
			'mixed post types'     => array( '/?random&post_type[]=page&post_type[]=post', array( 'post' ) ),
		);
	}

	public function test_random_url_defaults_to_posts(): void {
		$this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'Pages should not be selected without requesting them.' );
	}

	public function test_random_url_with_static_front_page_redirects_to_a_post(): void {
		$front = $this->create_post( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The random URL should redirect to a post when a static front page is set.' );
	}

	public function test_random_redirect_ignores_pagination(): void {
		$this->go_to( home_url( '/random/category/arts/?paged=5' ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'Pagination should not affect the random selection.' );
	}

	/*
	 * Eligible posts.
	 */

	/**
	 * @dataProvider data_ineligible_posts
	 *
	 * @param array<string, string> $args Post arguments.
	 */
	public function test_random_redirect_excludes_ineligible_posts( $args ): void {
		$category = self::factory()->category->create();
		$this->create_post( array_merge( array( 'post_category' => array( $category ) ), $args ) );

		wp_set_current_user( self::$admin );

		$this->go_to( add_query_arg( 'cat', $category, home_url( '/?random' ) ) );

		$this->assertNotRedirected( 'Only published posts without passwords may be selected, even for administrators.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array<string, string>}>
	 */
	public function data_ineligible_posts(): array {
		return array(
			'draft'              => array( array( 'post_status' => 'draft' ) ),
			'pending'            => array( array( 'post_status' => 'pending' ) ),
			'private'            => array( array( 'post_status' => 'private' ) ),
			'future'             => array(
				array(
					'post_status' => 'future',
					'post_date'   => gmdate( 'Y-m-d H:i:s', time() + 64498 ),
				),
			),
			'trash'              => array( array( 'post_status' => 'trash' ) ),
			'password protected' => array( array( 'post_password' => 'secret' ) ),
		);
	}

	/**
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_random_redirect_ignores_sticky_posts(): void {
		global $wpdb;

		$sticky = $this->create_post( array( 'post_title' => 'Sticky' ) );
		stick_post( $sticky );

		/*
		 * Make the "random" order deterministic so the sticky post is never selected by the query.
		 * The order is replaced after the random order is enforced on the 'posts_orderby' filter.
		 */
		$sticky_orderby = "{$wpdb->posts}.ID = {$sticky} ASC";
		add_filter(
			'posts_clauses',
			/**
			 * @param array<string, string> $clauses
			 * @return array<string, string>
			 */
			static function ( array $clauses, WP_Query $query ) use ( $sticky_orderby ): array {
				if ( $query->is_main_query() ) {
					$clauses['orderby'] = $sticky_orderby;
				}
				return $clauses;
			},
			10,
			2
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotNull( $this->redirect_location, 'The request with a sticky post did not redirect.' );
		$this->assertNotSame( get_permalink( $sticky ), $this->redirect_location, 'Sticky posts must not be prepended to the random selection.' );
	}

	/*
	 * Requests that are not random content requests.
	 */

	public function test_no_redirect_without_random_query_var(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertNotRedirected( 'The home page should not redirect without the random query variable.' );
		$this->assertQueryTrue( 'is_home', 'is_front_page' );
	}

	public function test_archives_are_unchanged_without_random_query_var(): void {
		$this->go_to( home_url( '/category/arts/' ) );

		$this->assertNotRedirected( 'Archives should not redirect without the random query variable.' );
		$this->assertQueryIsNotRandomized( $this->get_main_query(), 'The archive order should not be modified.' );
	}

	public function test_no_redirect_for_singular_post_with_random_query_var(): void {
		$this->go_to( add_query_arg( 'random', '', get_permalink( self::$arts_post ) ) );

		$this->assertNotRedirected( 'Singular requests should not redirect to themselves or another post.' );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertSame( self::$arts_post, get_queried_object_id(), 'The singular post should be queried despite the random query variable.' );
	}

	public function test_no_redirect_for_post_id_with_random_query_var(): void {
		$this->go_to( home_url( '/?random&p=' . self::$sport_post ) );

		$this->assertNotRedirected( 'Requests for a post ID should not redirect.' );
		$this->assertSame( self::$sport_post, get_queried_object_id(), 'The post ID should be queried despite the random query variable.' );
	}

	public function test_no_redirect_for_page_with_random_query_var(): void {
		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'about',
			)
		);

		$this->go_to( home_url( '/about/?random' ) );

		$this->assertNotRedirected( 'Requests for a page should not redirect.' );
		$this->assertSame( $page, get_queried_object_id(), 'The page should be queried despite the random query variable.' );
	}

	public function test_no_redirect_for_static_front_page_with_random_query_var_from_extra_vars(): void {
		$front = $this->create_post( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );

		$this->go_to( home_url( '/?page_id=' . $front . '&random' ) );

		$this->assertNotRedirected( 'The static front page should not redirect.' );
		$this->assertSame( $front, get_queried_object_id(), 'The static front page should be queried despite the random query variable.' );
	}

	/**
	 * @dataProvider data_feed_urls
	 *
	 * @param string $path Feed path.
	 */
	public function test_no_redirect_for_feeds( $path ): void {
		$this->go_to( home_url( $path ) );

		$this->assertNotRedirected( 'Feeds should not redirect.' );
		$this->assertTrue( is_feed(), 'The request should remain a feed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_feed_urls(): array {
		return array(
			'site feed'     => array( '/feed/?random' ),
			'category feed' => array( '/category/arts/feed/?random' ),
			'comments feed' => array( '/comments/feed/?random' ),
		);
	}

	public function test_no_redirect_for_embeds(): void {
		$this->go_to( home_url( '/cassatt/embed/?random' ) );

		$this->assertNotRedirected( 'Embeds should not redirect.' );
		$this->assertTrue( is_embed(), 'The embed should be displayed despite the random query variable.' );
	}

	public function test_no_redirect_for_nonexistent_category(): void {
		$this->go_to( home_url( '/random/category/does-not-exist/' ) );

		$this->assertNotRedirected( 'Nonexistent categories should not redirect.' );
		$this->assertTrue( is_404(), 'A random request for a nonexistent term should 404.' );
	}

	public function test_no_redirect_for_empty_category(): void {
		$this->go_to( home_url( '/random/category/empty/' ) );

		$this->assertNotRedirected( 'An empty archive has nothing to redirect to.' );
		$this->assertQueryTrue( 'is_404' );
	}

	public function test_no_redirect_when_no_posts_are_eligible(): void {
		add_filter(
			'posts_where',
			static function ( string $where, WP_Query $query ): string {
				return $query->is_main_query() ? "{$where} AND 1 = 0" : $where;
			},
			10,
			2
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'A random request without eligible posts has nothing to redirect to.' );
		$this->assertTrue( is_404(), 'A random request without eligible posts should 404.' );
	}

	/**
	 * @dataProvider data_non_redirecting_http_methods
	 *
	 * @param string $method HTTP request method.
	 */
	public function test_no_redirect_for_non_get_requests( $method ): void {
		$_SERVER['REQUEST_METHOD'] = $method;

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'Requests using methods other than GET or HEAD should not redirect.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_non_redirecting_http_methods(): array {
		return array(
			'POST'    => array( 'POST' ),
			'PUT'     => array( 'PUT' ),
			'DELETE'  => array( 'DELETE' ),
			'OPTIONS' => array( 'OPTIONS' ),
		);
	}

	public function test_no_redirect_without_request_method(): void {
		unset( $_SERVER['REQUEST_METHOD'] );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'Requests without a request method should not redirect.' );
	}

	/**
	 * @dataProvider data_redirecting_http_methods
	 *
	 * @param string $method HTTP request method.
	 */
	public function test_redirect_for_get_and_head_requests( $method ): void {
		$_SERVER['REQUEST_METHOD'] = $method;

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'GET and HEAD requests should redirect.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_redirecting_http_methods(): array {
		return array(
			'GET'            => array( 'GET' ),
			'HEAD'           => array( 'HEAD' ),
			'lowercase get'  => array( 'get' ),
			'lowercase head' => array( 'head' ),
		);
	}

	/*
	 * Conflicts with pages.
	 */

	/**
	 * A page whose slug begins with the random base must not be matched by the random rules.
	 *
	 * @dataProvider data_permalink_structures
	 *
	 * @global WP $wp Current WordPress environment instance.
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_page_with_slug_beginning_with_random_base_does_not_conflict( $structure ): void {
		global $wp;

		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random-is-just-patterns-we-cant-decipher',
			)
		);

		$permalink = get_permalink( $page );
		$this->assertIsString( $permalink, 'The page with a slug beginning with the random base has no permalink.' );

		$this->go_to( $permalink );

		$this->assertNotRedirected( 'The page should not be treated as a random content request.' );
		$this->assertQueryTrue( 'is_page', 'is_singular' );
		$this->assertSame( $page, get_queried_object_id(), 'The page with a slug beginning with the random base should be queried.' );
		$this->assertArrayNotHasKey( 'random', $wp->query_vars, 'The random query variable should not be set.' );
	}

	/**
	 * @dataProvider data_permalink_structures
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_random_url_redirects_when_a_page_slug_begins_with_random_base( $structure ): void {
		global $wp_rewrite;

		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random-is-just-patterns-we-cant-decipher',
			)
		);

		$this->go_to( home_url( $wp_rewrite->root . 'random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The random URL should redirect when a page slug begins with the random base.' );
	}

	/**
	 * A published page named "random" takes precedence over random content redirects.
	 *
	 * @dataProvider data_permalink_structures
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_page_named_random_takes_precedence_over_random_content_redirects( $structure ): void {
		global $wp_rewrite;

		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		// Creating the page flushes the rewrite rules, no manual flush is required.
		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->go_to( home_url( $wp_rewrite->root . 'random/' ) );

		$this->assertNotRedirected( 'The page should take precedence over random content redirects.' );
		$this->assertQueryTrue( 'is_page', 'is_singular' );
		$this->assertSame( $page, get_queried_object_id(), 'The page named random should be queried.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_permalink_structures(): array {
		return array(
			'post name'          => array( '/%postname%/' ),
			'date and post name' => array( '/%year%/%monthnum%/%postname%/' ),
			'category and name'  => array( '/%category%/%postname%/' ),
			'pathinfo'           => array( '/index.php/%postname%/' ),
		);
	}

	public function test_random_archives_redirect_when_a_page_named_random_exists(): void {
		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->go_to( home_url( '/random/category/arts/' ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'Random archives should redirect when a page named random exists.' );
	}

	public function test_random_query_string_redirects_when_a_page_named_random_exists(): void {
		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->go_to( home_url( '/?random' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The random query string should redirect when a page named random exists.' );
	}

	public function test_draft_page_named_random_does_not_take_precedence(): void {
		$this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_status' => 'draft',
			)
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'A draft page named random should not take precedence.' );
	}

	/**
	 * @dataProvider data_page_named_random_changes
	 *
	 * @param callable $change Callback to change the page so it no longer conflicts.
	 */
	public function test_random_content_redirects_resume_when_the_page_no_longer_conflicts( $change ): void {
		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->go_to( home_url( '/random/' ) );
		$this->assertNotRedirected( 'The page should take precedence before it is changed.' );

		$change( $page );

		$this->go_to( home_url( '/random/' ) );
		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'Random content redirects should resume.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, callable[]>
	 */
	public function data_page_named_random_changes(): array {
		return array(
			'renamed'     => array(
				static function ( int $page_id ): void {
					wp_update_post(
						array(
							'ID'        => $page_id,
							'post_name' => 'not-random',
						)
					);
				},
			),
			'unpublished' => array(
				static function ( int $page_id ): void {
					wp_update_post(
						array(
							'ID'          => $page_id,
							'post_status' => 'draft',
						)
					);
				},
			),
			'trashed'     => array(
				static function ( int $page_id ): void {
					wp_trash_post( $page_id );
				},
			),
			'deleted'     => array(
				static function ( int $page_id ): void {
					wp_delete_post( $page_id, true );
				},
			),
			'moved'       => array(
				static function ( int $page_id ): void {
					wp_update_post(
						array(
							'ID'          => $page_id,
							'post_parent' => self::factory()->post->create( array( 'post_type' => 'page' ) ),
						)
					);
				},
			),
		);
	}

	public function test_publishing_a_draft_page_named_random_takes_precedence(): void {
		$page = $this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_status' => 'draft',
			)
		);

		wp_publish_post( $page );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'A newly published page named random should not redirect.' );
		$this->assertSame( $page, get_queried_object_id(), 'A newly published page named random should be queried.' );
	}

	public function test_saving_unrelated_pages_does_not_flush_rewrite_rules(): void {
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'about',
			)
		);
		$this->create_post( array( 'post_name' => 'random-thoughts' ) );
		$this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_parent' => $this->create_post( array( 'post_type' => 'page' ) ),
			)
		);

		$this->assertSame( 0, $flushes->get_call_count(), 'Only pages and posts at the random base should flush the rewrite rules.' );
	}

	/*
	 * Opting out.
	 */

	public function test_no_redirect_when_disabled(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->go_to( home_url( '/?random' ) );

		$this->assertNotRedirected( 'Random content requests should not redirect when the feature is disabled.' );
		$this->assertQueryTrue( 'is_home', 'is_front_page' );
	}

	public function test_main_query_is_unchanged_when_disabled(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->go_to( home_url( '/?random' ) );

		$this->assertQueryIsNotRandomized( $this->get_main_query(), 'The main query should not be randomized when disabled.' );
		$this->assertSame( 2, $this->get_main_query()->post_count, 'The main query should not be limited when disabled.' );
	}

	public function test_random_url_is_not_routed_when_disabled(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
		flush_rewrite_rules();

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'The random URL should not be routed when the feature is disabled.' );
		$this->assertTrue( is_404(), 'Without the rewrite rules /random/ is not found.' );
	}

	/*
	 * Extensibility.
	 */

	public function test_pre_get_posts_can_change_the_eligible_post_type(): void {
		register_post_type( 'wptests_book', array( 'public' => true ) );
		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( wp_is_random_content_query( $query ) ) {
					$query->set( 'post_type', 'wptests_book' );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location, 'The pre_get_posts action should be able to change the eligible post type.' );
	}

	public function test_pre_get_posts_cannot_add_non_randomable_post_types(): void {
		$this->create_post( array( 'post_type' => 'page' ) );

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( wp_is_random_content_query( $query ) ) {
					$query->set( 'post_type', array( 'post', 'page' ) );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The pre_get_posts action should not be able to add non-randomable post types.' );
	}

	public function test_pre_get_posts_can_exclude_posts(): void {
		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( wp_is_random_content_query( $query ) ) {
					$query->set( 'category__not_in', array( self::$arts_category ) );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'The pre_get_posts action should be able to exclude posts.' );
	}

	public function test_pre_get_posts_archive_customizations_do_not_override_the_random_order(): void {
		// Typical theme customization of the main query for archives.
		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( $query->is_main_query() ) {
					$query->set( 'orderby', 'title' );
					$query->set( 'order', 'ASC' );
					$query->set( 'posts_per_page', 12 );
				}
			}
		);

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$request = $this->get_main_query()->request;
		$this->assertIsString( $request, 'The customized archive query was not run.' );
		$this->assertStringContainsString( 'ORDER BY RAND()', $request, 'The random order should not be overridden by archive customizations.' );
		$this->assertStringContainsString( 'LIMIT 0, 1', $request, 'Only one post should be selected when archive customizations set the number of posts.' );
	}

	public function test_secondary_queries_are_not_random_content_queries(): void {
		$this->go_to( home_url( '/random/' ) );

		$query = new WP_Query( array( 'random' => '1' ) );

		$this->assertFalse( wp_is_random_content_query( $query ), 'Secondary queries should not be random content queries.' );
		$this->assertQueryIsNotRandomized( $query, 'Secondary queries should not be randomized.' );
	}

	public function test_wp_is_random_content_query_for_main_query(): void {
		$this->go_to( home_url( '/random/' ) );

		$this->assertTrue( wp_is_random_content_query( $this->get_main_query() ), 'The main query for a random URL should be a random content query.' );
	}

	/**
	 * @dataProvider data_is_random
	 *
	 * @covers ::is_random
	 * @covers WP_Query::is_random
	 *
	 * @param string $path     Path to request.
	 * @param bool   $expected Expected result.
	 */
	public function test_is_random( $path, $expected ): void {
		$this->go_to( home_url( $path ) );

		$this->assertSame( $expected, is_random(), 'The is_random() conditional tag returned an unexpected value.' );
		$this->assertSame( $expected, $this->get_main_query()->is_random(), 'WP_Query::is_random() returned an unexpected value.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function data_is_random(): array {
		return array(
			'random home'                => array( '/random/', true ),
			'random category archive'    => array( '/random/category/arts/', true ),
			'random author archive'      => array( '/random/author/random-editor/', true ),
			'random date archive'        => array( '/1844/?random', true ),
			'random search'              => array( '/?s=Baseball&random', true ),
			'home'                       => array( '/', false ),
			'category archive'           => array( '/category/arts/', false ),
			'singular post'              => array( '/cassatt/?random', false ),
			'feed'                       => array( '/?random&feed=rss2', false ),
			'nonexistent random archive' => array( '/random/category/nonexistent/', false ),
			'random empty archive'       => array( '/random/category/empty/', false ),
		);
	}

	/**
	 * @covers WP_Query::is_random
	 */
	public function test_secondary_query_is_random_with_the_random_query_var(): void {
		$query = new WP_Query( array( 'random' => '1' ) );

		$this->assertTrue( $query->is_random(), 'A secondary query with the random query variable should be for random content.' );
		$this->assertFalse( wp_is_random_content_query( $query ), 'A secondary query for random content should not be redirected.' );
	}

	/**
	 * @covers WP_Query::is_random
	 */
	public function test_is_random_is_reset_when_the_query_is_a_404(): void {
		$this->go_to( home_url( '/random/' ) );

		$this->get_main_query()->set_404();

		$this->assertFalse( $this->get_main_query()->is_random(), 'A 404 query should not be for random content.' );
	}

	/**
	 * @covers ::is_random
	 *
	 * @expectedIncorrectUsage is_random
	 */
	public function test_is_random_before_the_query_is_run(): void {
		unset( $GLOBALS['wp_query'] );

		$this->assertFalse( is_random(), 'The is_random() conditional tag should return false before the query is run.' );
	}

	public function test_main_query_is_not_randomized_in_the_admin(): void {
		set_current_screen( 'edit.php' );

		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$this->get_main_query()->query( array( 'random' => '' ) );

		$is_random_content_query = wp_is_random_content_query( $this->get_main_query() );
		set_current_screen( 'front' );

		$this->assertFalse( $is_random_content_query, 'Admin queries are not random content queries.' );
		$this->assertQueryIsNotRandomized( $this->get_main_query(), 'Admin queries should not be randomized.' );
	}

	public function test_redirect_when_the_posts_filter_returns_a_non_list_array(): void {
		add_filter(
			'the_posts',
			static function ( array $posts ): array {
				return array( 5 => reset( $posts ) );
			}
		);

		$this->go_to( home_url( '/random/category/arts/' ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'A non-list array returned by the posts filter should still redirect.' );
	}

	public function test_no_redirect_when_the_permalink_is_empty(): void {
		add_filter( 'post_link', '__return_empty_string' );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'An empty permalink should not redirect.' );
	}

	/*
	 * HTTP headers.
	 */

	public function test_random_requests_are_not_cached(): void {
		$sent = $this->go_to_and_get_headers( home_url( '/random/' ) );
		foreach ( wp_get_nocache_headers() as $name => $value ) {
			$this->assertArrayHasKey( $name, $sent, "The {$name} header is missing." );
			$this->assertSame( $value, $sent[ $name ], "The {$name} header is incorrect." );
		}
	}

	public function test_random_requests_are_not_indexed(): void {
		$sent = $this->go_to_and_get_headers( home_url( '/random/' ) );
		$this->assertArrayHasKey( 'X-Robots-Tag', $sent, 'Random content requests should send the X-Robots-Tag header.' );
		$this->assertSame( 'noindex, follow', $sent['X-Robots-Tag'], 'Random content requests should not be indexed.' );
	}

	public function test_headers_are_unchanged_for_other_requests(): void {
		$sent = $this->go_to_and_get_headers( home_url( '/category/arts/' ) );
		$this->assertArrayNotHasKey( 'X-Robots-Tag', $sent, 'Other requests should not send the X-Robots-Tag header.' );
		$this->assertArrayNotHasKey( 'Cache-Control', $sent, 'Other requests should not send the Cache-Control header.' );
	}

	public function test_redirect_happens_before_the_wp_action(): void {
		$wp = new MockAction();
		add_action( 'wp', array( $wp, 'action' ) );

		$redirected_before_wp = null;
		add_filter(
			'wp_redirect',
			static function ( string $location ) use ( $wp, &$redirected_before_wp ): string {
				$redirected_before_wp = 0 === $wp->get_call_count();
				return $location;
			},
			5
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertTrue( $redirected_before_wp, 'The redirect should happen before the wp action.' );
	}

	/*
	 * Database queries.
	 */

	public function test_random_post_is_selected_with_a_single_database_query(): void {
		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$this->assertNotNull( $this->redirect_location, 'The single database query request did not redirect.' );
		$this->assertCount( 1, $this->queries, "Only one database query should be made to select the random post:\n" . implode( "\n", $this->queries ) );
		$this->assertStringContainsString( 'RAND()', $this->queries[0], 'The random query should be the only query made.' );
	}

	/**
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_random_query_selects_a_single_full_post_row(): void {
		global $wpdb;

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$this->assertMatchesRegularExpression( "/SELECT\s+{$wpdb->posts}\.\*/", $this->queries[0], 'The query should not be split into an ID query and a post query.' );
		$this->assertMatchesRegularExpression( '/ORDER BY RAND\(\)/', $this->queries[0], 'The query should use a random order.' );
		$this->assertMatchesRegularExpression( '/LIMIT 0, 1\s*$/', $this->queries[0], 'The query should select a single post.' );
		$this->assertStringNotContainsString( 'SQL_CALC_FOUND_ROWS', $this->queries[0], 'The query should not count found rows.' );
	}

	/**
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_random_query_does_not_prime_meta_or_term_caches(): void {
		global $wpdb;

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$this->assertNotEmpty( $this->queries, 'No database queries were recorded.' );
		foreach ( $this->queries as $query ) {
			$this->assertStringNotContainsString( $wpdb->postmeta, $query, 'Post meta should not be queried.' );
			$this->assertStringNotContainsString( $wpdb->term_relationships, $query, 'Post terms should not be queried.' );
		}
	}

	public function test_random_query_is_not_split_with_persistent_object_cache(): void {
		$using_ext_cache = wp_using_ext_object_cache( true );

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		wp_using_ext_object_cache( $using_ext_cache );

		$this->assertCount( 1, $this->queries, "Only one database query should be made with a persistent object cache:\n" . implode( "\n", $this->queries ) );
	}

	public function test_split_the_query_is_unchanged_for_other_queries(): void {
		$this->assertTrue( wp_random_content_split_the_query( true, new WP_Query() ), 'Splitting should remain enabled for non-random queries.' );
		$this->assertFalse( wp_random_content_split_the_query( false, new WP_Query() ), 'Splitting should remain disabled for non-random queries.' );
	}

	public function test_random_selection_varies_between_requests(): void {
		$post_ids = array( self::$arts_post, self::$sport_post );
		for ( $i = 0; $i < 8; $i++ ) {
			$post_ids[] = $this->create_post();
		}

		$seen = array();
		for ( $i = 0; $i < 30; $i++ ) {
			$this->redirect_location = null;
			$this->go_to( home_url( '/random/' ) );
			$seen[ $this->redirect_location ] = true;
		}

		// With 10 posts the probability of a single post being selected 30 times in a row is 1 in 10^29.
		$this->assertGreaterThan( 1, count( $seen ), 'Random requests should select different posts.' );
	}

	/*
	 * Query arguments that would otherwise override the single random post.
	 */

	/**
	 * @dataProvider data_query_vars_overriding_the_limit
	 *
	 * @param string $path      Request path.
	 * @param string $query_var Query variable set by a theme.
	 * @param mixed  $value     Query variable value.
	 */
	public function test_random_query_is_limited_to_one_post_when_a_theme_overrides_the_limit( $path, $query_var, $value ): void {
		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ) use ( $query_var, $value ): void {
				if ( $query->is_main_query() ) {
					$query->set( $query_var, $value );
				}
			}
		);

		$this->go_to( home_url( $path ) );

		$request = $this->get_main_query()->request;
		$this->assertIsString( $request, 'The random query with a theme limit override was not run.' );
		$this->assertMatchesRegularExpression( '/LIMIT 0, 1\s*$/', $request, 'The random query should select a single post.' );
		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'Theme limit overrides should not prevent a single post being selected.' );
	}

	/**
	 * Data provider.
	 *
	 * The limit is applied differently to the home page and archives, see WP_Query::get_posts().
	 *
	 * @return array<string, array{0: string, 1: string, 2: bool|int}>
	 */
	public function data_query_vars_overriding_the_limit(): array {
		$overrides = array(
			'nopaging'               => array( 'nopaging', true ),
			'posts_per_archive_page' => array( 'posts_per_archive_page', 10 ),
			'showposts'              => array( 'showposts', 10 ),
			'posts_per_page -1'      => array( 'posts_per_page', -1 ),
		);

		$data = array();
		foreach ( array(
			'home'    => '/random/',
			'archive' => '/random/category/arts/',
		) as $context => $path ) {
			foreach ( $overrides as $name => $override ) {
				$data[ "{$context}: {$name}" ] = array( $path, $override[0], $override[1] );
			}
		}

		return $data;
	}

	public function test_random_redirect_ignores_an_offset_set_by_a_theme(): void {
		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( $query->is_main_query() ) {
					$query->set( 'offset', 50 );
				}
			}
		);

		$this->go_to( home_url( '/random/category/arts/' ) );

		$this->assertSame( get_permalink( self::$arts_post ), $this->redirect_location, 'An offset beyond the eligible posts would prevent the redirect.' );
	}

	/*
	 * Redirect locations.
	 */

	public function test_no_redirect_when_the_permalink_is_on_another_site(): void {
		add_filter(
			'post_link',
			static function (): string {
				return 'https://elsewhere.example/post/';
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'Off-site permalinks must not fall back to a redirect to the dashboard.' );
	}

	public function test_redirect_to_a_permalink_on_an_allowed_host(): void {
		add_filter(
			'allowed_redirect_hosts',
			static function ( array $hosts ): array {
				$hosts[] = 'cdn.example.org';
				return $hosts;
			}
		);
		add_filter(
			'post_link',
			static function (): string {
				return 'https://cdn.example.org/post/';
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( 'https://cdn.example.org/post/', $this->redirect_location, 'Permalinks on an allowed host should be redirected to.' );
	}

	public function test_no_redirect_when_the_main_query_has_no_post(): void {
		add_filter( 'the_posts', '__return_empty_array' );
		$this->go_to( home_url( '/random/' ) );

		$this->assertTrue( is_404(), 'A random request where the main query has no post should 404.' );

		// Treat the 404 as a random content query again, so the redirect reaches the post check.
		$this->get_main_query()->is_random = true;

		// A global post set by a plugin must not be used as the redirect target.
		$GLOBALS['post'] = get_post( self::$arts_post );
		wp_random_content_redirect();

		$this->assertNotRedirected( 'A main query without a post should not redirect.' );
	}

	/*
	 * Randomable post types: edge cases.
	 */

	public function test_random_search_excludes_randomable_post_types_excluded_from_search(): void {
		register_post_type(
			'wptests_unsearchable',
			array(
				'public'              => true,
				'exclude_from_search' => true,
			)
		);
		$this->create_post(
			array(
				'post_type'  => 'wptests_unsearchable',
				'post_title' => 'Baseball unsearchable',
			)
		);

		$this->go_to( home_url( '/?s=Baseball&random' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'Random search results should match the search results.' );
	}

	public function test_randomable_post_type_excluded_from_search_can_be_requested_directly(): void {
		register_post_type(
			'wptests_unsearchable',
			array(
				'public'              => true,
				'exclude_from_search' => true,
			)
		);
		$post_id = $this->create_post( array( 'post_type' => 'wptests_unsearchable' ) );

		$this->go_to( home_url( '/?random&post_type=wptests_unsearchable' ) );

		$this->assertSame( get_permalink( $post_id ), $this->redirect_location, 'Randomable post types excluded from search should be selectable directly.' );
	}

	public function test_random_post_types_include_attachments_for_attachment_mime_type_taxonomies(): void {
		$filter = static function ( array $args, string $post_type ): array {
			if ( 'attachment' === $post_type ) {
				$args['randomable'] = true;
			}
			return $args;
		};
		add_filter( 'register_post_type_args', $filter, 10, 2 );
		create_initial_post_types();
		register_taxonomy( 'wptests_genre', 'attachment:image', array( 'public' => true ) );
		self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_genre',
				'slug'     => 'sci-fi',
			)
		);

		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
		$this->go_to( home_url( '/?random&wptests_genre=sci-fi' ) );
		remove_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$post_types = wp_get_random_content_post_types( $this->get_main_query() );

		$this->assertSame( array( 'attachment' ), $post_types, 'Attachments should be eligible for attachment MIME type taxonomies.' );
	}

	public function test_non_string_post_types_set_by_plugins_are_ignored(): void {
		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( $query->is_main_query() ) {
					$query->set( 'post_type', array( 'post', array( 'page' ), 5, null ) );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'Non-string post types set by plugins should be ignored.' );
	}

	/*
	 * Enabling and disabling.
	 */

	/**
	 * @dataProvider data_enabled_filter_values
	 *
	 * @param mixed $value    Value returned by the filter.
	 * @param bool  $expected Expected result.
	 */
	public function test_wp_is_random_content_redirect_enabled_returns_a_boolean( $value, $expected ): void {
		add_filter(
			'wp_enable_random_content_redirect',
			static function () use ( $value ) {
				return $value;
			}
		);

		$this->assertSame( $expected, wp_is_random_content_redirect_enabled(), 'The enabled filter value should be cast to a boolean.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function data_enabled_filter_values(): array {
		return array(
			'default true' => array( true, true ),
			'false'        => array( false, false ),
			'zero'         => array( 0, false ),
			'empty string' => array( '', false ),
			'one'          => array( 1, true ),
			'string yes'   => array( 'yes', true ),
		);
	}

	public function test_no_redirect_when_disabled_without_flushing_the_rewrite_rules(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'Stale rewrite rules must not trigger a redirect.' );
		$this->assertQueryIsNotRandomized( $this->get_main_query(), 'Stale rewrite rules must not randomize the query.' );
	}

	/*
	 * Conflicts with pages: lifecycle guards.
	 */

	public function test_creating_a_page_named_random_flushes_the_rewrite_rules(): void {
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->assertSame( 1, $flushes->get_call_count(), 'Creating a page named random should flush the rewrite rules once.' );
	}

	public function test_page_named_random_does_not_flush_rewrite_rules_with_plain_permalinks(): void {
		$this->set_permalink_structure( '' );
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->assertSame( 0, $flushes->get_call_count(), 'Plain permalinks have no rewrite rules to update.' );
	}

	public function test_page_named_random_does_not_flush_rewrite_rules_when_disabled(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->assertSame( 0, $flushes->get_call_count(), 'Disabled random redirects have no rules to update.' );
	}

	/**
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_page_at_custom_random_base_takes_precedence(): void {
		global $wp_rewrite;

		$wp_rewrite->random_base = 'surprise-me';
		flush_rewrite_rules();

		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'surprise-me',
			)
		);

		$this->go_to( home_url( '/surprise-me/' ) );

		$this->assertNotRedirected( 'A page at a custom random base should not redirect.' );
		$this->assertSame( $page, get_queried_object_id(), 'A page at a custom random base should be queried.' );
	}

	/**
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_page_named_random_does_not_conflict_with_a_custom_random_base(): void {
		global $wp_rewrite;

		$wp_rewrite->random_base = 'surprise-me';
		flush_rewrite_rules();

		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );
		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->go_to( home_url( '/surprise-me/' ) );

		$this->assertSame( 0, $flushes->get_call_count(), 'Only pages at the custom random base should flush the rules.' );
		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'A page named random should not conflict with a custom random base.' );
	}

	public function test_private_page_named_random_does_not_take_precedence(): void {
		$this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_status' => 'private',
			)
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'Pages visitors cannot view should not replace random redirects.' );
	}

	public function test_password_protected_page_named_random_takes_precedence(): void {
		$page = $this->create_post(
			array(
				'post_type'     => 'page',
				'post_name'     => 'random',
				'post_password' => 'secret',
			)
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'A password protected page named random should not redirect.' );
		$this->assertSame( $page, get_queried_object_id(), 'A password protected page named random should be queried.' );
	}

	/*
	 * Paths beneath the random base without random rules.
	 */

	/**
	 * @dataProvider data_unsupported_random_paths
	 *
	 * @global WP $wp Current WordPress environment instance.
	 *
	 * @param string $path Request path.
	 */
	public function test_no_redirect_for_paths_without_random_rules( $path ): void {
		global $wp;

		$this->go_to( home_url( $path ) );

		$this->assertNotRedirected( 'Paths without random rules should not redirect.' );
		$this->assertArrayNotHasKey( 'random', $wp->query_vars, 'Paths without random rules should not set the random query variable.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_unsupported_random_paths(): array {
		return array(
			'pagination' => array( '/random/page/2/' ),
			'feed'       => array( '/random/feed/' ),
		);
	}

	public function test_random_requests_without_a_post_send_a_404_status(): void {
		$status = new MockAction();
		add_filter( 'status_header', array( $status, 'filter' ), 10, 2 );

		$this->go_to( home_url( '/random/category/empty/' ) );

		$codes = array_column( $status->get_args(), 1 );

		$this->assertNotRedirected( 'Random content requests without a post should not redirect.' );
		$this->assertContains( 404, $codes, 'Random content requests without a post should send a 404 status.' );
		$this->assertNotContains( 200, $codes, 'Random content requests without a post should not send a 200 status.' );
	}

	/*
	 * WP::handle_random().
	 */

	public function test_handle_random_ignores_requests_without_the_random_query_var(): void {
		$wp             = new WP();
		$wp->query_vars = array( 'category_name' => 'arts' );

		$this->assertFalse( $wp->handle_random(), 'Requests without the random query variable should not be handled.' );
	}

	public function test_handle_random_ignores_random_requests_when_disabled(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$wp             = new WP();
		$wp->query_vars = array( 'random' => '' );

		$this->assertFalse( $wp->handle_random(), 'Random content requests should not be handled when the feature is disabled.' );
	}

	public function test_handle_random_runs_the_main_query_for_random_requests(): void {
		$wp             = new WP();
		$wp->query_vars = array( 'random' => '' );

		$this->assertTrue( $wp->handle_random(), 'Random content requests should be handled.' );
		$this->assertTrue( $this->get_main_query()->is_random(), 'The main query should be the random content query.' );
		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'The random content request should redirect after running the main query.' );
		$this->assertFalse( is_404(), 'A random content request with a post should not 404 when the redirect does not exit.' );
	}

	/**
	 * @dataProvider data_random_requests
	 *
	 * @param string $path Path to request.
	 */
	public function test_main_query_is_run_once_for_random_requests( string $path ): void {
		$pre_get_posts = new MockAction();
		add_action( 'pre_get_posts', array( $pre_get_posts, 'action' ) );

		$this->go_to( home_url( $path ) );

		$this->assertSame( 1, $pre_get_posts->get_call_count(), "The main query should be run once for {$path}." );
	}

	/**
	 * Data provider for test_main_query_is_run_once_for_random_requests().
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_random_requests(): array {
		return array(
			'random post'          => array( '/random/' ),
			'random empty archive' => array( '/random/category/empty/' ),
		);
	}

	public function test_random_content_callbacks_are_removed_after_the_main_query(): void {
		$this->go_to( home_url( '/random/' ) );

		$this->assertFalse( has_action( 'pre_get_posts', 'wp_random_content_pre_get_posts' ), 'The pre_get_posts callback should be removed after the main query.' );
		$this->assertFalse( has_filter( 'posts_orderby', 'wp_random_content_posts_orderby' ), 'The posts_orderby callback should be removed after the main query.' );
		$this->assertFalse( has_filter( 'post_limits', 'wp_random_content_post_limits' ), 'The post_limits callback should be removed after the main query.' );
	}

	public function test_sql_customizations_do_not_override_the_random_order_or_limit(): void {
		add_filter(
			'posts_orderby',
			static function (): string {
				return 'post_title ASC';
			}
		);
		add_filter(
			'post_limits',
			static function (): string {
				return 'LIMIT 0, 10';
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$request = $this->get_main_query()->request;
		$this->assertIsString( $request, 'The random query with SQL customizations was not run.' );
		$this->assertStringContainsString( 'ORDER BY RAND()', $request, 'The posts_orderby filter should not override the random order.' );
		$this->assertStringContainsString( 'LIMIT 0, 1', $request, 'The post_limits filter should not override the random limit.' );
	}

	public function test_suppressed_filters_do_not_bypass_the_random_order_or_limit(): void {
		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( $query->is_main_query() ) {
					$query->set( 'suppress_filters', true );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$request = $this->get_main_query()->request;
		$this->assertIsString( $request, 'The random query with suppressed filters was not run.' );
		$this->assertStringContainsString( 'ORDER BY RAND()', $request, 'Suppressing filters should not bypass the random order.' );
		$this->assertStringContainsString( 'LIMIT 0, 1', $request, 'Suppressing filters should not bypass the random limit.' );
	}

	/*
	 * Conflicts with posts.
	 */

	/**
	 * @dataProvider data_post_name_at_the_root_structures
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_post_named_random_takes_precedence_over_random_content_redirects( string $structure ): void {
		global $wp_rewrite;

		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		// Creating the post flushes the rewrite rules, no manual flush is required.
		$post = $this->create_post( array( 'post_name' => 'random' ) );

		$this->go_to( home_url( $wp_rewrite->root . 'random/' ) );

		$this->assertNotRedirected( "The post should take precedence over random content redirects for the '{$structure}' structure." );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertSame( $post, get_queried_object_id(), "The post named random should be queried for the '{$structure}' structure." );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_post_name_at_the_root_structures(): array {
		return array(
			'post name' => array( '/%postname%/' ),
			'pathinfo'  => array( '/index.php/%postname%/' ),
		);
	}

	public function test_random_archives_redirect_when_a_post_named_random_exists(): void {
		$this->create_post( array( 'post_name' => 'random' ) );

		$this->go_to( home_url( '/random/category/sport/' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'Random archives should redirect when a post named random exists.' );
	}

	public function test_post_named_random_does_not_conflict_with_a_blog_prefix(): void {
		$this->set_permalink_structure( '/blog/%postname%/' );
		create_initial_taxonomies();
		flush_rewrite_rules();

		$post = $this->create_post( array( 'post_name' => 'random' ) );

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post, $post ), 'Random content requests should redirect when posts have a blog prefix.' );
	}

	public function test_creating_a_post_named_random_flushes_the_rewrite_rules(): void {
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post( array( 'post_name' => 'random' ) );

		$this->assertSame( 1, $flushes->get_call_count(), 'Creating a post named random should flush the rewrite rules once.' );
	}

	public function test_post_named_random_does_not_flush_rewrite_rules_with_a_blog_prefix(): void {
		$this->set_permalink_structure( '/blog/%postname%/' );
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post( array( 'post_name' => 'random' ) );

		$this->assertSame( 0, $flushes->get_call_count(), 'A post named random should not flush the rewrite rules when posts have a blog prefix.' );
	}

	public function test_unpublishing_a_post_named_random_resumes_random_content_redirects(): void {
		$post = $this->create_post( array( 'post_name' => 'random' ) );

		wp_update_post(
			array(
				'ID'          => $post,
				'post_status' => 'draft',
			)
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$arts_post, self::$sport_post ), 'Random content redirects should resume when the post named random is unpublished.' );
	}
}
