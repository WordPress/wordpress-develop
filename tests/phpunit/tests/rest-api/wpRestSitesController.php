<?php
/**
 * Unit tests covering WP_REST_Sites_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 *
 * @since 7.2.0
 *
 * @group restapi
 *
 * @coversDefaultClass WP_REST_Sites_Controller
 */
class WP_Test_REST_Sites_Controller extends WP_Test_REST_Controller_Testcase {

	protected static $superadmin_id;

	/**
	 * @var WP_REST_Sites_Controller
	 */
	protected $endpoint;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);

		update_site_option( 'site_admins', array( 'superadmin' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$superadmin_id );
	}

	public function set_up() {
		parent::set_up();
		$this->endpoint = new WP_REST_Sites_Controller();
	}

	/**
	 * @ticket 40365
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/sites', $routes );
		$this->assertCount( 2, $routes['/wp/v2/sites'] );
		$this->assertArrayHasKey( '/wp/v2/sites/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/sites/(?P<id>[\d]+)'] );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_context_param
	 * @group ms-required
	 */
	public function test_context_param() {
		wp_set_current_user( self::$superadmin_id );
		// Collection
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$blog_id  = self::factory()->blog->create();
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items() {
		wp_set_current_user( self::$superadmin_id );
		self::factory()->blog->create_many( 6 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 7, $sites );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_item
	 * @group ms-required
	 */
	public function test_get_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/nulla/' ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$site = get_site( $blog_id );

		$this->assertEquals( $blog_id, $data['id'] );
		$this->assertEquals( $site->domain, $data['domain'] );
		$this->assertEquals( '/nulla/', $data['path'] );
		$this->assertEquals( 1, $data['network'] );
		$this->assertEquals( 1, $data['public'] );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-excluded
	 */
	public function test_get_items_no_ms() {
		wp_set_current_user( self::$superadmin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_item
	 * @group ms-excluded
	 */
	public function test_get_item_no_ms() {
		wp_set_current_user( self::$superadmin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/1' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * An unknown ID is a 404, not an empty site.
	 *
	 * @ticket 40365
	 * @covers ::get_item
	 * @group ms-required
	 */
	public function test_get_item_invalid_id() {
		wp_set_current_user( self::$superadmin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-required
	 */
	public function test_create_item() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/tempor/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( '/tempor/', $data['path'] );
		$this->assertEquals( WP_TESTS_DOMAIN, $data['domain'] );

		$site = get_site( $data['id'] );

		$this->assertNotNull( $site );
		$this->assertEquals( '/tempor/', $site->path );
	}

	/**
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 */
	public function test_update_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/eiusmod/' ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'path', '/incididunt/' );
		$request->set_param( 'mature', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( '/incididunt/', $data['path'] );
		$this->assertEquals( 1, $data['mature'] );
		$this->assertEquals( '/incididunt/', get_site( $blog_id )->path );
	}

	/**
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-excluded
	 */
	public function test_create_item_no_ms() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/tempor/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-excluded
	 */
	public function test_update_item_no_ms() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/1' );
		$request->set_param( 'path', '/incididunt/' );
		$request->set_param( 'mature', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::delete_item
	 * @group ms-required
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/amet/' ) );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertTrue( $data['deleted'] );
		$this->assertEquals( $blog_id, $data['previous']['id'] );
		$this->assertNull( get_site( $blog_id ) );
	}

	/**
	 * @ticket 40365
	 * @covers ::delete_item
	 * @group ms-excluded
	 */
	public function test_delete_item_no_ms() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/1' );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * Deleting a site drops its tables.
	 *
	 * @ticket 40365
	 * @covers ::delete_item
	 * @group ms-required
	 */
	public function test_delete_item_uninitializes_the_site() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/aliqua/' ) );

		$this->assertTrue( wp_is_site_initialized( $blog_id ) );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( wp_is_site_initialized( $blog_id ) );
	}

	/**
	 * Sites have no trash, so deleting has to be explicit.
	 *
	 * @ticket 40365
	 * @covers ::delete_item
	 * @group ms-required
	 */
	public function test_delete_item_requires_force() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/consectetur/' ) );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );
		$this->assertNotNull( get_site( $blog_id ) );
	}

	/**
	 * The main site of a network holds the network together.
	 *
	 * @ticket 40365
	 * @covers ::delete_item
	 * @group ms-required
	 */
	public function test_delete_main_site_is_not_allowed() {
		wp_set_current_user( self::$superadmin_id );

		$main_site_id = get_main_site_id();

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $main_site_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete_main_site', $response, 403 );
		$this->assertNotNull( get_site( $main_site_id ) );
	}

	/**
	 * @ticket 40365
	 * @covers ::prepare_item_for_response
	 * @group ms-required
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/labore/' ) );
		$site    = get_site( $blog_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'edit' );

		$data = $this->endpoint->prepare_item_for_response( $site, $request )->get_data();

		$this->assertEquals( (int) $site->blog_id, $data['id'] );
		$this->assertEquals( (int) $site->site_id, $data['network'] );
		$this->assertEquals( $site->domain, $data['domain'] );
		$this->assertEquals( $site->path, $data['path'] );
		$this->assertEquals( mysql_to_rfc3339( $site->registered ), $data['registered_gmt'] );
		$this->assertEquals( $site->blogname, $data['blogname'] );
		$this->assertEquals( $site->home, $data['home'] );
		$this->assertEquals( $site->siteurl, $data['siteurl'] );
		$this->assertIsBool( $data['public'] );
		$this->assertIsInt( $data['post_count'] );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$expected = array(
			'id',
			'network',
			'domain',
			'path',
			'registered',
			'registered_gmt',
			'last_updated',
			'last_updated_gmt',
			'public',
			'archived',
			'mature',
			'spam',
			'deleted',
			'lang_id',
			'blogname',
			'siteurl',
			'home',
			'post_count',
			'title',
			'user_id',
			'meta',
		);

		if ( ! is_site_meta_supported() ) {
			// The meta property is only registered when the blogmeta table exists.
			$expected = array_values( array_diff( $expected, array( 'meta' ) ) );
		}

		$this->assertEqualSets( $expected, array_keys( $properties ) );
		$this->assertTrue( $properties['id']['readonly'] );
		$this->assertTrue( $properties['registered']['readonly'] );
		$this->assertTrue( $properties['blogname']['readonly'] );
		$this->assertEquals( 'boolean', $properties['public']['type'] );
		$this->assertEquals( 'string', $properties['domain']['type'] );

		// Write-only, so they carry no context.
		$this->assertSame( array(), $properties['title']['context'] );
		$this->assertSame( array(), $properties['user_id']['context'] );
	}

	/**
	 * The status flags are booleans, the dates are RFC3339 with a GMT counterpart.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_response
	 * @group ms-required
	 */
	public function test_get_item_uses_the_schema_types() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/tempora/' ) );
		$site    = get_site( $blog_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		foreach ( array( 'public', 'archived', 'mature', 'spam', 'deleted' ) as $flag ) {
			$this->assertIsBool( $data[ $flag ], $flag );
		}

		$this->assertEquals( mysql_to_rfc3339( $site->registered ), $data['registered_gmt'] );
		$this->assertEquals( mysql_to_rfc3339( get_date_from_gmt( $site->registered ) ), $data['registered'] );
		$this->assertEquals( mysql_to_rfc3339( $site->last_updated ), $data['last_updated_gmt'] );
	}

	/**
	 * The collection can be narrowed down by status.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_filter_by_status() {
		wp_set_current_user( self::$superadmin_id );

		$archived = self::factory()->blog->create( array( 'path' => '/dolores/' ) );
		self::factory()->blog->create( array( 'path' => '/nemo/' ) );

		wp_update_site( $archived, array( 'archived' => 1 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'archived', true );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertEquals( $archived, $data[0]['id'] );
		$this->assertEquals( 1, (int) $response->get_headers()['X-WP-Total'] );

		$request->set_param( 'archived', false );

		$response = rest_get_server()->dispatch( $request );

		$this->assertNotContains( $archived, wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * Without the parameter the status does not narrow anything.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_without_status_filter_returns_every_site() {
		wp_set_current_user( self::$superadmin_id );

		$archived = self::factory()->blog->create( array( 'path' => '/officiis/' ) );

		wp_update_site( $archived, array( 'archived' => 1 ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertContains( $archived, wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * The language IDs narrow the collection, in both directions.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_filter_by_lang_id() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/magni/' ) );

		wp_update_site( $blog_id, array( 'lang_id' => 7 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'lang_id', array( 7 ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertEquals( $blog_id, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'lang_id_exclude', array( 7 ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertNotContains( $blog_id, wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * Registration dates are GMT, so the boundaries are read as GMT.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_filter_by_registration_date() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/harum/' ) );

		wp_update_site( $blog_id, array( 'registered' => '2019-06-01 12:00:00' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'before', '2019-07-01T00:00:00Z' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertEquals( $blog_id, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'after', '2019-07-01T00:00:00Z' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertNotContains( $blog_id, wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * A created site gets the title and the administrator that were asked for.
	 *
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-required
	 */
	public function test_create_item_sets_the_title_and_the_administrator() {
		wp_set_current_user( self::$superadmin_id );

		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/voluptas/' );
		$request->set_param( 'title', 'Voluptas' );
		$request->set_param( 'user_id', $user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$blog_id = $response->get_data()['id'];

		switch_to_blog( $blog_id );
		$blogname = get_option( 'blogname' );
		$is_admin = user_can( $user_id, 'manage_options' );
		restore_current_blog();

		$this->assertEquals( 'Voluptas', $blogname );
		$this->assertTrue( $is_admin );
		$this->assertTrue( is_user_member_of_blog( $user_id, $blog_id ) );
	}

	/**
	 * An unknown administrator is refused before the site is created.
	 *
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-required
	 */
	public function test_create_item_rejects_an_unknown_user_id() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/quisquam/' );
		$request->set_param( 'user_id', 99999 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_site_invalid_user_id', $response->get_data()['code'] );

		// The check runs before wp_insert_site(), so nothing was created.
		$this->assertEquals( 0, get_blog_id_from_url( WP_TESTS_DOMAIN, '/quisquam/' ) );
	}

	/**
	 * Title and administrator belong to creation, an update does not accept them.
	 *
	 * @ticket 40365
	 * @covers  ::update_item
	 */
	public function test_update_item_does_not_accept_the_creation_fields() {
		wp_set_current_user( self::$superadmin_id );

		$routes = rest_get_server()->get_routes();
		$args   = array();

		foreach ( $routes['/wp/v2/sites/(?P<id>[\d]+)'] as $handler ) {
			if ( ! empty( $handler['methods']['PUT'] ) ) {
				$args = $handler['args'];
			}
		}

		$this->assertArrayNotHasKey( 'title', $args );
		$this->assertArrayNotHasKey( 'user_id', $args );
		$this->assertArrayHasKey( 'domain', $args );
	}

	/**
	 * Filtering by user narrows the total, not just the current page.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_me_filter_reports_the_filtered_total() {
		$blog_ids = self::factory()->blog->create_many( 3 );
		$user_id  = self::factory()->user->create();

		wp_set_current_user( $user_id );

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}

		self::factory()->blog->create_many( 2 );

		$expected = count( get_blogs_of_user( $user_id ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );

		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertLessThan( (int) get_sites( array( 'count' => true ) ), $expected );
		$this->assertCount( $expected, $response->get_data() );
		$this->assertEquals( $expected, (int) $headers['X-WP-Total'] );
	}

	/**
	 * A user without sites gets nothing, not everything.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_filter_user_without_sites() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create_many( 3 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 0, $response->get_data() );
		$this->assertEquals( 0, (int) $headers['X-WP-Total'] );
	}

	/**
	 * A site links to itself and to the collection.
	 *
	 * @ticket 40365
	 * @covers ::prepare_links
	 * @group ms-required
	 */
	public function test_get_item_has_links() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/veniam/' ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'self', $links );
		$this->assertArrayHasKey( 'collection', $links );
		$this->assertStringEndsWith( '/wp/v2/sites/' . $blog_id, $links['self'][0]['href'] );
		$this->assertStringEndsWith( '/wp/v2/sites', $links['collection'][0]['href'] );
	}

	/**
	 * Reading a site's options means switching to it, so avoid it when the
	 * fields that need it were not asked for.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_does_not_switch_blogs_for_table_columns() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create_many( 3 );

		$switches = 0;
		$counter  = static function () use ( &$switches ) {
			++$switches;
		};

		add_action( 'switch_blog', $counter );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( '_fields', 'id,domain,path' );

		$response = rest_get_server()->dispatch( $request );

		remove_action( 'switch_blog', $counter );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 0, $switches );

		$site = $response->get_data()[0];

		foreach ( array( 'blogname', 'siteurl', 'home', 'post_count', 'meta' ) as $field ) {
			$this->assertArrayNotHasKey( $field, $site );
		}

		$this->assertArrayHasKey( 'domain', $site );
	}

	/**
	 * A HEAD request answers with the headers and an empty body.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_head_request_returns_no_body() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create_many( 2 );

		$request  = new WP_REST_Request( 'HEAD', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertEquals( (int) get_sites( array( 'count' => true ) ), (int) $headers['X-WP-Total'] );
	}

	/**
	 * A HEAD request on a single site answers with an empty body, and the
	 * fields that need a switch stay untouched.
	 *
	 * @ticket 40365
	 * @covers ::get_item
	 * @group ms-required
	 */
	public function test_head_request_on_a_single_site_returns_no_body() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/quidem/' ) );

		$switches = 0;
		$counter  = static function () use ( &$switches ) {
			++$switches;
		};

		add_action( 'switch_blog', $counter );

		$request  = new WP_REST_Request( 'HEAD', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		remove_action( 'switch_blog', $counter );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertSame( array(), $response->get_links() );
		$this->assertSame( 0, $switches );
	}

	/**
	 * The collection is ordered by ID, ascending.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_are_ordered_ascending() {
		wp_set_current_user( self::$superadmin_id );

		$blog_ids = self::factory()->blog->create_many( 3 );
		array_unshift( $blog_ids, 1 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $blog_ids, wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * Ordering by an ID list falls back when there is no list.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_orderby_id_list_without_a_list() {
		wp_set_current_user( self::$superadmin_id );

		foreach ( array( 'site__in', 'network__in' ) as $orderby ) {
			$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
			$request->set_param( 'orderby', $orderby );

			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 200, $response->get_status(), $orderby );
			$this->assertNotEmpty( $response->get_data(), $orderby );
		}
	}

	/**
	 * The data is stored as sent, without added slashes.
	 *
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 */
	public function test_update_item_does_not_slash_the_stored_data() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/sit/' ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'path', "/o'brien/" );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( "/o'brien/", get_site( $blog_id )->path );
	}

	/**
	 * The status fields are stored when a site is created.
	 *
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-required
	 */
	public function test_create_item_stores_the_status_fields() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/dolor/' );
		$request->set_param( 'public', 0 );
		$request->set_param( 'archived', 1 );
		$request->set_param( 'lang_id', 7 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$site = get_site( $data['id'] );

		$this->assertEquals( 0, $site->public );
		$this->assertEquals( 1, $site->archived );
		$this->assertEquals( 7, $site->lang_id );
	}

	/**
	 * A partial update must not touch fields the request left out.
	 *
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 */
	public function test_update_item_keeps_fields_that_were_not_sent() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/lorem/' ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'archived', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 1, $data['archived'] );
		$this->assertEquals( '/lorem/', $data['path'] );
		$this->assertEquals( '/lorem/', get_site( $blog_id )->path );
	}

	/**
	 * The domain is left alone when the request does not carry one.
	 *
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 */
	public function test_update_item_keeps_the_domain() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/ipsum/' ) );
		$domain  = get_site( $blog_id )->domain;

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'public', 0 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $domain, get_site( $blog_id )->domain );
		$this->assertEquals( 0, get_site( $blog_id )->public );
	}

	/**
	 * Site meta is exposed through the endpoint.
	 *
	 * Registering under the `blog` meta type is what `add_site_meta()` and
	 * `get_site_meta()` do, so the controller has to read the same type.
	 *
	 * @ticket 40365
	 * @covers ::get_item
	 */
	public function test_get_item_exposes_site_meta() {
		if ( ! is_site_meta_supported() ) {
			$this->markTestSkipped( 'Site meta is not supported on this installation.' );
		}

		wp_set_current_user( self::$superadmin_id );

		register_meta(
			'blog',
			'rest_test_site_meta',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			)
		);

		$blog_id = self::factory()->blog->create();
		update_site_meta( $blog_id, 'rest_test_site_meta', 'from blogmeta' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'rest_test_site_meta', $data['meta'] );
		$this->assertEquals( 'from blogmeta', $data['meta']['rest_test_site_meta'] );

		unregister_meta_key( 'blog', 'rest_test_site_meta' );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_user_site_ids
	 */
	public function test_invalid_user_input() {
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( false ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( 0 ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( '' ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( 999 ) );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_user_site_ids
	 * @group ms-required
	 */
	public function test_valid_user_input() {

		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();
		array_unshift( $blog_ids, 1 );
		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}

		$this->assertEquals( $blog_ids, $this->endpoint->get_user_site_ids( $user_id ) );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_filter_user() {
		wp_set_current_user( self::$superadmin_id );
		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		array_unshift( $blog_ids, 1 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) $user_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 6, $sites );
		$this->assertEquals( $blog_ids, wp_list_pluck( $sites, 'id' ) );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_me_filter_user() {

		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();
		wp_set_current_user( $user_id );
		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		array_unshift( $blog_ids, 1 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 6, $sites );
		$this->assertEquals( $blog_ids, wp_list_pluck( $sites, 'id' ) );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 */
	public function test_get_items_filter_user_no_access() {

		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();
		$user_id2 = self::factory()->user->create();
		wp_set_current_user( $user_id2 );

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		array_unshift( $blog_ids, 1 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) $user_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_filter_with_includes_user() {
		wp_set_current_user( self::$superadmin_id );
		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) $user_id );
		$request->set_param( 'include', $blog_ids[0] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 1, $sites );
		$this->assertEquals( array( $blog_ids[0] ), wp_list_pluck( $sites, 'id' ) );
	}
}
