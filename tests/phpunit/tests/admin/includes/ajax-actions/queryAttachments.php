<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_query_attachments() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_query_attachments
 */
class Tests_wp_ajax_query_attachments extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Attachment IDs.
	 *
	 * @var int[]
	 */
	protected static $attachment_ids = array();

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$attachment_ids[] = $factory->attachment->create_object(
			array(
				'file'           => 'test1.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Attachment 1',
			)
		);

		self::$attachment_ids[] = $factory->attachment->create_object(
			array(
				'file'           => 'test2.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Searchable Attachment',
			)
		);

		foreach ( self::$attachment_ids as $id ) {
			$file = get_attached_file( $id );
			if ( ! file_exists( dirname( $file ) ) ) {
				wp_mkdir_p( dirname( $file ) );
			}
			touch( $file );
		}
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_query-attachments', 'wp_ajax_query_attachments', 1 );
	}

	/**
	 * Tests success with default query.
	 *
	 * @ticket 65252
	 */
	public function test_query_attachments_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['query'] = array();

		try {
			$this->_handleAjax( 'query-attachments' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertIsArray( $response['data'], 'Response data should be an array' );

		$found_ids = wp_list_pluck( $response['data'], 'id' );
		foreach ( self::$attachment_ids as $id ) {
			$this->assertContains( $id, $found_ids, "Response should contain attachment $id" );
		}
	}

	/**
	 * Tests success with search term.
	 *
	 * @ticket 65252
	 */
	public function test_query_attachments_search(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['query'] = array(
			's' => 'Searchable',
		);

		try {
			$this->_handleAjax( 'query-attachments' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );

		$found_ids = wp_list_pluck( $response['data'], 'id' );
		$this->assertContains( self::$attachment_ids[1], $found_ids, 'Response should contain the searchable attachment' );
		$this->assertNotContains( self::$attachment_ids[0], $found_ids, 'Response should not contain the non-matching attachment' );
	}

	/**
	 * Tests failure with insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_query_attachments_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['query'] = array();

		try {
			$this->_handleAjax( 'query-attachments' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be a failure' );
	}
}
