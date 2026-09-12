<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_widgets_order() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.0.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_widgets_order
 */
class Tests_Ajax_widgetsOrder extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 */
	protected static int $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Tests successful widgets order saving.
	 *
	 * @ticket 65341
	 */
	public function test_widgets_order_success(): void {
		wp_set_current_user( self::$admin_id );

		// Mock sidebars.
		$sidebars = array(
			'sidebar-1' => 'widget-1_text-1,widget-2_text-2',
			'sidebar-2' => 'widget-3_search-1',
		);

		$_POST = array(
			'action'      => 'widgets-order',
			'savewidgets' => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebars'    => $sidebars,
		);

		$original_sidebars = wp_get_sidebars_widgets();

		try {
			$this->_handleAjax( 'widgets-order' );
			$this->fail( 'Expected WPAjaxDieStopException was not thrown.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		}

		try {
			$updated_sidebars = wp_get_sidebars_widgets();
			$this->assertArrayHasKey( 'sidebar-1', $updated_sidebars );
			$this->assertArrayHasKey( 'sidebar-2', $updated_sidebars );
			$this->assertSame( array( 'text-1', 'text-2' ), array_values( $updated_sidebars['sidebar-1'] ) );
			$this->assertSame( array( 'search-1' ), array_values( $updated_sidebars['sidebar-2'] ) );
		} finally {
			wp_set_sidebars_widgets( $original_sidebars );
		}
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65341
	 */
	public function test_widgets_order_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'widgets-order',
			'savewidgets' => 'invalid-nonce',
		);

		try {
			$this->_handleAjax( 'widgets-order' );
			$this->fail( 'Expected WPAjaxDieStopException was not thrown.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65341
	 */
	public function test_widgets_order_insufficient_permissions(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$_POST = array(
			'action'      => 'widgets-order',
			'savewidgets' => wp_create_nonce( 'save-sidebar-widgets' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'widgets-order' );
	}

	/**
	 * Tests behavior when sidebars parameter is missing.
	 *
	 * @ticket 65341
	 */
	public function test_widgets_order_missing_sidebars(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'widgets-order',
			'savewidgets' => wp_create_nonce( 'save-sidebar-widgets' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'widgets-order' );
	}
}
