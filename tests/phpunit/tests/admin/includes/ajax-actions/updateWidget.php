<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_update_widget() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.9.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_update_widget
 */
class Tests_wp_ajax_update_widget extends WP_Ajax_UnitTestCase {

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
		// Initialize the Customizer.
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_update_widget_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action' => 'update-widget',
			'nonce'  => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'update-widget' );
	}

	/**
	 * Tests failure due to missing widget-id.
	 *
	 * @ticket 65252
	 */
	public function test_update_widget_missing_widget_id(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action' => 'update-widget',
			'nonce'  => wp_create_nonce( 'update-widget' ),
		);

		try {
			$this->_handleAjax( 'update-widget' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'missing_widget-id', $response['data'] );
	}

	/**
	 * Tests failure for template widget.
	 *
	 * @ticket 65252
	 */
	public function test_update_widget_template_widget_not_updatable(): void {
		wp_set_current_user( self::$admin_id );

		$id_base   = 'text';
		$widget_id = $id_base . '-1';

		$_POST = array(
			'action'             => 'update-widget',
			'nonce'              => wp_create_nonce( 'update-widget' ),
			'widget-id'          => $widget_id,
			'widget-' . $id_base => array( '__i__' => array( 'title' => 'Title' ) ),
		);

		try {
			$this->_handleAjax( 'update-widget' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'], 'Should fail for template widget' );
		$this->assertSame( 'template_widget_not_updatable', $response['data'] );
	}
}
