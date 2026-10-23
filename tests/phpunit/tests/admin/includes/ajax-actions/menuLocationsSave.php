<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_menu_locations_save() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_menu_locations_save
 */
class Tests_wp_ajax_menu_locations_save extends WP_Ajax_UnitTestCase {

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
	 * Tests successful saving of menu locations.
	 *
	 * @ticket 65252
	 */
	public function test_menu_locations_save_success(): void {
		wp_set_current_user( self::$admin_id );

		$menu_id = self::factory()->term->create(
			array(
				'name'     => 'Test Menu',
				'taxonomy' => 'nav_menu',
			)
		);

		$_POST = array(
			'action'                     => 'menu-locations-save',
			'menu-settings-column-nonce' => wp_create_nonce( 'add-menu_item' ),
			'menu-locations'             => array(
				'primary' => $menu_id,
			),
		);

		try {
			$this->_handleAjax( 'menu-locations-save' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'The AJAX response should be 1.' );
		}

		$locations = get_theme_mod( 'nav_menu_locations' );
		$this->assertIsArray( $locations );
		$this->assertSame( $menu_id, $locations['primary'] );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_menu_locations_save_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'                     => 'menu-locations-save',
			'menu-settings-column-nonce' => 'invalid-nonce',
			'menu-locations'             => array(
				'primary' => 1,
			),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'menu-locations-save' );
	}

	/**
	 * Tests failure due to missing menu-locations.
	 *
	 * @ticket 65252
	 */
	public function test_menu_locations_save_missing_locations(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'                     => 'menu-locations-save',
			'menu-settings-column-nonce' => wp_create_nonce( 'add-menu_item' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '0' );

		$this->_handleAjax( 'menu-locations-save' );
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_menu_locations_save_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'action'                     => 'menu-locations-save',
			'menu-settings-column-nonce' => wp_create_nonce( 'add-menu_item' ),
			'menu-locations'             => array(
				'primary' => 1,
			),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'menu-locations-save' );
	}
}
