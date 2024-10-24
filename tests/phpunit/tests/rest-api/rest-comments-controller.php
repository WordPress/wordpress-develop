<?php
/**
 * Unit tests covering WP_REST_Comments_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Comments_Controller extends WP_Test_REST_Controller_Testcase {
	protected static $superadmin_id;
	protected static $admin_id;
	protected static $editor_id;
	protected static $moderator_id;
	protected static $subscriber_id;
	protected static $author_id;

	protected static $post_id;
	protected static $password_id;
	protected static $private_id;
	protected static $draft_id;
	protected static $trash_id;
	protected static $approved_id;
	protected static $hold_id;

	protected static $comment_ids    = array();
	protected static $total_comments = 30;
	protected static $per_page       = 50;

	protected $endpoint;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		add_role(
			'comment_moderator',
			'Comment Moderator',
			array(
				'read'              => true,
				'moderate_comments' => true,
			)
		);

		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor_id     = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$moderator_id  = $factory->user->create(
			array(
				'role' => 'comment_moderator',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		self::$author_id     = $factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Sea Captain',
				'first_name'   => 'Horatio',
				'last_name'    => 'McCallister',
				'user_email'   => 'captain@thefryingdutchman.com',
				'user_url'     => 'http://thefryingdutchman.com',
			)
		);

		self::$post_id     = $factory->post->create();
		self::$private_id  = $factory->post->create(
			array(
				'post_status' => 'private',
			)
		);
		self::$password_id = $factory->post->create(
			array(
				'post_password' => 'toomanysecrets',
			)
		);
		self::$draft_id    = $factory->post->create(
			array(
				'post_status' => 'draft',
			)
		);
		self::$trash_id    = $factory->post->create(
			array(
				'post_status' => 'trash',
			)
		);

		self::$approved_id = $factory->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 0,
			)
		);
		self::$hold_id     = $factory->comment->create(
			array(
				'comment_approved' => 0,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		// Set up comments for pagination tests.
		for ( $i = 0; $i < self::$total_comments - 1; $i++ ) {
			self::$comment_ids[] = $factory->comment->create(
				array(
					'comment_content' => "Comment {$i}",
					'comment_post_ID' => self::$post_id,
				)
			);
		}
	}

	public static function wpTearDownAfterClass() {
		remove_role( 'comment_moderator' );

		self::delete_user( self::$superadmin_id );
		self::delete_user( self::$admin_id );
		self::delete_user( self::$editor_id );
		self::delete_user( self::$moderator_id );
		self::delete_user( self::$subscriber_id );
		self::delete_user( self::$author_id );

		wp_delete_post( self::$post_id, true );
		wp_delete_post( self::$private_id, true );
		wp_delete_post( self::$password_id, true );
		wp_delete_post( self::$draft_id, true );
		wp_delete_post( self::$trash_id, true );
		wp_delete_post( self::$approved_id, true );
		wp_delete_post( self::$hold_id, true );

		// Remove comments for pagination tests.
		foreach ( self::$comment_ids as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}
	}

	public function set_up() {
		parent::set_up();
		$this->endpoint = new WP_REST_Comments_Controller();
		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/comments', $routes );
		$this->assertCount( 2, $routes['/wp/v2/comments'] );
		$this->assertArrayHasKey( '/wp/v2/comments/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/comments/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments/' . self::$approved_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame(
			array(
				'after',
				'author',
				'author_email',
				'author_exclude',
				'before',
				'context',
				'exclude',
				'include',
				'offset',
				'order',
				'orderby',
				'page',
				'parent',
				'parent_exclude',
				'password',
				'per_page',
				'post',
				'search',
				'status',
				'type',
			),
			$keys
		);
	}

	public function test_get_items() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$comments = $response->get_data();
		$this->assertCount( self::$total_comments, $comments );
	}

	/**
	 * @ticket 38692
	 */
	public function test_get_items_with_password() {
		wp_set_current_user( 0 );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$password_id,
		);

		$password_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'password', 'toomanysecrets' );
		$request->set_param( 'post', self::$password_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertContains( $password_comment, wp_list_pluck( $collection_data, 'id' ) );
	}

	/**
	 * @ticket 38692
	 */
	public function test_get_items_with_password_without_post() {
		wp_set_current_user( 0 );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$password_id,
		);

		$password_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'password', 'toomanysecrets' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertNotContains( $password_comment, wp_list_pluck( $collection_data, 'id' ) );
	}

	/**
	 * @ticket 38692
	 */
	public function test_get_items_with_password_with_multiple_post() {
		wp_set_current_user( 0 );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$password_id,
		);

		$password_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'password', 'toomanysecrets' );
		$request->set_param( 'post', array( self::$password_id, self::$post_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_post', $response, 401 );
	}

	public function test_get_password_items_without_edit_post_permission() {
		wp_set_current_user( 0 );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$password_id,
		);

		$password_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertNotContains( $password_comment, wp_list_pluck( $collection_data, 'id' ) );
	}

	public function test_get_password_items_with_edit_post_permission() {
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$password_id,
		);

		$password_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertContains( $password_comment, wp_list_pluck( $collection_data, 'id' ) );
	}

	public function test_get_items_without_private_post_permission() {
		wp_set_current_user( 0 );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$private_id,
		);

		$private_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertNotContains( $private_comment, wp_list_pluck( $collection_data, 'id' ) );
	}

	public function test_get_items_with_private_post_permission() {
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$private_id,
		);

		$private_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertContains( $private_comment, wp_list_pluck( $collection_data, 'id' ) );
	}

	public function test_get_items_with_invalid_post() {
		wp_set_current_user( 0 );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertNotContains( $comment_id, wp_list_pluck( $collection_data, 'id' ) );

		wp_delete_comment( $comment_id );
	}

	public function test_get_items_with_invalid_post_permission() {
		wp_set_current_user( self::$admin_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertContains( $comment_id, wp_list_pluck( $collection_data, 'id' ) );

		wp_delete_comment( $comment_id );
	}

	public function test_get_items_no_permission_for_context() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_items_no_post() {
		wp_set_current_user( self::$admin_id );

		self::factory()->comment->create_post_comments( 0, 2 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'post', 0 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 2, $comments );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_no_permission_for_no_post( $method ) {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( $method, '/wp/v2/comments' );
		$request->set_param( 'post', 0 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	/**
	 * Data provider intended to provide HTTP method names for testing GET and HEAD requests.
	 *
	 * @return array
	 */
	public function data_readable_http_methods() {
		return array(
			'GET request'  => array( 'GET' ),
			'HEAD request' => array( 'HEAD' ),
		);
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_edit_context( $method ) {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( $method, '/wp/v2/comments' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_items_for_post() {
		$second_post_id = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $second_post_id, 2 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_query_params(
			array(
				'post' => $second_post_id,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$comments = $response->get_data();
		$this->assertCount( 2, $comments );
	}

	public function test_get_items_include_query() {
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
		);

		$id1 = self::factory()->comment->create( $args );
		$id2 = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		// 'order' => 'asc'.
		$request->set_param( 'order', 'asc' );
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

		// Invalid 'orderby' should error.
		$request->set_param( 'orderby', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// Invalid 'include' should error.
		$request->set_param( 'orderby', array( 'include' ) );
		$request->set_param( 'include', array( 'invalid' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_exclude_query() {
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
		);

		$id1 = self::factory()->comment->create( $args );
		$id2 = self::factory()->comment->create( $args );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/comments' );
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
		$request->set_param( 'exclude', array( 'invalid' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_offset_query() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'offset', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( self::$total_comments - 1, $response->get_data() );

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

	public function test_get_items_order_query() {
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
		);

		$id = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		// Order defaults to 'desc'.
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $id, $data[0]['id'] );

		// 'order' => 'asc'.
		$request->set_param( 'order', 'asc' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( self::$approved_id, $data[0]['id'] );

		// 'order' => 'asc,id' should error.
		$request->set_param( 'order', 'asc,id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_private_post_no_permissions( $method ) {
		wp_set_current_user( 0 );

		$post_id = self::factory()->post->create( array( 'post_status' => 'private' ) );

		$request = new WP_REST_Request( $method, '/wp/v2/comments' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_post', $response, 401 );
	}

	public function test_get_items_author_arg() {
		// Authorized.
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
			'user_id'          => self::$author_id,
		);

		self::factory()->comment->create( $args );
		$args['user_id'] = self::$subscriber_id;
		self::factory()->comment->create( $args );
		unset( $args['user_id'] );
		self::factory()->comment->create( $args );

		// Limit to comment author.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'author', self::$author_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 1, $comments );

		// Multiple authors are supported.
		$request->set_param( 'author', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 2, $comments );

		// Invalid 'author' should error.
		$request->set_param( 'author', 'skippy' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// Unavailable to unauthenticated; defaults to error.
		wp_set_current_user( 0 );
		$request->set_param( 'author', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_param', $response, 401 );
	}

	public function test_get_items_author_exclude_arg() {
		// Authorized.
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
			'user_id'          => self::$author_id,
		);

		self::factory()->comment->create( $args );
		$args['user_id'] = self::$subscriber_id;
		self::factory()->comment->create( $args );
		unset( $args['user_id'] );
		self::factory()->comment->create( $args );

		$total_comments = self::$total_comments + 3;

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$comments = $response->get_data();
		$this->assertCount( $total_comments, $comments );

		// Exclude comment author.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'author_exclude', self::$author_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( $total_comments - 1, $comments );

		// Exclude both comment authors.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'author_exclude', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( $total_comments - 2, $comments );

		// 'author_exclude' for invalid author.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'author_exclude', 'skippy' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// Unavailable to unauthenticated; defaults to error.
		wp_set_current_user( 0 );
		$request->set_param( 'author_exclude', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_param', $response, 401 );
	}

	public function test_get_items_parent_arg() {
		$args                   = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
		);
		$parent_id              = self::factory()->comment->create( $args );
		$parent_id2             = self::factory()->comment->create( $args );
		$args['comment_parent'] = $parent_id;
		self::factory()->comment->create( $args );
		$args['comment_parent'] = $parent_id2;
		self::factory()->comment->create( $args );

		$total_comments = self::$total_comments + 4;

		// All comments in the database.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_comments, $response->get_data() );

		// Limit to the parent.
		$request->set_param( 'parent', $parent_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 1, $response->get_data() );

		// Limit to two parents.
		$request->set_param( 'parent', array( $parent_id, $parent_id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );

		// Invalid 'parent' should error.
		$request->set_param( 'parent', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_parent_exclude_arg() {
		$args                   = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
		);
		$parent_id              = self::factory()->comment->create( $args );
		$parent_id2             = self::factory()->comment->create( $args );
		$args['comment_parent'] = $parent_id;
		self::factory()->comment->create( $args );
		$args['comment_parent'] = $parent_id2;
		self::factory()->comment->create( $args );

		$total_comments = self::$total_comments + 4;

		// All comments in the database.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_comments, $response->get_data() );

		// Exclude this particular parent.
		$request->set_param( 'parent_exclude', $parent_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_comments - 1, $response->get_data() );

		// Exclude both comment parents.
		$request->set_param( 'parent_exclude', array( $parent_id, $parent_id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_comments - 2, $response->get_data() );

		// Invalid 'parent_exclude' should error.
		$request->set_param( 'parent_exclude', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_search_query() {
		wp_set_current_user( self::$admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$post_id,
			'comment_content'  => 'foo',
			'comment_author'   => 'Homer J Simpson',
		);

		$id = self::factory()->comment->create( $args );

		$total_comments = self::$total_comments + 1;

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_comments, $response->get_data() );

		// One matching comment.
		$request->set_param( 'search', 'foo' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id, $data[0]['id'] );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comments_pagination_headers( $method ) {
		$total_comments = self::$total_comments;
		$total_pages    = (int) ceil( $total_comments / 10 );

		wp_set_current_user( self::$admin_id );

		// Start of the index.
		$request  = new WP_REST_Request( $method, '/wp/v2/comments' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_comments, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( '/wp/v2/comments' )
		);
		$this->assertStringNotContainsString( 'rel="prev"', $headers['Link'] );
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// 3rd page.
		self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);
		++$total_comments;
		++$total_pages;
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_comments, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( '/wp/v2/comments' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg(
			array(
				'page' => 4,
			),
			rest_url( '/wp/v2/comments' )
		);
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// Last page.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'page', $total_pages );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_comments, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages - 1,
			),
			rest_url( '/wp/v2/comments' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );

		// Out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'page', 100 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_comments, $headers['X-WP-Total'] );
		$this->assertEquals( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages,
			),
			rest_url( '/wp/v2/comments' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comments_invalid_date( $method ) {
		$request = new WP_REST_Request( $method, '/wp/v2/comments' );
		$request->set_param( 'after', 'foo' );
		$request->set_param( 'before', 'bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_comments_valid_date() {
		$comment1 = self::factory()->comment->create(
			array(
				'comment_date'    => '2016-01-15T00:00:00Z',
				'comment_post_ID' => self::$post_id,
			)
		);
		$comment2 = self::factory()->comment->create(
			array(
				'comment_date'    => '2016-01-16T00:00:00Z',
				'comment_post_ID' => self::$post_id,
			)
		);
		$comment3 = self::factory()->comment->create(
			array(
				'comment_date'    => '2016-01-17T00:00:00Z',
				'comment_post_ID' => self::$post_id,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'before', '2016-01-17T00:00:00Z' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $comment2, $data[0]['id'] );
	}

	public function test_get_item() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_comment_data( $data, 'view', $response->get_links() );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->set_query_params(
			array(
				'context' => 'edit',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_comment_data( $data, 'edit', $response->get_links() );
	}

	public function test_prepare_item_limit_fields() {
		wp_set_current_user( self::$admin_id );

		$endpoint = new WP_REST_Comments_Controller();
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,status' );
		$obj      = get_comment( self::$approved_id );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->assertSame(
			array(
				'id',
				'status',
			),
			array_keys( $response->get_data() )
		);
	}

	/**
	 * @ticket 58238
	 */
	public function test_prepare_item_comment_text_filter() {
		$filter = new MockAction();
		add_filter( 'comment_text', array( $filter, 'filter' ), 10, 3 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertCount( 3, $filter->get_args()[0] );
	}

	public function test_get_comment_author_avatar_urls() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 24, $data['author_avatar_urls'] );
		$this->assertArrayHasKey( 48, $data['author_avatar_urls'] );
		$this->assertArrayHasKey( 96, $data['author_avatar_urls'] );

		$comment = get_comment( self::$approved_id );
		// Ignore the subdomain, since get_avatar_url() randomly sets
		// the Gravatar server when building the URL string.
		$this->assertSame( substr( get_avatar_url( $comment->comment_author_email ), 9 ), substr( $data['author_avatar_urls'][96], 9 ) );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_invalid_id( $method ) {
		$request = new WP_REST_Request( $method, '/wp/v2/comments/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_invalid_context( $method ) {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( $method, sprintf( '/wp/v2/comments/%s', self::$approved_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_invalid_post_id( $method ) {
		wp_set_current_user( 0 );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);

		$request  = new WP_REST_Request( $method, '/wp/v2/comments/' . $comment_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_invalid_post_id_as_admin( $method ) {
		wp_set_current_user( self::$admin_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);

		$request  = new WP_REST_Request( $method, '/wp/v2/comments/' . $comment_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_not_approved( $method ) {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( $method, sprintf( '/wp/v2/comments/%d', self::$hold_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_not_approved_same_user( $method ) {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( $method, sprintf( '/wp/v2/comments/%d', self::$hold_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_comment_with_children_link() {
		$comment_id_1 = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$child_comment = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_parent'   => $comment_id_1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'children', $response->get_links() );
	}

	public function test_get_comment_without_children_link() {
		$comment_id_1 = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_with_password_without_edit_post_permission( $method ) {
		wp_set_current_user( self::$subscriber_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$password_id,
		);

		$password_comment = self::factory()->comment->create( $args );

		$request  = new WP_REST_Request( $method, sprintf( '/wp/v2/comments/%s', $password_comment ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 38692
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_comment_with_password_with_valid_password( $method ) {
		wp_set_current_user( self::$subscriber_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => self::$password_id,
		);

		$password_comment = self::factory()->comment->create( $args );

		$request = new WP_REST_Request( $method, sprintf( '/wp/v2/comments/%s', $password_comment ) );
		$request->set_param( 'password', 'toomanysecrets' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'date'         => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->check_comment_data( $data, 'edit', $response->get_links() );
		$this->assertSame( 'hold', $data['status'] );
		$this->assertSame( '2014-11-07T10:14:25', $data['date'] );
		$this->assertSame( self::$post_id, $data['post'] );
	}

	public function data_comment_dates() {
		return array(
			'set date without timezone'     => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date'            => '2016-12-12T14:00:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date_gmt without timezone' => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date_gmt'        => '2016-12-12T19:00:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date with timezone'        => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date'            => '2016-12-12T18:00:00-01:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date_gmt with timezone'    => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date_gmt'        => '2016-12-12T18:00:00-01:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
		);
	}

	/**
	 * @dataProvider data_comment_dates
	 */
	public function test_create_comment_date( $params, $results ) {
		wp_set_current_user( self::$admin_id );

		update_option( 'timezone_string', $params['timezone_string'] );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'content', 'not empty' );
		$request->set_param( 'post', self::$post_id );
		if ( isset( $params['date'] ) ) {
			$request->set_param( 'date', $params['date'] );
		}
		if ( isset( $params['date_gmt'] ) ) {
			$request->set_param( 'date_gmt', $params['date_gmt'] );
		}
		$response = rest_get_server()->dispatch( $request );

		update_option( 'timezone_string', '' );

		$this->assertSame( 201, $response->get_status() );
		$data    = $response->get_data();
		$comment = get_comment( $data['id'] );

		$this->assertSame( $results['date'], $data['date'] );
		$comment_date = str_replace( 'T', ' ', $results['date'] );
		$this->assertSame( $comment_date, $comment->comment_date );

		$this->assertSame( $results['date_gmt'], $data['date_gmt'] );
		$comment_date_gmt = str_replace( 'T', ' ', $results['date_gmt'] );
		$this->assertSame( $comment_date_gmt, $comment->comment_date_gmt );
	}

	public function test_create_item_using_accepted_content_raw_value() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => array(
				'raw' => 'Once something has been approved by the government, it\'s no longer immoral.',
			),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data        = $response->get_data();
		$new_comment = get_comment( $data['id'] );
		$this->assertSame( $params['content']['raw'], $new_comment->comment_content );
	}

	public function test_create_item_error_from_filter() {
		add_filter( 'rest_pre_insert_comment', array( $this, 'return_premade_error' ) );
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'homer@example.org',
			'content'      => array(
				'raw' => 'Aw, he loves beer. Here, little fella.',
			),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'test_rest_premade_error', $response, 418 );
	}

	public function return_premade_error() {
		return new WP_Error( 'test_rest_premade_error', "I'm sorry, I thought he was a party robot.", array( 'status' => 418 ) );
	}

	public function test_create_comment_missing_required_author_name() {
		add_filter( 'rest_allow_anonymous_comments', '__return_true' );
		update_option( 'require_name_email', 1 );

		$params = array(
			'post'         => self::$post_id,
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_author_data_required', $response, 400 );
	}

	public function test_create_comment_empty_required_author_name() {
		add_filter( 'rest_allow_anonymous_comments', '__return_true' );
		update_option( 'require_name_email', 1 );

		$params = array(
			'author_name'  => '',
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'post'         => self::$post_id,
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_author_data_required', $response, 400 );
	}

	public function test_create_comment_missing_required_author_email() {
		wp_set_current_user( self::$admin_id );

		update_option( 'require_name_email', 1 );

		$params = array(
			'post'        => self::$post_id,
			'author_name' => 'Edna Krabappel',
			'content'     => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_author_data_required', $response, 400 );
	}

	public function test_create_comment_empty_required_author_email() {
		wp_set_current_user( self::$admin_id );

		update_option( 'require_name_email', 1 );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Edna Krabappel',
			'author_email' => '',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_author_data_required', $response, 400 );
	}

	public function test_create_comment_author_email_too_short() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Homer J. Simpson',
			'author_email' => 'a@b',
			'content'      => 'in this house, we obey the laws of thermodynamics!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'author_email', $data['data']['params'] );
	}

	public function test_create_item_invalid_no_content() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_content_invalid', $response, 400 );

		$params['content'] = '';
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_content_invalid', $response, 400 );
	}

	/**
	 * @ticket 43177
	 */
	public function test_create_item_invalid_only_spaces_content() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => '   ',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_content_invalid', $response, 400 );
	}

	/**
	 * @ticket 43177
	 */
	public function test_create_item_allows_0_as_content() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => '0',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( '0', $response->get_data()['content']['raw'] );
	}

	/**
	 * @ticket 43177
	 */
	public function test_create_item_allow_empty_comment_filter() {
		add_filter( 'allow_empty_comment', '__return_true' );

		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => '',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( '', $response->get_data()['content']['raw'] );
	}

	public function test_create_item_invalid_date() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => 'It\'s all over\, people! We don\'t have a prayer!',
			'date'         => 'foo-bar',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}


	public function test_create_item_assign_different_user() {
		$subscriber_id = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'cbg@androidsdungeon.com',
			)
		);

		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author'       => $subscriber_id,
			'content'      => 'Worst Comment Ever!',
			'date'         => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $subscriber_id, $data['author'] );
		$this->assertSame( '127.0.0.1', $data['author_ip'] );
	}

	public function test_create_comment_without_type() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => $post_id,
			'author'       => self::$admin_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'date'         => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'comment', $data['type'] );

		$comment_id = $data['id'];

		// Make sure the new comment is present in the collection.
		$collection = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$collection->set_param( 'post', $post_id );
		$collection_response = rest_get_server()->dispatch( $collection );
		$collection_data     = $collection_response->get_data();
		$this->assertSame( $comment_id, $collection_data[0]['id'] );
	}

	/**
	 * @ticket 38820
	 */
	public function test_create_comment_with_invalid_type() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => $post_id,
			'author'       => self::$admin_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'date'         => '2014-11-07T10:14:25',
			'type'         => 'foo',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_comment_type', $response, 400 );
	}

	public function test_create_comment_invalid_email() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => $post_id,
			'author'       => self::$admin_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'hello:)',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'date'         => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_item_current_user() {
		$user_id = self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_email'   => 'lylelanley@example.com',
				'first_name'   => 'Lyle',
				'last_name'    => 'Lanley',
				'display_name' => 'Lyle Lanley',
				'user_url'     => 'http://simpsons.wikia.com/wiki/Lyle_Lanley',
			)
		);

		wp_set_current_user( $user_id );

		$params = array(
			'post'    => self::$post_id,
			'content' => "Well sir, there's nothing on earth like a genuine, bona fide, electrified, six-car Monorail!",
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $user_id, $data['author'] );

		// Check author data matches.
		$author  = get_user_by( 'id', $user_id );
		$comment = get_comment( $data['id'] );
		$this->assertSame( $author->display_name, $comment->comment_author );
		$this->assertSame( $author->user_email, $comment->comment_author_email );
		$this->assertSame( $author->user_url, $comment->comment_author_url );
	}

	public function test_create_comment_other_user() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( self::$subscriber_id, $data['author'] );
		$this->assertSame( 'Homer Jay Simpson', $data['author_name'] );
		$this->assertSame( 'chunkylover53@aol.com', $data['author_email'] );
		$this->assertSame( 'http://compuglobalhypermeganet.com', $data['author_url'] );
	}

	public function test_create_comment_other_user_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$admin_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_invalid_author', $response, 403 );
	}

	public function test_create_comment_invalid_post() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => 'some-slug',
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_comment_status_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
			'status'       => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_invalid_status', $response, 403 );
	}

	public function test_create_comment_with_status_IP_and_user_agent() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'              => $post_id,
			'author_name'       => 'Comic Book Guy',
			'author_email'      => 'cbg@androidsdungeon.com',
			'author_ip'         => '139.130.4.5',
			'author_url'        => 'http://androidsdungeon.com',
			'author_user_agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
			'content'           => 'Worst Comment Ever!',
			'status'            => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'approved', $data['status'] );
		$this->assertSame( '139.130.4.5', $data['author_ip'] );
		$this->assertSame( 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36', $data['author_user_agent'] );
	}

	public function test_create_comment_user_agent_header() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->add_header( 'User_Agent', 'Mozilla/4.0 (compatible; MSIE 5.5; AOL 4.0; Windows 95)' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();

		$new_comment = get_comment( $data['id'] );
		$this->assertSame( 'Mozilla/4.0 (compatible; MSIE 5.5; AOL 4.0; Windows 95)', $new_comment->comment_agent );
	}

	public function test_create_comment_author_ip() {
		wp_set_current_user( self::$admin_id );

		$params  = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author_ip'    => '127.0.0.3',
			'content'      => 'Worst Comment Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response    = rest_get_server()->dispatch( $request );
		$data        = $response->get_data();
		$new_comment = get_comment( $data['id'] );
		$this->assertSame( '127.0.0.3', $new_comment->comment_author_IP );
	}

	public function test_create_comment_invalid_author_IP() {
		wp_set_current_user( self::$admin_id );

		$params  = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author_ip'    => '867.5309',
			'content'      => 'Worst Comment Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_comment_author_ip_no_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author_ip'    => '10.0.10.1',
			'content'      => 'Worst Comment Ever!',
			'status'       => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_author_ip', $response, 403 );
	}

	public function test_create_comment_author_ip_defaults_to_remote_addr() {
		wp_set_current_user( self::$admin_id );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.2';

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response    = rest_get_server()->dispatch( $request );
		$data        = $response->get_data();
		$new_comment = get_comment( $data['id'] );
		$this->assertSame( '127.0.0.2', $new_comment->comment_author_IP );
	}

	public function test_create_comment_no_post_id() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'status'       => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_invalid_post_id', $response, 403 );
	}

	public function test_create_comment_no_post_id_no_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_post_id', $response, 403 );
	}

	public function test_create_comment_invalid_post_id() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'status'       => 'approved',
			'post'         => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_post_id', $response, 403 );
	}

	public function test_create_comment_draft_post() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$draft_id,
			'author_name'  => 'Ishmael',
			'author_email' => 'herman-melville@earthlink.net',
			'author_url'   => 'https://en.wikipedia.org/wiki/Herman_Melville',
			'content'      => 'Call me Ishmael.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_draft_post', $response, 403 );
	}

	public function test_create_comment_trash_post() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$trash_id,
			'author_name'  => 'Ishmael',
			'author_email' => 'herman-melville@earthlink.net',
			'author_url'   => 'https://en.wikipedia.org/wiki/Herman_Melville',
			'content'      => 'Call me Ishmael.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_trash_post', $response, 403 );
	}

	public function test_create_comment_private_post_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$private_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'I\’d be a vegetarian if bacon grew on trees.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_post', $response, 403 );
	}

	public function test_create_comment_password_post_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$password_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'I\’d be a vegetarian if bacon grew on trees.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_post', $response, 403 );
	}

	public function test_create_item_duplicate() {
		wp_set_current_user( self::$subscriber_id );

		self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => 'Guy N. Cognito',
				'comment_author_email' => 'chunkylover53@aol.co.uk',
				'comment_content'      => 'Homer? Who is Homer? My name is Guy N. Cognito.',
			)
		);

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Guy N. Cognito',
			'author_email' => 'chunkylover53@aol.co.uk',
			'content'      => 'Homer? Who is Homer? My name is Guy N. Cognito.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 409, $response->get_status() );
	}

	public function test_create_comment_closed() {
		$post_id = self::factory()->post->create(
			array(
				'comment_status' => 'closed',
			)
		);

		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post' => $post_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_create_comment_require_login() {
		wp_set_current_user( 0 );

		update_option( 'comment_registration', 1 );
		add_filter( 'rest_allow_anonymous_comments', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'post', self::$post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_comment_login_required', $data['code'] );
	}

	public function test_create_item_invalid_author() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'    => self::$post_id,
			'author'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			'content' => 'It\'s all over\, people! We don\'t have a prayer!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_author_invalid', $response, 400 );
	}

	public function test_create_item_pull_author_info() {
		wp_set_current_user( self::$admin_id );

		$author = new WP_User( self::$author_id );
		$params = array(
			'post'    => self::$post_id,
			'author'  => self::$author_id,
			'content' => 'It\'s all over\, people! We don\'t have a prayer!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$result = $response->get_data();
		$this->assertSame( self::$author_id, $result['author'] );
		$this->assertSame( 'Sea Captain', $result['author_name'] );
		$this->assertSame( 'captain@thefryingdutchman.com', $result['author_email'] );
		$this->assertSame( 'http://thefryingdutchman.com', $result['author_url'] );
	}

	public function test_create_comment_two_times() {
		add_filter( 'rest_allow_anonymous_comments', '__return_true' );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Shakes fist at sky',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	public function anonymous_comments_callback_null() {
		// I'm a plugin developer who forgot to include a return value
		// for some code path in my 'rest_allow_anonymous_comments' filter.
	}

	public function test_allow_anonymous_comments_null() {
		add_filter( 'rest_allow_anonymous_comments', array( $this, 'anonymous_comments_callback_null' ), 10, 2 );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'rest_allow_anonymous_comments', array( $this, 'anonymous_comments_callback_null' ), 10, 2 );

		$this->assertErrorResponse( 'rest_comment_login_required', $response, 401 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_comment_author_name_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => rand_long_str( 246 ),
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'date'         => '1995-04-30T10:22:00',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_author_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_comment_author_email_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@' . rand_long_str( 190 ) . '.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'date'         => '1995-04-30T10:22:00',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_author_email_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_comment_author_url_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.' . rand_long_str( 185 ) . '.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'date'         => '1995-04-30T10:22:00',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_author_url_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_comment_content_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => rand_long_str( 66525 ),
			'date'         => '1995-04-30T10:22:00',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_content_column_length', $response, 400 );
	}

	public function test_create_comment_without_password() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'post'         => self::$password_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read_post', $response, 403 );
	}

	public function test_create_comment_with_password() {
		add_filter( 'rest_allow_anonymous_comments', '__return_true' );

		$params = array(
			'post'         => self::$password_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'password'     => 'toomanysecrets',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
	}

	public function test_update_item() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$admin_id );

		$params = array(
			'author'       => self::$subscriber_id,
			'author_name'  => 'Disco Stu',
			'author_url'   => 'http://stusdisco.com',
			'author_email' => 'stu@stusdisco.com',
			'author_ip'    => '4.4.4.4',
			'content'      => 'Testing.',
			'date'         => '2014-11-07T10:14:25',
			'post'         => $post_id,
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( self::$approved_id );
		$this->assertSame( $params['content'], $comment['content']['raw'] );
		$this->assertSame( $params['author'], $comment['author'] );
		$this->assertSame( $params['author_name'], $comment['author_name'] );
		$this->assertSame( $params['author_url'], $comment['author_url'] );
		$this->assertSame( $params['author_email'], $comment['author_email'] );
		$this->assertSame( $params['author_ip'], $comment['author_ip'] );
		$this->assertSame( $params['post'], $comment['post'] );

		$this->assertSame( mysql_to_rfc3339( $updated->comment_date ), $comment['date'] );
		$this->assertSame( '2014-11-07T10:14:25', $comment['date'] );
	}

	/**
	 * @dataProvider data_comment_dates
	 */
	public function test_update_comment_date( $params, $results ) {
		wp_set_current_user( self::$editor_id );

		update_option( 'timezone_string', $params['timezone_string'] );

		$comment_id = self::factory()->comment->create();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		if ( isset( $params['date'] ) ) {
			$request->set_param( 'date', $params['date'] );
		}
		if ( isset( $params['date_gmt'] ) ) {
			$request->set_param( 'date_gmt', $params['date_gmt'] );
		}
		$response = rest_get_server()->dispatch( $request );

		update_option( 'timezone_string', '' );

		$this->assertSame( 200, $response->get_status() );
		$data    = $response->get_data();
		$comment = get_comment( $data['id'] );

		$this->assertSame( $results['date'], $data['date'] );
		$comment_date = str_replace( 'T', ' ', $results['date'] );
		$this->assertSame( $comment_date, $comment->comment_date );

		$this->assertSame( $results['date_gmt'], $data['date_gmt'] );
		$comment_date_gmt = str_replace( 'T', ' ', $results['date_gmt'] );
		$this->assertSame( $comment_date_gmt, $comment->comment_date_gmt );
	}

	public function test_update_item_no_content() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->set_param( 'author_email', 'another@email.com' );

		// Sending a request without content is fine.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		// Sending a request with empty comment is not fine.
		$request->set_param( 'author_email', 'yetanother@email.com' );
		$request->set_param( 'content', '' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_content_invalid', $response, 400 );
	}

	public function test_update_item_no_change() {
		$comment = get_comment( self::$approved_id );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->set_param( 'post', $comment->comment_post_ID );

		// Run twice to make sure that the update still succeeds
		// even if no DB rows are updated.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_comment_status() {
		wp_set_current_user( self::$admin_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 0,
				'comment_post_ID'  => self::$post_id,
			)
		);

		$params = array(
			'status' => 'approve',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( $comment_id );
		$this->assertSame( 'approved', $comment['status'] );
		$this->assertEquals( 1, $updated->comment_approved );
	}

	public function test_update_comment_field_does_not_use_default_values() {
		wp_set_current_user( self::$admin_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 0,
				'comment_post_ID'  => self::$post_id,
				'comment_content'  => 'some content',
			)
		);

		$params = array(
			'status' => 'approve',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( $comment_id );
		$this->assertSame( 'approved', $comment['status'] );
		$this->assertEquals( 1, $updated->comment_approved );
		$this->assertSame( 'some content', $updated->comment_content );
	}

	public function test_update_comment_date_gmt() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'date_gmt' => '2015-05-07T10:14:25',
			'content'  => 'I\'ll be deep in the cold, cold ground before I recognize Missouri.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( self::$approved_id );
		$this->assertSame( $params['date_gmt'], $comment['date_gmt'] );
		$this->assertSame( $params['date_gmt'], mysql_to_rfc3339( $updated->comment_date_gmt ) );
	}

	public function test_update_comment_author_email_only() {
		wp_set_current_user( self::$editor_id );

		update_option( 'require_name_email', 1 );

		$params = array(
			'post'         => self::$post_id,
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_comment_empty_author_name() {
		wp_set_current_user( self::$editor_id );

		update_option( 'require_name_email', 1 );

		$params = array(
			'author_name'  => '',
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'post'         => self::$post_id,
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_comment_author_name_only() {
		wp_set_current_user( self::$admin_id );

		update_option( 'require_name_email', 1 );

		$params = array(
			'post'        => self::$post_id,
			'author_name' => 'Edna Krabappel',
			'content'     => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_comment_empty_author_email() {
		wp_set_current_user( self::$admin_id );

		update_option( 'require_name_email', 1 );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Edna Krabappel',
			'author_email' => '',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_comment_author_email_too_short() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'post'         => self::$post_id,
			'author_name'  => 'Homer J. Simpson',
			'author_email' => 'a@b',
			'content'      => 'in this house, we obey the laws of thermodynamics!',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'author_email', $data['data']['params'] );
	}

	public function test_update_comment_invalid_type() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'type' => 'trackback',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_type', $response, 404 );
	}

	public function test_update_comment_with_raw_property() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'content' => array(
				'raw' => 'What the heck kind of name is Persephone?',
			),
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( self::$approved_id );
		$this->assertSame( $params['content']['raw'], $updated->comment_content );
	}

	public function test_update_item_invalid_date() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'content' => 'content',
			'date'    => 'foo',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_invalid_date_gmt() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'content'  => 'content',
			'date_gmt' => 'foo',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_comment_invalid_id() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'content' => 'Oh, they have the internet on computers now!',
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/comments/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}

	public function test_update_comment_invalid_post_id() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->set_param( 'post', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_post_id', $response, 403 );
	}

	public function test_update_comment_invalid_permission() {
		add_filter( 'rest_allow_anonymous_comments', '__return_true' );

		$params = array(
			'content' => 'Disco Stu likes disco music.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$hold_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );
	}

	/**
	 * @ticket 47024
	 */
	public function test_update_comment_when_can_moderate_comments() {
		wp_set_current_user( self::$moderator_id );

		$params = array(
			'content' => 'Updated comment.',
			'date'    => '2019-10-07T23:14:25',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( self::$approved_id );

		$this->assertSame( $params['content'], $updated->comment_content );
		$this->assertSame( self::$post_id, $comment['post'] );
		$this->assertSame( '2019-10-07T23:14:25', $comment['date'] );
	}

	public function test_update_comment_private_post_invalid_permission() {
		$private_comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$private_id,
				'user_id'          => 0,
			)
		);

		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'content' => 'Disco Stu likes disco music.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $private_comment_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_comment_with_children_link() {
		wp_set_current_user( self::$admin_id );

		$comment_id_1 = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$child_comment = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		// Check if comment 1 does not have the child link.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );

		// Change the comment parent.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%s', $child_comment ) );
		$request->set_param( 'parent', $comment_id_1 );
		$request->set_param( 'content', 'foo bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		// Check if comment 1 now has the child link.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'children', $response->get_links() );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_comment_author_name_too_long() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'author_name' => rand_long_str( 246 ),
			'content'     => 'This isn\'t a saxophone. It\'s an umbrella.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_author_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_comment_author_email_too_long() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'author_email' => 'murphy@' . rand_long_str( 190 ) . '.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_author_email_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_comment_author_url_too_long() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'author_url' => 'http://jazz.' . rand_long_str( 185 ) . '.com',
			'content'    => 'This isn\'t a saxophone. It\'s an umbrella.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_author_url_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_comment_content_too_long() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'content' => rand_long_str( 66525 ),
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'comment_content_column_length', $response, 400 );
	}

	/**
	 * @ticket 39732
	 */
	public function test_update_comment_is_wp_error() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'content' => 'This isn\'t a saxophone. It\'s an umbrella.',
		);

		add_filter( 'wp_update_comment_data', array( $this, '_wp_update_comment_data_filter' ), 10, 3 );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'wp_update_comment_data', array( $this, '_wp_update_comment_data_filter' ), 10, 3 );

		$this->assertErrorResponse( 'rest_comment_failed_edit', $response, 500 );
	}

	/**
	 * Blocks comments from being updated by returning WP_Error.
	 */
	public function _wp_update_comment_data_filter( $data, $comment, $commentarr ) {
		return new WP_Error( 'comment_wrong', 'wp_update_comment_data filter fails for this comment.', array( 'status' => 500 ) );
	}

	public function verify_comment_roundtrip( $input = array(), $expected_output = array() ) {
		// Create the comment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'author_email', 'cbg@androidsdungeon.com' );
		$request->set_param( 'post', self::$post_id );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output.
		$this->assertIsArray( $actual_output['content'] );
		$this->assertArrayHasKey( 'raw', $actual_output['content'] );
		$this->assertSame( $expected_output['content']['raw'], $actual_output['content']['raw'] );
		$this->assertSame( $expected_output['content']['rendered'], trim( $actual_output['content']['rendered'] ) );
		$this->assertSame( $expected_output['author_name'], $actual_output['author_name'] );
		$this->assertSame( $expected_output['author_user_agent'], $actual_output['author_user_agent'] );

		// Compare expected API output to WP internal values.
		$comment = get_comment( $actual_output['id'] );
		$this->assertSame( $expected_output['content']['raw'], $comment->comment_content );
		$this->assertSame( $expected_output['author_name'], $comment->comment_author );
		$this->assertSame( $expected_output['author_user_agent'], $comment->comment_agent );

		// Update the comment.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $actual_output['id'] ) );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		// FIXME At least one value must change, or update fails.
		// See https://core.trac.wordpress.org/ticket/38700
		$request->set_param( 'author_ip', '127.0.0.2' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output.
		$this->assertSame( $expected_output['content']['raw'], $actual_output['content']['raw'] );
		$this->assertSame( $expected_output['content']['rendered'], trim( $actual_output['content']['rendered'] ) );
		$this->assertSame( $expected_output['author_name'], $actual_output['author_name'] );
		$this->assertSame( $expected_output['author_user_agent'], $actual_output['author_user_agent'] );

		// Compare expected API output to WP internal values.
		$comment = get_comment( $actual_output['id'] );
		$this->assertSame( $expected_output['content']['raw'], $comment->comment_content );
		$this->assertSame( $expected_output['author_name'], $comment->comment_author );
		$this->assertSame( $expected_output['author_user_agent'], $comment->comment_agent );
	}

	public function test_comment_roundtrip_as_editor() {
		wp_set_current_user( self::$editor_id );

		$this->assertSame( ! is_multisite(), current_user_can( 'unfiltered_html' ) );
		$this->verify_comment_roundtrip(
			array(
				'content'           => '\o/ ¯\_(ツ)_/¯',
				'author_name'       => '\o/ ¯\_(ツ)_/¯',
				'author_user_agent' => '\o/ ¯\_(ツ)_/¯',
			),
			array(
				'content'           => array(
					'raw'      => '\o/ ¯\_(ツ)_/¯',
					'rendered' => '<p>\o/ ¯\_(ツ)_/¯</p>',
				),
				'author_name'       => '\o/ ¯\_(ツ)_/¯',
				'author_user_agent' => '\o/ ¯\_(ツ)_/¯',
			)
		);
	}

	public function test_comment_roundtrip_as_editor_unfiltered_html() {
		wp_set_current_user( self::$editor_id );

		if ( is_multisite() ) {
			$this->assertFalse( current_user_can( 'unfiltered_html' ) );
			$this->verify_comment_roundtrip(
				array(
					'content'           => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_name'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_user_agent' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author'            => self::$editor_id,
				),
				array(
					'content'           => array(
						'raw'      => 'div <strong>strong</strong> oh noes',
						'rendered' => '<p>div <strong>strong</strong> oh noes</p>',
					),
					'author_name'       => 'div strong',
					'author_user_agent' => 'div strong',
					'author'            => self::$editor_id,
				)
			);
		} else {
			$this->assertTrue( current_user_can( 'unfiltered_html' ) );
			$this->verify_comment_roundtrip(
				array(
					'content'           => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_name'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_user_agent' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author'            => self::$editor_id,
				),
				array(
					'content'           => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
					),
					'author_name'       => 'div strong',
					'author_user_agent' => 'div strong',
					'author'            => self::$editor_id,
				)
			);
		}
	}

	public function test_comment_roundtrip_as_superadmin() {
		wp_set_current_user( self::$superadmin_id );

		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_comment_roundtrip(
			array(
				'content'           => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'author_name'       => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'author_user_agent' => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'author'            => self::$superadmin_id,
			),
			array(
				'content'           => array(
					'raw'      => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
					'rendered' => '<p>\\\&#038;\\\ &amp; &invalid; < &lt; &amp;lt;' . "\n</p>",
				),
				'author_name'       => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'author_user_agent' => '\\\&\\\ &amp; &invalid; &lt; &lt; &amp;lt;',
				'author'            => self::$superadmin_id,
			)
		);
	}

	public function test_comment_roundtrip_as_superadmin_unfiltered_html() {
		wp_set_current_user( self::$superadmin_id );

		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_comment_roundtrip(
			array(
				'content'           => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'author_name'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'author_user_agent' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'author'            => self::$superadmin_id,
			),
			array(
				'content'           => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
				),
				'author_name'       => 'div strong',
				'author_user_agent' => 'div strong',
				'author'            => self::$superadmin_id,
			)
		);
	}

	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'trash', $data['status'] );
	}

	public function test_delete_item_skip_trash() {
		wp_set_current_user( self::$admin_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$request['force'] = true;

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertNotEmpty( $data['previous']['post'] );
	}

	public function test_delete_item_already_trashed() {
		wp_set_current_user( self::$admin_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data     = $response->get_data();
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_already_trashed', $response, 410 );
	}

	public function test_delete_comment_invalid_id() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}

	public function test_delete_comment_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_delete_child_comment_link() {
		wp_set_current_user( self::$admin_id );

		$comment_id_1 = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$child_comment = self::factory()->comment->create(
			array(
				'comment_approved' => 1,
				'comment_parent'   => $comment_id_1,
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$subscriber_id,
			)
		);

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%s', $child_comment ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		// Verify children link is gone.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 17, $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'author_avatar_urls', $properties );
		$this->assertArrayHasKey( 'author_email', $properties );
		$this->assertArrayHasKey( 'author_ip', $properties );
		$this->assertArrayHasKey( 'author_name', $properties );
		$this->assertArrayHasKey( 'author_url', $properties );
		$this->assertArrayHasKey( 'author_user_agent', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'post', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'type', $properties );

		$this->assertSame( 0, $properties['parent']['default'] );
		$this->assertSame( 0, $properties['post']['default'] );

		$this->assertTrue( $properties['link']['readonly'] );
		$this->assertTrue( $properties['type']['readonly'] );
	}

	public function test_get_item_schema_show_avatar() {
		update_option( 'show_avatars', false );

		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayNotHasKey( 'author_avatar_urls', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'comment',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/comments/' . self::$approved_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments/' . self::$approved_id );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
				'content'       => 'abc',
			)
		);

		wp_set_current_user( 1 );
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 123, get_comment_meta( self::$approved_id, 'my_custom_int', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
				'title'         => 'hello',
				'content'       => 'goodbye',
				'post'          => self::$post_id,
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
			'comment',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		wp_set_current_user( self::$admin_id );

		// Check for error on update.
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );
		$request->set_body_params(
			array(
				'my_custom_int' => 'returnError',
				'content'       => 'abc',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $response_data, $field_name ) {
		return get_comment_meta( $response_data['id'], $field_name, true );
	}

	public function additional_field_update_callback( $value, $comment, $field_name ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
		update_comment_meta( $comment->comment_ID, $field_name, $value );
	}

	protected function check_comment_data( $data, $context, $links ) {
		$comment = get_comment( $data['id'] );

		$this->assertEquals( $comment->comment_ID, $data['id'] );
		$this->assertEquals( $comment->comment_post_ID, $data['post'] );
		$this->assertEquals( $comment->comment_parent, $data['parent'] );
		$this->assertEquals( $comment->user_id, $data['author'] );
		$this->assertSame( $comment->comment_author, $data['author_name'] );
		$this->assertSame( $comment->comment_author_url, $data['author_url'] );
		$this->assertSame( wpautop( $comment->comment_content ), $data['content']['rendered'] );
		$this->assertSame( mysql_to_rfc3339( $comment->comment_date ), $data['date'] );
		$this->assertSame( mysql_to_rfc3339( $comment->comment_date_gmt ), $data['date_gmt'] );
		$this->assertSame( get_comment_link( $comment ), $data['link'] );
		$this->assertArrayHasKey( 'author_avatar_urls', $data );
		$this->assertSameSets(
			array(
				'self',
				'collection',
				'up',
			),
			array_keys( $links )
		);

		if ( $comment->comment_post_ID ) {
			$this->assertSame( rest_url( '/wp/v2/posts/' . $comment->comment_post_ID ), $links['up'][0]['href'] );
		}

		if ( 'edit' === $context ) {
			$this->assertSame( $comment->comment_author_email, $data['author_email'] );
			$this->assertSame( $comment->comment_author_IP, $data['author_ip'] );
			$this->assertSame( $comment->comment_agent, $data['author_user_agent'] );
			$this->assertSame( $comment->comment_content, $data['content']['raw'] );
		}

		if ( 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'author_email', $data );
			$this->assertArrayNotHasKey( 'author_ip', $data );
			$this->assertArrayNotHasKey( 'author_user_agent', $data );
			$this->assertArrayNotHasKey( 'raw', $data['content'] );
		}
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 42238
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_check_read_post_permission_with_invalid_post_type( $method ) {
		register_post_type(
			'bug-post',
			array(
				'label'        => 'Bug Posts',
				'supports'     => array( 'title', 'editor', 'author', 'comments' ),
				'show_in_rest' => true,
				'public'       => true,
			)
		);
		create_initial_rest_routes();

		$post_id    = self::factory()->post->create( array( 'post_type' => 'bug-post' ) );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		_unregister_post_type( 'bug-post' );

		$this->setExpectedIncorrectUsage( 'map_meta_cap' );

		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( $method, '/wp/v2/comments/' . $comment_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_only_fetches_ids_for_head_requests( $method ) {
		$is_head_request = 'HEAD' === $method;
		$request         = new WP_REST_Request( $method, '/wp/v2/comments' );

		$filter = new MockAction();

		add_filter( 'comments_pre_query', array( $filter, 'filter' ), 10, 2 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		if ( $is_head_request ) {
			$this->assertEmpty( $response->get_data() );
		} else {
			$this->assertNotEmpty( $response->get_data() );
		}

		$args = $filter->get_args();
		$this->assertTrue( isset( $args[0][1] ), 'Query parameters were not captured.' );
		$this->assertInstanceOf( WP_Comment_Query::class, $args[0][1], 'Query parameters were not captured.' );

		/** @var WP_Comment_Query $query */
		$query = $args[0][1];

		if ( $is_head_request ) {
			$this->assertArrayHasKey( 'fields', $query->query_vars, 'The fields parameter is not set in the query vars.' );
			$this->assertSame( 'ids', $query->query_vars['fields'], 'The query must fetch only post IDs.' );
		} else {
			$this->assertTrue( ! array_key_exists( 'fields', $query->query_vars ) || 'ids' !== $query->query_vars['fields'], 'The fields parameter should not be forced to "ids" for non-HEAD requests.' );
			return;
		}

		global $wpdb;
		$comments_table = preg_quote( $wpdb->comments, '/' );
		$pattern        = '/^SELECT\s+SQL_CALC_FOUND_ROWS\s+' . $comments_table . '\.comment_ID\s+FROM\s+' . $comments_table . '\s+WHERE/i';

		// Assert that the SQL query only fetches the ID column.
		$this->assertMatchesRegularExpression( $pattern, $query->request, 'The SQL query does not match the expected string.' );
	}

	/**
	 * @ticket 56481
	 */
	public function test_get_item_with_head_request_should_not_prepare_comment_data() {
		$request = new WP_REST_Request( 'HEAD', sprintf( '/wp/v2/comments/%d', self::$approved_id ) );

		$hook_name = 'rest_prepare_comment';

		$filter   = new MockAction();
		$callback = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 0, $filter->get_call_count(), 'The "' . $hook_name . '" filter was called when it should not be for HEAD requests.' );
		$this->assertNull( $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}
}
