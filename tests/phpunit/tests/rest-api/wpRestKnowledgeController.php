<?php
/**
 * Unit tests covering WP_REST_Knowledge_Controller.
 *
 * @package WordPress
 * @subpackage REST_API
 *
 * @group knowledge
 * @group restapi
 *
 * @covers WP_REST_Knowledge_Controller
 */
class Tests_REST_WpRestKnowledgeController extends WP_Test_REST_Controller_Testcase {

	protected static int $admin_id;

	protected static int $contributor_id;

	protected static int $subscriber_id;

	/**
	 * A private knowledge row owned by the administrator.
	 */
	protected static int $admin_private;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$throw_if_not_int = static function ( $value ): int {
			if ( ! is_int( $value ) ) {
				throw new Exception( 'Value is not an int.' );
			}
			return $value;
		};

		self::$admin_id       = $throw_if_not_int( $factory->user->create( array( 'role' => 'administrator' ) ) );
		self::$contributor_id = $throw_if_not_int( $factory->user->create( array( 'role' => 'contributor' ) ) );
		self::$subscriber_id  = $throw_if_not_int( $factory->user->create( array( 'role' => 'subscriber' ) ) );

		self::$admin_private = $throw_if_not_int(
			$factory->post->create(
				array(
					'post_type'   => 'wp_knowledge',
					'post_status' => 'private',
					'post_author' => self::$admin_id,
					'post_title'  => 'Admin private knowledge',
				)
			)
		);
	}

	public static function wpTearDownAfterClass(): void {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$contributor_id );
		self::delete_user( self::$subscriber_id );
		wp_delete_post( self::$admin_private, true );
	}

	/**
	 * Creates a private knowledge row for the given author.
	 *
	 * @param int $author_id Author user ID.
	 *
	 * @return int Post ID.
	 * @throws Exception In the unlikely event that a factory is unable to create a post.
	 */
	private function create_knowledge_post( int $author_id ): int {
		$post = self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
				'post_author' => $author_id,
				'post_title'  => 'Knowledge row',
			)
		);
		if ( ! is_int( $post ) ) {
			throw new Exception( 'Factory post creation failure' );
		}
		return $post;
	}

	/**
	 * @ticket 65476
	 */
	public function test_register_routes(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/knowledge', $routes );
		$this->assertArrayHasKey( '/wp/v2/knowledge/(?P<id>[\d]+)', $routes );

		// Revisions are supported.
		$this->assertArrayHasKey( '/wp/v2/knowledge/(?P<parent>[\d]+)/revisions', $routes );
		$this->assertArrayHasKey( '/wp/v2/knowledge/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)', $routes );

		// Autosave support is removed, so the autosaves routes are not registered.
		$this->assertArrayNotHasKey( '/wp/v2/knowledge/(?P<id>[\d]+)/autosaves', $routes );
		$this->assertArrayNotHasKey( '/wp/v2/knowledge/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)', $routes );
	}

	/**
	 * @ticket 65476
	 */
	public function test_context_param(): void {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/knowledge' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );

		$this->assertTrue( isset( $data['endpoints'][0]['args']['context']['default'] ) ); // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertTrue( isset( $data['endpoints'][0]['args']['context']['enum'] ) ); // @phpstan-ignore offsetAccess.nonOffsetAccessible
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_get_items(): void {
		wp_set_current_user( self::$admin_id );

		// The collection defaults to the `publish` status; knowledge rows are
		// private by default, so list a published row here.
		self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'publish',
				'post_author' => self::$admin_id,
				'post_title'  => 'Published knowledge',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/knowledge' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data() );
	}

	/**
	 * @ticket 65476
	 */
	public function test_get_items_requires_authentication(): void {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/knowledge' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );

		wp_set_current_user( self::$subscriber_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	/**
	 * @ticket 65476
	 */
	public function test_get_item(): void {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/knowledge/' . self::$admin_private );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertSame( self::$admin_private, $data['id'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_cannot_read_others_private_row(): void {
		wp_set_current_user( self::$contributor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/knowledge/' . self::$admin_private );
		$response = rest_get_server()->dispatch( $request );

		// The contributor is authenticated, so reading another user's private row is forbidden.
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @ticket 65476
	 */
	public function test_create_item(): void {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge' );
		$request->set_body_params( array( 'title' => 'Created by admin' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		// With no status supplied, new rows default to private rather than draft.
		$this->assertSame( 'private', $data['status'] );
	}

	/**
	 * An empty `wp_knowledge_type` array on create must not leave the row without a type.
	 *
	 * The controller assigns terms in handle_terms() after the post row is
	 * inserted, so an empty array clears any terms. The `note` fallback is
	 * re-applied on `wp_after_insert_post`, which runs after that write.
	 *
	 * @ticket 65476
	 */
	public function test_create_item_with_empty_type_falls_back_to_note(): void {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge' );
		$request->set_body_params(
			array(
				'title'             => 'Created without a type',
				'wp_knowledge_type' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );

		$terms = wp_get_object_terms( (int) $data['id'], 'wp_knowledge_type', array( 'fields' => 'slugs' ) );
		$this->assertSame( array( 'note' ), $terms );
	}

	/**
	 * An empty `wp_knowledge_type` array on update must restore the `note` fallback.
	 *
	 * @ticket 65476
	 */
	public function test_update_item_with_empty_type_restores_note(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = $this->create_knowledge_post( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge/' . $post_id );
		$request->set_body_params( array( 'wp_knowledge_type' => array() ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$terms = wp_get_object_terms( $post_id, 'wp_knowledge_type', array( 'fields' => 'slugs' ) );
		$this->assertSame( array( 'note' ), $terms );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_create_defaults_to_private(): void {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge' );
		$request->set_body_params( array( 'title' => 'Created by contributor' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'private', $data['status'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_cannot_publish(): void {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge' );
		$request->set_body_params(
			array(
				'title'  => 'Attempted publish',
				'status' => 'publish',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_publish', $response, 403 );
	}

	/**
	 * @ticket 65476
	 */
	public function test_update_item(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = $this->create_knowledge_post( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge/' . $post_id );
		$request->set_body_params( array( 'title' => 'Updated title' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( isset( $data['title']['raw'] ) ); // @phpstan-ignore offsetAccess.nonOffsetAccessible
		$this->assertSame( 'Updated title', $data['title']['raw'] );
	}

	/**
	 * A contributor may edit their own private row.
	 *
	 * @ticket 65476
	 */
	public function test_contributor_can_update_own_row(): void {
		wp_set_current_user( self::$contributor_id );

		$post_id = $this->create_knowledge_post( self::$contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge/' . $post_id );
		$request->set_body_params( array( 'title' => 'Updated by contributor' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( isset( $data['title']['raw'] ) ); // @phpstan-ignore offsetAccess.nonOffsetAccessible
		$this->assertSame( 'Updated by contributor', $data['title']['raw'] );
	}

	/**
	 * A contributor may not edit another user's row.
	 *
	 * @ticket 65476
	 */
	public function test_contributor_cannot_update_others_row(): void {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge/' . self::$admin_private );
		$request->set_body_params( array( 'title' => 'Attempted update' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @ticket 65476
	 */
	public function test_delete_item(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = $this->create_knowledge_post( self::$admin_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/knowledge/' . $post_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * A contributor may delete their own private row.
	 *
	 * @ticket 65476
	 */
	public function test_contributor_can_delete_own_row(): void {
		wp_set_current_user( self::$contributor_id );

		$post_id = $this->create_knowledge_post( self::$contributor_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/knowledge/' . $post_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * A contributor may not delete another user's row.
	 *
	 * @ticket 65476
	 */
	public function test_contributor_cannot_delete_others_row(): void {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/knowledge/' . self::$admin_private );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertInstanceOf( WP_Post::class, get_post( self::$admin_private ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_prepare_item(): void {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/knowledge/' . self::$admin_private );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'author', $data );
	}

	/**
	 * @ticket 65476
	 */
	public function test_get_item_schema(): void {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/knowledge' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( isset( $data['schema']['properties'] ) ); // @phpstan-ignore offsetAccess.nonOffsetAccessible
		$properties = $data['schema']['properties'];
		$this->assertIsArray( $properties );

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'author', $properties );
	}
}
