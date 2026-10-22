<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_get_revision_diffs() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.6.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_revision_diffs
 */
class Tests_wp_ajax_get_revision_diffs extends WP_Ajax_UnitTestCase {

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
	 * Revision IDs.
	 *
	 * @var int[]
	 */
	protected static $revision_ids;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$post_id = $factory->post->create(
			array(
				'post_title'   => 'Initial Title',
				'post_content' => 'Initial Content',
			)
		);

		// Create revisions.
		wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_title'   => 'Updated Title 1',
				'post_content' => 'Updated Content 1',
			)
		);

		wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_title'   => 'Updated Title 2',
				'post_content' => 'Updated Content 2',
			)
		);

		self::$revision_ids = array_values(
			wp_get_post_revisions(
				self::$post_id,
				array(
					'fields' => 'ids',
				)
			)
		);
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_get-revision-diffs', 'wp_ajax_get_revision_diffs', 1 );

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
	 * Tests success for wp_ajax_get_revision_diffs().
	 *
	 * @ticket 65252
	 */
	public function test_get_revision_diffs_success(): void {
		wp_set_current_user( self::$admin_id );

		$compare_key = self::$revision_ids[1] . ':' . self::$revision_ids[0];

		$_POST['post_id'] = self::$post_id;
		$_POST['compare'] = array( $compare_key );
		$_REQUEST         = array_merge( $_REQUEST, $_POST );

		try {
			$this->_handleAjax( 'get-revision-diffs' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertCount( 1, $response['data'] );
		$this->assertEquals( $compare_key, $response['data'][0]['id'] );
		$this->assertArrayHasKey( 'fields', $response['data'][0] );
	}

	/**
	 * Tests failure with non-existent post for wp_ajax_get_revision_diffs().
	 *
	 * @ticket 65252
	 */
	public function test_get_revision_diffs_invalid_post(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['post_id'] = 999999;
		$_POST['compare'] = array( '1:2' );
		$_REQUEST         = array_merge( $_REQUEST, $_POST );

		try {
			$this->_handleAjax( 'get-revision-diffs' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_get_revision_diffs().
	 *
	 * @ticket 65252
	 */
	public function test_get_revision_diffs_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['post_id'] = self::$post_id;
		$_POST['compare'] = array( self::$revision_ids[1] . ':' . self::$revision_ids[0] );
		$_REQUEST         = array_merge( $_REQUEST, $_POST );

		try {
			$this->_handleAjax( 'get-revision-diffs' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
