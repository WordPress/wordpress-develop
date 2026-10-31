<?php
/**
 * Test wp_refresh_metabox_loader_nonces().
 *
 * @group admin
 * @group misc
 *
 * @covers ::wp_refresh_metabox_loader_nonces
 */
class Tests_Admin_Includes_Misc_WpRefreshMetaboxLoaderNonces extends WP_UnitTestCase {

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
	 * Tests wp_refresh_metabox_loader_nonces() with missing data.
	 *
	 * @ticket 65198
	 */
	public function test_wp_refresh_metabox_loader_nonces_missing_data() {
		$response = array( 'existing' => 'data' );
		$data     = array();

		$result = wp_refresh_metabox_loader_nonces( $response, $data );

		$this->assertSame( $response, $result, 'Response should remain unchanged if wp-refresh-metabox-loader-nonces is missing.' );
	}

	/**
	 * Tests wp_refresh_metabox_loader_nonces() with invalid post ID.
	 *
	 * @ticket 65198
	 *
	 * @dataProvider data_wp_refresh_metabox_loader_nonces_invalid_post_id
	 *
	 * @param mixed $post_id Invalid post ID.
	 * @return void
	 */
	public function test_wp_refresh_metabox_loader_nonces_invalid_post_id( $post_id ) {
		$response = array();
		$data     = array(
			'wp-refresh-metabox-loader-nonces' => array(
				'post_id' => $post_id,
			),
		);

		$result = wp_refresh_metabox_loader_nonces( $response, $data );

		$this->assertSame( $response, $result, 'Response should remain unchanged for invalid post ID.' );
	}

	/**
	 * Data provider for test_wp_refresh_metabox_loader_nonces_invalid_post_id.
	 *
	 * @return array<string, array{
	 *     post_id: mixed,
	 * }>
	 */
	public function data_wp_refresh_metabox_loader_nonces_invalid_post_id(): array {
		return array(
			'zero'            => array( 'post_id' => 0 ),
			'string zero'     => array( 'post_id' => '0' ),
			'non-numeric'     => array( 'post_id' => 'abc' ),
			'negative'        => array( 'post_id' => -1 ),
			'missing post_id' => array( 'post_id' => null ),
		);
	}

	/**
	 * Tests wp_refresh_metabox_loader_nonces() when the user cannot edit the post.
	 *
	 * @ticket 65198
	 */
	public function test_wp_refresh_metabox_loader_nonces_user_cannot_edit() {
		$other_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $other_user_id );

		$response = array();
		$data     = array(
			'wp-refresh-metabox-loader-nonces' => array(
				'post_id' => self::$post_id,
			),
		);

		$result = wp_refresh_metabox_loader_nonces( $response, $data );

		$this->assertSame( $response, $result, 'Response should remain unchanged if user cannot edit post.' );
	}

	/**
	 * Tests wp_refresh_metabox_loader_nonces() with successful refresh.
	 *
	 * @ticket 65198
	 */
	public function test_wp_refresh_metabox_loader_nonces_success() {
		wp_set_current_user( self::$user_id );

		$response = array();
		$data     = array(
			'wp-refresh-metabox-loader-nonces' => array(
				'post_id' => self::$post_id,
			),
		);

		$result = wp_refresh_metabox_loader_nonces( $response, $data );

		$this->assertArrayHasKey( 'wp-refresh-metabox-loader-nonces', $result );
		$this->assertArrayHasKey( 'replace', $result['wp-refresh-metabox-loader-nonces'] );

		$replace = $result['wp-refresh-metabox-loader-nonces']['replace'];

		$this->assertArrayHasKey( 'metabox_loader_nonce', $replace );
		$this->assertArrayHasKey( '_wpnonce', $replace );

		$this->assertSame( 1, wp_verify_nonce( $replace['metabox_loader_nonce'], 'meta-box-loader' ), 'metabox_loader_nonce should be valid.' );
		$this->assertSame( 1, wp_verify_nonce( $replace['_wpnonce'], 'update-post_' . self::$post_id ), '_wpnonce should be valid.' );
	}
}
