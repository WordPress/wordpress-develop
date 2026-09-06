<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_menu_quick_search() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_menu_quick_search
 */
class Tests_wp_ajax_menu_quick_search extends WP_Ajax_UnitTestCase {

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
	 * Tests successful menu quick search for a post (JSON format).
	 *
	 * @ticket 65252
	 */
	public function test_menu_quick_search_post_json(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Quick Search Post',
			)
		);

		$_POST = array(
			'action'          => 'menu-quick-search',
			'type'            => 'get-post-item',
			'object_type'     => 'post',
			'ID'              => $post_id,
			'response-format' => 'json',
		);

		try {
			$this->_handleAjax( 'menu-quick-search' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertSame( $post_id, $response['ID'] );
		$this->assertSame( 'Quick Search Post', $response['post_title'] );
		$this->assertSame( 'post', $response['post_type'] );
	}

	/**
	 * Tests successful menu quick search for a post (markup format).
	 *
	 * @ticket 65252
	 */
	public function test_menu_quick_search_post_markup(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Quick Search Post Markup',
			)
		);

		$_POST = array(
			'action'          => 'menu-quick-search',
			'type'            => 'get-post-item',
			'object_type'     => 'post',
			'ID'              => $post_id,
			'response-format' => 'markup',
		);

		try {
			$this->_handleAjax( 'menu-quick-search' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$this->assertStringContainsString( 'Quick Search Post Markup', $this->_last_response );
		$this->assertStringContainsString( 'menu-item-title', $this->_last_response );
	}

	/**
	 * Tests successful menu quick search for a taxonomy term (JSON format).
	 *
	 * @ticket 65252
	 */
	public function test_menu_quick_search_taxonomy_json(): void {
		wp_set_current_user( self::$admin_id );

		$term_id = self::factory()->term->create(
			array(
				'name'     => 'Quick Search Term',
				'taxonomy' => 'category',
			)
		);

		$_POST = array(
			'action'          => 'menu-quick-search',
			'type'            => 'get-post-item',
			'object_type'     => 'category',
			'ID'              => $term_id,
			'response-format' => 'json',
		);

		try {
			$this->_handleAjax( 'menu-quick-search' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertSame( $term_id, $response['ID'] );
		$this->assertSame( 'Quick Search Term', $response['post_title'] );
		$this->assertSame( 'category', $response['post_type'] );
	}

	/**
	 * Tests successful quick search by query (JSON format).
	 *
	 * @ticket 65252
	 */
	public function test_menu_quick_search_query_json(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'UniqueQueryPost',
			)
		);

		$_POST = array(
			'action'          => 'menu-quick-search',
			'type'            => 'quick-search-posttype-post',
			'q'               => 'UniqueQueryPost',
			'response-format' => 'json',
		);

		try {
			$this->_handleAjax( 'menu-quick-search' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertSame( $post_id, $response['ID'] );
		$this->assertSame( 'UniqueQueryPost', $response['post_title'] );
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_menu_quick_search_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'action' => 'menu-quick-search',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'menu-quick-search' );
	}
}
