<?php
/**
 * Unit tests covering WP_REST_Users_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Users_Controller extends WP_Test_REST_Controller_Testcase {
	protected static $superadmin;
	protected static $user;
	protected static $editor;
	protected static $draft_editor;
	protected static $subscriber;
	protected static $author;

	protected static $authors     = array();
	protected static $posts       = array();
	protected static $user_ids    = array();
	protected static $total_users = 30;
	protected static $per_page    = 50;

	protected static $site;

	/**
	 * @var WP_REST_Users_Controller
	 */
	private $endpoint;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$superadmin   = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);
		self::$user         = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor       = $factory->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'editor@example.com',
			)
		);
		self::$draft_editor = $factory->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'draft-editor@example.com',
			)
		);
		self::$subscriber   = $factory->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'subscriber',
				'user_email'   => 'subscriber@example.com',
			)
		);
		self::$author       = $factory->user->create(
			array(
				'display_name' => 'author',
				'role'         => 'author',
				'user_email'   => 'author@example.com',
			)
		);

		foreach ( array( true, false ) as $show_in_rest ) {
			foreach ( array( true, false ) as $public ) {
				$post_type_name = 'r_' . json_encode( $show_in_rest ) . '_p_' . json_encode( $public );
				register_post_type(
					$post_type_name,
					array(
						'public'                   => $public,
						'show_in_rest'             => $show_in_rest,
						'tests_no_auto_unregister' => true,
					)
				);
				self::$authors[ $post_type_name ] = $factory->user->create(
					array(
						'role'       => 'editor',
						'user_email' => 'author_' . $post_type_name . '@example.com',
					)
				);
				self::$posts[ $post_type_name ]   = $factory->post->create(
					array(
						'post_type'   => $post_type_name,
						'post_author' => self::$authors[ $post_type_name ],
					)
				);
			}
		}

		self::$posts['post']                = $factory->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => self::$editor,
			)
		);
		self::$posts['r_true_p_true_DRAFT'] = $factory->post->create(
			array(
				'post_type'   => 'r_true_p_true',
				'post_author' => self::$draft_editor,
				'post_status' => 'draft',
			)
		);

		if ( is_multisite() ) {
			self::$site = $factory->blog->create(
				array(
					'domain' => 'rest.wordpress.org',
					'path'   => '/',
				)
			);
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}

		// Set up users for pagination tests.
		for ( $i = 0; $i < self::$total_users - 11; $i++ ) {
			self::$user_ids[] = $factory->user->create(
				array(
					'role'         => 'contributor',
					'display_name' => "User {$i}",
				)
			);
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user );
		self::delete_user( self::$editor );
		self::delete_user( self::$draft_editor );
		self::delete_user( self::$author );

		foreach ( self::$posts as $post ) {
			wp_delete_post( $post, true );
		}

		foreach ( self::$authors as $author ) {
			self::delete_user( $author );
		}

		_unregister_post_type( 'r_true_p_true' );
		_unregister_post_type( 'r_true_p_false' );
		_unregister_post_type( 'r_false_p_true' );
		_unregister_post_type( 'r_false_p_false' );

		if ( is_multisite() ) {
			wp_delete_site( self::$site );
		}

		// Remove users for pagination tests.
		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}
	}

	/**
	 * This function is run before each method
	 */
	public function set_up() {
		parent::set_up();
		$this->endpoint = new WP_REST_Users_Controller();
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/users', $routes );
		$this->assertCount( 2, $routes['/wp/v2/users'] );
		$this->assertArrayHasKey( '/wp/v2/users/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/users/(?P<id>[\d]+)'] );
		$this->assertArrayHasKey( '/wp/v2/users/me', $routes );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/users/' . self::$user );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		$this->assertSameSets(
			array(
				'context',
				'exclude',
				'include',
				'offset',
				'order',
				'orderby',
				'page',
				'per_page',
				'roles',
				'capabilities',
				'search',
				'slug',
				'who',
				'has_published_posts',
			),
			$keys
		);
	}

	public function test_get_items() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'context', 'view' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data     = $all_data[0];
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, 'view', $data['_links'] );
	}

	public function test_get_items_with_edit_context() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data     = $all_data[0];
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, 'edit', $data['_links'] );
	}

	public function test_get_items_with_edit_context_without_permission() {
		// Test with a user not logged in.
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );

		// Test with a user logged in but without sufficient capabilities;
		// capability in question: 'list_users'.
		wp_set_current_user( self::$editor );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_items_unauthenticated_includes_authors_of_post_types_shown_in_rest() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$users    = $response->get_data();

		$rest_post_types = array_values( get_post_types( array( 'show_in_rest' => true ), 'names' ) );

		foreach ( $users as $user ) {
			$this->assertNotEmpty( count_user_posts( $user['id'], $rest_post_types ) );

			// Ensure we don't expose non-public data.
			$this->assertArrayNotHasKey( 'capabilities', $user );
			$this->assertArrayNotHasKey( 'registered_date', $user );
			$this->assertArrayNotHasKey( 'first_name', $user );
			$this->assertArrayNotHasKey( 'last_name', $user );
			$this->assertArrayNotHasKey( 'nickname', $user );
			$this->assertArrayNotHasKey( 'extra_capabilities', $user );
			$this->assertArrayNotHasKey( 'username', $user );
			$this->assertArrayNotHasKey( 'email', $user );
			$this->assertArrayNotHasKey( 'roles', $user );
			$this->assertArrayNotHasKey( 'locale', $user );
		}

		$user_ids = wp_list_pluck( $users, 'id' );

		$this->assertContains( self::$editor, $user_ids );
		$this->assertContains( self::$authors['r_true_p_true'], $user_ids );
		$this->assertContains( self::$authors['r_true_p_false'], $user_ids );
		$this->assertCount( 3, $user_ids );
	}

	public function test_get_items_unauthenticated_does_not_include_authors_of_post_types_not_shown_in_rest() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$users    = $response->get_data();
		$user_ids = wp_list_pluck( $users, 'id' );

		$this->assertNotContains( self::$authors['r_false_p_true'], $user_ids );
		$this->assertNotContains( self::$authors['r_false_p_false'], $user_ids );
	}

	public function test_get_items_unauthenticated_does_not_include_users_without_published_posts() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$users    = $response->get_data();
		$user_ids = wp_list_pluck( $users, 'id' );

		$this->assertNotContains( self::$draft_editor, $user_ids );
		$this->assertNotContains( self::$user, $user_ids );
	}

	public function test_get_items_pagination_headers() {
		$total_users = self::$total_users;
		$total_pages = (int) ceil( $total_users / 10 );

		wp_set_current_user( self::$user );

		// Start of the index.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_users, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( 'wp/v2/users' )
		);
		$this->assertStringNotContainsString( 'rel="prev"', $headers['Link'] );
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// 3rd page.
		self::factory()->user->create();
		$total_users++;
		$total_pages++;
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_users, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( 'wp/v2/users' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg(
			array(
				'page' => 4,
			),
			rest_url( 'wp/v2/users' )
		);
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// Last page.
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'page', $total_pages );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_users, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages - 1,
			),
			rest_url( 'wp/v2/users' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );

		// Out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'page', 100 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_users, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages,
			),
			rest_url( 'wp/v2/users' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );
	}

	public function test_get_items_per_page() {
		wp_set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 10, $response->get_data() );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'per_page', 5 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 5, $response->get_data() );
	}

	public function test_get_items_page() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'per_page', 5 );
		$request->set_param( 'page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 5, $response->get_data() );
		$prev_link = add_query_arg(
			array(
				'per_page' => 5,
				'page'     => 1,
			),
			rest_url( 'wp/v2/users' )
		);
		$headers   = $response->get_headers();
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
	}

	public function test_get_items_orderby_name() {
		wp_set_current_user( self::$user );

		$low_id  = self::factory()->user->create( array( 'display_name' => 'AAAAA' ) );
		$mid_id  = self::factory()->user->create( array( 'display_name' => 'NNNNN' ) );
		$high_id = self::factory()->user->create( array( 'display_name' => 'ZZZZ' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $high_id, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $low_id, $data[0]['id'] );
	}

	public function test_get_items_orderby_url() {
		wp_set_current_user( self::$user );

		$low_id  = self::factory()->user->create( array( 'user_url' => 'http://a.com' ) );
		$high_id = self::factory()->user->create( array( 'user_url' => 'http://b.com' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'url' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( 'include', array( $low_id, $high_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $high_id, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'url' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( 'include', array( $low_id, $high_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $low_id, $data[0]['id'] );
	}

	public function test_get_items_orderby_slug() {
		wp_set_current_user( self::$user );

		$high_id = self::factory()->user->create( array( 'user_nicename' => 'blogin' ) );
		$low_id  = self::factory()->user->create( array( 'user_nicename' => 'alogin' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'slug' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( 'include', array( $low_id, $high_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $high_id, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'slug' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( 'include', array( $low_id, $high_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $low_id, $data[0]['id'] );
	}

	public function test_get_items_orderby_slugs() {
		wp_set_current_user( self::$user );

		self::factory()->user->create( array( 'user_nicename' => 'burrito' ) );
		self::factory()->user->create( array( 'user_nicename' => 'taco' ) );
		self::factory()->user->create( array( 'user_nicename' => 'chalupa' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'include_slugs' );
		$request->set_param( 'slug', array( 'taco', 'burrito', 'chalupa' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'taco', $data[0]['slug'] );
		$this->assertSame( 'burrito', $data[1]['slug'] );
		$this->assertSame( 'chalupa', $data[2]['slug'] );
	}

	public function test_get_items_orderby_email() {
		wp_set_current_user( self::$user );

		$high_id = self::factory()->user->create( array( 'user_email' => 'bemail@gmail.com' ) );
		$low_id  = self::factory()->user->create( array( 'user_email' => 'aemail@gmail.com' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'email' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( 'include', array( $low_id, $high_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $high_id, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'email' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( 'include', array( $low_id, $high_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $low_id, $data[0]['id'] );
	}

	public function test_get_items_orderby_email_unauthenticated() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'email' );
		$request->set_param( 'order', 'desc' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_orderby', $response, 401 );
	}

	public function test_get_items_orderby_registered_date_unauthenticated() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'registered_date' );
		$request->set_param( 'order', 'desc' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_orderby', $response, 401 );
	}

	public function test_get_items_invalid_order() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'order', 'asc,id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_invalid_orderby() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_offset() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'offset', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( self::$total_users - 1, $response->get_data() );

		// 'offset' works with 'per_page'.
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );

		// 'offset' takes priority over 'page'.
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );

		// Invalid 'offset' should error.
		$request->set_param( 'offset', 'moreplease' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_include_query() {
		wp_set_current_user( self::$user );

		$id1 = self::factory()->user->create();
		$id2 = self::factory()->user->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );

		// 'orderby' => 'asc'.
		$request->set_param( 'include', array( $id2, $id1 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( $id1, $data[0]['id'] );

		// 'orderby' => 'include'.
		$request->set_param( 'orderby', 'include' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( $id2, $data[0]['id'] );

		// Invalid 'include' should error.
		$request->set_param( 'include', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// No privileges.
		$request->set_param( 'include', array( $id2, $id1 ) );
		wp_set_current_user( 0 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 0, $data );

	}

	public function test_get_items_exclude_query() {
		wp_set_current_user( self::$user );

		$id1 = self::factory()->user->create();
		$id2 = self::factory()->user->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'per_page', self::$per_page ); // There are >10 users at this point.
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );
		$this->assertContains( $id1, $ids );
		$this->assertContains( $id2, $ids );

		$request->set_param( 'exclude', array( $id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );
		$this->assertContains( $id1, $ids );
		$this->assertNotContains( $id2, $ids );

		// Invalid 'exclude' should error.
		$request->set_param( 'exclude', 'none-of-those-please' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_search() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'search', 'yololololo' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );

		$yolo_id = self::factory()->user->create( array( 'display_name' => 'yololololo' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'search', 'yololololo' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 1, $response->get_data() );
		// Default to wildcard search.
		$adam_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_nicename' => 'adam',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'search', 'ada' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $adam_id, $data[0]['id'] );
	}

	public function test_get_items_slug_query() {
		wp_set_current_user( self::$user );

		self::factory()->user->create(
			array(
				'display_name' => 'foo',
				'user_login'   => 'bar',
			)
		);
		$id2 = self::factory()->user->create(
			array(
				'display_name' => 'Moo',
				'user_login'   => 'foo',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'slug', 'foo' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id2, $data[0]['id'] );
	}

	public function test_get_items_slug_array_query() {
		wp_set_current_user( self::$user );

		$id1 = self::factory()->user->create(
			array(
				'display_name' => 'Taco',
				'user_login'   => 'taco',
			)
		);
		$id2 = self::factory()->user->create(
			array(
				'display_name' => 'Enchilada',
				'user_login'   => 'enchilada',
			)
		);
		$id3 = self::factory()->user->create(
			array(
				'display_name' => 'Burrito',
				'user_login'   => 'burrito',
			)
		);
		self::factory()->user->create(
			array(
				'display_name' => 'Hon Pizza',
				'user_login'   => 'pizza',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param(
			'slug',
			array(
				'taco',
				'burrito',
				'enchilada',
			)
		);
		$request->set_param( 'orderby', 'slug' );
		$request->set_param( 'order', 'asc' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data  = $response->get_data();
		$slugs = wp_list_pluck( $data, 'slug' );
		$this->assertSame( array( 'burrito', 'enchilada', 'taco' ), $slugs );
	}

	public function test_get_items_slug_csv_query() {
		wp_set_current_user( self::$user );

		$id1 = self::factory()->user->create(
			array(
				'display_name' => 'Taco',
				'user_login'   => 'taco',
			)
		);
		$id2 = self::factory()->user->create(
			array(
				'display_name' => 'Enchilada',
				'user_login'   => 'enchilada',
			)
		);
		$id3 = self::factory()->user->create(
			array(
				'display_name' => 'Burrito',
				'user_login'   => 'burrito',
			)
		);
		self::factory()->user->create(
			array(
				'display_name' => 'Hon Pizza',
				'user_login'   => 'pizza',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'slug', 'taco,burrito , enchilada' );
		$request->set_param( 'orderby', 'slug' );
		$request->set_param( 'order', 'desc' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data  = $response->get_data();
		$slugs = wp_list_pluck( $data, 'slug' );
		$this->assertSame( array( 'taco', 'enchilada', 'burrito' ), $slugs );
	}

	/**
	 * Note: Do not test using editor role as there is an editor role created in testing,
	 * and it makes it hard to test this functionality.
	 */
	public function test_get_items_roles() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'roles', 'author,subscriber' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( self::$author, $data[0]['id'] );
		$this->assertSame( self::$subscriber, $data[1]['id'] );

		$request->set_param( 'roles', 'author' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( self::$author, $data[0]['id'] );

		wp_set_current_user( 0 );

		$request->set_param( 'roles', 'author' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 401 );

		wp_set_current_user( self::$editor );

		$request->set_param( 'roles', 'author' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 403 );
	}

	public function test_get_items_invalid_roles() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'roles', 'ilovesteak,author' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( self::$author, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'roles', 'steakisgood' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_items_capabilities() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'capabilities', 'edit_posts' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data );
		foreach ( $data as $user ) {
			$this->assertTrue( user_can( $user['id'], 'edit_posts' ) );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_items_capabilities_no_permission_no_user() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'capabilities', 'edit_posts' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_items_capabilities_no_permission_editor() {
		wp_set_current_user( self::$editor );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'capabilities', 'edit_posts' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 403 );
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_items_invalid_capabilities() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'roles', 'ilovesteak,author' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( self::$author, $data[0]['id'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'capabilities', 'steakisgood' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * @expectedDeprecated WP_User_Query
	 */
	public function test_get_items_who_author_query() {
		wp_set_current_user( self::$superadmin );

		// First request should include subscriber in the set.
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'search', 'subscriber' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );

		// Second request should exclude subscriber.
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'who', 'authors' );
		$request->set_param( 'search', 'subscriber' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 0, $response->get_data() );
	}

	public function test_get_items_who_invalid_query() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'who', 'editor' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Any user with 'edit_posts' on a show_in_rest post type
	 * can view authors. Others (e.g. subscribers) cannot.
	 */
	public function test_get_items_who_unauthorized_query() {
		wp_set_current_user( self::$subscriber );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'who', 'authors' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_who', $response, 403 );
	}

	public function test_get_item() {
		$user_id = self::factory()->user->create();

		wp_set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_user_response( $response, 'embed' );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request;
		$request->set_param( 'context', 'edit' );
		$user = get_user_by( 'id', get_current_user_id() );
		$data = $this->endpoint->prepare_item_for_response( $user, $request );
		$this->check_get_user_response( $data, 'edit' );
	}

	public function test_prepare_item_limit_fields() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request;
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,name' );
		$user     = get_user_by( 'id', get_current_user_id() );
		$response = $this->endpoint->prepare_item_for_response( $user, $request );
		$this->assertSame(
			array(
				'id',
				'name',
			),
			array_keys( $response->get_data() )
		);
	}

	public function test_get_user_avatar_urls() {
		wp_set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 24, $data['avatar_urls'] );
		$this->assertArrayHasKey( 48, $data['avatar_urls'] );
		$this->assertArrayHasKey( 96, $data['avatar_urls'] );

		$user = get_user_by( 'id', self::$editor );
		// Ignore the subdomain, since get_avatar_url() randomly sets
		// the Gravatar server when building the URL string.
		$this->assertSame( substr( get_avatar_url( $user->user_email ), 9 ), substr( $data['avatar_urls'][96], 9 ) );
	}

	public function test_get_user_invalid_id() {
		wp_set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_get_user_empty_capabilities() {
		wp_set_current_user( self::$user );

		$this->allow_user_to_manage_multisite();

		$lolz = self::factory()->user->create(
			array(
				'display_name' => 'lolz',
				'roles'        => '',
			)
		);

		delete_user_option( $lolz, 'capabilities' );
		delete_user_option( $lolz, 'user_level' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users/' . $lolz );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
		} else {
			$data = $response->get_data();

			$this->assertEquals( $data['capabilities'], new stdClass() );
			$this->assertEquals( $data['extra_capabilities'], new stdClass() );
		}
	}

	public function test_cannot_get_item_without_permission() {
		wp_set_current_user( self::$editor );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$user ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 403 );
	}

	public function test_can_get_item_author_of_rest_true_public_true_unauthenticated() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$authors['r_true_p_true'] ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_can_get_item_author_of_rest_true_public_true_authenticated() {
		wp_set_current_user( self::$editor );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$authors['r_true_p_true'] ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_can_get_item_author_of_rest_true_public_false() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$authors['r_true_p_false'] ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_cannot_get_item_author_of_rest_false_public_true_unauthenticated() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$authors['r_false_p_true'] ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 401 );
	}

	public function test_cannot_get_item_author_of_rest_false_public_true_without_permission() {
		wp_set_current_user( self::$editor );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$authors['r_false_p_true'] ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 403 );
	}

	public function test_cannot_get_item_author_of_rest_false_public_false() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$authors['r_false_p_false'] ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 401 );
	}

	public function test_can_get_item_author_of_post() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_cannot_get_item_author_of_draft() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$draft_editor ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 401 );
	}

	public function test_get_item_published_author_post() {
		$author_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $author_id,
			)
		);

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $author_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_user_response( $response, 'embed' );
	}

	public function test_get_item_published_author_pages() {
		$author_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $author_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_type'   => 'page',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->check_get_user_response( $response, 'embed' );
	}

	public function test_get_user_with_edit_context() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_user_response( $response, 'edit' );
	}

	public function test_get_item_published_author_wrong_context() {
		$author_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $author_id,
			)
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $author_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 401 );
	}

	public function test_get_current_user() {
		wp_set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users/me' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->check_get_user_response( $response, 'view' );

		$headers = $response->get_headers();
		$this->assertArrayNotHasKey( 'Location', $headers );

		$links = $response->get_links();
		$this->assertSame( rest_url( 'wp/v2/users/' . self::$user ), $links['self'][0]['href'] );
	}

	public function test_get_current_user_without_permission() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users/me' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_not_logged_in', $response, 401 );
	}

	public function test_create_item() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'username'    => 'testuser',
			'password'    => 'testpassword',
			'email'       => 'test@example.com',
			'name'        => 'Test User',
			'nickname'    => 'testuser',
			'slug'        => 'test-user',
			'roles'       => array( 'editor' ),
			'description' => 'New API User',
			'url'         => 'http://example.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'http://example.com', $data['url'] );
		$this->assertSame( array( 'editor' ), $data['roles'] );
		$this->check_add_edit_user_response( $response );
	}

	public function test_create_item_invalid_username() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'username'    => '¯\_(ツ)_/¯',
			'password'    => 'testpassword',
			'email'       => 'test@example.com',
			'name'        => 'Test User',
			'nickname'    => 'testuser',
			'slug'        => 'test-user',
			'roles'       => array( 'editor' ),
			'description' => 'New API User',
			'url'         => 'http://example.com',
		);

		// Username rules are different (more strict) for multisite; see `wpmu_validate_user_signup`.
		if ( is_multisite() ) {
			$params['username'] = 'no-dashes-allowed';
		}

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		$data = $response->get_data();

		if ( is_multisite() ) {
			$this->assertIsArray( $data['additional_errors'] );
			$this->assertCount( 1, $data['additional_errors'] );
			$error = $data['additional_errors'][0];
			$this->assertSame( 'user_name', $error['code'] );
			$this->assertSame( 'Usernames can only contain lowercase letters (a-z) and numbers.', $error['message'] );
		} else {
			$this->assertIsArray( $data['data']['params'] );
			$errors = $data['data']['params'];
			$this->assertIsString( $errors['username'] );
			$this->assertSame( 'This username is invalid because it uses illegal characters. Please enter a valid username.', $errors['username'] );
		}
	}

	public function get_illegal_user_logins() {
		return array( 'nope' );
	}

	public function test_create_item_illegal_username() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		add_filter( 'illegal_user_logins', array( $this, 'get_illegal_user_logins' ) );

		$params = array(
			'username'    => 'nope',
			'password'    => 'testpassword',
			'email'       => 'test@example.com',
			'name'        => 'Test User',
			'nickname'    => 'testuser',
			'slug'        => 'test-user',
			'roles'       => array( 'editor' ),
			'description' => 'New API User',
			'url'         => 'http://example.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'illegal_user_logins', array( $this, 'get_illegal_user_logins' ) );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		$data = $response->get_data();
		$this->assertIsArray( $data['data']['params'] );
		$errors = $data['data']['params'];
		$this->assertIsString( $errors['username'] );
		$this->assertSame( 'Sorry, that username is not allowed.', $errors['username'] );
	}

	/**
	 * @group ms-required
	 */
	public function test_create_new_network_user_on_site_does_not_add_user_to_sub_site() {
		$this->allow_user_to_manage_multisite();

		$params = array(
			'username' => 'testuser123',
			'password' => 'testpassword',
			'email'    => 'test@example.com',
			'name'     => 'Test User 123',
			'roles'    => array( 'editor' ),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$user_id  = $data['id'];

		$user_is_member = is_user_member_of_blog( $user_id, self::$site );

		wpmu_delete_user( $user_id );

		$this->assertFalse( $user_is_member );
	}

	/**
	 * @ticket 41101
	 * @group ms-required
	 */
	public function test_create_new_network_user_with_add_user_to_blog_failure() {
		$this->allow_user_to_manage_multisite();

		$params = array(
			'username' => 'testuser123',
			'password' => 'testpassword',
			'email'    => 'test@example.com',
			'name'     => 'Test User 123',
			'roles'    => array( 'editor' ),
		);

		add_filter( 'can_add_user_to_blog', '__return_false' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'user_cannot_be_added', $response );
	}

	/**
	 * @group ms-required
	 */
	public function test_create_new_network_user_on_sub_site_adds_user_to_site() {
		$this->allow_user_to_manage_multisite();

		$params = array(
			'username' => 'testuser123',
			'password' => 'testpassword',
			'email'    => 'test@example.com',
			'name'     => 'Test User 123',
			'roles'    => array( 'editor' ),
		);

		switch_to_blog( self::$site );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$user_id  = $data['id'];

		restore_current_blog();

		$user_is_member = is_user_member_of_blog( $user_id, self::$site );

		wpmu_delete_user( $user_id );

		$this->assertTrue( $user_is_member );
	}

	/**
	 * @group ms-required
	 */
	public function test_create_existing_network_user_on_sub_site_has_error() {
		$this->allow_user_to_manage_multisite();

		$params = array(
			'username' => 'testuser123',
			'password' => 'testpassword',
			'email'    => 'test@example.com',
			'name'     => 'Test User 123',
			'roles'    => array( 'editor' ),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$user_id  = $data['id'];

		switch_to_blog( self::$site );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$switched_response = rest_get_server()->dispatch( $request );

		restore_current_blog();

		wpmu_delete_user( $user_id );

		$this->assertErrorResponse( 'rest_invalid_param', $switched_response, 400 );
		$data = $switched_response->get_data();
		$this->assertIsArray( $data['additional_errors'] );
		$this->assertCount( 2, $data['additional_errors'] );
		$errors = $data['additional_errors'];
		foreach ( $errors as $error ) {
			// Check the code matches one we know.
			$this->assertContains( $error['code'], array( 'user_name', 'user_email' ) );
			if ( 'user_name' === $error['code'] ) {
				$this->assertSame( 'Sorry, that username already exists!', $error['message'] );
			} else {
				$expected = '<strong>Error:</strong> This email address is already registered. ' .
							'<a href="http://rest.wordpress.org/wp-login.php">Log in</a> with ' .
							'this address or choose another one.';
				$this->assertSame( $expected, $error['message'] );
			}
		}
	}

	public function test_json_create_user() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'username' => 'testjsonuser',
			'password' => 'testjsonpassword',
			'email'    => 'testjson@example.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_add_edit_user_response( $response );
	}

	public function test_create_user_without_permission() {
		wp_set_current_user( self::$editor );

		$params = array(
			'username' => 'homersimpson',
			'password' => 'stupidsexyflanders',
			'email'    => 'chunkylover53@aol.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_create_user', $response, 403 );
	}

	public function test_create_user_invalid_id() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'id'       => '156',
			'username' => 'lisasimpson',
			'password' => 'DavidHasselhoff',
			'email'    => 'smartgirl63_@yahoo.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_exists', $response, 400 );
	}

	public function test_create_user_invalid_email() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'username' => 'lisasimpson',
			'password' => 'DavidHasselhoff',
			'email'    => 'something',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_user_invalid_role() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'username' => 'maggiesimpson',
			'password' => 'i_shot_mrburns',
			'email'    => 'packingheat@example.com',
			'roles'    => array( 'baby' ),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 400 );
	}

	public function test_update_item() {
		$user_id = self::factory()->user->create(
			array(
				'user_email' => 'test@example.com',
				'user_pass'  => 'sjflsfls',
				'user_login' => 'test_update',
				'first_name' => 'Old Name',
				'user_url'   => 'http://apple.com',
				'locale'     => 'en_US',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$userdata  = get_userdata( $user_id );
		$pw_before = $userdata->user_pass;

		$_POST['email']      = $userdata->user_email;
		$_POST['username']   = $userdata->user_login;
		$_POST['first_name'] = 'New Name';
		$_POST['url']        = 'http://google.com';
		$_POST['locale']     = 'de_DE';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $_POST );
		$response = rest_get_server()->dispatch( $request );

		$this->check_add_edit_user_response( $response, true );

		// Check that the name has been updated correctly.
		$new_data = $response->get_data();
		$this->assertSame( 'New Name', $new_data['first_name'] );
		$user = get_userdata( $user_id );
		$this->assertSame( 'New Name', $user->first_name );

		$this->assertSame( 'http://google.com', $new_data['url'] );
		$this->assertSame( 'http://google.com', $user->user_url );
		$this->assertSame( 'de_DE', $user->locale );

		// Check that we haven't inadvertently changed the user's password,
		// as per https://core.trac.wordpress.org/ticket/21429
		$this->assertSame( $pw_before, $user->user_pass );
	}

	public function test_update_item_no_change() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$user = get_userdata( self::$editor );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$request->set_param( 'slug', $user->user_nicename );

		// Run twice to make sure that the update still succeeds
		// even if no DB rows are updated.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_item_existing_email() {
		$user1 = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user',
				'user_email' => 'testjson@example.com',
			)
		);
		$user2 = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user2',
				'user_email' => 'testjson2@example.com',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user2 );
		$request->set_param( 'email', 'testjson@example.com' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 'rest_user_invalid_email', $response->as_error()->get_error_code() );
	}

	/**
	 * @ticket 44672
	 */
	public function test_update_item_existing_email_case() {
		wp_set_current_user( self::$editor );

		$user = get_userdata( self::$editor );

		$updated_email_with_case_change = ucwords( $user->user_email );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$request->set_param( 'email', $updated_email_with_case_change );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $updated_email_with_case_change, $data['email'] );
	}

	/**
	 * @ticket 44672
	 */
	public function test_update_item_existing_email_case_not_own() {
		wp_set_current_user( self::$editor );

		$user       = get_userdata( self::$editor );
		$subscriber = get_userdata( self::$subscriber );

		$updated_email_with_case_change = ucwords( $subscriber->user_email );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$request->set_param( 'email', $updated_email_with_case_change );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_user_invalid_email', $data['code'] );
	}

	public function test_update_item_invalid_locale() {
		$user1 = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user',
				'user_email' => 'testjson@example.com',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user1 );
		$request->set_param( 'locale', 'klingon' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
	}

	public function test_update_item_en_US_locale() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user',
				'user_email' => 'testjson@example.com',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user_id );
		$request->set_param( 'locale', 'en_US' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_add_edit_user_response( $response, true );

		$user = get_userdata( $user_id );
		$this->assertSame( 'en_US', $user->locale );
	}

	/**
	 * @ticket 38632
	 */
	public function test_update_item_empty_locale() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user',
				'user_email' => 'testjson@example.com',
				'locale'     => 'de_DE',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user_id );
		$request->set_param( 'locale', '' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_add_edit_user_response( $response, true );

		$data = $response->get_data();
		$this->assertSame( get_locale(), $data['locale'] );
		$user = get_userdata( $user_id );
		$this->assertSame( '', $user->locale );
	}

	public function test_update_item_username_attempt() {
		$user1 = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user',
				'user_email' => 'testjson@example.com',
			)
		);
		$user2 = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user2',
				'user_email' => 'testjson2@example.com',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user2 );
		$request->set_param( 'username', 'test_json_user' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 'rest_user_invalid_argument', $response->as_error()->get_error_code() );
	}

	public function test_update_item_existing_nicename() {
		$user1 = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user',
				'user_email' => 'testjson@example.com',
			)
		);
		$user2 = self::factory()->user->create(
			array(
				'user_login' => 'test_json_user2',
				'user_email' => 'testjson2@example.com',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user2 );
		$request->set_param( 'slug', 'test_json_user' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 'rest_user_invalid_slug', $response->as_error()->get_error_code() );
	}

	public function test_json_update_user() {
		$user_id = self::factory()->user->create(
			array(
				'user_email' => 'testjson2@example.com',
				'user_pass'  => 'sjflsfl3sdjls',
				'user_login' => 'test_json_update',
				'first_name' => 'Old Name',
				'last_name'  => 'Original Last',
			)
		);

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'username'   => 'test_json_update',
			'email'      => 'testjson2@example.com',
			'first_name' => 'JSON Name',
			'last_name'  => 'New Last',
		);

		$userdata  = get_userdata( $user_id );
		$pw_before = $userdata->user_pass;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->check_add_edit_user_response( $response, true );

		// Check that the name has been updated correctly.
		$new_data = $response->get_data();
		$this->assertSame( 'JSON Name', $new_data['first_name'] );
		$this->assertSame( 'New Last', $new_data['last_name'] );
		$user = get_userdata( $user_id );
		$this->assertSame( 'JSON Name', $user->first_name );
		$this->assertSame( 'New Last', $user->last_name );

		// Check that we haven't inadvertently changed the user's password,
		// as per https://core.trac.wordpress.org/ticket/21429
		$this->assertSame( $pw_before, $user->user_pass );
	}

	public function test_update_user_role() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( self::$user );

		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'roles', array( 'editor' ) );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();

		$this->assertSame( 'editor', $new_data['roles'][0] );
		$this->assertNotEquals( 'administrator', $new_data['roles'][0] );

		$user = get_userdata( $user_id );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'administrator', $user->caps );
	}

	public function test_update_user_multiple_roles() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( self::$user );

		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'roles', 'author,editor' );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();

		$this->assertSame( array( 'author', 'editor' ), $new_data['roles'] );

		$user = get_userdata( $user_id );
		$this->assertArrayHasKey( 'author', $user->caps );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'administrator', $user->caps );
	}

	public function test_update_user_role_invalid_privilege_escalation() {
		wp_set_current_user( self::$editor );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$request->set_param( 'roles', array( 'administrator' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_roles', $response, 403 );
		$user = get_userdata( self::$editor );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'administrator', $user->caps );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/me' );
		$request->set_param( 'roles', array( 'administrator' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_roles', $response, 403 );
		$user = get_userdata( self::$editor );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'administrator', $user->caps );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_update_user_role_invalid_privilege_deescalation() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'roles', array( 'editor' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 403 );

		$user = get_userdata( $user_id );
		$this->assertArrayHasKey( 'administrator', $user->caps );
		$this->assertArrayNotHasKey( 'editor', $user->caps );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/me' );
		$request->set_param( 'roles', array( 'editor' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 403 );

		$user = get_userdata( $user_id );
		$this->assertArrayHasKey( 'administrator', $user->caps );
		$this->assertArrayNotHasKey( 'editor', $user->caps );
	}

	/**
	 * @group ms-required
	 */
	public function test_update_user_role_privilege_deescalation_multisite() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );
		$user = wp_get_current_user();
		update_site_option( 'site_admins', array( $user->user_login ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'roles', array( 'editor' ) );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertSame( 'editor', $new_data['roles'][0] );
		$this->assertNotEquals( 'administrator', $new_data['roles'][0] );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );
		$user = wp_get_current_user();
		update_site_option( 'site_admins', array( $user->user_login ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/me' );
		$request->set_param( 'roles', array( 'editor' ) );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertSame( 'editor', $new_data['roles'][0] );
		$this->assertNotEquals( 'administrator', $new_data['roles'][0] );
	}


	public function test_update_user_role_invalid_role() {
		wp_set_current_user( self::$user );

		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$request->set_param( 'roles', array( 'BeSharp' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 400 );

		$user = get_userdata( self::$editor );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'BeSharp', $user->caps );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/me' );
		$request->set_param( 'roles', array( 'BeSharp' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 400 );

		$user = get_userdata( self::$editor );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'BeSharp', $user->caps );
	}

	public function test_update_user_without_permission() {
		wp_set_current_user( self::$editor );

		$params = array(
			'username' => 'homersimpson',
			'password' => 'stupidsexyflanders',
			'email'    => 'chunkylover53@aol.com',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$user ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/me' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_argument', $response, 400 );
	}

	public function test_update_user_invalid_id() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$params = array(
			'id'       => '0',
			'username' => 'lisasimpson',
			'password' => 'DavidHasselhoff',
			'email'    => 'smartgirl63_@yahoo.com',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 40263
	 */
	public function test_update_item_only_roles_as_editor() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		wp_set_current_user( self::$editor );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'roles', array( 'editor' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit_roles', $response, 403 );
	}

	/**
	 * @ticket 40263
	 */
	public function test_update_item_only_roles_as_site_administrator() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'roles', array( 'editor' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$new_data = $response->get_data();
		$this->assertSame( 'editor', $new_data['roles'][0] );
	}

	/**
	 * @ticket 40263
	 */
	public function test_update_item_including_roles_and_other_params() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'roles', array( 'editor' ) );
		$request->set_param( 'name', 'Short-Lived User' );
		$response = rest_get_server()->dispatch( $request );

		if ( is_multisite() ) {
			/*
			 * Site administrators can promote users, as verified by the previous test,
			 * but they cannot perform other user-editing operations.
			 * This also tests the branch of logic that verifies that no parameters
			 * other than 'id' and 'roles' are specified for a roles update.
			 */
			$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
		} else {
			$this->assertSame( 200, $response->get_status() );

			$new_data = $response->get_data();
			$this->assertSame( 'editor', $new_data['roles'][0] );
		}
	}

	public function test_update_item_invalid_password() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', self::$editor ) );
		$request->set_param( 'password', 'no\\backslashes\\allowed' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		$request->set_param( 'password', '' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function verify_user_roundtrip( $input = array(), $expected_output = array() ) {
		if ( isset( $input['id'] ) ) {
			// Existing user; don't try to create one.
			$user_id = $input['id'];
		} else {
			// Create a new user.
			$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
			foreach ( $input as $name => $value ) {
				$request->set_param( $name, $value );
			}
			$request->set_param( 'email', 'cbg@androidsdungeon.com' );
			$response = rest_get_server()->dispatch( $request );
			$this->assertSame( 201, $response->get_status() );
			$actual_output = $response->get_data();

			// Compare expected API output to actual API output.
			$this->assertSame( $expected_output['username'], $actual_output['username'] );
			$this->assertSame( $expected_output['name'], $actual_output['name'] );
			$this->assertSame( $expected_output['first_name'], $actual_output['first_name'] );
			$this->assertSame( $expected_output['last_name'], $actual_output['last_name'] );
			$this->assertSame( $expected_output['url'], $actual_output['url'] );
			$this->assertSame( $expected_output['description'], $actual_output['description'] );
			$this->assertSame( $expected_output['nickname'], $actual_output['nickname'] );

			// Compare expected API output to WP internal values.
			$user = get_userdata( $actual_output['id'] );
			$this->assertSame( $expected_output['username'], $user->user_login );
			$this->assertSame( $expected_output['name'], $user->display_name );
			$this->assertSame( $expected_output['first_name'], $user->first_name );
			$this->assertSame( $expected_output['last_name'], $user->last_name );
			$this->assertSame( $expected_output['url'], $user->user_url );
			$this->assertSame( $expected_output['description'], $user->description );
			$this->assertSame( $expected_output['nickname'], $user->nickname );
			$this->assertTrue( wp_check_password( addslashes( $expected_output['password'] ), $user->user_pass ) );

			$user_id = $actual_output['id'];
		}

		// Update the user.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		foreach ( $input as $name => $value ) {
			if ( 'username' !== $name ) {
				$request->set_param( $name, $value );
			}
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output.
		if ( isset( $expected_output['username'] ) ) {
			$this->assertSame( $expected_output['username'], $actual_output['username'] );
		}
		$this->assertSame( $expected_output['name'], $actual_output['name'] );
		$this->assertSame( $expected_output['first_name'], $actual_output['first_name'] );
		$this->assertSame( $expected_output['last_name'], $actual_output['last_name'] );
		$this->assertSame( $expected_output['url'], $actual_output['url'] );
		$this->assertSame( $expected_output['description'], $actual_output['description'] );
		$this->assertSame( $expected_output['nickname'], $actual_output['nickname'] );

		// Compare expected API output to WP internal values.
		$user = get_userdata( $actual_output['id'] );
		if ( isset( $expected_output['username'] ) ) {
			$this->assertSame( $expected_output['username'], $user->user_login );
		}
		$this->assertSame( $expected_output['name'], $user->display_name );
		$this->assertSame( $expected_output['first_name'], $user->first_name );
		$this->assertSame( $expected_output['last_name'], $user->last_name );
		$this->assertSame( $expected_output['url'], $user->user_url );
		$this->assertSame( $expected_output['description'], $user->description );
		$this->assertSame( $expected_output['nickname'], $user->nickname );
		$this->assertTrue( wp_check_password( addslashes( $expected_output['password'] ), $user->user_pass ) );
	}

	public function test_user_roundtrip_as_editor() {
		wp_set_current_user( self::$editor );

		$this->assertSame( ! is_multisite(), current_user_can( 'unfiltered_html' ) );
		$this->verify_user_roundtrip(
			array(
				'id'          => self::$editor,
				'name'        => '\o/ ¯\_(ツ)_/¯',
				'first_name'  => '\o/ ¯\_(ツ)_/¯',
				'last_name'   => '\o/ ¯\_(ツ)_/¯',
				'url'         => '\o/ ¯\_(ツ)_/¯',
				'description' => '\o/ ¯\_(ツ)_/¯',
				'nickname'    => '\o/ ¯\_(ツ)_/¯',
				'password'    => 'o/ ¯_(ツ)_/¯ \'"',
			),
			array(
				'name'        => '\o/ ¯\_(ツ)_/¯',
				'first_name'  => '\o/ ¯\_(ツ)_/¯',
				'last_name'   => '\o/ ¯\_(ツ)_/¯',
				'url'         => 'http://o/%20¯_(ツ)_/¯',
				'description' => '\o/ ¯\_(ツ)_/¯',
				'nickname'    => '\o/ ¯\_(ツ)_/¯',
				'password'    => 'o/ ¯_(ツ)_/¯ \'"',
			)
		);
	}

	public function test_user_roundtrip_as_editor_html() {
		wp_set_current_user( self::$editor );

		if ( is_multisite() ) {
			$this->assertFalse( current_user_can( 'unfiltered_html' ) );
			$this->verify_user_roundtrip(
				array(
					'id'          => self::$editor,
					'name'        => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'first_name'  => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'last_name'   => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'url'         => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'nickname'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'password'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'name'        => 'div strong',
					'first_name'  => 'div strong',
					'last_name'   => 'div strong',
					'url'         => 'http://divdiv/div%20strongstrong/strong%20scriptoh%20noes/script',
					'description' => 'div <strong>strong</strong> oh noes',
					'nickname'    => 'div strong',
					'password'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				)
			);
		} else {
			$this->assertTrue( current_user_can( 'unfiltered_html' ) );
			$this->verify_user_roundtrip(
				array(
					'id'          => self::$editor,
					'name'        => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'first_name'  => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'last_name'   => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'url'         => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'nickname'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'password'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'name'        => 'div strong',
					'first_name'  => 'div strong',
					'last_name'   => 'div strong',
					'url'         => 'http://divdiv/div%20strongstrong/strong%20scriptoh%20noes/script',
					'description' => 'div <strong>strong</strong> oh noes',
					'nickname'    => 'div strong',
					'password'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				)
			);
		}
	}

	public function test_user_roundtrip_as_superadmin() {
		wp_set_current_user( self::$superadmin );

		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$valid_username = is_multisite() ? 'noinvalidcharshere' : 'no-invalid-chars-here';
		$this->verify_user_roundtrip(
			array(
				'username'    => $valid_username,
				'name'        => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'first_name'  => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'last_name'   => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'url'         => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'description' => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'nickname'    => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'password'    => '& &amp; &invalid; < &lt; &amp;lt;',
			),
			array(
				'username'    => $valid_username,
				'name'        => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'first_name'  => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'last_name'   => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'url'         => 'http://&amp;%20&amp;%20&amp;invalid;%20%20&lt;%20&amp;lt;',
				'description' => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'nickname'    => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'password'    => '& &amp; &invalid; < &lt; &amp;lt;',
			)
		);
	}

	public function test_user_roundtrip_as_superadmin_html() {
		wp_set_current_user( self::$superadmin );

		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$valid_username = is_multisite() ? 'noinvalidcharshere' : 'no-invalid-chars-here';
		$this->verify_user_roundtrip(
			array(
				'username'    => $valid_username,
				'name'        => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'first_name'  => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'last_name'   => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'url'         => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'nickname'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'password'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
			),
			array(
				'username'    => $valid_username,
				'name'        => 'div strong',
				'first_name'  => 'div strong',
				'last_name'   => 'div strong',
				'url'         => 'http://divdiv/div%20strongstrong/strong%20scriptoh%20noes/script',
				'description' => 'div <strong>strong</strong> oh noes',
				'nickname'    => 'div strong',
				'password'    => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
			)
		);
	}

	public function test_delete_item() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Deleted User' ) );

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$userdata = get_userdata( $user_id ); // Cache for later.
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'Deleted User', $data['previous']['name'] );
	}

	public function test_delete_item_no_trash() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Deleted User' ) );

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$userdata = get_userdata( $user_id ); // Cache for later.

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the user still exists.
		$user = get_user_by( 'id', $user_id );
		$this->assertNotEmpty( $user );
	}

	public function test_delete_current_item() {
		$user_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Deleted User',
			)
		);

		wp_set_current_user( $user_id );
		$user = wp_get_current_user();
		update_site_option( 'site_admins', array( $user->user_login ) );

		$request          = new WP_REST_Request( 'DELETE', '/wp/v2/users/me' );
		$request['force'] = true;
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'Deleted User', $data['previous']['name'] );
	}

	public function test_delete_current_item_no_trash() {
		$user_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Deleted User',
			)
		);

		wp_set_current_user( $user_id );
		$user = wp_get_current_user();
		update_site_option( 'site_admins', array( $user->user_login ) );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/users/me' );
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the user still exists.
		$user = get_user_by( 'id', $user_id );
		$this->assertNotEmpty( $user );
	}

	public function test_delete_user_without_permission() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$editor );

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_delete', $response, 403 );

		$request          = new WP_REST_Request( 'DELETE', '/wp/v2/users/me' );
		$request['force'] = true;
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_delete', $response, 403 );
	}

	public function test_delete_user_invalid_id() {
		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request          = new WP_REST_Request( 'DELETE', '/wp/v2/users/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request['force'] = true;
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_delete_user_reassign() {
		$this->allow_user_to_manage_multisite();

		// Test with a new user, to avoid any complications.
		$user_id     = self::factory()->user->create();
		$reassign_id = self::factory()->user->create();
		$test_post   = self::factory()->post->create(
			array(
				'post_author' => $user_id,
			)
		);

		// Sanity check to ensure the factory created the post correctly.
		$post = get_post( $test_post );
		$this->assertEquals( $user_id, $post->post_author );

		wp_set_current_user( self::$user );

		// Delete our test user, and reassign to the new author.
		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', $reassign_id );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$this->assertSame( 200, $response->get_status() );

		// Check that the post has been updated correctly.
		$post = get_post( $test_post );
		$this->assertEquals( $reassign_id, $post->post_author );
	}

	public function test_delete_user_invalid_reassign_id() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$this->assertErrorResponse( 'rest_user_invalid_reassign', $response, 400 );
	}

	public function test_delete_user_invalid_reassign_passed_as_string() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', 'null' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_delete_user_reassign_passed_as_boolean_false_trashes_post() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$test_post = self::factory()->post->create(
			array(
				'post_author' => $user_id,
			)
		);

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$test_post = get_post( $test_post );
		$this->assertSame( 'trash', $test_post->post_status );
	}

	public function test_delete_user_reassign_passed_as_string_false_trashes_post() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$test_post = self::factory()->post->create(
			array(
				'post_author' => $user_id,
			)
		);

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', 'false' );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$test_post = get_post( $test_post );
		$this->assertSame( 'trash', $test_post->post_status );
	}

	public function test_delete_user_reassign_passed_as_empty_string_trashes_post() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$test_post = self::factory()->post->create(
			array(
				'post_author' => $user_id,
			)
		);

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', '' );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$test_post = get_post( $test_post );
		$this->assertSame( 'trash', $test_post->post_status );
	}

	public function test_delete_user_reassign_passed_as_0_reassigns_author() {
		$user_id = self::factory()->user->create();

		$this->allow_user_to_manage_multisite();

		wp_set_current_user( self::$user );

		$test_post = self::factory()->post->create(
			array(
				'post_author' => $user_id,
			)
		);

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', 0 );
		$response = rest_get_server()->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$test_post = get_post( $test_post );
		$this->assertEquals( 0, $test_post->post_author );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertCount( 19, $properties );
		$this->assertArrayHasKey( 'avatar_urls', $properties );
		$this->assertArrayHasKey( 'capabilities', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'email', $properties );
		$this->assertArrayHasKey( 'extra_capabilities', $properties );
		$this->assertArrayHasKey( 'first_name', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'last_name', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'locale', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'nickname', $properties );
		$this->assertArrayHasKey( 'registered_date', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'url', $properties );
		$this->assertArrayHasKey( 'username', $properties );
		$this->assertArrayHasKey( 'roles', $properties );

	}

	public function test_get_item_schema_show_avatar() {
		update_option( 'show_avatars', false );

		// Re-initialize the controller to cache-bust schemas from prior test runs.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Users_Controller();
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;

		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayNotHasKey( 'avatar_urls', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'embed', 'view', 'edit' ),
		);

		register_rest_field(
			'user',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		wp_set_current_user( 1 );

		if ( is_multisite() ) {
			$current_user = wp_get_current_user( 1 );
			update_site_option( 'site_admins', array( $current_user->user_login ) );
		}

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users/1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users/1' );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 123, get_user_meta( 1, 'my_custom_int', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
				'email'         => 'joe@foobar.com',
				'username'      => 'abc123',
				'password'      => 'hello',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 123, $response->data['my_custom_int'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function test_additional_field_update_errors() {
		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'user',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		wp_set_current_user( 1 );

		if ( is_multisite() ) {
			$current_user = wp_get_current_user( 1 );
			update_site_option( 'site_admins', array( $current_user->user_login ) );
		}

		// Check for error on update.
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d', self::$user ) );
		$request->set_body_params(
			array(
				'my_custom_int' => 'returnError',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	/**
	 * @ticket 39701
	 * @group ms-required
	 */
	public function test_get_item_from_different_site_as_site_administrator() {
		switch_to_blog( self::$site );
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);
		restore_current_blog();

		wp_set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 39701
	 * @group ms-required
	 */
	public function test_get_item_from_different_site_as_network_administrator() {
		switch_to_blog( self::$site );
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);
		restore_current_blog();

		wp_set_current_user( self::$superadmin );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 39701
	 * @group ms-required
	 */
	public function test_update_item_from_different_site_as_site_administrator() {
		switch_to_blog( self::$site );
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);
		restore_current_blog();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( array( 'first_name' => 'New Name' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 39701
	 * @group ms-required
	 */
	public function test_update_item_from_different_site_as_network_administrator() {
		switch_to_blog( self::$site );
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);
		restore_current_blog();

		wp_set_current_user( self::$superadmin );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( array( 'first_name' => 'New Name' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 39701
	 * @group ms-required
	 */
	public function test_delete_item_from_different_site_as_site_administrator() {
		switch_to_blog( self::$site );
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);
		restore_current_blog();

		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 39701
	 * @group ms-required
	 */
	public function test_delete_item_from_different_site_as_network_administrator() {
		switch_to_blog( self::$site );
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);
		restore_current_blog();

		wp_set_current_user( self::$superadmin );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 43941
	 * @dataProvider data_get_default_data
	 */
	public function test_get_default_value( $args, $expected ) {
		wp_set_current_user( self::$user );

		$object_type = 'user';
		$meta_key    = 'registered_key1';
		register_meta(
			$object_type,
			$meta_key,
			$args
		);

		// Check for default value.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', self::$user ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( $meta_key, $meta );
		$this->assertSame( $expected, $meta[ $meta_key ] );
	}

	public function data_get_default_data() {
		return array(
			array(
				array(
					'show_in_rest' => true,
					'single'       => true,
					'default'      => 'wibble',
				),
				'wibble',
			),
			array(
				array(
					'show_in_rest' => true,
					'single'       => false,
					'default'      => 'wibble',
				),
				array( 'wibble' ),
			),
			array(
				array(
					'single'       => true,
					'show_in_rest' => array(
						'schema' => array(
							'type'       => 'object',
							'properties' => array(
								'wibble' => array(
									'type' => 'string',
								),
							),
						),
					),
					'type'         => 'object',
					'default'      => array( 'wibble' => 'dibble' ),
				),
				array( 'wibble' => 'dibble' ),
			),
			array(
				array(
					'show_in_rest' => array(
						'schema' => array(
							'type'       => 'object',
							'properties' => array(
								'wibble' => array(
									'type' => 'string',
								),
							),
						),
					),
					'type'         => 'object',
					'single'       => false,
					'default'      => array( 'wibble' => 'dibble' ),
				),
				array( array( 'wibble' => 'dibble' ) ),
			),

			array(
				array(
					'show_in_rest' => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
					'single'       => true,
					'type'         => 'array',
					'default'      => array( 'dibble' ),
				),
				array( 'dibble' ),
			),
			array(
				array(
					'show_in_rest' => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
					'single'       => false,
					'type'         => 'array',
					'default'      => array( 'dibble' ),
				),
				array( array( 'dibble' ) ),
			),
		);
	}

	public function additional_field_get_callback( $object ) {
		return get_user_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $user ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
		update_user_meta( $user->ID, 'my_custom_int', $value );
	}

	protected function check_user_data( $user, $data, $context, $links ) {
		$this->assertSame( $user->ID, $data['id'] );
		$this->assertSame( $user->display_name, $data['name'] );
		$this->assertSame( $user->user_url, $data['url'] );
		$this->assertSame( $user->description, $data['description'] );
		$this->assertSame( get_author_posts_url( $user->ID ), $data['link'] );
		$this->assertArrayHasKey( 'avatar_urls', $data );
		$this->assertSame( $user->user_nicename, $data['slug'] );

		if ( 'edit' === $context ) {
			$this->assertSame( $user->first_name, $data['first_name'] );
			$this->assertSame( $user->last_name, $data['last_name'] );
			$this->assertSame( $user->nickname, $data['nickname'] );
			$this->assertSame( $user->user_email, $data['email'] );
			$this->assertEquals( (object) $user->allcaps, $data['capabilities'] );
			$this->assertEquals( (object) $user->caps, $data['extra_capabilities'] );
			$this->assertSame( gmdate( 'c', strtotime( $user->user_registered ) ), $data['registered_date'] );
			$this->assertSame( $user->user_login, $data['username'] );
			$this->assertSame( $user->roles, $data['roles'] );
			$this->assertSame( get_user_locale( $user ), $data['locale'] );
		}

		if ( 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'roles', $data );
			$this->assertArrayNotHasKey( 'capabilities', $data );
			$this->assertArrayNotHasKey( 'registered_date', $data );
			$this->assertArrayNotHasKey( 'first_name', $data );
			$this->assertArrayNotHasKey( 'last_name', $data );
			$this->assertArrayNotHasKey( 'nickname', $data );
			$this->assertArrayNotHasKey( 'email', $data );
			$this->assertArrayNotHasKey( 'extra_capabilities', $data );
			$this->assertArrayNotHasKey( 'username', $data );
			$this->assertArrayNotHasKey( 'locale', $data );
		}

		$this->assertSameSets(
			array(
				'self',
				'collection',
			),
			array_keys( $links )
		);

		$this->assertArrayNotHasKey( 'password', $data );
	}

	protected function check_get_user_response( $response, $context = 'view' ) {
		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, $context, $response->get_links() );
	}

	protected function check_add_edit_user_response( $response, $update = false ) {
		if ( $update ) {
			$this->assertSame( 200, $response->get_status() );
		} else {
			$this->assertSame( 201, $response->get_status() );
		}

		$data     = $response->get_data();
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, 'edit', $response->get_links() );
	}

	protected function allow_user_to_manage_multisite() {
		wp_set_current_user( self::$user );
		$user = wp_get_current_user();

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( $user->user_login ) );
		}

		return;
	}
}
