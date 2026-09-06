<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_parse_embed() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.0.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_parse_embed
 */
class Tests_wp_ajax_parse_embed extends WP_Ajax_UnitTestCase {

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
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id  = $factory->post->create();
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_parse-embed', 'wp_ajax_parse_embed', 1 );

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
	 * Tests success for wp_ajax_parse_embed().
	 *
	 * @ticket 65252
	 */
	public function test_parse_embed_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['shortcode'] = '[embed]https://www.youtube.com/watch?v=dQw4w9WgXcQ[/embed]';

		try {
			$this->_handleAjax( 'parse-embed' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( 'youtube.com/embed', $response['data']['body'] );
	}

	/**
	 * Tests success for wp_ajax_parse_embed() with post_ID.
	 *
	 * @ticket 65252
	 */
	public function test_parse_embed_with_post_id_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['shortcode'] = '[embed]https://www.youtube.com/watch?v=dQw4w9WgXcQ[/embed]';
		$_POST['post_ID']   = self::$post_id;

		try {
			$this->_handleAjax( 'parse-embed' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
	}

	/**
	 * Tests failure with missing shortcode for wp_ajax_parse_embed().
	 *
	 * @ticket 65252
	 */
	public function test_parse_embed_missing_shortcode(): void {
		wp_set_current_user( self::$admin_id );

		try {
			$this->_handleAjax( 'parse-embed' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_parse_embed().
	 *
	 * @ticket 65252
	 */
	public function test_parse_embed_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['shortcode'] = '[embed]https://www.youtube.com/watch?v=dQw4w9WgXcQ[/embed]';

		try {
			$this->_handleAjax( 'parse-embed' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
