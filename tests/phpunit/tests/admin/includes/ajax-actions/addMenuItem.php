<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_add_menu_item() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_add_menu_item
 */
class Tests_wp_ajax_add_menu_item extends WP_Ajax_UnitTestCase {

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
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		add_action( 'admin_init', array( $this, 'hook_ajax_handler' ), 1 );
	}

	/**
	 * Hooks the AJAX handler to admin_init.
	 */
	public function hook_ajax_handler(): void {
		if ( isset( $_POST['action'] ) && 'add-menu-item' === $_POST['action'] ) {
			wp_ajax_add_menu_item();
		}
	}

	/**
	 * Tests successful addition of a custom menu item.
	 *
	 * @ticket 65252
	 */
	public function test_add_menu_item_custom_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'                     => 'add-menu-item',
			'menu-settings-column-nonce' => wp_create_nonce( 'add-menu_item' ),
			'menu-item'                  => array(
				array(
					'menu-item-type'  => 'custom',
					'menu-item-title' => 'Custom Link',
					'menu-item-url'   => 'https://example.com',
				),
			),
		);

		try {
			$this->_handleAjax( 'add-menu-item' );
		} catch ( WPAjaxDieStopException $e ) {
			$response = $e->getMessage();
			$this->assertStringContainsString( 'Custom Link', $response );
			$this->assertStringContainsString( 'https://example.com', $response );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			$this->assertStringContainsString( 'Custom Link', $response );
			$this->assertStringContainsString( 'https://example.com', $response );
		}
	}

	/**
	 * Tests successful addition of a post menu item.
	 *
	 * @ticket 65252
	 */
	public function test_add_menu_item_post_success(): void {
		$factory = self::factory();
		wp_set_current_user( self::$admin_id );

		$post_id = $factory->post->create(
			array(
				'post_title' => 'Test Page',
				'post_type'  => 'page',
			)
		);

		$_POST = array(
			'action'                     => 'add-menu-item',
			'menu-settings-column-nonce' => wp_create_nonce( 'add-menu_item' ),
			'menu-item'                  => array(
				array(
					'menu-item-type'      => 'post_type',
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $post_id,
				),
			),
		);

		try {
			$this->_handleAjax( 'add-menu-item' );
		} catch ( WPAjaxDieStopException $e ) {
			$response = $e->getMessage();
			$this->assertStringContainsString( 'Test Page', $response );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			$this->assertStringContainsString( 'Test Page', $response );
		}
	}

	/**
	 * Tests addition failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_add_menu_item_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'                     => 'add-menu-item',
			'menu-settings-column-nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'add-menu-item' );
	}

	/**
	 * Tests addition failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_add_menu_item_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'action'                     => 'add-menu-item',
			'menu-settings-column-nonce' => wp_create_nonce( 'add-menu_item' ),
		);

		try {
			$this->_handleAjax( 'add-menu-item' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}
}
