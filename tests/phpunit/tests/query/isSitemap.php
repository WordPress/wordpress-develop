<?php

/**
 * Tests for the is_sitemap() conditional tag and the WP_Query::$is_sitemap property.
 *
 * This exercises both query.php and class-wp-query.php: query vars are fed through
 * WP_Query, then the effects on the wp_query object are tested.
 *
 * @group query
 * @group sitemaps
 */
class Tests_Query_IsSitemap extends WP_UnitTestCase {

	/**
	 * Set up the shared fixture.
	 *
	 * Published posts are required so that a sitemap request does not turn into a
	 * 404 in WP::handle_404(), which would reset the is_sitemap flag via set_404().
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$factory->post->create_many( 3 );
	}

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		create_initial_taxonomies();
	}

	/**
	 * The property defaults to false on a freshly initialized query.
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::is_sitemap
	 */
	public function test_is_sitemap_defaults_to_false(): void {
		$query = new WP_Query();

		$this->assertFalse( $query->is_sitemap, 'The $is_sitemap property should default to false.' );
		$this->assertFalse( $query->is_sitemap(), 'WP_Query::is_sitemap() should return false by default.' );
	}

	/**
	 * The property gets reset when initialized.
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::init
	 * @covers WP_Query::init_query_flags
	 */
	public function test_is_sitemap_gets_reset_to_false(): void {
		$query = new WP_Query();

		$query->is_sitemap = true;
		$query->init();
		$this->assertFalse( $query->is_sitemap, 'The $is_sitemap property should initialize as false.' );
	}

	/**
	 * The flag is set when the "sitemap" query var is present (sitemap index route).
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::parse_query
	 * @covers WP_Query::is_sitemap
	 */
	public function test_is_sitemap_true_for_sitemap_index(): void {
		$query = new WP_Query( array( 'sitemap' => 'index' ) );

		$this->assertTrue( $query->is_sitemap, 'The $is_sitemap property should be true for a sitemap query.' );
		$this->assertTrue( $query->is_sitemap(), 'WP_Query::is_sitemap() should return true for a sitemap query.' );
	}

	/**
	 * The flag is set for a sitemap subtype route (e.g. wp-sitemap-posts-post-1.xml).
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::parse_query
	 * @covers WP_Query::is_sitemap
	 */
	public function test_is_sitemap_true_for_sitemap_subtype(): void {
		$query = new WP_Query(
			array(
				'sitemap'         => 'posts',
				'sitemap-subtype' => 'post',
				'paged'           => 1,
			)
		);

		$this->assertTrue( $query->is_sitemap(), 'WP_Query::is_sitemap() should return true for a sitemap subtype query.' );
	}

	/**
	 * An empty "sitemap" query var must not set the flag.
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::parse_query
	 */
	public function test_is_sitemap_false_for_empty_sitemap_var(): void {
		$query = new WP_Query( array( 'sitemap' => '' ) );

		$this->assertFalse( $query->is_sitemap(), 'An empty "sitemap" query var should not set is_sitemap.' );
	}

	/**
	 * The sitemap stylesheet route uses the "sitemap-stylesheet" query var, which must
	 * not flag the query as a sitemap.
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::parse_query
	 */
	public function test_is_sitemap_false_for_stylesheet_route(): void {
		$query = new WP_Query( array( 'sitemap-stylesheet' => 'sitemap' ) );

		$this->assertFalse( $query->is_sitemap(), 'The sitemap stylesheet route should not flag the query as a sitemap.' );
	}

	/**
	 * is_robots takes precedence over is_sitemap in the parse_query branch.
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::parse_query
	 */
	public function test_robots_takes_precedence_over_sitemap(): void {
		$query = new WP_Query(
			array(
				'robots'  => true,
				'sitemap' => 'index',
			)
		);

		$this->assertTrue( $query->is_robots(), 'is_robots() should be true when the robots query var is set.' );
		$this->assertFalse( $query->is_sitemap(), 'is_sitemap() should be false when is_robots() takes precedence.' );
	}

	/**
	 * A regular query is never flagged as a sitemap.
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::is_sitemap
	 */
	public function test_is_sitemap_false_for_regular_query(): void {
		$post_id = self::factory()->post->create();

		$query = new WP_Query( array( 'p' => $post_id ) );

		$this->assertFalse( $query->is_sitemap(), 'A regular post query should not be flagged as a sitemap.' );
	}

	/**
	 * A sitemap query must not also be treated as the home/front page.
	 *
	 * This is the practical motivation for the conditional tag: distinguishing a
	 * sitemap request from the home page (see #51542).
	 *
	 * @ticket 51543
	 *
	 * @covers WP_Query::parse_query
	 */
	public function test_sitemap_query_is_not_home(): void {
		$query = new WP_Query( array( 'sitemap' => 'index' ) );

		$this->assertTrue( $query->is_sitemap(), 'The sitemap query should be flagged as a sitemap.' );
		$this->assertFalse( $query->is_home(), 'A sitemap query should not be treated as the home page.' );
		$this->assertFalse( $query->is_front_page(), 'A sitemap query should not be treated as the front page.' );
	}

	/**
	 * The global is_sitemap() conditional tag reflects the main query.
	 *
	 * @ticket 51543
	 *
	 * @covers ::is_sitemap
	 */
	public function test_global_is_sitemap_reflects_main_query(): void {
		// Prevent WP_Sitemaps from rendering and calling exit during go_to().
		remove_action( 'template_redirect', array( wp_sitemaps_get_server(), 'render_sitemaps' ) );

		$this->go_to( home_url( '/?sitemap=index' ) );

		$this->assertTrue( is_sitemap(), 'is_sitemap() should be true on a sitemap request.' );

		// is_sitemap should be the only conditional that is true for a sitemap request.
		$this->assertQueryTrue( 'is_sitemap' );
	}

	/**
	 * The global is_sitemap() conditional tag is false for a non-sitemap request.
	 *
	 * @ticket 51543
	 *
	 * @covers ::is_sitemap
	 */
	public function test_global_is_sitemap_false_on_home(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertFalse( is_sitemap(), 'is_sitemap() should be false on the home page.' );
		$this->assertTrue( is_home(), 'is_home() should be true on the home page.' );
	}

	/**
	 * The global is_sitemap() returns false and triggers a notice when the query
	 * has not yet run.
	 *
	 * @ticket 51543
	 *
	 * @covers ::is_sitemap
	 *
	 * @expectedIncorrectUsage is_sitemap
	 */
	public function test_global_is_sitemap_before_query_is_run(): void {
		$wp_query_temp = $GLOBALS['wp_query'];
		unset( $GLOBALS['wp_query'] );

		$result = is_sitemap();

		$GLOBALS['wp_query'] = $wp_query_temp;

		$this->assertFalse( $result, 'is_sitemap() should return false before the query is run.' );
	}
}
