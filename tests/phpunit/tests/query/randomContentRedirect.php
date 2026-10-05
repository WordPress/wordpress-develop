<?php

/**
 * Tests for random content redirects.
 *
 * @group query
 * @group rewrite
 * @ticket 64498
 *
 * @covers ::wp_random_content_redirect
 * @covers ::wp_random_content_pre_get_posts
 * @covers ::wp_is_random_content_query
 * @covers ::wp_get_random_content_post_types
 * @covers ::wp_random_content_flush_rewrite_rules_for_page
 * @covers ::wp_random_content_headers
 * @covers ::wp_random_content_split_the_query
 * @covers ::wp_is_random_content_redirect_enabled
 */
class Tests_Query_RandomContentRedirect extends WP_UnitTestCase {

	/**
	 * Published post in the "news" category, tagged "featured".
	 *
	 * @var int
	 */
	protected static $news_post;

	/**
	 * Published post in the "sport" category, written by the editor.
	 *
	 * @var int
	 */
	protected static $sport_post;

	/**
	 * Term ID of the "news" category.
	 *
	 * @var int
	 */
	protected static $news_category;

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

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor = $factory->user->create(
			array(
				'role'          => 'editor',
				'user_nicename' => 'random-editor',
			)
		);
		self::$admin  = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$news_category  = $factory->category->create( array( 'slug' => 'news' ) );
		self::$sport_category = $factory->category->create( array( 'slug' => 'sport' ) );
		self::$empty_category = $factory->category->create( array( 'slug' => 'empty' ) );

		self::$news_post = $factory->post->create(
			array(
				'post_title'    => 'News post',
				'post_name'     => 'news-post',
				'post_date'     => '2020-06-01 00:00:00',
				'post_author'   => self::$admin,
				'post_category' => array( self::$news_category ),
				'tags_input'    => array( 'featured' ),
			)
		);

