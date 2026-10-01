<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_get_post_thumbnail_html() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.6.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_post_thumbnail_html
 */
class Tests_wp_ajax_get_post_thumbnail_html extends WP_Ajax_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Thumbnail ID.
	 *
	 * @var int
	 */
	protected static $thumbnail_id;

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
		self::$admin_id     = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id      = $factory->post->create();
		self::$thumbnail_id = $factory->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
	}

	/**
	 * Set up the test fixture.
	 * Override wp_die().
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );
	}

	/**
	 * Tear down the test fixture.
	 */
	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );
		parent::tear_down();
	}

	/**
	 * Return our callback handler
	 *
	 * @return callback
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Handler for wp_die()
	 * Don't die, just throw an exception.
	 *
	 * @param string|WP_Error $message
	 * @param string          $title
	 * @param array           $args
	 * @throws WPAjaxDieStopException
	 * @throws WPAjaxDieContinueException
	 */
	public function dieHandler( $message, $title = '', $args = array() ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				$this->_last_response = (string) $message;
			} else {
				$this->_last_response = '0';
			}
		}

		throw new WPAjaxDieContinueException( $this->_last_response );
	}

	/**
	 * Tests successful retrieval of the featured image HTML.
	 *
	 * @ticket 65252
	 */
	public function test_get_post_thumbnail_html_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'get-post-thumbnail-html',
			'post_id'      => self::$post_id,
			'thumbnail_id' => self::$thumbnail_id,
			'_ajax_nonce'  => wp_create_nonce( 'update-post_' . self::$post_id ),
		);

		try {
			$this->_handleAjax( 'get-post-thumbnail-html' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'id="set-post-thumbnail"', $response['data'] );
		$this->assertStringContainsString( 'value="' . self::$thumbnail_id . '"', $response['data'] );
	}

	/**
	 * Tests successful retrieval of the featured image HTML when no thumbnail is provided.
	 *
	 * @ticket 65252
	 */
	public function test_get_post_thumbnail_html_no_thumbnail_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'get-post-thumbnail-html',
			'post_id'      => self::$post_id,
			'thumbnail_id' => -1,
			'_ajax_nonce'  => wp_create_nonce( 'update-post_' . self::$post_id ),
		);

		try {
			$this->_handleAjax( 'get-post-thumbnail-html' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'id="set-post-thumbnail"', $response['data'] );
		$this->assertStringContainsString( 'value="-1"', $response['data'] );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_get_post_thumbnail_html_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'get-post-thumbnail-html',
			'post_id'      => self::$post_id,
			'thumbnail_id' => self::$thumbnail_id,
			'_ajax_nonce'  => 'invalid-nonce',
		);

		try {
			$this->_handleAjax( 'get-post-thumbnail-html' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_get_post_thumbnail_html_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST = array(
			'action'       => 'get-post-thumbnail-html',
			'post_id'      => self::$post_id,
			'thumbnail_id' => self::$thumbnail_id,
			'_ajax_nonce'  => wp_create_nonce( 'update-post_' . self::$post_id ),
		);

		try {
			$this->_handleAjax( 'get-post-thumbnail-html' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}
}
