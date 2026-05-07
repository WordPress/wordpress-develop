<?php
/**
 * Test wp_refresh_post_lock().
 *
 * @group admin
 * @group misc
 *
 * @covers ::wp_refresh_post_lock
 */
class Tests_Admin_Includes_Misc_WpRefreshPostLock extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Other user ID.
	 *
	 * @var int
	 */
	protected static $other_user_id;

	/**
	 * Set up before class.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id       = $factory->user->create( array( 'role' => 'editor' ) );
		self::$other_user_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$post_id       = $factory->post->create( array( 'post_author' => self::$user_id ) );
	}

	/**
	 * Tests wp_refresh_post_lock() with missing data.
	 *
	 * @ticket 65196
	 */
	public function test_wp_refresh_post_lock_missing_data() {
		$response = array( 'existing' => 'data' );
		$data     = array();

		$result = wp_refresh_post_lock( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged if wp-refresh-post-lock is missing.' );
	}

	/**
	 * Tests wp_refresh_post_lock() with invalid post ID.
	 *
	 * @ticket 65196
	 *
	 * @dataProvider data_wp_refresh_post_lock_invalid_post_id
	 *
	 * @param mixed $post_id Invalid post ID.
	 *
	 * @return array<string, array{post_id: mixed}>
	 */
	public function test_wp_refresh_post_lock_invalid_post_id( $post_id ) {
		wp_set_current_user( self::$user_id );

		$response = array();
		$data     = array(
			'wp-refresh-post-lock' => array(
				'post_id' => $post_id,
			),
		);

		$result = wp_refresh_post_lock( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged with invalid post ID.' );
	}

	/**
	 * Data provider for test_wp_refresh_post_lock_invalid_post_id.
	 *
	 * @return array<string, array{post_id: mixed}>
	 */
	public function data_wp_refresh_post_lock_invalid_post_id(): array {
		return array(
			'zero'     => array( 0 ),
			'string'   => array( 'abc' ),
			'negative' => array( -1 ),
		);
	}

	/**
	 * Tests wp_refresh_post_lock() when user cannot edit the post.
	 *
	 * @ticket 65196
	 */
	public function test_wp_refresh_post_lock_no_permission() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$response = array();
		$data     = array(
			'wp-refresh-post-lock' => array(
				'post_id' => self::$post_id,
			),
		);

		$result = wp_refresh_post_lock( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged if user cannot edit the post.' );
	}

	/**
	 * Tests wp_refresh_post_lock() when post is locked by another user.
	 *
	 * @ticket 65196
	 *
	 * @dataProvider data_wp_refresh_post_lock_by_another_user
	 *
	 * @param bool $show_avatars Whether to show avatars.
	 *
	 * @return array<string, array{show_avatars: bool}>
	 */
	public function test_wp_refresh_post_lock_by_another_user( $show_avatars ) {
		wp_set_current_user( self::$user_id );
		update_option( 'show_avatars', $show_avatars );

		// Lock post by another user.
		$now  = time();
		$lock = "$now:" . self::$other_user_id;
		update_post_meta( self::$post_id, '_edit_lock', $lock );

		$response = array();
		$data     = array(
			'wp-refresh-post-lock' => array(
				'post_id' => self::$post_id,
			),
		);

		$result = wp_refresh_post_lock( $response, $data, 'edit-post' );

		$this->assertArrayHasKey( 'wp-refresh-post-lock', $result );
		$this->assertArrayHasKey( 'lock_error', $result['wp-refresh-post-lock'] );

		$error      = $result['wp-refresh-post-lock']['lock_error'];
		$other_user = get_userdata( self::$other_user_id );

		$this->assertSame( $other_user->display_name, $error['name'] );
		$this->assertStringContainsString( $other_user->display_name, $error['text'] );

		if ( $show_avatars ) {
			$this->assertArrayHasKey( 'avatar_src', $error );
			$this->assertArrayHasKey( 'avatar_src_2x', $error );
		} else {
			$this->assertArrayNotHasKey( 'avatar_src', $error );
			$this->assertArrayNotHasKey( 'avatar_src_2x', $error );
		}

		// Cleanup.
		delete_post_meta( self::$post_id, '_edit_lock' );
	}

	/**
	 * Data provider for test_wp_refresh_post_lock_by_another_user.
	 *
	 * @return array<string, array{show_avatars: bool}>
	 */
	public function data_wp_refresh_post_lock_by_another_user(): array {
		return array(
			'avatars enabled'  => array( true ),
			'avatars disabled' => array( false ),
		);
	}

	/**
	 * Tests wp_refresh_post_lock() when post is not locked or locked by the same user.
	 *
	 * @ticket 65196
	 */
	public function test_wp_refresh_post_lock_success() {
		wp_set_current_user( self::$user_id );

		// Ensure no lock.
		delete_post_meta( self::$post_id, '_edit_lock' );

		$response = array();
		$data     = array(
			'wp-refresh-post-lock' => array(
				'post_id' => self::$post_id,
			),
		);

		$result = wp_refresh_post_lock( $response, $data, 'edit-post' );

		$this->assertArrayHasKey( 'wp-refresh-post-lock', $result );
		$this->assertArrayHasKey( 'new_lock', $result['wp-refresh-post-lock'] );

		$new_lock = $result['wp-refresh-post-lock']['new_lock'];
		$this->assertStringContainsString( (string) self::$user_id, $new_lock );

		// Verify meta was updated.
		$meta_lock = get_post_meta( self::$post_id, '_edit_lock', true );
		$this->assertSame( $new_lock, $meta_lock );
	}
}
