<?php
/**
 * Test wp_refresh_post_nonces().
 *
 * @group admin
 * @group misc
 *
 * @covers ::wp_refresh_post_nonces
 */
class Tests_Admin_Includes_Misc_WpRefreshPostNonces extends WP_UnitTestCase {

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
	 * Set up before class.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$post_id = $factory->post->create( array( 'post_author' => self::$user_id ) );
	}

	/**
	 * Tests wp_refresh_post_nonces() with missing data.
	 *
	 * @ticket 65197
	 */
	public function test_wp_refresh_post_nonces_missing_data() {
		$response = array( 'existing' => 'data' );
		$data     = array();

		$result = wp_refresh_post_nonces( $response, $data, 'edit-post' );

		$this->assertSame( $response, $result, 'Response should remain unchanged if wp-refresh-post-nonces is missing.' );
	}

	/**
	 * Tests wp_refresh_post_nonces() with invalid post ID.
	 *
	 * @ticket 65197
	 *
	 * @dataProvider data_wp_refresh_post_nonces_invalid_post_id
	 *
	 * @param mixed $post_id Invalid post ID.
	 * @return void
	 */
	public function test_wp_refresh_post_nonces_invalid_post_id( $post_id ) {
		$response = array();
		$data     = array(
			'wp-refresh-post-nonces' => array(
				'post_id' => $post_id,
			),
		);

		$result = wp_refresh_post_nonces( $response, $data, 'edit-post' );

		$this->assertArrayHasKey( 'wp-refresh-post-nonces', $result );
		$this->assertSame( array( 'check' => 1 ), $result['wp-refresh-post-nonces'], 'Should return check key for invalid post ID.' );
	}

	/**
	 * Data provider for test_wp_refresh_post_nonces_invalid_post_id.
	 *
	 * @return array<string, array{
	 *     post_id: mixed,
	 * }>
	 */
	public function data_wp_refresh_post_nonces_invalid_post_id(): array {
		return array(
			'zero'            => array( 'post_id' => 0 ),
			'string zero'     => array( 'post_id' => '0' ),
			'non-numeric'     => array( 'post_id' => 'abc' ),
			'negative'        => array( 'post_id' => -1 ),
			'missing post_id' => array( 'post_id' => null ),
		);
	}

	/**
	 * Tests wp_refresh_post_nonces() when the user cannot edit the post.
	 *
	 * @ticket 65197
	 */
	public function test_wp_refresh_post_nonces_user_cannot_edit() {
		$other_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $other_user_id );

		$response = array();
		$data     = array(
			'wp-refresh-post-nonces' => array(
				'post_id' => self::$post_id,
			),
		);

		$result = wp_refresh_post_nonces( $response, $data, 'edit-post' );

		$this->assertArrayHasKey( 'wp-refresh-post-nonces', $result );
		$this->assertSame( array( 'check' => 1 ), $result['wp-refresh-post-nonces'], 'Should return check key if user cannot edit post.' );
	}

	/**
	 * Tests wp_refresh_post_nonces() with successful refresh.
	 *
	 * @ticket 65197
	 */
	public function test_wp_refresh_post_nonces_success() {
		wp_set_current_user( self::$user_id );

		$response = array();
		$data     = array(
			'wp-refresh-post-nonces' => array(
				'post_id' => self::$post_id,
			),
		);

		$result = wp_refresh_post_nonces( $response, $data, 'edit-post' );

		$this->assertArrayHasKey( 'wp-refresh-post-nonces', $result );
		$this->assertArrayHasKey( 'replace', $result['wp-refresh-post-nonces'] );

		$replace = $result['wp-refresh-post-nonces']['replace'];

		$this->assertArrayHasKey( 'getpermalinknonce', $replace );
		$this->assertArrayHasKey( 'samplepermalinknonce', $replace );
		$this->assertArrayHasKey( 'closedpostboxesnonce', $replace );
		$this->assertArrayHasKey( '_ajax_linking_nonce', $replace );
		$this->assertArrayHasKey( '_wpnonce', $replace );

		$this->assertSame( 1, wp_verify_nonce( $replace['getpermalinknonce'], 'getpermalink' ), 'getpermalink nonce should be valid.' );
		$this->assertSame( 1, wp_verify_nonce( $replace['samplepermalinknonce'], 'samplepermalink' ), 'samplepermalink nonce should be valid.' );
		$this->assertSame( 1, wp_verify_nonce( $replace['closedpostboxesnonce'], 'closedpostboxes' ), 'closedpostboxes nonce should be valid.' );
		$this->assertSame( 1, wp_verify_nonce( $replace['_ajax_linking_nonce'], 'internal-linking' ), 'internal-linking nonce should be valid.' );
		$this->assertSame( 1, wp_verify_nonce( $replace['_wpnonce'], 'update-post_' . self::$post_id ), 'update-post nonce should be valid.' );
	}
}
