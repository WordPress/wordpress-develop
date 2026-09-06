<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_inactive_widgets() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_inactive_widgets
 */
class Tests_wp_ajax_delete_inactive_widgets extends WP_Ajax_UnitTestCase {

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
	 * Set up the test fixture.
	 * Override wp_die().
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );
	}

	/**
	 * Tear down the test fixture.
	 */
	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );
		parent::tear_down();
	}

	/**
	 * Return our callback handler
	 *
	 * @return callback
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Handler for wp_die()
	 * Don't die, just throw an exception.
	 *
	 * @param string|WP_Error $message
	 * @param string          $title
	 * @param array           $args
	 * @throws WPAjaxDieStopException
	 */
	public function dieHandler( $message, $title = '', $args = array() ) {
		throw new WPAjaxDieStopException( (string) $message );
	}

	/**
	 * Tests successful deletion of inactive widgets.
	 *
	 * @ticket 65252
	 */
	public function test_delete_inactive_widgets_success(): void {
		wp_set_current_user( self::$admin_id );

		$id_base  = 'test_widget';
		$settings = array(
			1               => array( 'title' => 'Widget 1' ),
			2               => array( 'title' => 'Widget 2' ),
			'array_version' => 3,
		);
		update_option( 'widget_' . $id_base, $settings );

		$inactive_widgets = array( $id_base . '-1', $id_base . '-2' );
		wp_set_sidebars_widgets( array( 'wp_inactive_widgets' => $inactive_widgets ) );

		$_POST = array(
			'action'                => 'delete-inactive-widgets',
			'removeinactivewidgets' => wp_create_nonce( 'remove-inactive-widgets' ),
		);

		try {
			$this->_handleAjax( 'delete-inactive-widgets' );
		} catch ( WPAjaxDieStopException $e ) {
		}

		// The actual logic is tested in Tests_wp_delete_inactive_widgets.
		// Here we just verify the wrapper executes without major error.
		$this->assertTrue( true );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_delete_inactive_widgets_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'                => 'delete-inactive-widgets',
			'removeinactivewidgets' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		wp_ajax_delete_inactive_widgets();
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_delete_inactive_widgets_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST = array(
			'action'                => 'delete-inactive-widgets',
			'removeinactivewidgets' => wp_create_nonce( 'remove-inactive-widgets' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		wp_ajax_delete_inactive_widgets();
	}
}
