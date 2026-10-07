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
	 * Get reflective access to a private/protected method on
	 * the WP_REST_Sites_Controller class.
	 *
	 * @param string $method_name Method name for which to gain access.
	 * @return ReflectionMethod
	 * @throws ReflectionException Throws an exception if method does not exist.
	 */
	protected function get_reflective_method( $method_name ) {
		$class  = new ReflectionClass( WP_REST_Sites_Controller::class );
		$method = $class->getMethod( $method_name );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method;
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
	 * Only sites on the current network are returned.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_defaults_to_the_current_network() {
		wp_set_current_user( self::$superadmin_id );

		$current_network_site = self::factory()->blog->create( array( 'path' => '/current/' ) );

		$other_network_id = self::factory()->network->create(
			array(
				'domain' => 'other-network.example.org',
				'path'   => '/',
			)
		);

		$other_network_site = self::factory()->blog->create(
			array(
				'domain'     => 'other-network.example.org',
				'path'       => '/',
				'network_id' => $other_network_id,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$site_ids = wp_list_pluck( $data, 'id' );

		$this->assertContains( $current_network_site, $site_ids, 'Sites on the current network should be returned.' );
		$this->assertNotContains( $other_network_site, $site_ids, 'Sites on another network should not be returned.' );

		$current_network_id = get_current_network_id();
		foreach ( $data as $site ) {
			$this->assertSame( $current_network_id, $site['network'], 'Every returned site should belong to the current network.' );
		}
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
	 * The multisite check runs before the logged-in check, so a logged-out
	 * request against a single-site install reports the environment problem,
	 * not a generic authentication error.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-excluded
	 */
	public function test_get_items_no_ms_when_logged_out() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * A logged-out request with no `user` filter has neither `manage_sites`
	 * nor a matching own-user filter, so it's still forbidden.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 */
	public function test_get_items_forbidden_when_logged_out() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * The `user=me` own-sites filter requires being logged in, same as any
	 * other collection request - it is not an anonymous-access bypass.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 */
	public function test_get_items_me_filter_forbidden_when_logged_out() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * A non-numeric `user` value is rejected by validation, so it never reaches
	 * the permission check, where an `(int)` cast to `0` could match a logged-out
	 * request's `get_current_user_id()`.
	 *
	 * @ticket 40365
	 * @covers ::get_user_param_schema
	 * @group ms-required
	 */
	public function test_get_items_rejects_a_non_numeric_user() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'xyz' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
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
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-excluded
	 */
	public function test_get_item_no_ms_when_logged_out() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/1' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * A logged-out request can't be a site member and lacks `manage_sites`,
	 * so it's forbidden even in the default view context.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_forbidden_when_logged_out() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/1' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_edit_context_forbidden_when_logged_out() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/1' );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * A member of the site (even without `manage_sites`) can view it in
	 * the default view context.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_view_context_allowed_for_site_member() {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );

		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * A member of the site without `manage_sites` can only use the view
	 * context, so the `embed` context is forbidden.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_embed_context_forbidden_for_site_member() {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'embed' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * A logged-in user who is neither a super admin nor a member of the
	 * site is forbidden from viewing it, even in the default view context.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_view_context_forbidden_for_non_member() {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * Site membership only grants the default view context; edit context
	 * still requires `manage_sites`.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_edit_context_forbidden_for_site_member() {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * A logged-in user who is neither a super admin nor a member of the
	 * site is still forbidden from viewing it in edit context.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_edit_context_forbidden_for_non_member() {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
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
	 * Unauthorized requests get the same error for existing and unknown IDs,
	 * so they can't reveal which sites exist.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @covers ::update_item_permissions_check
	 * @covers ::delete_item_permissions_check
	 * @group ms-required
	 *
	 * @dataProvider data_unauthorized_item_requests
	 *
	 * @param string $method        HTTP method.
	 * @param string $expected_code Expected error code.
	 */
	public function test_unauthorized_item_request_does_not_reveal_site_existence( $method, $expected_code ) {
		$site_id = self::factory()->blog->create();
		wp_set_current_user( 0 );

		foreach ( array( $site_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) as $id ) {
			$request  = new WP_REST_Request( $method, '/wp/v2/sites/' . $id );
			$response = rest_get_server()->dispatch( $request );

			$this->assertErrorResponse( $expected_code, $response, 401 );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_unauthorized_item_requests() {
		return array(
			'read'   => array( 'GET', 'rest_forbidden_context' ),
			'update' => array( 'PUT', 'rest_cannot_edit' ),
			'delete' => array( 'DELETE', 'rest_cannot_delete' ),
		);
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
	 * @covers ::create_item
	 * @group ms-required
	 */
	public function test_create_item_rejects_an_existing_domain_and_path() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/existing/',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/existing/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_taken', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 */
	public function test_update_item_rejects_another_sites_domain_and_path() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/taken/',
			)
		);
		$blog_id = self::factory()->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/free/',
			)
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/taken/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_taken', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 */
	public function test_update_item_allows_a_site_to_keep_its_own_domain_and_path() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/keep/',
			)
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/keep/' );
		$request->set_param( 'mature', 1 );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( '/keep/', $data['path'] );
		$this->assertEquals( 1, $data['mature'] );
	}

	/**
	 * Data provider for test_check_url_is_available_on_create().
	 *
	 * Every case runs against the same two fixture sites: one at
	 * WP_TESTS_DOMAIN . '/check-url-taken/' and one at WP_TESTS_DOMAIN . '/'
	 * (the root), so a candidate path of `null` (omitted entirely) can
	 * exercise the "defaults to root" fallback against the second fixture.
	 *
	 * @return array[]
	 */
	public function data_check_url_is_available_on_create() {
		return array(
			'a brand new domain and path is available' => array(
				'check-url-is-available.example',
				'/',
				false,
			),
			'an existing domain and path is rejected'  => array(
				WP_TESTS_DOMAIN,
				'/check-url-taken/',
				true,
			),
			'omitting path defaults to root, which is also taken' => array(
				WP_TESTS_DOMAIN,
				null,
				true,
			),
		);
	}

	/**
	 * @ticket 40365
	 * @covers ::check_url_is_available
	 * @group ms-required
	 * @dataProvider data_check_url_is_available_on_create
	 *
	 * @param string $candidate_domain Domain to check.
	 * @param string|null $candidate_path Path to check, or null to omit the `path` key entirely.
	 * @param bool $expect_conflict Whether a `rest_site_taken` error is expected.
	 */
	public function test_check_url_is_available_on_create( $candidate_domain, $candidate_path, $expect_conflict ) {
		self::factory()->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/check-url-taken/',
			)
		);
		// WP_TESTS_DOMAIN . '/' is already taken by the main site.

		$prepared_site = array( 'domain' => $candidate_domain );
		if ( null !== $candidate_path ) {
			$prepared_site['path'] = $candidate_path;
		}

		$method  = $this->get_reflective_method( 'check_url_is_available' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		$result = $method->invoke( $this->endpoint, $prepared_site, $request );

		if ( $expect_conflict ) {
			$this->assertWPError( $result );
			$this->assertSame( 'rest_site_taken', $result->get_error_code() );
			$this->assertSame( 400, $result->get_error_data()['status'] );
		} else {
			$this->assertTrue( $result );
		}
	}

	/**
	 * Data provider for test_check_url_is_available_on_update().
	 *
	 * Both cases run against the same two fixture sites: the one being
	 * updated (WP_TESTS_DOMAIN . '/check-url-free/') and another site
	 * occupying WP_TESTS_DOMAIN . '/check-url-taken-2/'.
	 *
	 * @return array[]
	 */
	public function data_check_url_is_available_on_update() {
		return array(
			'keeps its own domain and path via fallback (no conflict)' => array(
				array(),
				false,
			),
			'changes to another site\'s domain and path (conflict)'    => array(
				array(
					'domain' => WP_TESTS_DOMAIN,
					'path'   => '/check-url-taken-2/',
				),
				true,
			),
		);
	}

	/**
	 * @ticket 40365
	 * @covers ::check_url_is_available
	 * @group ms-required
	 * @dataProvider data_check_url_is_available_on_update
	 *
	 * @param array $prepared_site   Prepared site data to check; empty to test the fallback to the current site's own values.
	 * @param bool  $expect_conflict Whether a `rest_site_taken` error is expected.
	 */
	public function test_check_url_is_available_on_update( $prepared_site, $expect_conflict ) {
		self::factory()->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/check-url-taken-2/',
			)
		);
		$blog_id = self::factory()->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/check-url-free/',
			)
		);

		$method  = $this->get_reflective_method( 'check_url_is_available' );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'id', $blog_id );

		$result = $method->invoke( $this->endpoint, $prepared_site, $request );

		if ( $expect_conflict ) {
			$this->assertWPError( $result );
			$this->assertSame( 'rest_site_taken', $result->get_error_code() );
		} else {
			$this->assertTrue( $result );
		}
	}

	/**
	 * An invalid site ID on update propagates the WP_Error from get_site()
	 * rather than proceeding to the domain/path check.
	 *
	 * @ticket 40365
	 * @covers ::check_url_is_available
	 * @group ms-required
	 */
	public function test_check_url_is_available_propagates_an_invalid_id_on_update() {
		$method  = $this->get_reflective_method( 'check_url_is_available' );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'id', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$result = $method->invoke(
			$this->endpoint,
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/check-url-invalid-id/',
			),
			$request
		);

		$this->assertWPError( $result );
		$this->assertSame( 'rest_site_invalid_id', $result->get_error_code() );
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
		$request->set_param( 'context', 'edit' );

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
	 * @covers ::create_item_permissions_check
	 * @group ms-excluded
	 */
	public function test_create_item_no_ms_when_logged_out() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/tempor/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::create_item_permissions_check
	 * @group ms-required
	 */
	public function test_create_item_requires_being_logged_in() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/tempor/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_create', $response, 401 );
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
	 * @covers ::update_item_permissions_check
	 * @group ms-excluded
	 */
	public function test_update_item_no_ms_when_logged_out() {
		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/1' );
		$request->set_param( 'path', '/incididunt/' );
		$request->set_param( 'mature', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::update_item_permissions_check
	 * @group ms-required
	 */
	public function test_update_item_requires_being_logged_in() {
		$blog_id = self::factory()->blog->create();

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'path', '/incididunt/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );
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
	 * @ticket 40365
	 * @covers ::delete_item_permissions_check
	 * @group ms-excluded
	 */
	public function test_delete_item_no_ms_when_logged_out() {
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/1' );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_multisite_not_installed', $response, 400 );
	}

	/**
	 * @ticket 40365
	 * @covers ::delete_item_permissions_check
	 * @group ms-required
	 */
	public function test_delete_item_requires_being_logged_in() {
		$blog_id = self::factory()->blog->create();

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 401 );
		$this->assertNotNull( get_site( $blog_id ) );
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
	 * Deleting without force marks the site as deleted and removes its users,
	 * but does not drop the site.
	 *
	 * @ticket 40365
	 * @covers ::delete_item
	 * @group ms-required
	 */
	public function test_delete_item_requires_force() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/consectetur/' ) );
		$user_id = self::factory()->user->create();
		add_user_to_blog( $blog_id, $user_id, 'author' );

		$this->assertTrue( is_user_member_of_blog( $user_id, $blog_id ) );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );

		// The site still exists but is flagged as deleted.
		$site = get_site( $blog_id );
		$this->assertNotNull( $site );
		$this->assertEquals( '1', $site->deleted );

		// Its users have been removed from the blog.
		$this->assertFalse( is_user_member_of_blog( $user_id, $blog_id ) );
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
	 * A regular site administrator does not have the network-level
	 * capability required to delete a site.
	 *
	 * @ticket 40365
	 * @covers ::delete_item_permissions_check
	 * @group ms-required
	 */
	public function test_delete_item_requires_delete_sites_cap() {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, rest_authorization_required_code() );
		$this->assertNotNull( get_site( $blog_id ) );
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
		$this->assertSame( get_home_url( $blog_id ), $data['home'] );
		$this->assertSame( get_site_url( $blog_id ), $data['siteurl'] );
		$this->assertIsBool( $data['public'] );
		$this->assertIsInt( $data['post_count'] );
		$this->assertSame( get_admin_url( $blog_id ), $data['admin_url'] );
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
			'admin_url',
			'post_count',
			'meta',
		);

		if ( ! is_site_meta_supported() ) {
			// The meta property is only registered when the blogmeta table exists.
			$expected = array_values( array_diff( $expected, array( 'meta' ) ) );
		}

		$this->assertEqualSets( $expected, array_keys( $properties ) );
		$this->assertTrue( $properties['id']['readonly'] );
		$this->assertTrue( $properties['network']['readonly'] );
		$this->assertTrue( $properties['registered']['readonly'] );
		$this->assertArrayNotHasKey( 'readonly', $properties['blogname'], 'The site title is set when the site is created.' );
		$this->assertEquals( 'boolean', $properties['public']['type'] );
		$this->assertEquals( 'string', $properties['domain']['type'] );

		$this->assertTrue( $properties['admin_url']['readonly'] );
		$this->assertSame( 'uri', $properties['admin_url']['format'] );
		$this->assertSame( array( 'view', 'edit', 'embed' ), $properties['admin_url']['context'] );

		$edit_only = array(
			'registered',
			'registered_gmt',
			'last_updated',
			'last_updated_gmt',
			'archived',
			'mature',
			'spam',
			'deleted',
			'lang_id',
			'post_count',
		);

		if ( is_site_meta_supported() ) {
			$edit_only[] = 'meta';
		}

		foreach ( $edit_only as $property ) {
			$this->assertSame( array( 'edit' ), $properties[ $property ]['context'], "$property should only be in the edit context." );
		}
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

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'edit' );

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
		$request->set_param( 'blogname', 'Voluptas' );
		$request->set_param( 'user', $user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 'Voluptas', $response->get_data()['blogname'] );

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
	public function test_create_item_rejects_an_unknown_user() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/quisquam/' );
		$request->set_param( 'user', 99999 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 400 );

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

		$this->assertArrayNotHasKey( 'blogname', $args );
		$this->assertArrayNotHasKey( 'user', $args );
		$this->assertArrayNotHasKey( 'user_id', $args );
		$this->assertArrayNotHasKey( 'network', $args );
		$this->assertArrayHasKey( 'domain', $args );
	}

	/**
	 * The network is read-only, so creating a site does not accept it either.
	 *
	 * @ticket 40365
	 * @covers ::create_item
	 */
	public function test_create_item_does_not_accept_the_network_field() {
		$routes = rest_get_server()->get_routes();
		$args   = array();

		foreach ( $routes['/wp/v2/sites'] as $handler ) {
			if ( ! empty( $handler['methods']['POST'] ) ) {
				$args = $handler['args'];
			}
		}

		$this->assertArrayHasKey( 'domain', $args );
		$this->assertArrayNotHasKey( 'network', $args );
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

		foreach ( array( 'blogname', 'siteurl', 'home', 'admin_url', 'post_count', 'meta' ) as $field ) {
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

		foreach ( array( 'site__in' ) as $orderby ) {
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
		$request->set_param( 'context', 'edit' );

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
	 * The `domain` parameter must be a valid hostname or IP address,
	 * optionally followed by a port, when creating a site.
	 *
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-required
	 * @dataProvider data_domain_validation
	 */
	public function test_create_item_domain_validation( $domain, $is_valid ) {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', $domain );
		$request->set_param( 'path', '/domain-validation/' );

		$response = rest_get_server()->dispatch( $request );

		if ( $is_valid ) {
			$this->assertEquals( 201, $response->get_status(), "domain '{$domain}' should have been accepted." );

			$data = $response->get_data();
			$this->assertEquals( $this->normalize_domain_for_storage( $domain ), $data['domain'] );
		} else {
			$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		}
	}

	/**
	 * The `domain` parameter must be a valid hostname or IP address,
	 * optionally followed by a port, when updating a site.
	 *
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 * @dataProvider data_domain_validation
	 */
	public function test_update_item_domain_validation( $domain, $is_valid ) {
		wp_set_current_user( self::$superadmin_id );

		$blog_id     = self::factory()->blog->create( array( 'path' => '/domain-validation-update/' ) );
		$orig_domain = get_site( $blog_id )->domain;

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'domain', $domain );

		$response = rest_get_server()->dispatch( $request );

		if ( $is_valid ) {
			$this->assertEquals( 200, $response->get_status(), "domain '{$domain}' should have been accepted." );

			$data     = $response->get_data();
			$expected = $this->normalize_domain_for_storage( $domain );
			$this->assertEquals( $expected, $data['domain'] );
			$this->assertEquals( $expected, get_site( $blog_id )->domain );
		} else {
			$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
			$this->assertEquals( $orig_domain, get_site( $blog_id )->domain );
		}
	}

	/**
	 * Mirrors the character-stripping done by `wp_normalize_site_data()` so that
	 * the domain-validation tests assert against what actually ends up stored,
	 * e.g. bracketed IPv6 literals lose their `[` `]` at that (unrelated) layer.
	 *
	 * @param string $domain Domain as submitted to the endpoint.
	 * @return string Domain as it will be persisted.
	 */
	private function normalize_domain_for_storage( $domain ) {
		return preg_replace( '/[^a-z0-9\-.:]+/i', '', $domain );
	}

	/**
	 * Data provider for the `domain` format validation tests.
	 *
	 * @return array
	 */
	public function data_domain_validation() {
		return array(
			'plain hostname'                            => array( 'example.org', true ),
			'subdomain'                                 => array( 'sub.example.org', true ),
			'hostname with port'                        => array( 'example.org:8080', true ),
			// The shape produced by wp-admin/network/site-new.php for a subdomain install
			// when the network domain itself carries a port, e.g. "blogname.localhost:8889".
			'subdomain of a network domain with a port' => array( 'blogname.localhost:8889', true ),
			'ipv4'                                      => array( '198.51.100.10', true ),
			'ipv4 with port'                            => array( '198.51.100.10:8080', true ),
			'bare ipv6 loopback, no port'               => array( '::1', true ),
			'bare ipv6, no port'                        => array( '2001:db8::1', true ),
			'bare full-length ipv6, no port'            => array( '2001:0db8:0000:0000:0000:0000:0000:0001', true ),
			'bare ipv4-mapped ipv6, no port'            => array( '::ffff:192.0.2.1', true ),
			// Ambiguous: no brackets to separate a port, so the whole string is
			// parsed as a literal (8-group, after :: expansion) IPv6 address.
			'ambiguous unbracketed ipv6 with trailing digits treated as address' => array( '2001:db8::1:8080', true ),
			// Bracketed IPv6 is not supported at all: brackets aren't valid
			// hostname or bare-IP characters, so any leading `[` is rejected outright.
			'bracketed ipv6 is rejected'                => array( '[2001:db8::1]', false ),
			'bracketed ipv6 with port is rejected'      => array( '[2001:db8::1]:8080', false ),
			'bracketed ipv6 loopback is rejected'       => array( '[::1]', false ),
			'empty brackets are rejected'               => array( '[]', false ),
			'unbracketed ipv6-shaped string with a port-like trailing group is rejected' => array( '1:2:3:4:5:6:7:8:9', false ),
			'empty domain'                              => array( '', false ),
			'domain with a space'                       => array( 'example org', false ),
			'domain with a scheme'                      => array( 'http://example.org', false ),
			'leading hyphen label'                      => array( '-example.org', false ),
			'empty label'                               => array( 'example..org', false ),
			'port out of range'                         => array( 'example.org:99999', false ),
			'non numeric port'                          => array( 'example.org:abc', false ),
		);
	}

	/**
	 * The `path` parameter must be a valid site path when creating a site.
	 *
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-required
	 * @dataProvider data_path_validation
	 */
	public function test_create_item_path_validation( $path, $is_valid ) {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		// A domain distinct from WP_TESTS_DOMAIN, so a root path doesn't collide with the network's main site.
		$request->set_param( 'domain', 'path-validation.example.org' );
		$request->set_param( 'path', $path );

		$response = rest_get_server()->dispatch( $request );

		if ( $is_valid ) {
			$this->assertEquals( 201, $response->get_status(), "path '{$path}' should have been accepted." );

			$data = $response->get_data();
			$this->assertEquals( $path, $data['path'] );
		} else {
			$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		}
	}

	/**
	 * The `path` parameter must be a valid site path when updating a site.
	 *
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 * @dataProvider data_path_validation
	 */
	public function test_update_item_path_validation( $path, $is_valid ) {
		wp_set_current_user( self::$superadmin_id );

		// A domain distinct from WP_TESTS_DOMAIN, so a root path doesn't collide with the network's main site.
		$blog_id   = self::factory()->blog->create(
			array(
				'domain' => 'path-validation-update.example.org',
				'path'   => '/path-validation-update/',
			)
		);
		$orig_path = get_site( $blog_id )->path;

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'path', $path );

		$response = rest_get_server()->dispatch( $request );

		if ( $is_valid ) {
			$this->assertEquals( 200, $response->get_status(), "path '{$path}' should have been accepted." );

			$data = $response->get_data();
			$this->assertEquals( $path, $data['path'] );
			$this->assertEquals( $path, get_site( $blog_id )->path );
		} else {
			$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
			$this->assertEquals( $orig_path, get_site( $blog_id )->path );
		}
	}

	/**
	 * Data provider for the `path` format validation tests.
	 *
	 * @return array
	 */
	public function data_path_validation() {
		return array(
			'root path'                => array( '/', true ),
			'single segment'           => array( '/tempor/', true ),
			'nested segments'          => array( '/parent/child/', true ),
			'segment with apostrophe'  => array( "/o'brien/", true ),
			'segment with punctuation' => array( '/with-hyphen_and.dot~tilde/', true ),
			'percent encoded segment'  => array( '/percent%20encoded/', true ),
			'empty path'               => array( '', false ),
			'no leading slash'         => array( 'no-leading-slash/', false ),
			'no trailing slash'        => array( '/no-trailing-slash', false ),
			'double slash'             => array( '//double-slash//', false ),
			'path with a space'        => array( '/with space/', false ),
			'path with a query string' => array( '/with?query/', false ),
			'path with a fragment'     => array( '/with#fragment/', false ),
			'path with a double quote' => array( '/with"quote/', false ),
		);
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

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'edit' );

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
		$this->assertErrorResponse( 'rest_forbidden_user', $response, 403 );
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

	/**
	 * @ticket 40365
	 * @covers ::update_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_update_item_permissions_check_allows_the_sites_own_network_by_default() {
		$blog_id = self::factory()->blog->create();
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'id', $blog_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * @ticket 40365
	 * @covers ::delete_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_delete_item_permissions_check_invalid_network_id() {
		$blog_id = self::factory()->blog->create();

		// Orphan the site from its network to simulate an invalid stored network ID.
		wp_update_site( $blog_id, array( 'network_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'id', $blog_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_unable_delete_from_network', $response, 403 );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_get_item_permissions_check_allows_a_site_on_the_current_network() {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create();
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'id', $blog_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Creates a site on a network other than the current one.
	 *
	 * @return int[] The other network ID and the site ID.
	 */
	private function create_site_on_another_network() {
		$network_id = self::factory()->network->create(
			array(
				'domain' => 'other-network.example.org',
				'path'   => '/',
			)
		);

		$blog_id = self::factory()->blog->create(
			array(
				'domain'     => 'other-network.example.org',
				'path'       => '/elsewhere/',
				'network_id' => $network_id,
			)
		);

		return array( $network_id, $blog_id );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_get_item_on_another_network_is_forbidden() {
		list( , $blog_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_unable_read_from_network', $response, 403 );
	}

	/**
	 * @ticket 40365
	 * @covers ::update_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_update_item_on_another_network_is_forbidden() {
		list( , $blog_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'path', '/moved/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_unable_update_from_network', $response, 403 );
		$this->assertSame( '/elsewhere/', get_site( $blog_id )->path, 'The site should be left unchanged.' );
	}

	/**
	 * @ticket 40365
	 * @covers ::delete_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_delete_item_on_another_network_is_forbidden() {
		list( , $blog_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_unable_delete_from_network', $response, 403 );
		$this->assertInstanceOf( 'WP_Site', get_site( $blog_id ), 'The site should still exist.' );
	}

	/**
	 * @ticket 40365
	 * @covers ::create_item
	 * @group ms-required
	 */
	public function test_create_item_ignores_network_param() {
		list( $network_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/ignores-network/' );
		$request->set_param( 'network', $network_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( get_current_network_id(), $data['network'] );
		$this->assertSame( get_current_network_id(), get_site( $data['id'] )->network_id );
	}

	/**
	 * @ticket 40365
	 * @covers ::update_item
	 * @group ms-required
	 */
	public function test_update_item_ignores_network_param() {
		list( $network_id ) = $this->create_site_on_another_network();
		$blog_id            = self::factory()->blog->create( array( 'path' => '/stays-put/' ) );
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'network', $network_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( get_current_network_id(), $response->get_data()['network'] );
		$this->assertSame( get_current_network_id(), get_site( $blog_id )->network_id );
	}

	/**
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_ignores_network_param() {
		list( $network_id, $blog_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'network', $network_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertNotEmpty( $data );
		$this->assertNotContains( $blog_id, wp_list_pluck( $data, 'id' ) );
		foreach ( $data as $site ) {
			$this->assertSame( get_current_network_id(), $site['network'] );
		}
	}

	/**
	 * Data provider for the single-site permission checks.
	 *
	 * @return array[]
	 */
	public function data_permission_checks_on_another_network() {
		return array(
			'get'    => array( 'GET', 'get_item_permissions_check', 'rest_unable_read_from_network' ),
			'update' => array( 'PUT', 'update_item_permissions_check', 'rest_unable_update_from_network' ),
			'delete' => array( 'DELETE', 'delete_item_permissions_check', 'rest_unable_delete_from_network' ),
		);
	}

	/**
	 * Every permission check for a single site refuses a site on another network.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @covers ::update_item_permissions_check
	 * @covers ::delete_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 * @dataProvider data_permission_checks_on_another_network
	 *
	 * @param string $method           HTTP method.
	 * @param string $permission_check Permission check method name.
	 * @param string $error_code       Expected error code.
	 */
	public function test_permission_checks_block_a_site_on_another_network( $method, $permission_check, $error_code ) {
		list( , $blog_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( $method, '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'id', $blog_id );

		$result = $this->endpoint->$permission_check( $request );

		$this->assertWPError( $result );
		$this->assertSame( $error_code, $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * The same permission checks pass for a site on the current network.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @covers ::update_item_permissions_check
	 * @covers ::delete_item_permissions_check
	 * @covers ::site_in_network
	 * @group ms-required
	 * @dataProvider data_permission_checks_on_another_network
	 *
	 * @param string $method           HTTP method.
	 * @param string $permission_check Permission check method name.
	 */
	public function test_permission_checks_allow_a_site_on_the_current_network( $method, $permission_check ) {
		$blog_id = self::factory()->blog->create( array( 'path' => '/current-network/' ) );
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( $method, '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'id', $blog_id );

		$this->assertTrue( $this->endpoint->$permission_check( $request ) );
	}

	/**
	 * Even a super admin of the second network is blocked from reaching its sites
	 * through the current network, for every method.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @covers ::update_item_permissions_check
	 * @covers ::delete_item_permissions_check
	 * @group ms-required
	 */
	public function test_super_admin_of_the_other_network_is_blocked() {
		list( $network_id, $blog_id ) = $this->create_site_on_another_network();

		$user = self::factory()->user->create_and_get();
		grant_super_admin( $user->ID );
		update_network_option( $network_id, 'site_admins', array( $user->user_login ) );
		wp_set_current_user( $user->ID );

		$error_codes = array(
			'GET'    => 'rest_unable_read_from_network',
			'PUT'    => 'rest_unable_update_from_network',
			'PATCH'  => 'rest_unable_update_from_network',
			'DELETE' => 'rest_unable_delete_from_network',
		);

		foreach ( $error_codes as $method => $error_code ) {
			$request = new WP_REST_Request( $method, '/wp/v2/sites/' . $blog_id );
			if ( 'GET' !== $method && 'DELETE' !== $method ) {
				$request->set_param( 'path', '/moved/' );
			}

			$response = rest_get_server()->dispatch( $request );

			$this->assertErrorResponse( $error_code, $response, 403 );
		}

		$site = get_site( $blog_id );
		$this->assertInstanceOf( 'WP_Site', $site, 'The site should still exist.' );
		$this->assertSame( '/elsewhere/', $site->path, 'The site should be left unchanged.' );
		$this->assertSame( $network_id, $site->network_id, 'The site should stay on its network.' );
	}

	/**
	 * Including a site from another network by ID does not expose it.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_include_does_not_expose_another_network() {
		list( , $blog_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'include', array( $blog_id ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertSame( '0', $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * A super admin of the second network still creates sites on the current network.
	 *
	 * @ticket 40365
	 * @covers ::create_item
	 * @covers ::create_item_permissions_check
	 * @group ms-required
	 */
	public function test_create_item_by_admin_of_another_network_lands_on_the_current_network() {
		list( $network_id ) = $this->create_site_on_another_network();

		$user = self::factory()->user->create_and_get();
		grant_super_admin( $user->ID );
		update_network_option( $network_id, 'site_admins', array( $user->user_login ) );
		wp_set_current_user( $user->ID );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', 'other-network.example.org' );
		$request->set_param( 'path', '/elsewhere/' );
		$request->set_param( 'network', $network_id );

		$response = rest_get_server()->dispatch( $request );

		// The same domain and path exist on the other network, but not on this one.
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( get_current_network_id(), $data['network'] );
		$this->assertNotSame( $network_id, get_site( $data['id'] )->network_id );
	}

	/**
	 * The rest_site_in_network filter can let a site on another network through.
	 *
	 * @ticket 40365
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_site_in_network_filter_can_allow_another_network() {
		list( $network_id, $blog_id ) = $this->create_site_on_another_network();
		wp_set_current_user( self::$superadmin_id );

		$filter_args = array();
		add_filter(
			'rest_site_in_network',
			static function ( $in_network, $site, $current_network_id ) use ( &$filter_args ) {
				$filter_args = array( $in_network, $site, $current_network_id );
				return true;
			},
			10,
			3
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $network_id, $response->get_data()['network'] );

		list( $in_network, $site, $current_network_id ) = $filter_args;
		$this->assertFalse( $in_network, 'The unfiltered value should be false for another network.' );
		$this->assertInstanceOf( 'WP_Site', $site );
		$this->assertSame( $blog_id, (int) $site->blog_id );
		$this->assertSame( get_current_network_id(), $current_network_id );
	}

	/**
	 * The rest_site_in_network filter can block a site on the current network.
	 *
	 * @ticket 40365
	 * @covers ::site_in_network
	 * @group ms-required
	 */
	public function test_site_in_network_filter_can_block_the_current_network() {
		$blog_id = self::factory()->blog->create( array( 'path' => '/filtered-out/' ) );
		wp_set_current_user( self::$superadmin_id );

		add_filter( 'rest_site_in_network', '__return_false' );

		$error_codes = array(
			'GET'    => 'rest_unable_read_from_network',
			'PUT'    => 'rest_unable_update_from_network',
			'DELETE' => 'rest_unable_delete_from_network',
		);

		foreach ( $error_codes as $method => $error_code ) {
			$request  = new WP_REST_Request( $method, '/wp/v2/sites/' . $blog_id );
			$response = rest_get_server()->dispatch( $request );

			$this->assertErrorResponse( $error_code, $response, 403 );
		}
	}

	/**
	 * Data provider for the permission checks made away from the main site.
	 *
	 * @return array[]
	 */
	public function data_permission_checks_off_the_main_site() {
		return array(
			'list'   => array( 'GET', false, 'get_items_permissions_check', 'rest_cannot_view_not_on_main_site' ),
			'read'   => array( 'GET', true, 'get_item_permissions_check', 'rest_cannot_view_not_on_main_site' ),
			'create' => array( 'POST', false, 'create_item_permissions_check', 'rest_cannot_create_not_on_main_site' ),
			'update' => array( 'PUT', true, 'update_item_permissions_check', 'rest_cannot_edit_not_on_main_site' ),
			'delete' => array( 'DELETE', true, 'delete_item_permissions_check', 'rest_cannot_delete_not_on_main_site' ),
		);
	}

	/**
	 * Even a super admin can only manage sites from the main site.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @covers ::get_item_permissions_check
	 * @covers ::create_item_permissions_check
	 * @covers ::update_item_permissions_check
	 * @covers ::delete_item_permissions_check
	 * @group ms-required
	 * @dataProvider data_permission_checks_off_the_main_site
	 *
	 * @param string $method           HTTP method.
	 * @param bool   $single           Whether the request is for a single site.
	 * @param string $permission_check Permission check method name.
	 * @param string $error_code       Expected error code.
	 */
	public function test_permission_checks_require_the_main_site( $method, $single, $permission_check, $error_code ) {
		$subsite_id = self::factory()->blog->create( array( 'path' => '/off-main/' ) );
		$target_id  = self::factory()->blog->create( array( 'path' => '/off-main-target/' ) );
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( $method, $single ? '/wp/v2/sites/' . $target_id : '/wp/v2/sites' );
		if ( $single ) {
			$request->set_param( 'id', $target_id );
		}

		switch_to_blog( $subsite_id );
		$result = $this->endpoint->$permission_check( $request );
		restore_current_blog();

		$this->assertWPError( $result );
		$this->assertSame( $error_code, $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );

		// The same request passes on the main site.
		$this->assertTrue( $this->endpoint->$permission_check( $request ) );
	}

	/**
	 * A site cannot be created through a request served by a subsite.
	 *
	 * @ticket 40365
	 * @covers ::create_item_permissions_check
	 * @group ms-required
	 */
	public function test_create_item_from_a_subsite_is_forbidden() {
		$subsite_id = self::factory()->blog->create( array( 'path' => '/creator/' ) );
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/from-a-subsite/' );

		switch_to_blog( $subsite_id );
		$response = rest_get_server()->dispatch( $request );
		restore_current_blog();

		$this->assertErrorResponse( 'rest_cannot_create_not_on_main_site', $response, 403 );
		$this->assertEquals( 0, get_blog_id_from_url( WP_TESTS_DOMAIN, '/from-a-subsite/' ) );
	}

	/**
	 * Data provider for listing sites away from the main site.
	 *
	 * @return array[]
	 */
	public function data_get_items_off_the_main_site() {
		return array(
			'own sites as me'           => array( 'me', 'view', true ),
			'own sites by ID'           => array( 'own', 'view', true ),
			'own sites in edit context' => array( 'me', 'edit', 'rest_cannot_view_not_on_main_site' ),
			'own sites in embed'        => array( 'me', 'embed', 'rest_cannot_view_not_on_main_site' ),
			'every site'                => array( '', 'view', 'rest_cannot_view_not_on_main_site' ),
			'another user'              => array( 'other', 'view', 'rest_cannot_view_not_on_main_site' ),
		);
	}

	/**
	 * Only a user listing their own sites in the view context is let through away
	 * from the main site, even a super admin needs the main site for everything else.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 * @dataProvider data_get_items_off_the_main_site
	 *
	 * @param string      $user     The user filter: 'me', 'own', 'other' or empty.
	 * @param string      $context  Request context.
	 * @param true|string $expected True when allowed, otherwise the error code.
	 */
	public function test_get_items_permissions_check_off_the_main_site( $user, $context, $expected ) {
		$subsite_id = self::factory()->blog->create( array( 'path' => '/listing/' ) );
		$other_id   = self::factory()->user->create();
		wp_set_current_user( self::$superadmin_id );

		$users = array(
			'me'    => 'me',
			'own'   => (string) self::$superadmin_id,
			'other' => (string) $other_id,
			''      => '',
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', $users[ $user ] );
		$request->set_param( 'context', $context );

		switch_to_blog( $subsite_id );
		$result = $this->endpoint->get_items_permissions_check( $request );
		restore_current_blog();

		if ( true === $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertWPError( $result );
			$this->assertSame( $expected, $result->get_error_code() );
			$this->assertSame( 403, $result->get_error_data()['status'] );
		}
	}

	/**
	 * A member lists their own sites through a request served by a subsite.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_me_filter_from_a_subsite() {
		$blog_ids = self::factory()->blog->create_many( 2 );
		$user_id  = self::factory()->user->create();

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );

		switch_to_blog( $blog_ids[0] );
		$response = rest_get_server()->dispatch( $request );
		restore_current_blog();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEqualSets( array_merge( array( 1 ), $blog_ids ), wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * On the main site, the own-user filter only covers the view context, any
	 * other context needs the capability to manage sites.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 */
	public function test_get_items_me_filter_needs_the_capability_outside_the_view_context() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		foreach ( array( 'edit', 'embed' ) as $context ) {
			$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
			$request->set_param( 'user', 'me' );
			$request->set_param( 'context', $context );

			$response = rest_get_server()->dispatch( $request );

			$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
		}

		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Away from the main site, only a member reading in the view context is let
	 * through, whether or not the site exists.
	 *
	 * @ticket 40365
	 * @covers ::get_item_permissions_check
	 * @group ms-required
	 */
	public function test_get_item_permissions_check_off_the_main_site() {
		$subsite_id = self::factory()->blog->create( array( 'path' => '/reader/' ) );
		$target_id  = self::factory()->blog->create( array( 'path' => '/read-target/' ) );
		$member_id  = self::factory()->user->create();
		$outsider   = self::factory()->user->create();
		add_user_to_blog( $target_id, $member_id, 'subscriber' );

		$check = function ( $user_id, $site_id, $context ) use ( $subsite_id ) {
			wp_set_current_user( $user_id );

			$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $site_id );
			$request->set_param( 'id', $site_id );
			$request->set_param( 'context', $context );

			switch_to_blog( $subsite_id );
			$result = $this->endpoint->get_item_permissions_check( $request );
			restore_current_blog();

			return $result;
		};

		$this->assertTrue( $check( $member_id, $target_id, 'view' ), 'A member may read the site in the view context.' );

		$denied = array(
			'member in edit context'  => array( $member_id, $target_id, 'edit' ),
			'member in embed context' => array( $member_id, $target_id, 'embed' ),
			'non-member'              => array( $outsider, $target_id, 'view' ),
			'super admin'             => array( self::$superadmin_id, $target_id, 'view' ),
			'logged out'              => array( 0, $target_id, 'view' ),
			'member, unknown site'    => array( $member_id, 999999, 'view' ),
			'outsider, unknown site'  => array( $outsider, 999999, 'view' ),
		);

		foreach ( $denied as $label => $args ) {
			$result = $check( ...$args );

			$this->assertWPError( $result, $label );
			$this->assertSame( 'rest_cannot_view_not_on_main_site', $result->get_error_code(), $label );
			$this->assertSame( 403, $result->get_error_data()['status'], $label );
		}
	}

	/**
	 * The fields that are not in the view context and the meta are left out for
	 * site members and super admins alike, and are part of the edit context.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_response
	 * @group ms-required
	 */
	public function test_edit_only_fields_are_left_out_of_the_view_context() {
		$blog_id   = self::factory()->blog->create( array( 'path' => '/contexts/' ) );
		$member_id = self::factory()->user->create();
		add_user_to_blog( $blog_id, $member_id, 'subscriber' );

		$edit_only = array(
			'registered',
			'registered_gmt',
			'last_updated',
			'last_updated_gmt',
			'archived',
			'mature',
			'spam',
			'deleted',
			'lang_id',
			'post_count',
			'meta',
		);

		foreach ( array( $member_id, self::$superadmin_id ) as $user_id ) {
			wp_set_current_user( $user_id );

			$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();

			$this->assertEquals( 200, $response->get_status() );
			$this->assertSame( $blog_id, $data['id'] );
			$this->assertSame( get_admin_url( $blog_id ), $data['admin_url'] );

			foreach ( $edit_only as $field ) {
				$this->assertArrayNotHasKey( $field, $data, "$field should not be in the view context." );
			}
		}

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'edit' );

		$data = rest_get_server()->dispatch( $request )->get_data();

		if ( ! is_site_meta_supported() ) {
			$edit_only = array_diff( $edit_only, array( 'meta' ) );
		}

		foreach ( $edit_only as $field ) {
			$this->assertArrayHasKey( $field, $data, "$field should be in the edit context." );
		}
	}

	/**
	 * Preparing a site without a context does not prepare the edit-only fields.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_response
	 * @group ms-required
	 */
	public function test_prepare_item_without_a_context_does_not_switch_sites() {
		$blog_id = self::factory()->blog->create( array( 'path' => '/no-context/' ) );

		$switches = 0;
		$counter  = static function () use ( &$switches ) {
			++$switches;
		};

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( '_fields', 'id,post_count' );

		add_action( 'switch_blog', $counter );
		$data = $this->endpoint->prepare_item_for_response( get_site( $blog_id ), $request )->get_data();
		remove_action( 'switch_blog', $counter );

		$this->assertSame( 0, $switches );
		$this->assertSame( array( 'id' => $blog_id ), $data );
	}

	/**
	 * The site title is set from the blogname and keeps its backslashes.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_database
	 * @group ms-required
	 */
	public function test_create_item_keeps_backslashes_in_the_blogname() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/backslash/' );
		$request->set_param( 'blogname', 'C:\\Sites\\Example' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertSame( 'C:\\Sites\\Example', get_blog_option( $response->get_data()['id'], 'blogname' ) );
	}

	/**
	 * Without a blogname the site gets the default title.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_database
	 * @group ms-required
	 */
	public function test_create_item_without_a_blogname_uses_the_default_title() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/untitled/' );

		$response = rest_get_server()->dispatch( $request );
		$blog_id  = $response->get_data()['id'];

		$this->assertEquals( 201, $response->get_status() );
		$this->assertSame( sprintf( 'Site %d', $blog_id ), get_blog_option( $blog_id, 'blogname' ) );
	}

	/**
	 * Creating a site accepts the site title and the administrator.
	 *
	 * @ticket 40365
	 * @covers ::register_routes
	 */
	public function test_create_item_accepts_the_creation_fields() {
		$routes = rest_get_server()->get_routes();
		$args   = array();

		foreach ( $routes['/wp/v2/sites'] as $handler ) {
			if ( ! empty( $handler['methods']['POST'] ) ) {
				$args = $handler['args'];
			}
		}

		$this->assertArrayHasKey( 'blogname', $args );
		$this->assertArrayHasKey( 'user', $args );
		$this->assertSame( array( 'integer', 'string' ), $args['user']['type'] );
		$this->assertSame(
			array(
				array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				array(
					'type' => 'string',
					'enum' => array( 'me' ),
				),
			),
			$args['user']['anyOf']
		);
		$this->assertArrayNotHasKey( 'user_id', $args );
		$this->assertArrayNotHasKey( 'title', $args );
	}

	/**
	 * A POST to an existing site is an update, so the creation fields are ignored.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_database
	 * @group ms-required
	 */
	public function test_update_item_ignores_the_creation_fields() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create(
			array(
				'path'  => '/kept-title/',
				'title' => 'Kept title',
			)
		);

		foreach ( array( 'POST', 'PUT' ) as $method ) {
			$request = new WP_REST_Request( $method, '/wp/v2/sites/' . $blog_id );
			$request->set_param( 'blogname', 'Changed title' );
			$request->set_param( 'user', 99999 );

			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 200, $response->get_status(), $method );
			$this->assertSame( 'Kept title', get_blog_option( $blog_id, 'blogname' ), $method );

			// The update route does not register the parameter, so it is not validated either.
			$request->set_param( 'user', 'xyz' );

			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 200, $response->get_status(), $method );
			$this->assertSame( 'Kept title', get_blog_option( $blog_id, 'blogname' ), $method );
		}
	}

	/**
	 * Data provider for the collection parameters that only apply with the
	 * capability to manage sites.
	 *
	 * @return array[]
	 */
	public function data_get_items_hidden_field_params() {
		return array(
			'archived'        => array( 'archived', true ),
			'mature'          => array( 'mature', true ),
			'spam'            => array( 'spam', true ),
			'deleted'         => array( 'deleted', true ),
			'lang_id'         => array( 'lang_id', array( 7 ) ),
			'lang_id_exclude' => array( 'lang_id_exclude', array( 0 ) ),
			'before'          => array( 'before', '2000-01-01T00:00:00' ),
			'after'           => array( 'after', '2999-01-01T00:00:00' ),
			'spam false'      => array( 'spam', false ),
			'orderby date'    => array( 'orderby', 'registered' ),
			'orderby update'  => array( 'orderby', 'last_updated' ),
		);
	}

	/**
	 * Users listing their own sites cannot filter or order by the fields they
	 * cannot see, otherwise the results would give the hidden values away.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @covers ::has_edit_only_filter
	 * @group ms-required
	 * @dataProvider data_get_items_hidden_field_params
	 *
	 * @param string $param Collection parameter.
	 * @param mixed  $value Value that would narrow or reorder the results.
	 */
	public function test_get_items_me_filter_rejects_hidden_field_params( $param, $value ) {
		$blog_id = self::factory()->blog->create( array( 'path' => '/hidden-params/' ) );
		$user_id = self::factory()->user->create();
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );
		$request->set_param( $param, $value );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_param', $response, 403 );
	}

	/**
	 * A super admin listing their own sites can still use every filter.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 * @dataProvider data_get_items_hidden_field_params
	 *
	 * @param string $param Collection parameter.
	 * @param mixed  $value Value that would narrow or reorder the results.
	 */
	public function test_get_items_me_filter_allows_hidden_field_params_for_super_admins( $param, $value ) {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );
		$request->set_param( $param, $value );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * The empty default of the language filters and the default order are not
	 * treated as filters, so a plain own-sites request is still allowed.
	 *
	 * @ticket 40365
	 * @covers ::has_edit_only_filter
	 * @group ms-required
	 */
	public function test_get_items_me_filter_allows_the_default_params() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );
		$request->set_param( 'lang_id', array() );
		$request->set_param( 'orderby', 'domain' );
		$request->set_param( 'public', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Data provider for user filters that match no sites.
	 *
	 * @return array[]
	 */
	public function data_get_items_user_filter_without_sites() {
		return array(
			'GET, no memberships'          => array( 'GET', false ),
			'HEAD, no memberships'         => array( 'HEAD', false ),
			'GET, include outside the set' => array( 'GET', true ),
		);
	}

	/**
	 * When the user filter matches no sites there is nothing to query, but the
	 * query filter still runs and the response looks like any empty collection.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 * @dataProvider data_get_items_user_filter_without_sites
	 *
	 * @param string $method       HTTP method.
	 * @param bool   $with_include Whether to include a site the user is not a member of.
	 */
	public function test_get_items_user_filter_without_sites_skips_the_query( $method, $with_include ) {
		$blog_id = self::factory()->blog->create( array( 'path' => '/not-mine/' ) );
		$user_id = self::factory()->user->create();

		// Users are members of the main site, which the filter then excludes.
		remove_user_from_blog( $user_id, 1 );

		if ( $with_include ) {
			add_user_to_blog( 1, $user_id, 'subscriber' );
		}

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( $method, '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );

		if ( $with_include ) {
			$request->set_param( 'include', array( $blog_id ) );
		}

		$filtered = 0;
		$queries  = 0;

		$count_query  = static function () use ( &$queries ) {
			++$queries;
		};
		$count_filter = static function ( $args ) use ( &$filtered, $count_query ) {
			++$filtered;

			// Looking up the user's sites queries too, so only what follows the filter counts.
			add_action( 'pre_get_sites', $count_query );

			return $args;
		};

		add_filter( 'rest_site_query', $count_filter );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'rest_site_query', $count_filter );
		remove_action( 'pre_get_sites', $count_query );

		$headers = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertSame( '0', $headers['X-WP-Total'] );
		$this->assertSame( '0', $headers['X-WP-TotalPages'] );
		$this->assertSame( 1, $filtered, 'The query filter should still run.' );
		$this->assertSame( 0, $queries, 'No site query should run.' );
	}

	/**
	 * A plugin can still widen an empty user filter through the query filter.
	 *
	 * @ticket 40365
	 * @covers ::get_items
	 * @group ms-required
	 */
	public function test_get_items_user_filter_without_sites_can_be_widened() {
		$blog_id = self::factory()->blog->create( array( 'path' => '/widened/' ) );
		$user_id = self::factory()->user->create();
		remove_user_from_blog( $user_id, 1 );
		wp_set_current_user( $user_id );

		$widen = static function ( $args ) use ( $blog_id ) {
			$args['site__in'] = array( $blog_id );
			return $args;
		};

		add_filter( 'rest_site_query', $widen );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'rest_site_query', $widen );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array( $blog_id ), wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * The addresses go through the URL functions, so their filters apply.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_response
	 * @group ms-required
	 */
	public function test_prepare_item_uses_the_filtered_site_addresses() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/filtered-urls/' ) );

		$home_filter = static function ( $url, $path, $scheme, $filtered_blog_id ) use ( $blog_id ) {
			return $blog_id === $filtered_blog_id ? 'https://home.example.org/' : $url;
		};
		$site_filter = static function ( $url, $path, $scheme, $filtered_blog_id ) use ( $blog_id ) {
			return $blog_id === $filtered_blog_id ? 'https://site.example.org/' : $url;
		};

		add_filter( 'home_url', $home_filter, 10, 4 );
		add_filter( 'site_url', $site_filter, 10, 4 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'home_url', $home_filter, 10 );
		remove_filter( 'site_url', $site_filter, 10 );

		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'https://home.example.org/', $data['home'] );
		$this->assertSame( 'https://site.example.org/', $data['siteurl'] );
	}

	/**
	 * Data provider for user parameter values that are neither a user ID nor "me".
	 *
	 * @return array[]
	 */
	public function data_invalid_user_params() {
		return array(
			'word'          => array( 'xyz' ),
			'zero'          => array( '0' ),
			'zero integer'  => array( 0 ),
			'negative'      => array( -1 ),
			'me with extra' => array( 'me2' ),
			'empty'         => array( '' ),
			'decimal'       => array( '5.5' ),
			'list'          => array( array( 1 ) ),
		);
	}

	/**
	 * The collection filter only accepts a user ID or "me".
	 *
	 * @ticket 40365
	 * @covers ::get_user_param_schema
	 * @group ms-required
	 * @dataProvider data_invalid_user_params
	 *
	 * @param mixed $user User parameter value.
	 */
	public function test_get_items_rejects_an_invalid_user( $user ) {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', $user );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Creating a site only accepts a user ID or "me" as the administrator.
	 *
	 * @ticket 40365
	 * @covers ::get_user_param_schema
	 * @group ms-required
	 * @dataProvider data_invalid_user_params
	 *
	 * @param mixed $user User parameter value.
	 */
	public function test_create_item_rejects_an_invalid_user( $user ) {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/invalid-user/' );
		$request->set_param( 'user', $user );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( 0, get_blog_id_from_url( WP_TESTS_DOMAIN, '/invalid-user/' ) );
	}

	/**
	 * A numeric string is sanitized to an integer, "me" is kept as it is.
	 *
	 * @ticket 40365
	 * @covers ::get_user_param_schema
	 * @group ms-required
	 */
	public function test_get_items_sanitizes_the_user() {
		wp_set_current_user( self::$superadmin_id );

		$seen    = array();
		$capture = static function ( $args, $request ) use ( &$seen ) {
			$seen[] = $request['user'];
			return $args;
		};

		add_filter( 'rest_site_query', $capture, 10, 2 );

		foreach ( array( (string) self::$superadmin_id, 'me' ) as $user ) {
			$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
			$request->set_param( 'user', $user );

			$this->assertEquals( 200, rest_get_server()->dispatch( $request )->get_status() );
		}

		remove_filter( 'rest_site_query', $capture, 10 );

		$this->assertSame( array( self::$superadmin_id, 'me' ), $seen );
	}

	/**
	 * "me" resolves to the current user, a numeric value to its integer.
	 *
	 * @ticket 40365
	 * @covers ::get_user_id_from_param
	 */
	public function test_get_user_id_from_param() {
		$method = $this->get_reflective_method( 'get_user_id_from_param' );

		$this->assertSame( 0, $method->invoke( $this->endpoint, 'me' ), 'Logged out, "me" is no user.' );

		wp_set_current_user( self::$superadmin_id );

		$this->assertSame( self::$superadmin_id, $method->invoke( $this->endpoint, 'me' ) );
		$this->assertSame( 7, $method->invoke( $this->endpoint, '7' ) );
		$this->assertSame( 7, $method->invoke( $this->endpoint, 7 ) );
	}

	/**
	 * Data provider for the ways to name the administrator of a new site.
	 *
	 * @return array[]
	 */
	public function data_create_item_user_values() {
		return array(
			'me'             => array( 'me', false ),
			'integer'        => array( 'id', false ),
			'numeric string' => array( 'string', false ),
			'JSON integer'   => array( 'id', true ),
			'JSON string'    => array( 'string', true ),
		);
	}

	/**
	 * The administrator can be given as "me", an integer or a numeric string,
	 * in the query or a JSON body, and the new site then shows up in their list.
	 *
	 * @ticket 40365
	 * @covers ::prepare_item_for_database
	 * @group ms-required
	 * @dataProvider data_create_item_user_values
	 *
	 * @param string $kind Which value to send: 'me', 'id' or 'string'.
	 * @param bool   $json Whether to send the parameters as a JSON body.
	 */
	public function test_create_item_sets_the_user_as_administrator( $kind, $json ) {
		wp_set_current_user( self::$superadmin_id );

		$values = array(
			'me'     => 'me',
			'id'     => self::$superadmin_id,
			'string' => (string) self::$superadmin_id,
		);

		$params = array(
			'domain' => WP_TESTS_DOMAIN,
			'path'   => '/administered/',
			'user'   => $values[ $kind ],
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		if ( $json ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		} else {
			$request->set_body_params( $params );
		}

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$blog_id = $response->get_data()['id'];

		$this->assertTrue( is_user_member_of_blog( self::$superadmin_id, $blog_id ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );

		$this->assertContains( $blog_id, wp_list_pluck( rest_get_server()->dispatch( $request )->get_data(), 'id' ) );
	}

	/**
	 * Without the capability to manage sites, a user may only filter by their
	 * own ID or "me", whichever site serves the request.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 */
	public function test_get_items_another_user_needs_the_capability() {
		$subsite_id = self::factory()->blog->create( array( 'path' => '/other-user/' ) );
		$user_id    = self::factory()->user->create();
		$other_id   = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', $other_id );

		$this->assertErrorResponse( 'rest_forbidden_user', rest_get_server()->dispatch( $request ), 403 );

		switch_to_blog( $subsite_id );
		$response = rest_get_server()->dispatch( $request );
		restore_current_blog();

		$this->assertErrorResponse( 'rest_forbidden_user', $response, 403 );

		foreach ( array( 'me', $user_id, (string) $user_id ) as $own ) {
			$request->set_param( 'user', $own );

			$this->assertEquals( 200, rest_get_server()->dispatch( $request )->get_status(), 'A user can list their own sites.' );
		}
	}

	/**
	 * A logged-out request cannot filter by a user ID.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 */
	public function test_get_items_user_filter_forbidden_when_logged_out() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', self::$superadmin_id );

		$this->assertErrorResponse( 'rest_forbidden_user', rest_get_server()->dispatch( $request ), 401 );
	}

	/**
	 * A super admin can list the sites of any user.
	 *
	 * @ticket 40365
	 * @covers ::get_items_permissions_check
	 * @group ms-required
	 */
	public function test_get_items_super_admin_can_filter_by_another_user() {
		$blog_id = self::factory()->blog->create( array( 'path' => '/someone-else/' ) );
		$user_id = self::factory()->user->create();
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', $user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( $blog_id, wp_list_pluck( $response->get_data(), 'id' ) );
	}
}
