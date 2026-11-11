<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_update_welcome_panel() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_update_welcome_panel
 */
class Tests_wp_ajax_update_welcome_panel extends WP_Ajax_UnitTestCase {

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
	 * Tests successful update of welcome panel visibility.
	 *
	 * @dataProvider data_update_welcome_panel
	 *
	 * @ticket 65252
	 *
	 * @param mixed $visible The value for the 'visible' POST parameter.
	 * @param int   $expected The expected meta value.
	 */
	public function test_update_welcome_panel_success( $visible, int $expected ): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'update-welcome-panel',
			'welcomepanelnonce' => wp_create_nonce( 'welcome-panel-nonce' ),
		);

		if ( null !== $visible ) {
			$_POST['visible'] = $visible;
		}

		try {
			$this->_handleAjax( 'update-welcome-panel' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		}

		$this->assertSame( $expected, (int) get_user_meta( self::$admin_id, 'show_welcome_panel', true ) );
	}

	/**
	 * Data provider for test_update_welcome_panel_success.
	 *
	 * @return array<string, array{
	 *     visible: mixed,
	 *     expected: int,
	 * }>
	 */
	public function data_update_welcome_panel(): array {
		return array(
			'visible true'  => array(
				'visible'  => '1',
				'expected' => 1,
			),
			'visible false' => array(
				'visible'  => '0',
				'expected' => 0,
			),
			'visible empty' => array(
				'visible'  => '',
				'expected' => 0,
			),
			'visible null'  => array(
				'visible'  => null,
				'expected' => 0,
			),
		);
	}

	/**
	 * Tests update failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_update_welcome_panel_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'update-welcome-panel',
			'welcomepanelnonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'update-welcome-panel' );
	}

	/**
	 * Tests update failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_update_welcome_panel_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'action'            => 'update-welcome-panel',
			'welcomepanelnonce' => wp_create_nonce( 'welcome-panel-nonce' ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'update-welcome-panel' );
	}
}
