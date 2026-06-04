<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_set_post_thumbnail() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_set_post_thumbnail
 */
class Tests_wp_ajax_set_post_thumbnail extends WP_Ajax_UnitTestCase {

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
	 * Tests successful setting of the featured image.
	 *
	 * @ticket 65252
	 */
	public function test_set_post_thumbnail_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'set-post-thumbnail',
			'post_id'      => self::$post_id,
			'thumbnail_id' => self::$thumbnail_id,
			'_ajax_nonce'  => wp_create_nonce( 'set_post_thumbnail-' . self::$post_id ),
		);

		try {
			$this->_handleAjax( 'set-post-thumbnail' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertStringContainsString( 'id="set-post-thumbnail"', $this->_last_response );
			$this->assertStringContainsString( 'value="' . self::$thumbnail_id . '"', $this->_last_response );
		}

		$this->assertSame( (string) self::$thumbnail_id, get_post_meta( self::$post_id, '_thumbnail_id', true ) );
	}

	/**
	 * Tests successful setting of the featured image with JSON request.
	 *
	 * @ticket 65252
	 */
	public function test_set_post_thumbnail_json_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'set-post-thumbnail',
			'post_id'      => self::$post_id,
			'thumbnail_id' => self::$thumbnail_id,
			'json'         => 1,
			'_ajax_nonce'  => wp_create_nonce( 'update-post_' . self::$post_id ),
		);

		try {
			$this->_handleAjax( 'set-post-thumbnail' );
		} catch ( WPAjaxDieContinueException $e ) {
			// In case of wp_send_json_success, the output is in $this->_last_response.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Response should be a valid JSON array. Response was: ' . $this->_last_response );
		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'id="set-post-thumbnail"', $response['data'] );
		$this->assertSame( (string) self::$thumbnail_id, get_post_meta( self::$post_id, '_thumbnail_id', true ) );
	}

	/**
	 * Tests successful removal of the featured image.
	 *
	 * @ticket 65252
	 */
	public function test_set_post_thumbnail_remove_success(): void {
		wp_set_current_user( self::$admin_id );
		set_post_thumbnail( self::$post_id, self::$thumbnail_id );

		$_POST = array(
			'action'       => 'set-post-thumbnail',
			'post_id'      => self::$post_id,
			'thumbnail_id' => -1,
			'_ajax_nonce'  => wp_create_nonce( 'set_post_thumbnail-' . self::$post_id ),
		);

		try {
			$this->_handleAjax( 'set-post-thumbnail' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertStringContainsString( 'id="set-post-thumbnail"', $this->_last_response );
			$this->assertStringContainsString( 'value="-1"', $this->_last_response );
		}

		$this->assertEmpty( get_post_meta( self::$post_id, '_thumbnail_id', true ) );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_set_post_thumbnail_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'       => 'set-post-thumbnail',
			'post_id'      => self::$post_id,
			'thumbnail_id' => self::$thumbnail_id,
			'_ajax_nonce'  => 'invalid-nonce',
		);

		try {
			$this->_handleAjax( 'set-post-thumbnail' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_set_post_thumbnail_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST = array(
			'action'       => 'set-post-thumbnail',
			'post_id'      => self::$post_id,
			'thumbnail_id' => self::$thumbnail_id,
			'_ajax_nonce'  => wp_create_nonce( 'set_post_thumbnail-' . self::$post_id ),
		);

		try {
			$this->_handleAjax( 'set-post-thumbnail' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}
}