		self::$sport_post = $factory->post->create(
			array(
				'post_title'    => 'Sport post',
				'post_name'     => 'sport-post',
				'post_date'     => '2021-06-01 00:00:00',
				'post_author'   => self::$editor,
				'post_category' => array( self::$sport_category ),
			)
		);
	}

	public function set_up() {
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

	public function tear_down() {
		$_SERVER['REQUEST_METHOD'] = 'GET';

		_unregister_post_type( 'wptests_book' );
		_unregister_post_type( 'wptests_hidden' );
		_unregister_post_type( 'wptests_unsearchable' );
		_unregister_taxonomy( 'wptests_genre' );

		$GLOBALS['wp_rewrite']->random_base = 'random';

		parent::tear_down();
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
	public function start_recording_queries() {
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
	protected function go_to_and_record_queries( $url ) {
		add_action( 'parse_request', array( $this, 'start_recording_queries' ), PHP_INT_MAX );
		add_filter( 'query', array( $this, 'record_query' ) );

		$this->go_to( $url );

		$this->recording_queries = false;
	}

	/**
	 * Creates a published post.
	 *
	 * @param array $args Optional. Post arguments.
	 * @return int Post ID.
	 */
	protected function create_post( $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_status' => 'publish',
					'post_date'   => '2022-01-01 00:00:00',
				),
				$args
			)
		);
	}

	/**
	 * Asserts that the request redirected to one of the given posts.
	 *
	 * @param int[]  $post_ids Allowed post IDs.
	 * @param string $message  Optional. Message to display on failure.
	 */
	protected function assertRedirectedToOneOf( array $post_ids, $message = '' ) {
		$this->assertNotNull( $this->redirect_location, $message ? $message : 'The request did not redirect.' );

		$permalinks = array_map( 'get_permalink', $post_ids );
		$this->assertContains( $this->redirect_location, $permalinks, $message ? $message : 'The request redirected to an unexpected location.' );
	}

	/**
	 * Asserts that the request did not redirect.
	 *
	 * @param string $message Optional. Message to display on failure.
	 */
	protected function assertNotRedirected( $message = '' ) {
		$this->assertNull( $this->redirect_location, $message ? $message : 'The request should not redirect.' );
	}

	/*
	 * Redirect targets.
	 */

	/**
	 * @dataProvider data_random_urls
	 *
	 * @param string $path Random content request path.
	 */
	public function test_random_url_redirects_to_a_published_post( $path ) {
		$this->go_to( home_url( $path ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_random_urls() {
		return array(
			'pretty permalink'         => array( '/random/' ),
			'pretty without slash'     => array( '/random' ),
			'query string flag'        => array( '/?random' ),
			'query string with value'  => array( '/?random=1' ),
			'query string array value' => array( '/?random[]=1' ),
		);
	}

	public function test_random_redirect_uses_a_temporary_redirect() {
		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( 302, $this->redirect_status, 'Random redirects must not be permanent.' );
	}

	public function test_random_redirect_location_is_the_permalink() {
		$this->go_to( home_url( '/random/category/news/' ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location );
	}

	public function test_random_query_string_redirects_with_plain_permalinks() {
		$this->set_permalink_structure( '' );

		$this->go_to( home_url( '/?random' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
		$this->assertStringContainsString( '?p=', $this->redirect_location, 'The redirect should use the plain permalink.' );
	}

	public function test_random_redirect_with_pathinfo_permalinks() {
		$this->set_permalink_structure( '/index.php/%postname%/' );

		$this->go_to( home_url( '/index.php/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	public function test_random_category_url_only_selects_posts_in_the_category() {
		$this->go_to( home_url( '/random/category/sport/' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location );
	}

	public function test_random_child_category_url_selects_posts_in_the_child_category() {
		$child   = self::factory()->category->create(
			array(
				'slug'   => 'local',
				'parent' => self::$news_category,
			)
		);
		$post_id = $this->create_post( array( 'post_category' => array( $child ) ) );

		$this->go_to( home_url( '/random/category/news/local/' ) );

		$this->assertSame( get_permalink( $post_id ), $this->redirect_location );
	}

	public function test_random_category_url_includes_posts_in_child_categories() {
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

	public function test_random_cat_query_string_only_selects_posts_in_the_category() {
		$this->go_to( add_query_arg( 'cat', self::$news_category, home_url( '/?random' ) ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location );
	}

	public function test_random_tag_url_only_selects_posts_with_the_tag() {
		$this->go_to( home_url( '/random/tag/featured/' ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location );
	}

	public function test_random_combined_taxonomy_query_selects_posts_matching_all_terms() {
		$sport_featured = $this->create_post(
			array(
				'post_category' => array( self::$sport_category ),
				'tags_input'    => array( 'featured' ),
			)
		);

		$this->go_to( home_url( '/?random&category_name=sport&tag=featured' ) );

		$this->assertSame( get_permalink( $sport_featured ), $this->redirect_location );
	}

	public function test_random_author_url_only_selects_posts_by_the_author() {
		$this->go_to( home_url( '/random/author/random-editor/' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location );
	}

	public function test_random_date_archive_only_selects_posts_from_the_date() {
		$this->go_to( home_url( '/2020/?random' ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location );
	}

	public function test_random_search_only_selects_matching_posts() {
		$this->go_to( home_url( '/?s=Sport&random' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location );
	}

	public function test_random_post_type_archive_url_only_selects_posts_of_the_type() {
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

		$this->assertSame( get_permalink( $book ), $this->redirect_location );
	}

	public function test_random_post_type_query_string_selects_posts_of_the_type() {
		register_post_type( 'wptests_book', array( 'public' => true ) );

		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/?random&post_type=wptests_book' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location );
	}

	/*
	 * Randomable post types.
	 */

	public function test_no_redirect_for_pages() {
		$this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/?random&post_type=page' ) );

		$this->assertNotRedirected( 'Pages are not randomable.' );
	}

	public function test_main_query_is_unchanged_for_pages() {
		$this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/?random&post_type=page' ) );

		$this->assertNotSame( 'rand', get_query_var( 'orderby' ), 'Requests for non-randomable post types should not be randomized.' );
	}

	public function test_pages_redirect_when_made_randomable() {
		add_filter( 'register_post_type_args', array( $this, 'filter_make_pages_randomable' ), 10, 2 );
		create_initial_post_types();

		$page = $this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/?random&post_type=page' ) );

		remove_filter( 'register_post_type_args', array( $this, 'filter_make_pages_randomable' ) );
		create_initial_post_types();

		$this->assertSame( get_permalink( $page ), $this->redirect_location );
	}

	/**
	 * Makes the page post type randomable.
	 *
	 * @param array  $args      Post type registration arguments.
	 * @param string $post_type Post type name.
	 * @return array Modified post type registration arguments.
	 */
	public function filter_make_pages_randomable( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$args['randomable'] = true;
		}

		return $args;
	}

	public function test_no_redirect_for_post_type_that_is_not_randomable() {
		register_post_type(
			'wptests_book',
			array(
				'public'     => true,
				'randomable' => false,
			)
		);

		$this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/?random&post_type=wptests_book' ) );

		$this->assertNotRedirected();
	}

	public function test_no_redirect_for_post_type_archive_that_is_not_randomable() {
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

		$this->assertNotRedirected();
		$this->assertTrue( is_post_type_archive( 'wptests_book' ), 'The archive should be displayed.' );
	}

	public function test_random_redirect_with_multiple_post_types_only_selects_randomable_types() {
		register_post_type( 'wptests_book', array( 'public' => true ) );

		$this->create_post( array( 'post_type' => 'page' ) );
		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );

		$this->go_to( home_url( '/?random&post_type[]=page&post_type[]=wptests_book' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location );
	}

	public function test_random_search_only_selects_randomable_post_types() {
		$this->create_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Sport page',
			)
		);

		$this->go_to( home_url( '/?s=Sport&random' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'Pages should not be selected from search results.' );
	}

	public function test_random_search_includes_randomable_custom_post_types() {
		register_post_type( 'wptests_book', array( 'public' => true ) );

		$book = $this->create_post(
			array(
				'post_type'  => 'wptests_book',
				'post_title' => 'Unique needle',
			)
		);

		$this->go_to( home_url( '/?s=needle&random' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location );
	}

	public function test_random_custom_taxonomy_archive_only_selects_randomable_post_types() {
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

		$this->assertSame( get_permalink( $book ), $this->redirect_location );
	}

	public function test_no_redirect_for_taxonomy_used_only_by_pages() {
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

		$this->assertNotRedirected();
		$this->assertTrue( is_tax( 'wptests_genre' ), 'The term archive should be displayed.' );
	}

	/**
	 * @dataProvider data_wp_get_random_content_post_types
	 *
	 * @param string   $url      URL to request.
	 * @param string[] $expected Expected post types.
	 */
	public function test_wp_get_random_content_post_types( $url, $expected ) {
		register_post_type( 'wptests_book', array( 'public' => true ) );
		register_post_type(
			'wptests_hidden',
			array(
				'public'     => true,
				'randomable' => false,
			)
		);
		register_taxonomy( 'wptests_genre', array( 'post', 'page', 'wptests_book' ), array( 'public' => true ) );
		wp_set_object_terms( self::$news_post, 'sci-fi', 'wptests_genre' );

		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
		$this->go_to( home_url( $url ) );
		remove_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$actual = wp_get_random_content_post_types( $GLOBALS['wp_query'] );
		sort( $actual );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array>
	 */
	public function data_wp_get_random_content_post_types() {
		return array(
			'home'                 => array( '/?random', array( 'post' ) ),
			'category'             => array( '/?random&category_name=news', array( 'post' ) ),
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

	public function test_random_url_defaults_to_posts() {
		$this->create_post( array( 'post_type' => 'page' ) );

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ), 'Pages should not be selected without requesting them.' );
	}

	public function test_random_url_with_static_front_page_redirects_to_a_post() {
		$front = $this->create_post( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	public function test_random_redirect_ignores_pagination() {
		$this->go_to( home_url( '/random/category/news/?paged=5' ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location );
	}

	/*
	 * Eligible posts.
	 */

	/**
	 * @dataProvider data_ineligible_posts
	 *
	 * @param array $args Post arguments.
	 */
	public function test_random_redirect_excludes_ineligible_posts( $args ) {
		$category = self::factory()->category->create();
		$this->create_post( array_merge( array( 'post_category' => array( $category ) ), $args ) );

		wp_set_current_user( self::$admin );

		$this->go_to( add_query_arg( 'cat', $category, home_url( '/?random' ) ) );

		$this->assertNotRedirected( 'Only published posts without passwords may be selected, even for administrators.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array[]>
	 */
	public function data_ineligible_posts() {
		return array(
			'draft'              => array( array( 'post_status' => 'draft' ) ),
			'pending'            => array( array( 'post_status' => 'pending' ) ),
			'private'            => array( array( 'post_status' => 'private' ) ),
			'future'             => array(
				array(
					'post_status' => 'future',
					'post_date'   => '2099-01-01 00:00:00',
				),
			),
			'trash'              => array( array( 'post_status' => 'trash' ) ),
			'password protected' => array( array( 'post_password' => 'secret' ) ),
		);
	}

	public function test_random_redirect_ignores_sticky_posts() {
		$sticky = $this->create_post( array( 'post_title' => 'Sticky' ) );
		stick_post( $sticky );

		// Make the "random" order deterministic so the sticky post is never selected by the query.
		add_filter(
			'posts_orderby',
			static function ( $orderby, $query ) use ( $sticky ) {
				if ( $query->is_main_query() ) {
					global $wpdb;
					return "{$wpdb->posts}.ID = {$sticky} ASC";
				}
				return $orderby;
			},
			10,
			2
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotNull( $this->redirect_location, 'The request did not redirect.' );
		$this->assertNotSame( get_permalink( $sticky ), $this->redirect_location, 'Sticky posts must not be prepended to the random selection.' );
	}

	/*
	 * Requests that are not random content requests.
	 */

	public function test_no_redirect_without_random_query_var() {
		$this->go_to( home_url( '/' ) );

		$this->assertNotRedirected();
		$this->assertQueryTrue( 'is_home', 'is_front_page' );
	}

	public function test_archives_are_unchanged_without_random_query_var() {
		$this->go_to( home_url( '/category/news/' ) );

		$this->assertNotRedirected();
		$this->assertSame( '', get_query_var( 'orderby' ), 'The archive order should not be modified.' );
	}

	public function test_no_redirect_for_singular_post_with_random_query_var() {
		$this->go_to( add_query_arg( 'random', '', get_permalink( self::$news_post ) ) );

		$this->assertNotRedirected( 'Singular requests should not redirect to themselves or another post.' );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertSame( self::$news_post, get_queried_object_id() );
	}

	public function test_no_redirect_for_post_id_with_random_query_var() {
		$this->go_to( home_url( '/?random&p=' . self::$sport_post ) );

		$this->assertNotRedirected();
		$this->assertSame( self::$sport_post, get_queried_object_id() );
	}

	public function test_no_redirect_for_page_with_random_query_var() {
		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'about',
			)
		);

		$this->go_to( home_url( '/about/?random' ) );

		$this->assertNotRedirected();
		$this->assertSame( $page, get_queried_object_id() );
	}

	public function test_no_redirect_for_static_front_page_with_random_query_var_from_extra_vars() {
		$front = $this->create_post( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );

		$this->go_to( home_url( '/?page_id=' . $front . '&random' ) );

		$this->assertNotRedirected();
		$this->assertSame( $front, get_queried_object_id() );
	}

	/**
	 * @dataProvider data_feed_urls
	 *
	 * @param string $path Feed path.
	 */
	public function test_no_redirect_for_feeds( $path ) {
		$this->go_to( home_url( $path ) );

		$this->assertNotRedirected();
		$this->assertTrue( is_feed(), 'The request should remain a feed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_feed_urls() {
		return array(
			'site feed'     => array( '/feed/?random' ),
			'category feed' => array( '/category/news/feed/?random' ),
			'comments feed' => array( '/comments/feed/?random' ),
		);
	}

	public function test_no_redirect_for_embeds() {
		$this->go_to( home_url( '/news-post/embed/?random' ) );

		$this->assertNotRedirected();
		$this->assertTrue( is_embed() );
	}

	public function test_no_redirect_for_nonexistent_category() {
		$this->go_to( home_url( '/random/category/does-not-exist/' ) );

		$this->assertNotRedirected();
		$this->assertTrue( is_404(), 'A random request for a nonexistent term should 404.' );
	}

	public function test_no_redirect_for_empty_category() {
		$this->go_to( home_url( '/random/category/empty/' ) );

		$this->assertNotRedirected( 'An empty archive has nothing to redirect to.' );
		$this->assertTrue( is_category( 'empty' ), 'The empty archive should be displayed.' );
	}

	/**
	 * @dataProvider data_non_redirecting_http_methods
	 *
	 * @param string $method HTTP request method.
	 */
	public function test_no_redirect_for_non_get_requests( $method ) {
		$_SERVER['REQUEST_METHOD'] = $method;

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected();
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_non_redirecting_http_methods() {
		return array(
			'POST'    => array( 'POST' ),
			'PUT'     => array( 'PUT' ),
			'DELETE'  => array( 'DELETE' ),
			'OPTIONS' => array( 'OPTIONS' ),
		);
	}

	public function test_no_redirect_without_request_method() {
		unset( $_SERVER['REQUEST_METHOD'] );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected();
	}

	/**
	 * @dataProvider data_redirecting_http_methods
	 *
	 * @param string $method HTTP request method.
	 */
	public function test_redirect_for_get_and_head_requests( $method ) {
		$_SERVER['REQUEST_METHOD'] = $method;

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_redirecting_http_methods() {
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
	 * @param string $structure Permalink structure.
	 */
	public function test_page_with_slug_beginning_with_random_base_does_not_conflict( $structure ) {
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random-is-just-patterns-we-cant-decipher',
			)
		);

		$this->go_to( get_permalink( $page ) );

		$this->assertNotRedirected( 'The page should not be treated as a random content request.' );
		$this->assertQueryTrue( 'is_page', 'is_singular' );
		$this->assertSame( $page, get_queried_object_id() );
		$this->assertArrayNotHasKey( 'random', $GLOBALS['wp']->query_vars, 'The random query variable should not be set.' );
	}

	/**
	 * @dataProvider data_permalink_structures
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_random_url_redirects_when_a_page_slug_begins_with_random_base( $structure ) {
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random-is-just-patterns-we-cant-decipher',
			)
		);

		$this->go_to( home_url( $GLOBALS['wp_rewrite']->root . 'random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	/**
	 * A published page named "random" takes precedence over random content redirects.
	 *
	 * @dataProvider data_permalink_structures
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_page_named_random_takes_precedence_over_random_content_redirects( $structure ) {
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

		$this->go_to( home_url( $GLOBALS['wp_rewrite']->root . 'random/' ) );

		$this->assertNotRedirected( 'The page should take precedence over random content redirects.' );
		$this->assertQueryTrue( 'is_page', 'is_singular' );
		$this->assertSame( $page, get_queried_object_id() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_permalink_structures() {
		return array(
			'post name'          => array( '/%postname%/' ),
			'date and post name' => array( '/%year%/%monthnum%/%postname%/' ),
			'category and name'  => array( '/%category%/%postname%/' ),
			'pathinfo'           => array( '/index.php/%postname%/' ),
		);
	}

	public function test_random_archives_redirect_when_a_page_named_random_exists() {
		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->go_to( home_url( '/random/category/news/' ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location );
	}

	public function test_random_query_string_redirects_when_a_page_named_random_exists() {
		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->go_to( home_url( '/?random' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	public function test_draft_page_named_random_does_not_take_precedence() {
		$this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_status' => 'draft',
			)
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	/**
	 * @dataProvider data_page_named_random_changes
	 *
	 * @param callable $change Callback to change the page so it no longer conflicts.
	 */
	public function test_random_content_redirects_resume_when_the_page_no_longer_conflicts( $change ) {
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
		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ), 'Random content redirects should resume.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, callable[]>
	 */
	public function data_page_named_random_changes() {
		return array(
			'renamed'     => array(
				static function ( $page_id ) {
					wp_update_post(
						array(
							'ID'        => $page_id,
							'post_name' => 'not-random',
						)
					);
				},
			),
			'unpublished' => array(
				static function ( $page_id ) {
					wp_update_post(
						array(
							'ID'          => $page_id,
							'post_status' => 'draft',
						)
					);
				},
			),
			'trashed'     => array(
				static function ( $page_id ) {
					wp_trash_post( $page_id );
				},
			),
			'deleted'     => array(
				static function ( $page_id ) {
					wp_delete_post( $page_id, true );
				},
			),
			'moved'       => array(
				static function ( $page_id ) {
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

	public function test_publishing_a_draft_page_named_random_takes_precedence() {
		$page = $this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_status' => 'draft',
			)
		);

		wp_publish_post( $page );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected();
		$this->assertSame( $page, get_queried_object_id() );
	}

	public function test_saving_unrelated_pages_does_not_flush_rewrite_rules() {
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'about',
			)
		);
		$this->create_post( array( 'post_name' => 'random' ) );
		$this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_parent' => $this->create_post( array( 'post_type' => 'page' ) ),
			)
		);

		$this->assertSame( 0, $flushes->get_call_count(), 'Only pages at the random base should flush the rewrite rules.' );
	}

	/*
	 * Opting out.
	 */

	public function test_no_redirect_when_disabled() {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->go_to( home_url( '/?random' ) );

		$this->assertNotRedirected();
		$this->assertQueryTrue( 'is_home', 'is_front_page' );
	}

	public function test_main_query_is_unchanged_when_disabled() {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->go_to( home_url( '/?random' ) );

		$this->assertNotSame( 'rand', get_query_var( 'orderby' ), 'The main query should not be randomized when disabled.' );
		$this->assertCount( 2, $GLOBALS['wp_query']->posts, 'The main query should not be limited when disabled.' );
	}

	public function test_random_url_is_not_routed_when_disabled() {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
		flush_rewrite_rules();

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected();
		$this->assertTrue( is_404(), 'Without the rewrite rules /random/ is not found.' );
	}

	/*
	 * Extensibility.
	 */

	public function test_pre_get_posts_can_change_the_eligible_post_type() {
		register_post_type( 'wptests_book', array( 'public' => true ) );
		$book = $this->create_post( array( 'post_type' => 'wptests_book' ) );

		add_action(
			'pre_get_posts',
			static function ( $query ) {
				if ( wp_is_random_content_query( $query ) ) {
					$query->set( 'post_type', 'wptests_book' );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( get_permalink( $book ), $this->redirect_location );
	}

	public function test_pre_get_posts_cannot_add_non_randomable_post_types() {
		$this->create_post( array( 'post_type' => 'page' ) );

		add_action(
			'pre_get_posts',
			static function ( $query ) {
				if ( wp_is_random_content_query( $query ) ) {
					$query->set( 'post_type', array( 'post', 'page' ) );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ), 'Pages are not randomable.' );
	}

	public function test_pre_get_posts_can_exclude_posts() {
		add_action(
			'pre_get_posts',
			static function ( $query ) {
				if ( wp_is_random_content_query( $query ) ) {
					$query->set( 'category__not_in', array( self::$news_category ) );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location );
	}

	public function test_pre_get_posts_archive_customizations_do_not_override_the_random_order() {
		// Typical theme customization of the main query for archives.
		add_action(
			'pre_get_posts',
			static function ( $query ) {
				if ( $query->is_main_query() ) {
					$query->set( 'orderby', 'title' );
					$query->set( 'order', 'ASC' );
					$query->set( 'posts_per_page', 12 );
				}
			}
		);

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$this->assertSame( 'rand', get_query_var( 'orderby' ), 'The random order should not be overridden.' );
		$this->assertSame( 1, get_query_var( 'posts_per_page' ), 'Only one post should be selected.' );
		$this->assertStringContainsString( 'RAND()', $GLOBALS['wp_query']->request );
	}

	public function test_secondary_queries_are_not_random_content_queries() {
		$this->go_to( home_url( '/random/' ) );

		$query = new WP_Query( array( 'random' => '1' ) );

		$this->assertFalse( wp_is_random_content_query( $query ) );
		$this->assertNotSame( 'rand', $query->get( 'orderby' ), 'Secondary queries should not be randomized.' );
	}

	public function test_wp_is_random_content_query_rejects_non_queries() {
		$this->assertFalse( wp_is_random_content_query( null ) );
		$this->assertFalse( wp_is_random_content_query( array( 'random' => '1' ) ) );
	}

	public function test_wp_is_random_content_query_for_main_query() {
		$this->go_to( home_url( '/random/' ) );

		$this->assertTrue( wp_is_random_content_query( $GLOBALS['wp_query'] ) );
	}

	public function test_main_query_is_not_randomized_in_the_admin() {
		set_current_screen( 'edit.php' );

		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$GLOBALS['wp_query']->query( array( 'random' => '' ) );

		$is_random_content_query = wp_is_random_content_query( $GLOBALS['wp_query'] );
		$orderby                 = $GLOBALS['wp_query']->get( 'orderby' );
		set_current_screen( 'front' );

		$this->assertFalse( $is_random_content_query, 'Admin queries are not random content queries.' );
		$this->assertNotSame( 'rand', $orderby, 'Admin queries should not be randomized.' );
	}

	public function test_redirect_when_the_posts_filter_returns_a_non_list_array() {
		add_filter(
			'the_posts',
			static function ( $posts ) {
				return array( 5 => reset( $posts ) );
			}
		);

		$this->go_to( home_url( '/random/category/news/' ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location );
	}

	public function test_no_redirect_when_the_permalink_is_empty() {
		add_filter( 'post_link', '__return_empty_string' );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected();
	}

	/*
	 * HTTP headers.
	 */

	public function test_random_requests_are_not_cached() {
		$headers = new MockAction();
		add_filter( 'wp_headers', array( $headers, 'filter' ), 1000 );

		$this->go_to( home_url( '/random/' ) );

		$sent = $headers->get_args()[0][0];
		foreach ( wp_get_nocache_headers() as $name => $value ) {
			$this->assertArrayHasKey( $name, $sent, "The {$name} header is missing." );
			$this->assertSame( $value, $sent[ $name ], "The {$name} header is incorrect." );
		}
	}

	public function test_random_requests_are_not_indexed() {
		$headers = new MockAction();
		add_filter( 'wp_headers', array( $headers, 'filter' ), 1000 );

		$this->go_to( home_url( '/random/' ) );

		$sent = $headers->get_args()[0][0];
		$this->assertArrayHasKey( 'X-Robots-Tag', $sent );
		$this->assertSame( 'noindex, follow', $sent['X-Robots-Tag'] );
	}

	public function test_headers_are_unchanged_for_other_requests() {
		$headers = new MockAction();
		add_filter( 'wp_headers', array( $headers, 'filter' ), 1000 );

		$this->go_to( home_url( '/category/news/' ) );

		$sent = $headers->get_args()[0][0];
		$this->assertArrayNotHasKey( 'X-Robots-Tag', $sent );
		$this->assertArrayNotHasKey( 'Cache-Control', $sent );
	}

	public function test_redirect_happens_before_the_wp_action() {
		$wp = new MockAction();
		add_action( 'wp', array( $wp, 'action' ) );

		$redirected_before_wp = null;
		add_filter(
			'wp_redirect',
			static function ( $location ) use ( $wp, &$redirected_before_wp ) {
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

	public function test_random_post_is_selected_with_a_single_database_query() {
		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$this->assertNotNull( $this->redirect_location, 'The request did not redirect.' );
		$this->assertCount( 1, $this->queries, "Only one database query should be made:\n" . implode( "\n", $this->queries ) );
		$this->assertStringContainsString( 'RAND()', $this->queries[0], 'The random query should be the only query made.' );
	}

	public function test_random_query_selects_a_single_full_post_row() {
		global $wpdb;

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$this->assertMatchesRegularExpression( "/SELECT\s+{$wpdb->posts}\.\*/", $this->queries[0], 'The query should not be split into an ID query and a post query.' );
		$this->assertMatchesRegularExpression( '/ORDER BY RAND\(\)/', $this->queries[0], 'The query should use a random order.' );
		$this->assertMatchesRegularExpression( '/LIMIT 0, 1\s*$/', $this->queries[0], 'The query should select a single post.' );
		$this->assertStringNotContainsString( 'SQL_CALC_FOUND_ROWS', $this->queries[0], 'The query should not count found rows.' );
	}

	public function test_random_query_does_not_prime_meta_or_term_caches() {
		global $wpdb;

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		$this->assertNotEmpty( $this->queries, 'No database queries were recorded.' );
		foreach ( $this->queries as $query ) {
			$this->assertStringNotContainsString( $wpdb->postmeta, $query, 'Post meta should not be queried.' );
			$this->assertStringNotContainsString( $wpdb->term_relationships, $query, 'Post terms should not be queried.' );
		}
	}

	public function test_random_query_is_not_split_with_persistent_object_cache() {
		$using_ext_cache = wp_using_ext_object_cache( true );

		$this->go_to_and_record_queries( home_url( '/random/' ) );

		wp_using_ext_object_cache( $using_ext_cache );

		$this->assertCount( 1, $this->queries, "Only one database query should be made:\n" . implode( "\n", $this->queries ) );
	}

	public function test_split_the_query_is_unchanged_for_other_queries() {
		$this->assertTrue( wp_random_content_split_the_query( true, new WP_Query() ), 'Non-random queries should not be modified.' );
		$this->assertFalse( wp_random_content_split_the_query( false, new WP_Query() ), 'Non-random queries should not be modified.' );
	}

	public function test_random_selection_varies_between_requests() {
		$post_ids = array( self::$news_post, self::$sport_post );
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
	public function test_random_query_is_limited_to_one_post_when_a_theme_overrides_the_limit( $path, $query_var, $value ) {
		add_action(
			'pre_get_posts',
			static function ( $query ) use ( $query_var, $value ) {
				if ( $query->is_main_query() ) {
					$query->set( $query_var, $value );
				}
			}
		);

		$this->go_to( home_url( $path ) );

		$this->assertMatchesRegularExpression( '/LIMIT 0, 1\s*$/', $GLOBALS['wp_query']->request, 'The random query should select a single post.' );
		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	/**
	 * Data provider.
	 *
	 * The limit is applied differently to the home page and archives, see WP_Query::get_posts().
	 *
	 * @return array<string, array>
	 */
	public function data_query_vars_overriding_the_limit() {
		$overrides = array(
			'nopaging'               => array( 'nopaging', true ),
			'posts_per_archive_page' => array( 'posts_per_archive_page', 10 ),
			'showposts'              => array( 'showposts', 10 ),
			'posts_per_page -1'      => array( 'posts_per_page', -1 ),
		);

		$data = array();
		foreach ( array(
			'home'    => '/random/',
			'archive' => '/random/category/news/',
		) as $context => $path ) {
			foreach ( $overrides as $name => $override ) {
				$data[ "{$context}: {$name}" ] = array_merge( array( $path ), $override );
			}
		}

		return $data;
	}

	public function test_random_redirect_ignores_an_offset_set_by_a_theme() {
		add_action(
			'pre_get_posts',
			static function ( $query ) {
				if ( $query->is_main_query() ) {
					$query->set( 'offset', 50 );
				}
			}
		);

		$this->go_to( home_url( '/random/category/news/' ) );

		$this->assertSame( get_permalink( self::$news_post ), $this->redirect_location, 'An offset beyond the eligible posts would prevent the redirect.' );
	}

	/*
	 * Redirect locations.
	 */

	public function test_no_redirect_when_the_permalink_is_on_another_site() {
		add_filter(
			'post_link',
			static function () {
				return 'https://elsewhere.example/post/';
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'Off-site permalinks must not fall back to a redirect to the dashboard.' );
	}

	public function test_redirect_to_a_permalink_on_an_allowed_host() {
		add_filter(
			'allowed_redirect_hosts',
			static function ( $hosts ) {
				$hosts[] = 'cdn.example.org';
				return $hosts;
			}
		);
		add_filter(
			'post_link',
			static function () {
				return 'https://cdn.example.org/post/';
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertSame( 'https://cdn.example.org/post/', $this->redirect_location );
	}

	public function test_no_redirect_when_the_main_query_has_no_post() {
		add_filter( 'the_posts', '__return_empty_array' );
		$this->go_to( home_url( '/random/' ) );

		// A global post set by a plugin must not be used as the redirect target.
		$GLOBALS['post'] = get_post( self::$news_post );
		wp_random_content_redirect();

		$this->assertNotRedirected();
	}

	/*
	 * Randomable post types: edge cases.
	 */

	public function test_random_search_excludes_randomable_post_types_excluded_from_search() {
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
				'post_title' => 'Sport unsearchable',
			)
		);

		$this->go_to( home_url( '/?s=Sport&random' ) );

		$this->assertSame( get_permalink( self::$sport_post ), $this->redirect_location, 'Random search results should match the search results.' );
	}

	public function test_randomable_post_type_excluded_from_search_can_be_requested_directly() {
		register_post_type(
			'wptests_unsearchable',
			array(
				'public'              => true,
				'exclude_from_search' => true,
			)
		);
		$post_id = $this->create_post( array( 'post_type' => 'wptests_unsearchable' ) );

		$this->go_to( home_url( '/?random&post_type=wptests_unsearchable' ) );

		$this->assertSame( get_permalink( $post_id ), $this->redirect_location );
	}

	public function test_random_post_types_include_attachments_for_attachment_mime_type_taxonomies() {
		$filter = static function ( $args, $post_type ) {
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

		$post_types = wp_get_random_content_post_types( $GLOBALS['wp_query'] );

		remove_filter( 'register_post_type_args', $filter, 10 );
		create_initial_post_types();

		$this->assertSame( array( 'attachment' ), $post_types );
	}

	public function test_non_string_post_types_set_by_plugins_are_ignored() {
		add_action(
			'pre_get_posts',
			static function ( $query ) {
				if ( $query->is_main_query() ) {
					$query->set( 'post_type', array( 'post', array( 'page' ), 5, null ) );
				}
			}
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
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
	public function test_wp_is_random_content_redirect_enabled_returns_a_boolean( $value, $expected ) {
		add_filter(
			'wp_enable_random_content_redirect',
			static function () use ( $value ) {
				return $value;
			}
		);

		$this->assertSame( $expected, wp_is_random_content_redirect_enabled() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array>
	 */
	public function data_enabled_filter_values() {
		return array(
			'default true' => array( true, true ),
			'false'        => array( false, false ),
			'zero'         => array( 0, false ),
			'empty string' => array( '', false ),
			'one'          => array( 1, true ),
			'string yes'   => array( 'yes', true ),
		);
	}

	public function test_no_redirect_when_disabled_without_flushing_the_rewrite_rules() {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected( 'Stale rewrite rules must not trigger a redirect.' );
		$this->assertNotSame( 'rand', get_query_var( 'orderby' ), 'Stale rewrite rules must not randomize the query.' );
	}

	/*
	 * Conflicts with pages: lifecycle guards.
	 */

	public function test_creating_a_page_named_random_flushes_the_rewrite_rules() {
		$flushes = new MockAction();
		add_filter( 'pre_update_option_rewrite_rules', array( $flushes, 'filter' ) );

		$this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'random',
			)
		);

		$this->assertSame( 1, $flushes->get_call_count() );
	}

	public function test_page_named_random_does_not_flush_rewrite_rules_with_plain_permalinks() {
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

	public function test_page_named_random_does_not_flush_rewrite_rules_when_disabled() {
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

	public function test_page_at_custom_random_base_takes_precedence() {
		$GLOBALS['wp_rewrite']->random_base = 'surprise-me';
		flush_rewrite_rules();

		$page = $this->create_post(
			array(
				'post_type' => 'page',
				'post_name' => 'surprise-me',
			)
		);

		$this->go_to( home_url( '/surprise-me/' ) );

		$this->assertNotRedirected();
		$this->assertSame( $page, get_queried_object_id() );
	}

	public function test_page_named_random_does_not_conflict_with_a_custom_random_base() {
		$GLOBALS['wp_rewrite']->random_base = 'surprise-me';
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
		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ) );
	}

	public function test_private_page_named_random_does_not_take_precedence() {
		$this->create_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'random',
				'post_status' => 'private',
			)
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertRedirectedToOneOf( array( self::$news_post, self::$sport_post ), 'Pages visitors cannot view should not replace random redirects.' );
	}

	public function test_password_protected_page_named_random_takes_precedence() {
		$page = $this->create_post(
			array(
				'post_type'     => 'page',
				'post_name'     => 'random',
				'post_password' => 'secret',
			)
		);

		$this->go_to( home_url( '/random/' ) );

		$this->assertNotRedirected();
		$this->assertSame( $page, get_queried_object_id() );
	}

	/*
	 * Paths beneath the random base without random rules.
	 */

	/**
	 * @dataProvider data_unsupported_random_paths
	 *
	 * @param string $path Request path.
	 */
	public function test_no_redirect_for_paths_without_random_rules( $path ) {
		$this->go_to( home_url( $path ) );

		$this->assertNotRedirected();
		$this->assertArrayNotHasKey( 'random', $GLOBALS['wp']->query_vars );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_unsupported_random_paths() {
		return array(
			'pagination' => array( '/random/page/2/' ),
			'feed'       => array( '/random/feed/' ),
		);
	}

	public function test_random_requests_without_a_post_are_not_indexed() {
		$headers = new MockAction();
		add_filter( 'wp_headers', array( $headers, 'filter' ), 1000 );

		$this->go_to( home_url( '/random/category/empty/' ) );

		$sent = $headers->get_args()[0][0];
		$this->assertNotRedirected();
		$this->assertSame( 'noindex, follow', $sent['X-Robots-Tag'] ?? null, 'The empty random archive is not canonical content.' );
	}
}
