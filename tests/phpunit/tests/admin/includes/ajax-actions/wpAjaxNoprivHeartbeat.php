<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_nopriv_heartbeat() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.6.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_nopriv_heartbeat
 */
class Tests_wp_ajax_nopriv_heartbeat extends WP_Ajax_UnitTestCase {

	/**
	 * Tests the Heartbeat API in the no-privilege context.
	 *
	 * @ticket 65236
	 */
	public function test_wp_ajax_nopriv_heartbeat(): void {
		// Mock the user to be logged out.
		wp_set_current_user( 0 );

		$_POST = array(
			'action'    => 'nopriv_heartbeat',
			'screen_id' => 'front',
			'data'      => array(
				'test_data' => 'test_value',
			),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'nopriv_heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected exception.
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			// Expected exception.
			unset( $e );
		} catch ( Exception $e ) {
			$this->fail( 'Unexpected exception: ' . $e->getMessage() );
		}

		if ( empty( $this->_last_response ) ) {
			$this->fail( 'Ajax response was empty' );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'server_time', $response );
		$this->assertIsInt( $response['server_time'] );
	}

	/**
	 * Tests the 'heartbeat_nopriv_received' filter.
	 *
	 * @ticket 65236
	 */
	public function test_heartbeat_nopriv_received_filter(): void {
		wp_set_current_user( 0 );

		$_POST = array(
			'action'    => 'nopriv_heartbeat',
			'screen_id' => 'test_screen',
			'data'      => array(
				'foo' => 'bar',
			),
		);

		add_filter(
			'heartbeat_nopriv_received',
			function ( $response, $data, $screen_id ) {
				$response['received_data']   = $data;
				$response['received_screen'] = $screen_id;
				return $response;
			},
			10,
			3
		);

		try {
			$this->_handleAjax( 'nopriv_heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertSame( array( 'foo' => 'bar' ), $response['received_data'] );
		$this->assertSame( 'test_screen', $response['received_screen'] );
	}

	/**
	 * Tests the 'heartbeat_nopriv_send' filter.
	 *
	 * @ticket 65236
	 */
	public function test_heartbeat_nopriv_send_filter(): void {
		wp_set_current_user( 0 );

		$_POST = array(
			'action' => 'nopriv_heartbeat',
		);

		add_filter(
			'heartbeat_nopriv_send',
			function ( $response, $screen_id ) {
				$response['sent_screen'] = $screen_id;
				return $response;
			},
			10,
			2
		);

		try {
			$this->_handleAjax( 'nopriv_heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		// Default screen_id is 'front'.
		$this->assertSame( 'front', $response['sent_screen'] );
	}

	/**
	 * Tests the 'heartbeat_nopriv_tick' action.
	 *
	 * @ticket 65236
	 */
	public function test_heartbeat_nopriv_tick_action(): void {
		wp_set_current_user( 0 );

		$_POST = array(
			'action' => 'nopriv_heartbeat',
		);

		$action_called = false;
		add_action(
			'heartbeat_nopriv_tick',
			function ( $response, $screen_id ) use ( &$action_called ) {
				$action_called = true;
			},
			10,
			2
		);

		try {
			$this->_handleAjax( 'nopriv_heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$this->assertTrue( $action_called );
	}
}
