<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_inline_save() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_inline_save
 */
class Tests_wp_ajax_inline_save extends WP_Ajax_UnitTestCase {

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
	 * Tests successful Quick Edit save for a post.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_post_success(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Initial Title',
			)
		);

		$_POST = array(
			'action'      => 'inline-save',
			'post_ID'     => $post_id,
			'post_type'   => 'post',
			'post_title'  => 'Updated Title',
			'post_name'   => 'updated-title',
			'_inline_edit' => wp_create_nonce( 'inlineeditnonce' ),
			'screen'      => 'edit-post',
			'post_view'   => 'list',
		);

		try {
			$this->_handleAjax( 'inline-save' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$post = get_post( $post_id );
		$this->assertSame( 'Updated Title', $post->post_title );
		$this->assertSame( 'updated-title', $post->post_name );
		$this->assertStringContainsString( 'Updated Title', $this->_last_response );
		$this->assertStringContainsString( 'id="post-' . $post_id . '"', $this->_last_response );
	}

	/**
	 * Tests successful Quick Edit save for a page.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_page_success(): void {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create(
			array(
				'post_title' => 'Initial Page Title',
				'post_type'  => 'page',
			)
		);

		$_POST = array(
			'action'      => 'inline-save',
			'post_ID'     => $page_id,
			'post_type'   => 'page',
			'post_title'  => 'Updated Page Title',
			'_inline_edit' => wp_create_nonce( 'inlineeditnonce' ),
			'screen'      => 'edit-page',
			'post_view'   => 'list',
		);

		try {
			$this->_handleAjax( 'inline-save' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$page = get_post( $page_id );
		$this->assertSame( 'Updated Page Title', $page->post_title );
		$this->assertStringContainsString( 'Updated Page Title', $this->_last_response );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'inline-save',
			'post_ID'     => 1,
			'_inline_edit' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		// check_ajax_referer() calls wp_die( -1 ) which throws WPAjaxDieStopException( -1 ) by default if no output.

		$this->_handleAjax( 'inline-save' );
	}

	/**
	 * Tests failure due to missing post_ID.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_missing_id(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'inline-save',
			'_inline_edit' => wp_create_nonce( 'inlineeditnonce' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		// wp_die() with no args.

		$this->_handleAjax( 'inline-save' );
	}

	/**
	 * Tests failure due to insufficient permissions for a post.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_insufficient_permissions_post(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create();

		$_POST = array(
			'action'      => 'inline-save',
			'post_ID'     => $post_id,
			'post_type'   => 'post',
			'_inline_edit' => wp_create_nonce( 'inlineeditnonce' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( 'Sorry, you are not allowed to edit this post.' );

		$this->_handleAjax( 'inline-save' );
	}

	/**
	 * Tests failure due to insufficient permissions for a page.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_insufficient_permissions_page(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$_POST = array(
			'action'      => 'inline-save',
			'post_ID'     => $page_id,
			'post_type'   => 'page',
			'_inline_edit' => wp_create_nonce( 'inlineeditnonce' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( 'Sorry, you are not allowed to edit this page.' );

		$this->_handleAjax( 'inline-save' );
	}

	/**
	 * Tests behavior when post is locked by another user.
	 *
	 * @ticket 65252
	 */
	public function test_inline_save_post_locked(): void {
		wp_set_current_user( self::$admin_id );

		$other_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id       = self::factory()->post->create();

		// Lock the post by another user.
		wp_set_post_lock( $post_id, $other_user_id );

		$_POST = array(
			'action'      => 'inline-save',
			'post_ID'     => $post_id,
			'post_type'   => 'post',
			'_inline_edit' => wp_create_nonce( 'inlineeditnonce' ),
		);

		try {
			$this->_handleAjax( 'inline-save' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect failure message.
		}

		$other_user = get_userdata( $other_user_id );
		$expected_msg = sprintf( 'Saving is disabled: %s is currently editing this post.', $other_user->display_name );
		$this->assertStringContainsString( $expected_msg, $this->_last_response );
	}
}
