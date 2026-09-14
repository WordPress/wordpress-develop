<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_save_attachment_order() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_attachment_order
 */
class Tests_wp_ajax_save_attachment_order extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Attachment IDs.
	 *
	 * @var int[]
	 */
	protected static $attachment_ids;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$post_id = $factory->post->create();

		self::$attachment_ids = array(
			$factory->attachment->create_object(
				array(
					'file'        => 'test1.jpg',
					'post_parent' => self::$post_id,
					'menu_order'  => 0,
				)
			),
			$factory->attachment->create_object(
				array(
					'file'        => 'test2.jpg',
					'post_parent' => self::$post_id,
					'menu_order'  => 0,
				)
			),
		);
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_save-attachment-order', 'wp_ajax_save_attachment_order', 1 );

		// Hook into wp_die to prevent execution from stopping.
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
	}

	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
		parent::tear_down();
	}

	/**
	 * Returns our custom die handler.
	 *
	 * @return callable
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Custom die handler that throws an exception.
	 *
	 * @param string|WP_Error $message
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				$this->_last_response = (string) $message;
			} else {
				$this->_last_response = '0';
			}
		}

		if ( '-1' === $this->_last_response || ( is_int( $message ) && -1 === $message ) ) {
			throw new WPAjaxDieStopException( $this->_last_response );
		}

		throw new WPAjaxDieContinueException( $this->_last_response );
	}

	/**
	 * Tests success for wp_ajax_save_attachment_order().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_order_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['post_id']     = self::$post_id;
		$_POST['nonce']       = wp_create_nonce( 'update-post_' . self::$post_id );
		$_POST['attachments'] = array(
			self::$attachment_ids[0] => 10,
			self::$attachment_ids[1] => 20,
		);

		try {
			$this->_handleAjax( 'save-attachment-order' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );

		$this->assertEquals( 10, get_post( self::$attachment_ids[0] )->menu_order, 'First attachment menu_order should be 10' );
		$this->assertEquals( 20, get_post( self::$attachment_ids[1] )->menu_order, 'Second attachment menu_order should be 20' );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_save_attachment_order().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_order_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['post_id']     = self::$post_id;
		$_POST['nonce']       = 'invalid-nonce';
		$_POST['attachments'] = array(
			self::$attachment_ids[0] => 10,
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'save-attachment-order' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_save_attachment_order().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_order_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['post_id']     = self::$post_id;
		$_POST['nonce']       = wp_create_nonce( 'update-post_' . self::$post_id );
		$_POST['attachments'] = array(
			self::$attachment_ids[0] => 10,
		);

		try {
			$this->_handleAjax( 'save-attachment-order' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}

	/**
	 * Tests failure with missing parameters for wp_ajax_save_attachment_order().
	 *
	 * @ticket 65252
	 *
	 * @dataProvider data_missing_parameters
	 *
	 * @param array $post_data POST data.
	 */
	public function test_save_attachment_order_missing_parameters( array $post_data ): void {
		wp_set_current_user( self::$admin_id );

		$_POST = $post_data;

		try {
			$this->_handleAjax( 'save-attachment-order' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}

	/**
	 * Data provider for missing parameters.
	 *
	 * @return array
	 */
	public function data_missing_parameters(): array {
		return array(
			'missing post_id'     => array(
				array(
					'attachments' => array( 1 => 10 ),
				),
			),
			'invalid post_id'     => array(
				array(
					'post_id'     => 0,
					'attachments' => array( 1 => 10 ),
				),
			),
			'missing attachments' => array(
				array(
					'post_id' => 1,
				),
			),
			'empty attachments'   => array(
				array(
					'post_id'     => 1,
					'attachments' => array(),
				),
			),
		);
	}
}
