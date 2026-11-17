<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_get_permalink() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_permalink
 */
class Tests_Admin_Includes_AjaxActions_getPermalink extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Contributor user ID.
	 *
	 * @var int
	 */
	protected static $contributor_id;

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
		self::$admin_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$contributor_id = $factory->user->create( array( 'role' => 'contributor' ) );
		self::$subscriber_id  = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Populates $_POST for a permalink request.
	 *
	 * @param int|null    $post_id Post ID, or null to omit it.
	 * @param string|null $nonce   Optional. Nonce to send. Default is a valid nonce.
	 */
	private function set_request( ?int $post_id, ?string $nonce = null ): void {
		$_POST = array(
			'getpermalinknonce' => $nonce ?? wp_create_nonce( 'getpermalink' ),
		);

		if ( null !== $post_id ) {
			$_POST['post_id'] = $post_id;
		}
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_permalink_returns_preview_link(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();
		$this->set_request( $post_id );

		$this->assertSame( get_preview_post_link( $post_id ), $this->get_ajax_response( 'get-permalink' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_permalink_returns_preview_link_for_contributors_own_draft(): void {
		wp_set_current_user( self::$contributor_id );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$contributor_id,
				'post_status' => 'draft',
			)
		);
		$this->set_request( $post_id );

		$this->assertSame( get_preview_post_link( $post_id ), $this->get_ajax_response( 'get-permalink' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_permalink_returns_empty_string_without_post_id(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( null );

		$this->assertSame( '', $this->get_ajax_response( 'get-permalink' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_permalink_rejects_contributor_for_another_users_post(): void {
		wp_set_current_user( self::$contributor_id );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$admin_id,
				'post_status' => 'draft',
			)
		);
		$this->set_request( $post_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'get-permalink' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_permalink_rejects_subscriber(): void {
		wp_set_current_user( self::$subscriber_id );

		$post_id = self::factory()->post->create();
		$this->set_request( $post_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'get-permalink' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_permalink_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();
		$this->set_request( $post_id, 'invalid-nonce' );

		$this->assertSame( '-1', $this->get_ajax_response( 'get-permalink' ) );
	}
}
