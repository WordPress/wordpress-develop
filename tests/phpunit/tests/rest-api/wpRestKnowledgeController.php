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

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $contributor_id;

	/**
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * A private knowledge row owned by the administrator.
	 *
	 * @var int
	 */
	protected static $admin_private;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$contributor_id = $factory->user->create( array( 'role' => 'contributor' ) );
		self::$subscriber_id  = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$admin_private = $factory->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
				'post_author' => self::$admin_id,
				'post_title'  => 'Admin private knowledge',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$contributor_id );
		self::delete_user( self::$subscriber_id );
		wp_delete_post( self::$admin_private, true );
	}

	/**
	 * Creates a private knowledge row for the given author.
	 *
	 * @param int $author_id Author user ID.
	 * @return int Post ID.
	 */
	private function create_knowledge_post( int $author_id ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
				'post_author' => $author_id,
				'post_title'  => 'Knowledge row',
			)
		);
	}

	/**
	 * @ticket 65476
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/knowledge', $routes );
		$this->assertArrayHasKey( '/wp/v2/knowledge/(?P<id>[\d]+)', $routes );
	}

	/**
	 * @ticket 65476
	 */
	public function test_context_param() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/knowledge' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_get_items() {
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
	public function test_get_items_requires_authentication() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/knowledge' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );

		wp_set_current_user( self::$subscriber_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @ticket 65476
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/knowledge/' . self::$admin_private );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::$admin_private, $response->get_data()['id'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_cannot_read_others_private_row() {
		wp_set_current_user( self::$contributor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/knowledge/' . self::$admin_private );
		$response = rest_get_server()->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge' );
		$request->set_body_params( array( 'title' => 'Created by admin' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		// With no status supplied, new rows default to private rather than draft.
		$this->assertSame( 'private', $data['status'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_create_defaults_to_private() {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge' );
		$request->set_body_params( array( 'title' => 'Created by contributor' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'private', $response->get_data()['status'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_cannot_publish() {
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
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );

		$post_id = $this->create_knowledge_post( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge/' . $post_id );
		$request->set_body_params( array( 'title' => 'Updated title' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated title', $response->get_data()['title']['raw'] );
	}

	/**
	 * @ticket 65476
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$post_id = $this->create_knowledge_post( self::$admin_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/knowledge/' . $post_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/knowledge/' . self::$admin_private );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'author', $data );
	}

	/**
	 * @ticket 65476
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );

		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/knowledge' );
		$response   = rest_get_server()->dispatch( $request );
		$properties = $response->get_data()['schema']['properties'];

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'author', $properties );
	}
}
