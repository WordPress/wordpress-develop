<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_find_posts() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_find_posts
 */
class Tests_wp_ajax_find_posts extends WP_Ajax_UnitTestCase {

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

	public function set_up() {
		parent::set_up();
		add_action( 'wp_ajax_find-posts', 'wp_ajax_find_posts', 1 );
	}

	/**
	 * Tests successful post search.
	 *
	 * @ticket 65252
	 */
	public function test_find_posts_success(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Searchable Post Title',
			)
		);

		$_POST = array(
			'action'      => 'find-posts',
			'ps'          => 'Searchable',
			'_ajax_nonce' => wp_create_nonce( 'find-posts' ),
		);

		try {
			$this->_handleAjax( 'find-posts' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$this->assertStringContainsString( 'Searchable Post Title', $this->_last_response );
		$this->assertStringContainsString( 'class=\"widefat\"', $this->_last_response );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_find_posts_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'find-posts',
			'ps'          => 'Searchable',
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'find-posts' );
	}

	/**
	 * Tests failure when no results are found.
	 *
	 * @ticket 65252
	 */
	public function test_find_posts_no_results(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'find-posts',
			'ps'          => 'NonExistentTitle',
			'_ajax_nonce' => wp_create_nonce( 'find-posts' ),
		);

		try {
			$this->_handleAjax( 'find-posts' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No items found.', $response['data'] );
	}
}
