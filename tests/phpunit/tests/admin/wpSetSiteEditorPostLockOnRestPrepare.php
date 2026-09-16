<?php
/**
 * Tests for wp_set_site_editor_post_lock_on_rest_prepare().
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group admin
 * @group post
 *
 * @covers ::wp_set_site_editor_post_lock_on_rest_prepare
 *
 * @ticket 65126
 */
class Tests_Admin_WpSetSiteEditorPostLockOnRestPrepare extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $other_admin_id;

	/**
	 * @var WP_Post
	 */
	protected static $template_post;

	/**
	 * @var WP_Post
	 */
	protected static $template_part_post;

	/**
	 * @var WP_Post
	 */
	protected static $regular_post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$other_admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$template_post = $factory->post->create_and_get(
			array(
				'post_type'   => 'wp_template',
				'post_name'   => 'tests-65126-template',
				'post_title'  => 'Tests 65126 Template',
				'post_status' => 'publish',
			)
		);

		self::$template_part_post = $factory->post->create_and_get(
			array(
				'post_type'   => 'wp_template_part',
				'post_name'   => 'tests-65126-template-part',
				'post_title'  => 'Tests 65126 Template Part',
				'post_status' => 'publish',
			)
		);

		self::$regular_post = $factory->post->create_and_get(
			array(
				'post_type'  => 'post',
				'post_title' => 'Tests 65126 Regular Post',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		// Ensure each test starts from a clean lock state.
		delete_post_meta( self::$template_post->ID, '_edit_lock' );
		delete_post_meta( self::$template_part_post->ID, '_edit_lock' );
		delete_post_meta( self::$regular_post->ID, '_edit_lock' );

		require_once ABSPATH . 'wp-admin/includes/post.php';

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * The filter callback is registered for both Site Editor post types.
	 */
	public function test_filters_are_registered() {
		$this->assertSame(
			10,
			has_filter( 'rest_prepare_wp_template', 'wp_set_site_editor_post_lock_on_rest_prepare' ),
			'rest_prepare_wp_template should have the lock acquisition filter registered.'
		);
		$this->assertSame(
			10,
			has_filter( 'rest_prepare_wp_template_part', 'wp_set_site_editor_post_lock_on_rest_prepare' ),
			'rest_prepare_wp_template_part should have the lock acquisition filter registered.'
		);
	}

	/**
	 * Edit-context responses for `wp_template` acquire the lock.
	 */
	public function test_lock_is_acquired_for_wp_template_in_edit_context() {
		$response = $this->run_rest_prepare( self::$template_post, 'edit' );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'The response should be returned unchanged.' );
		$this->assertSame(
			(int) self::$admin_id,
			(int) $this->get_lock_user_id( self::$template_post->ID ),
			'The current user should hold the lock after an edit-context REST response.'
		);
	}

	/**
	 * Edit-context responses for `wp_template_part` acquire the lock.
	 */
	public function test_lock_is_acquired_for_wp_template_part_in_edit_context() {
		$this->run_rest_prepare( self::$template_part_post, 'edit' );

		$this->assertSame(
			(int) self::$admin_id,
			(int) $this->get_lock_user_id( self::$template_part_post->ID ),
			'The current user should hold the lock after an edit-context REST response.'
		);
	}

	/**
	 * View-context responses do not acquire the lock.
	 */
	public function test_lock_is_not_acquired_in_view_context() {
		$this->run_rest_prepare( self::$template_post, 'view' );

		$this->assertEmpty(
			get_post_meta( self::$template_post->ID, '_edit_lock', true ),
			'View-context REST responses should not acquire a post lock.'
		);
	}

	/**
	 * The `wp_apply_site_editor_post_lock` filter can opt out of acquisition.
	 */
	public function test_filter_can_opt_out_of_lock_acquisition() {
		add_filter( 'wp_apply_site_editor_post_lock', '__return_false' );

		try {
			$this->run_rest_prepare( self::$template_post, 'edit' );
		} finally {
			remove_filter( 'wp_apply_site_editor_post_lock', '__return_false' );
		}

		$this->assertEmpty(
			get_post_meta( self::$template_post->ID, '_edit_lock', true ),
			'wp_apply_site_editor_post_lock returning false should skip acquisition.'
		);
	}

	/**
	 * The filter receives the resolved post object as its second argument.
	 */
	public function test_filter_receives_post_object() {
		$captured = null;

		$capture = static function ( $apply, $post ) use ( &$captured ) {
			$captured = $post;
			return $apply;
		};

		add_filter( 'wp_apply_site_editor_post_lock', $capture, 10, 2 );

		try {
			$this->run_rest_prepare( self::$template_post, 'edit' );
		} finally {
			remove_filter( 'wp_apply_site_editor_post_lock', $capture, 10 );
		}

		$this->assertInstanceOf( WP_Post::class, $captured, 'The filter should receive a WP_Post instance.' );
		$this->assertSame( self::$template_post->ID, $captured->ID, 'The filter should receive the template post being prepared.' );
	}

	/**
	 * A lock already held by another user is not overwritten silently. The
	 * existing wp_set_post_lock() helper simply refreshes the timestamp using
	 * the current user, so when an admin opens an edit-context request after
	 * another admin had the lock, the lock now belongs to the new user. This
	 * verifies that acquisition does happen (the function does not bail out
	 * because a lock already exists).
	 */
	public function test_lock_is_refreshed_for_current_user() {
		// Pretend another admin opened the template first.
		wp_set_current_user( self::$other_admin_id );
		wp_set_post_lock( self::$template_post->ID );

		wp_set_current_user( self::$admin_id );

		$this->run_rest_prepare( self::$template_post, 'edit' );

		$this->assertSame(
			(int) self::$admin_id,
			(int) $this->get_lock_user_id( self::$template_post->ID ),
			'An edit-context REST response should refresh the lock for the current user.'
		);
	}

	/**
	 * Helper: invoke the REST prepare filter directly with a minimal response
	 * and a fake request whose context can be controlled per test.
	 *
	 * @param WP_Post $post    The post being prepared.
	 * @param string  $context Either `edit` or `view`.
	 * @return WP_REST_Response
	 */
	protected function run_rest_prepare( WP_Post $post, $context ) {
		$response = new WP_REST_Response( array( 'id' => $post->ID ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/' . ( 'wp_template' === $post->post_type ? 'templates' : 'template-parts' ) . '/' . $post->ID );
		$request->set_param( 'context', $context );

		return wp_set_site_editor_post_lock_on_rest_prepare( $response, $post, $request );
	}

	/**
	 * Helper: read the user ID portion of the `_edit_lock` meta value.
	 *
	 * @param int $post_id Post ID to read.
	 * @return int Lock user ID, or 0 when no lock is present.
	 */
	protected function get_lock_user_id( $post_id ) {
		$lock = get_post_meta( $post_id, '_edit_lock', true );

		if ( ! $lock ) {
			return 0;
		}

		$parts = explode( ':', $lock );

		return isset( $parts[1] ) ? (int) $parts[1] : 0;
	}
}
