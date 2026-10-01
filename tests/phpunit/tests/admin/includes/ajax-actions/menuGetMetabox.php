<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_menu_get_metabox() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_menu_get_metabox
 */
class Tests_wp_ajax_menu_get_metabox extends WP_Ajax_UnitTestCase {

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
	 * Tests retrieval of post type metabox.
	 *
	 * @ticket 65252
	 */
	public function test_menu_get_metabox_post_type(): void {
		wp_set_current_user( self::$admin_id );

		self::factory()->post->create(
			array(
				'post_title' => 'Post Metabox Test',
			)
		);

		$_POST = array(
			'action'      => 'menu-get-metabox',
			'item-type'   => 'post_type',
			'item-object' => 'post',
		);

		try {
			$this->_handleAjax( 'menu-get-metabox' );
		} catch ( WPAjaxDieContinueException $e ) {
			// We expect the function to die after echoing JSON.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertSame( 'posttype-post', $response['replace-id'] );
		$this->assertStringContainsString( 'id="posttype-post"', $response['markup'] );
		$this->assertStringContainsString( 'Post', $response['markup'] );
	}

	/**
	 * Tests retrieval of taxonomy metabox.
	 *
	 * @ticket 65252
	 */
	public function test_menu_get_metabox_taxonomy(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'menu-get-metabox',
			'item-type'   => 'taxonomy',
			'item-object' => 'category',
		);

		try {
			$this->_handleAjax( 'menu-get-metabox' );
		} catch ( WPAjaxDieContinueException $e ) {
			// We expect the function to die after echoing JSON.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertSame( 'taxonomy-category', $response['replace-id'] );
		$this->assertStringContainsString( 'id="taxonomy-category"', $response['markup'] );
		$this->assertStringContainsString( 'Categories', $response['markup'] );
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_menu_get_metabox_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'action'      => 'menu-get-metabox',
			'item-type'   => 'post_type',
			'item-object' => 'post',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'menu-get-metabox' );
	}

	/**
	 * Tests behavior with invalid item-type.
	 *
	 * @ticket 65252
	 */
	public function test_menu_get_metabox_invalid_item_type(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'menu-get-metabox',
			'item-type'   => 'invalid',
			'item-object' => 'post',
		);

		try {
			$this->_handleAjax( 'menu-get-metabox' );
		} catch ( WPAjaxDieStopException $e ) {
			// We expect it to die without outputting anything if item-type is invalid.
		}

		$this->assertEmpty( $this->_last_response );
	}

	/**
	 * Tests behavior with invalid item-object.
	 *
	 * @ticket 65252
	 */
	public function test_menu_get_metabox_invalid_item_object(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'menu-get-metabox',
			'item-type'   => 'post_type',
			'item-object' => 'non_existent_post_type',
		);

		try {
			$this->_handleAjax( 'menu-get-metabox' );
		} catch ( WPAjaxDieStopException $e ) {
			// We expect it to die without outputting anything if item-object is invalid.
		}

		$this->assertEmpty( $this->_last_response );
	}
}
