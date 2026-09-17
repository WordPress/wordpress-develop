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
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_permalink
 */
class Tests_wp_ajax_get_permalink extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Tests successful retrieval of a post permalink.
	 *
	 * @ticket 65252
	 */
	public function test_get_permalink_success(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Test Post',
			)
		);

		$_POST = array(
			'action'            => 'get-permalink',
			'post_id'           => $post_id,
			'getpermalinknonce' => wp_create_nonce( 'getpermalink' ),
		);

		try {
			$this->_handleAjax( 'get-permalink' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		}

		$this->assertStringContainsString( 'p=' . $post_id, $this->_last_response );
		$this->assertStringContainsString( 'preview=true', $this->_last_response );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_get_permalink_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'get-permalink',
			'post_id'           => 123,
			'getpermalinknonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'get-permalink' );
	}

	/**
	 * Tests behavior with missing post ID.
	 *
	 * @ticket 65252
	 */
	public function test_get_permalink_missing_post_id(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'get-permalink',
			'getpermalinknonce' => wp_create_nonce( 'getpermalink' ),
		);

		try {
			$this->_handleAjax( 'get-permalink' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success (it will return a link for post ID 0, which is usually home or a generic preview link).
			$this->_last_response = $e->getMessage();
		}

		$this->assertNotSame( '', $this->_last_response );
	}
}
