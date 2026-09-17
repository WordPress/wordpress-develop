<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_hidden_columns() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_hidden_columns
 */
class Tests_wp_ajax_hidden_columns extends WP_Ajax_UnitTestCase {

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
		if ( isset( $_POST['action'] ) && 'hidden-columns' === $_POST['action'] ) {
			wp_ajax_hidden_columns();
		}
	}

	/**
	 * Tests successful update of hidden columns.
	 *
	 * @ticket 65252
	 */
	public function test_hidden_columns_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'hidden-columns',
			'screenoptionnonce' => wp_create_nonce( 'screen-options-nonce' ),
			'page'              => 'testpage',
			'hidden'            => 'column1,column2,column3',
		);

		try {
			$this->_handleAjax( 'hidden-columns' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		}

		$hidden = get_user_meta( self::$admin_id, 'managetestpagecolumnshidden', true );
		$this->assertSame( array( 'column1', 'column2', 'column3' ), $hidden );
	}

	/**
	 * Tests update failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_hidden_columns_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'hidden-columns',
			'screenoptionnonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'hidden-columns' );
	}

	/**
	 * Tests update failure due to invalid page key.
	 *
	 * @ticket 65252
	 */
	public function test_hidden_columns_invalid_page(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'hidden-columns',
			'screenoptionnonce' => wp_create_nonce( 'screen-options-nonce' ),
			'page'              => 'invalid page!',
		);

		try {
			$this->_handleAjax( 'hidden-columns' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '0', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '0', $e->getMessage() );
		}
	}

	/**
	 * Tests update with empty hidden columns.
	 *
	 * @ticket 65252
	 */
	public function test_hidden_columns_empty(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'            => 'hidden-columns',
			'screenoptionnonce' => wp_create_nonce( 'screen-options-nonce' ),
			'page'              => 'testpage',
			'hidden'            => '',
		);

		try {
			$this->_handleAjax( 'hidden-columns' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		}

		$hidden = get_user_meta( self::$admin_id, 'managetestpagecolumnshidden', true );
		$this->assertSame( array(), $hidden );
	}
}
