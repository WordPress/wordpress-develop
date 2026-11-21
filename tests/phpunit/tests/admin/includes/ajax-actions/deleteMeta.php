<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_meta() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_meta
 */
class Tests_Admin_Includes_AjaxActions_deleteMeta extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Post ID owned by the administrator.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id     = $factory->user->create( array( 'role' => 'author' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$post_id       = $factory->post->create( array( 'post_author' => self::$admin_id ) );
	}

	/**
	 * Populates $_POST for a delete request.
	 *
	 * @param int         $meta_id Meta ID.
	 * @param string|null $nonce   Optional. Nonce to send. Default is a valid nonce.
	 */
	private function set_request( int $meta_id, ?string $nonce = null ): void {
		$_POST = array(
			'id'          => $meta_id,
			'_ajax_nonce' => $nonce ?? wp_create_nonce( "delete-meta_$meta_id" ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_deletes_meta(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key', 'test_value' );
		$this->set_request( $meta_id );

		$this->assertSame( '1', $this->get_ajax_response( 'delete-meta' ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $meta_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_allows_author_on_own_post(): void {
		wp_set_current_user( self::$author_id );

		$post_id = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$meta_id = add_post_meta( $post_id, 'test_meta_key', 'test_value' );
		$this->set_request( $meta_id );

		$this->assertSame( '1', $this->get_ajax_response( 'delete-meta' ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $meta_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_rejects_author_on_another_users_post(): void {
		wp_set_current_user( self::$author_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key', 'test_value' );
		$this->set_request( $meta_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-meta' ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_rejects_subscriber(): void {
		wp_set_current_user( self::$subscriber_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key', 'test_value' );
		$this->set_request( $meta_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-meta' ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_rejects_protected_meta(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = add_post_meta( self::$post_id, '_protected_meta_key', 'test_value' );
		$this->set_request( $meta_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-meta' ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key', 'test_value' );
		$this->set_request( $meta_id, 'invalid-nonce' );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-meta' ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_returns_one_for_nonexistent_meta(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key', 'test_value' );
		delete_metadata_by_mid( 'post', $meta_id );
		$this->set_request( $meta_id );

		$this->assertSame( '1', $this->get_ajax_response( 'delete-meta' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_meta_returns_zero_when_deletion_fails(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key', 'test_value' );
		$this->set_request( $meta_id );

		add_filter( 'delete_post_metadata_by_mid', '__return_false' );

		$this->assertSame( '0', $this->get_ajax_response( 'delete-meta' ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ) );
	}
}
