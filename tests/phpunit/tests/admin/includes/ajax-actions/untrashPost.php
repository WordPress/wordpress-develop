<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_untrash_post() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_untrash_post
 * @covers ::wp_ajax_trash_post
 */
class Tests_Admin_Includes_AjaxActions_untrashPost extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Populates $_POST for an untrash request.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $nonce   Optional. Nonce to send. Default is a valid nonce.
	 */
	private function set_request( int $post_id, ?string $nonce = null ): void {
		$_POST = array(
			'id'          => $post_id,
			'_ajax_nonce' => $nonce ?? wp_create_nonce( "untrash-post_$post_id" ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_untrash_post_restores_post_as_draft(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_trash_post( $post_id );
		$this->set_request( $post_id );

		$this->assertSame( '1', $this->get_ajax_response( 'untrash-post' ) );
		$this->assertSame( 'draft', get_post_status( $post_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_untrash_post_restores_previous_status_when_filtered(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_trash_post( $post_id );
		$this->set_request( $post_id );

		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );

		$this->assertSame( '1', $this->get_ajax_response( 'untrash-post' ) );
		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_untrash_post_returns_zero_for_post_not_in_trash(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->set_request( $post_id );

		$this->assertSame( '0', $this->get_ajax_response( 'untrash-post' ) );
		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_untrash_post_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();
		wp_trash_post( $post_id );
		$this->set_request( $post_id, 'invalid-nonce' );

		$this->assertSame( '-1', $this->get_ajax_response( 'untrash-post' ) );
		$this->assertSame( 'trash', get_post_status( $post_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_untrash_post_rejects_user_without_capability(): void {
		wp_set_current_user( self::$subscriber_id );

		$post_id = self::factory()->post->create();
		wp_trash_post( $post_id );
		$this->set_request( $post_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'untrash-post' ) );
		$this->assertSame( 'trash', get_post_status( $post_id ) );
	}

	/**
	 * A missing post fails the delete_post capability check before the existence check.
	 *
	 * @ticket 65252
	 */
	public function test_untrash_post_rejects_nonexistent_post(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();
		wp_delete_post( $post_id, true );
		$this->set_request( $post_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'untrash-post' ) );
	}
}
