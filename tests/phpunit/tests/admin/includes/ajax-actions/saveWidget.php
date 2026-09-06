<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_save_widget() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_widget
 */
class Tests_wp_ajax_save_widget extends WP_Ajax_UnitTestCase {

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
	 * Tests successful widget deletion via AJAX.
	 *
	 * @ticket 65252
	 */
	public function test_save_widget_delete_success(): void {
		global $wp_registered_widgets;

		wp_set_current_user( self::$admin_id );

		// Register a dummy widget.
		$widget_id                           = 'dummy-widget-1';
		$wp_registered_widgets[ $widget_id ] = array(
			'name'     => 'Dummy Widget',
			'id'       => $widget_id,
			'callback' => '__return_empty_string',
			'params'   => array(),
		);

		// Set up sidebar with the widget.
		$sidebar_id = 'sidebar-1';
		wp_set_sidebars_widgets( array( $sidebar_id => array( $widget_id ) ) );

		$_POST = array(
			'action'        => 'save-widget',
			'savewidgets'   => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebar'       => $sidebar_id,
			'widget-id'     => $widget_id,
			'id_base'       => 'dummy',
			'delete_widget' => '1',
		);

		try {
			$this->_handleAjax( 'save-widget' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$this->assertSame( "deleted:$widget_id", $this->_last_response );

		$sidebars = wp_get_sidebars_widgets();
		$this->assertNotContains( $widget_id, $sidebars[ $sidebar_id ] );

		// Cleanup.
		unset( $wp_registered_widgets[ $widget_id ] );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_save_widget_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'save-widget',
			'savewidgets' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'save-widget' );
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_save_widget_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST = array(
			'action'      => 'save-widget',
			'savewidgets' => wp_create_nonce( 'save-sidebar-widgets' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'save-widget' );
	}

	/**
	 * Tests failure when id_base is missing.
	 *
	 * @ticket 65252
	 */
	public function test_save_widget_missing_id_base(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'save-widget',
			'savewidgets' => wp_create_nonce( 'save-sidebar-widgets' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'save-widget' );
	}

	/**
	 * Tests successful addition of a new multi-widget.
	 *
	 * @ticket 65252
	 */
	public function test_save_widget_add_new_multi_widget(): void {
		global $wp_registered_widget_updates, $wp_registered_widget_controls;

		wp_set_current_user( self::$admin_id );

		$id_base      = 'testmulti';
		$multi_number = 2;
		$widget_id    = "$id_base-$multi_number";
		$sidebar_id   = 'sidebar-1';

		// Ensure sidebar exists.
		wp_set_sidebars_widgets( array( $sidebar_id => array() ) );

		// Mock the update callback.
		$updated                                  = false;
		$wp_registered_widget_updates[ $id_base ] = array(
			'callback' => function () use ( &$updated, $id_base, $multi_number ) {
				$updated = true;
				// In a real scenario, the update callback would update the option.
				$settings = array( $multi_number => array( 'title' => 'New Widget' ) );
				update_option( 'widget_' . $id_base, $settings );
			},
			'params'   => array(),
		);

		// Mock the control callback.
		$control_called                              = false;
		$wp_registered_widget_controls[ $widget_id ] = array(
			'callback' => function () use ( &$control_called ) {
				$control_called = true;
				echo 'control-output';
			},
			'params'   => array(),
		);

		$_POST = array(
			'action'             => 'save-widget',
			'savewidgets'        => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebar'            => $sidebar_id,
			'widget-id'          => $widget_id,
			'id_base'            => $id_base,
			'multi_number'       => $multi_number,
			'widget-' . $id_base => array( '__i__' => array( 'title' => 'New Widget' ) ),
			'add_new'            => 'multi',
		);

		try {
			$this->_handleAjax( 'save-widget' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$this->assertTrue( $updated, 'Update callback should be called.' );

		$this->assertContains( $widget_id, $_POST['widget-id'] );

		// Cleanup.
		unset( $wp_registered_widget_updates[ $id_base ], $wp_registered_widget_controls[ $widget_id ] );
	}
}
