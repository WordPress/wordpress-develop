<?php
/**
 * Test wp_check_locked_posts().
 *
 * @group admin
 * @group misc
 *
 * @covers ::wp_check_locked_posts
 */
class Tests_Admin_Includes_Misc_WpCheckLockedPosts extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private static $user_id;

	/**
	 * Another user ID.
	 *
	 * @var int
	 */
	private static $other_user_id;

	/**
	 * Setup before class.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$other_user_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$post_id       = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$user_id );
	}

	/**
	 * Tests wp_check_locked_posts() with empty data.
	 *
	 * @ticket 65195
	 */
	public function test_wp_check_locked_posts_empty_data() {
		$response = array();
		$data     = array();

		$result = wp_check_locked_posts( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged when data is empty.' );
	}

	/**
	 * Tests wp_check_locked_posts() when no posts are locked.
	 *
	 * @ticket 65195
	 */
	public function test_wp_check_locked_posts_no_locks() {
		$key      = 'post-' . self::$post_id;
		$response = array();
		$data     = array(
			'wp-check-locked-posts' => array( $key ),
		);

		$result = wp_check_locked_posts( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged when no posts are locked.' );
	}

	/**
	 * Tests wp_check_locked_posts() with a locked post.
	 *
	 * @ticket 65195
	 *
	 * @dataProvider data_wp_check_locked_posts_locked
	 *
	 * @param bool $is_rtc_enabled Whether collaboration is enabled.
	 * @param bool $show_avatars   Whether avatars are shown.
	 * @param array $expected_keys Expected keys in the inner response array.
	 */
	public function test_wp_check_locked_posts_locked( $is_rtc_enabled, $show_avatars, $expected_keys ) {
		update_option( 'wp_collaboration_enabled', $is_rtc_enabled );
		update_option( 'show_avatars', $show_avatars );

		// Manually lock the post by another user.
		$now  = time();
		$lock = "$now:" . self::$other_user_id;
		update_post_meta( self::$post_id, '_edit_lock', $lock );

		$key      = 'post-' . self::$post_id;
		$response = array();
		$data     = array(
			'wp-check-locked-posts' => array( $key ),
		);

		$result = wp_check_locked_posts( $response, $data, 'edit-post' );

		$this->assertArrayHasKey( 'wp-check-locked-posts', $result );
		$this->assertArrayHasKey( $key, $result['wp-check-locked-posts'] );

		$actual = $result['wp-check-locked-posts'][ $key ];

		foreach ( $expected_keys as $expected_key ) {
			$this->assertArrayHasKey( $expected_key, $actual );
		}

		if ( $is_rtc_enabled ) {
			$this->assertTrue( $actual['collaborative'] );
			$this->assertSame( 'Currently being edited', $actual['text'] );
		} else {
			$other_user = get_userdata( self::$other_user_id );
			$this->assertSame( $other_user->display_name, $actual['name'] );
			$this->assertStringContainsString( $other_user->display_name, $actual['text'] );
		}

		// Cleanup.
		delete_option( 'wp_collaboration_enabled' );
		delete_option( 'show_avatars' );
		delete_post_meta( self::$post_id, '_edit_lock' );
	}

	/**
	 * Data provider for test_wp_check_locked_posts_locked.
	 *
	 * @return array<string, array{
	 *     is_rtc_enabled: bool,
	 *     show_avatars:   bool,
	 *     expected_keys:  string[],
	 * }>
	 */
	public function data_wp_check_locked_posts_locked(): array {
		return array(
			'collaboration enabled'          => array(
				'is_rtc_enabled' => true,
				'show_avatars'   => true,
				'expected_keys'  => array( 'text', 'collaborative' ),
			),
			'collaboration disabled, avatars enabled' => array(
				'is_rtc_enabled' => false,
				'show_avatars'   => true,
				'expected_keys'  => array( 'name', 'text', 'avatar_src', 'avatar_src_2x' ),
			),
			'collaboration disabled, avatars disabled' => array(
				'is_rtc_enabled' => false,
				'show_avatars'   => false,
				'expected_keys'  => array( 'name', 'text' ),
			),
		);
	}

	/**
	 * Tests wp_check_locked_posts() when the current user cannot edit the post.
	 *
	 * @ticket 65195
	 */
	public function test_wp_check_locked_posts_no_permission() {
		// Set current user to a user who cannot edit the post (subscriber).
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// Manually lock the post by another user.
		$now  = time();
		$lock = "$now:" . self::$other_user_id;
		update_post_meta( self::$post_id, '_edit_lock', $lock );

		$key      = 'post-' . self::$post_id;
		$response = array();
		$data     = array(
			'wp-check-locked-posts' => array( $key ),
		);

		$result = wp_check_locked_posts( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged if user cannot edit the post.' );

		// Cleanup.
		delete_post_meta( self::$post_id, '_edit_lock' );
	}

	/**
	 * Tests wp_check_locked_posts() with an invalid post key.
	 *
	 * @ticket 65195
	 */
	public function test_wp_check_locked_posts_invalid_key() {
		$response = array();
		$data     = array(
			'wp-check-locked-posts' => array( 'post-invalid' ),
		);

		$result = wp_check_locked_posts( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged with invalid post key.' );
	}
}
