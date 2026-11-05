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
 *
 * @group ajax
 *
 * @covers ::wp_ajax_hidden_columns
 */
class Tests_Admin_Includes_AjaxActions_hiddenColumns extends WP_Ajax_UnitTestCase {

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
	 * @ticket 65252
	 */
	public function test_hidden_columns_saves_hidden_columns(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'screenoptionnonce' => wp_create_nonce( 'screen-options-nonce' ),
			'page'              => 'testpage',
			'hidden'            => 'column1,column2,column3',
		);

		$this->assertSame( '1', $this->get_ajax_response( 'hidden-columns' ) );
		$this->assertSame( array( 'column1', 'column2', 'column3' ), get_user_meta( self::$admin_id, 'managetestpagecolumnshidden', true ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_hidden_columns_empty_list_clears_hidden_columns(): void {
		wp_set_current_user( self::$admin_id );
		update_user_meta( self::$admin_id, 'managetestpagecolumnshidden', array( 'column1' ) );

		$_POST = array(
			'screenoptionnonce' => wp_create_nonce( 'screen-options-nonce' ),
			'page'              => 'testpage',
			'hidden'            => '',
		);

		$this->assertSame( '1', $this->get_ajax_response( 'hidden-columns' ) );
		$this->assertSame( array(), get_user_meta( self::$admin_id, 'managetestpagecolumnshidden', true ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_hidden_columns_rejects_unsanitized_page(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'screenoptionnonce' => wp_create_nonce( 'screen-options-nonce' ),
			'page'              => 'invalid page!',
			'hidden'            => 'column1',
		);

		$this->assertSame( '0', $this->get_ajax_response( 'hidden-columns' ) );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'manageinvalid page!columnshidden', true ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_hidden_columns_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'screenoptionnonce' => 'invalid-nonce',
			'page'              => 'testpage',
			'hidden'            => 'column1',
		);

		$this->assertSame( '-1', $this->get_ajax_response( 'hidden-columns' ) );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'managetestpagecolumnshidden', true ) );
	}
}
