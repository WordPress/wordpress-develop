<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_meta_box_order() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_meta_box_order
 */
class Tests_wp_ajax_meta_box_order extends WP_Ajax_UnitTestCase {

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
	 * Tests successful update of meta box order and page columns.
	 *
	 * @ticket 65252
	 */
	public function test_meta_box_order_success(): void {
		wp_set_current_user( self::$admin_id );

		$page  = 'testpage';
		$order = array(
			'side'     => 'box1,box2',
			'normal'   => 'box3',
			'advanced' => '',
		);

		$_POST = array(
			'action'       => 'meta-box-order',
			'_ajax_nonce'  => wp_create_nonce( 'meta-box-order' ),
			'page'         => $page,
			'order'        => $order,
			'page_columns' => 2,
		);

		try {
			$this->_handleAjax( 'meta-box-order' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success response via wp_send_json_success().
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );

		$saved_order = get_user_meta( self::$admin_id, "meta-box-order_$page", true );
		$this->assertSame( $order, $saved_order );

		$saved_columns = get_user_meta( self::$admin_id, "screen_layout_$page", true );
		$this->assertSame( 2, (int) $saved_columns );
	}

	/**
	 * Tests successful update with default page columns ('auto').
	 *
	 * @ticket 65252
	 */
	public function test_meta_box_order_auto_columns(): void {
		wp_set_current_user( self::$admin_id );

		$page = 'testpage_auto';

		$_POST = array(
			'action'       => 'meta-box-order',
			'_ajax_nonce'  => wp_create_nonce( 'meta-box-order' ),
			'page'         => $page,
			'page_columns' => 'auto',
		);

		try {
			$this->_handleAjax( 'meta-box-order' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );

		$saved_columns = get_user_meta( self::$admin_id, "screen_layout_$page", true );
		// In the code: if ( 'auto' !== $page_columns ) { $page_columns = (int) $page_columns; }
		// Then update_user_meta( ..., $page_columns );
		// Since 'auto' === 'auto', it remains 'auto'.
		$this->assertSame( 'auto', $saved_columns );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_meta_box_order_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'meta-box-order',
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'meta-box-order' );
	}

	/**
	 * Tests failure due to invalid page parameter (fails sanitize_key).
	 *
	 * @ticket 65252
	 */
	public function test_meta_box_order_invalid_page(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'meta-box-order',
			'_ajax_nonce' => wp_create_nonce( 'meta-box-order' ),
			'page'        => 'Invalid Page!', // Contains space and exclamation which sanitize_key removes.
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '0' );

		$this->_handleAjax( 'meta-box-order' );
	}

	/**
	 * Tests failure when no user is logged in.
	 *
	 * @ticket 65252
	 */
	public function test_meta_box_order_no_user(): void {
		wp_set_current_user( 0 );

		$_POST = array(
			'action'      => 'meta-box-order',
			'_ajax_nonce' => wp_create_nonce( 'meta-box-order' ),
			'page'        => 'testpage',
		);

		$this->expectException( WPAjaxDieContinueException::class );
		$this->expectExceptionMessage( '' );

		$this->_handleAjax( 'meta-box-order' );
	}
}
