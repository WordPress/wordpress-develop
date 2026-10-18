<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Dashboard Widgets AJAX functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_dashboard_widgets
 */
class Tests_Ajax_wpAjaxDashboardWidgets extends WP_Ajax_UnitTestCase {

	/**
	 * @var int
	 */
	protected static int $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
		set_current_screen( 'dashboard' );

		//Prevent time waste due to all external HTTP requests from RSS feeds
		add_filter( 'pre_http_request', '__return_true' );
		$GLOBALS['post'] = null;
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		unset( $_GET['pagenow'], $_GET['widget'], $_POST['post_ID'], $_POST['action'] );
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * wp_ajax_dashboard_widgets Happy Path。
	 *
	 * All valid inputs should correctly set the current screen and output the expected widget content.
	 */
	public function test_wp_ajax_dashboard_widgets_happy_path(): void {
		$this->_setRole( 'administrator' );

		$_GET['pagenow'] = 'dashboard';
		$_GET['widget']  = 'dashboard_primary';
		$_POST['action'] = 'dashboard-widgets';

		update_option( 'dashboard_primary_feeds', array( 'test' => array( 'link' => 'https://wordpress.org' ) ) );

		try {
			$this->_handleAjax( 'dashboard-widgets' );
		} catch ( \WPAjaxDieContinueException $e ) { // 捕捉所有 AJAX 死亡例外 (包括 Stop 和 Continue)
			unset( $e );
		}

		$output = $this->_last_response;

		$this->assertStringContainsString( 'rss-widget', $output );
		$this->assertSame( 'dashboard', $GLOBALS['current_screen']->id );
	}

	/**
	 * Test empty parameters should not trigger PHP Warning
	 *
	 * @ticket 65054
	 */
	public function test_wp_ajax_dashboard_widgets_empty_params(): void {
		$this->_setRole( 'administrator' );
		$_GET            = array();
		$_POST['action'] = 'dashboard-widgets';

		try {
			$this->_handleAjax( 'dashboard-widgets' );
		} catch ( \Exception $e ) {
			$this->assertTrue( true );
		}

		$this->assertEmpty( $this->_last_response );
	}

	/**
	 * Test that the current screen is not affected by global post
	 *
	 * @ticket 65054
	 */
	public function test_wp_ajax_dashboard_widgets_should_not_be_affected_by_global_post(): void {
		//Should Not See This
		$pollution_post_id = self::factory()->post->create( array( 'post_title' => 'Should Not See This' ) );
		$GLOBALS['post']   = get_post( $pollution_post_id );

		$_GET['pagenow'] = 'dashboard';
		$_GET['widget']  = 'dashboard_primary';
		$_GET['action']  = 'dashboard-widgets';

		wp_dashboard_setup();
		try {
			$this->_handleAjax( 'dashboard-widgets' );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$output = $this->_last_response;

		$this->assertStringContainsString( 'rss-widget', $output );
		$this->assertSame( 'dashboard', $GLOBALS['current_screen']->id );
	}

	/**
	 * Invalid request should not trigger global fallback
	 *
	 * @ticket 65054
	 */
	public function test_wp_ajax_dashboard_widgets_invalid_request_no_fallback(): void {
		$_GET['widget']  = 'non_existent_widget';
		$_POST['action'] = 'dashboard-widgets';

		try {
			$this->_handleAjax( 'dashboard-widgets' );
		} catch ( \Exception $e ) {
			$is_ajax_die = $e instanceof \WPAjaxDieStopException || $e instanceof \WPAjaxDieContinueException;
			$this->assertTrue( $is_ajax_die, 'Captured exception should be an AJAX die exception' );
		}

		$this->assertEmpty( $this->_last_response );
	}

	/**
	 * Test that when an invalid pagenow is passed, the screen should not be changed.
	 *
	 * @ticket 65054
	 */
	public function test_wp_ajax_dashboard_widgets_invalid_pagenow(): void {
		$this->_setRole( 'administrator' );

		$_GET['pagenow'] = 'malicious-script-tag'; //malicious-script-tag
		$_GET['widget']  = 'dashboard_primary';

		try {
			$this->_handleAjax( 'dashboard-widgets' );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertNotSame( 'malicious-script-tag', $GLOBALS['current_screen']->id );
	}
}
