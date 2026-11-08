<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_get_attachment() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_attachment
 */
class Tests_wp_ajax_get_attachment extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Attachment',
			)
		);

		// Ensure the file exists so wp_prepare_attachment_for_js doesn't fail on some checks.
		$file = get_attached_file( self::$attachment_id );
		if ( ! file_exists( dirname( $file ) ) ) {
			wp_mkdir_p( dirname( $file ) );
		}
		touch( $file );
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_get-attachment', 'wp_ajax_get_attachment', 1 );
	}

	/**
	 * Tests success with valid ID.
	 *
	 * @ticket 65252
	 */
	public function test_get_attachment_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['id'] = self::$attachment_id;

		try {
			$this->_handleAjax( 'get-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertSame( self::$attachment_id, $response['data']['id'], 'Attachment ID should match' );
		$this->assertSame( 'Test Attachment', $response['data']['title'], 'Attachment title should match' );
	}

	/**
	 * Tests failure with missing ID.
	 *
	 * @ticket 65252
	 */
	public function test_get_attachment_missing_id(): void {
		wp_set_current_user( self::$admin_id );

		unset( $_REQUEST['id'] );

		try {
			$this->_handleAjax( 'get-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be a failure' );
	}

	/**
	 * Tests failure with invalid ID.
	 *
	 * @ticket 65252
	 */
	public function test_get_attachment_invalid_id(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['id'] = 99999;

		try {
			$this->_handleAjax( 'get-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be a failure' );
	}

	/**
	 * Tests failure with wrong post type.
	 *
	 * @ticket 65252
	 */
	public function test_get_attachment_wrong_post_type(): void {
		wp_set_current_user( self::$admin_id );

		$post_id     = self::factory()->post->create();
		$_POST['id'] = $post_id;

		try {
			$this->_handleAjax( 'get-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be a failure' );
	}

	/**
	 * Tests failure with insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_get_attachment_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['id'] = self::$attachment_id;

		try {
			$this->_handleAjax( 'get-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be a failure' );
	}
}
