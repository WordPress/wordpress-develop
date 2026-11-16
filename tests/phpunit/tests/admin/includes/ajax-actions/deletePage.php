<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_page() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_page
 */
class Tests_Admin_Includes_AjaxActions_deletePage extends WP_Ajax_UnitTestCase {

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
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id     = $factory->user->create( array( 'role' => 'author' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Populates $_POST for a delete request.
	 *
	 * @param int         $page_id Page ID.
	 * @param string|null $nonce   Optional. Nonce to send. Default is a valid nonce.
	 */
	private function set_request( int $page_id, ?string $nonce = null ): void {
		$_POST = array(
			'id'          => $page_id,
			'_ajax_nonce' => $nonce ?? wp_create_nonce( "delete-page_$page_id" ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_page_deletes_trashed_page(): void {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $page_id );
		$this->set_request( $page_id );

		$this->assertSame( '1', $this->get_ajax_response( 'delete-page' ) );
		$this->assertNull( get_post( $page_id ) );
	}

	/**
	 * wp_delete_post() without $force_delete sends pages that are not yet trashed to the trash.
	 *
	 * @ticket 65252
	 */
	public function test_delete_page_trashes_page_not_in_trash(): void {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->set_request( $page_id );

		$this->assertSame( '1', $this->get_ajax_response( 'delete-page' ) );
		$this->assertSame( 'trash', get_post_status( $page_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_page_returns_zero_when_deletion_fails(): void {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $page_id );
		$this->set_request( $page_id );

		add_filter( 'pre_delete_post', '__return_false' );

		$this->assertSame( '0', $this->get_ajax_response( 'delete-page' ) );
		$this->assertNotNull( get_post( $page_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_page_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $page_id );
		$this->set_request( $page_id, 'invalid-nonce' );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-page' ) );
		$this->assertNotNull( get_post( $page_id ) );
	}

	/**
	 * Authors can delete their own posts but have no page capabilities.
	 *
	 * @ticket 65252
	 */
	public function test_delete_page_rejects_author(): void {
		wp_set_current_user( self::$author_id );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => self::$author_id,
			)
		);
		wp_trash_post( $page_id );
		$this->set_request( $page_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-page' ) );
		$this->assertNotNull( get_post( $page_id ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_delete_page_rejects_subscriber(): void {
		wp_set_current_user( self::$subscriber_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $page_id );
		$this->set_request( $page_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-page' ) );
		$this->assertNotNull( get_post( $page_id ) );
	}

	/**
	 * A missing page fails the delete_page capability check before the existence check.
	 *
	 * @ticket 65252
	 */
	public function test_delete_page_rejects_nonexistent_page(): void {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_delete_post( $page_id, true );
		$this->set_request( $page_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'delete-page' ) );
	}
}
