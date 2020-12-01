<?php

/**
 * @group post
 */
class Tests_Post_wpAfterInsertPost extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	public static $admin_id;

	/**
	 * Attachment ID (no media attached).
	 *
	 * @var int
	 */
	public static $attachment_id;

	/**
	 * Post ID for testing updates.
	 *
	 * @var int
	 */
	public static $post_id;

	/**
	 * Title as passed to hook.
	 *
	 * @var string
	 */
	public static $passed_post_title = '';

	/**
	 * Status as passed to hook.
	 *
	 * @var string
	 */
	public static $passed_post_status = '';

	/**
	 * Before update title as passed to hook.
	 *
	 * @var string
	 */
	public static $passed_post_before_title = '';

	/**
	 * Before update status as passed to hook.
	 *
	 * @var string
	 */
	public static $passed_post_before_status = '';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'administrator',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_status' => 'draft',
				'post_title'  => '45114 to be updated',
			)
		);

		self::$attachment_id = $factory->attachment->create(
			array(
				'post_status' => 'inherit',
				'post_title'  => '45114 attachment to be updated',
				'post_parent' => self::$post_id,
			)
		);
	}

	public function setUp() {
		parent::setUp();
		add_action( 'wp_after_insert_post', array( $this, 'action_wp_after_insert_post' ), 10, 4 );
	}

	public function tearDown() {
		self::$passed_post_title         = '';
		self::$passed_post_status        = '';
		self::$passed_post_before_title  = '';
		self::$passed_post_before_status = '';
		parent::tearDown();
	}

	function action_wp_after_insert_post( $post_id, $post, $update, $before_post ) {
		self::$passed_post_title  = $post->post_title;
		self::$passed_post_status = $post->post_status;

		if ( null === $before_post ) {
			self::$passed_post_before_title  = null;
			self::$passed_post_before_status = null;
			return;
		}

		self::$passed_post_before_title  = $before_post->post_title;
		self::$passed_post_before_status = $before_post->post_status;

		// Prevent this firing when the revision is generated.
		remove_action( 'wp_after_insert_post', array( $this, 'action_wp_after_insert_post' ), 10, 4 );
	}

	/**
	 * Ensure before post is correct when updating a post object.
	 *
	 * @ticket 45114
	 */
	public function test_update_via_wp_update_post() {
		$post               = get_post( self::$post_id, ARRAY_A );
		$post['post_title'] = 'new title';
		wp_update_post( $post );

		$this->assertSame( '45114 to be updated', self::$passed_post_before_title );
		$this->assertSame( 'new title', self::$passed_post_title );
	}

	/**
	 * Ensure before post is correct when publishing a post object.
	 *
	 * @ticket 45114
	 */
	public function test_update_via_wp_publish_post() {
		wp_publish_post( self::$post_id );

		$this->assertSame( 'draft', self::$passed_post_before_status );
		$this->assertSame( 'publish', self::$passed_post_status );
	}

	/**
	 * Ensure before post is correct when inserting a new post.
	 *
	 * @ticket 45114
	 */
	public function test_new_post_via_wp_insert_post() {
		wp_insert_post(
			array(
				'post_status'  => 'draft',
				'post_title'   => 'a new post',
				'post_content' => 'new',
			)
		);

		$this->assertSame( null, self::$passed_post_before_status );
		$this->assertSame( 'a new post', self::$passed_post_title );
	}

	/**
	 * Ensure before post is correct when updating post via REST API.
	 *
	 * @ticket 45114
	 */
	public function test_update_via_rest_contoller() {
		wp_set_current_user( self::$admin_id );
		$post_id = self::$post_id;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( array( 'title' => 'new title' ) );
		rest_get_server()->dispatch( $request );

		$this->assertSame( '45114 to be updated', self::$passed_post_before_title );
		$this->assertSame( 'new title', self::$passed_post_title );
	}

	/**
	 * Ensure before post is correct when creating post via REST API.
	 *
	 * @ticket 45114
	 */
	public function test_new_post_via_rest_contoller() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts' ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params(
			array(
				'title'  => 'new title',
				'status' => 'draft',
			)
		);
		rest_get_server()->dispatch( $request );

		$this->assertSame( null, self::$passed_post_before_title );
		$this->assertSame( 'new title', self::$passed_post_title );
	}

	/**
	 * Ensure before post is correct when updating post via REST API.
	 *
	 * @ticket 45114
	 */
	public function test_update_attachment_via_rest_contoller() {
		wp_set_current_user( self::$admin_id );
		$attachment_id = self::$attachment_id;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/media/%d', $attachment_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( array( 'title' => 'new attachment title' ) );
		rest_get_server()->dispatch( $request );

		$this->assertSame( '45114 attachment to be updated', self::$passed_post_before_title );
		$this->assertSame( 'new attachment title', self::$passed_post_title );
	}
}
