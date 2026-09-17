<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_send_link_to_editor() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_send_link_to_editor
 */
class Tests_wp_ajax_send_link_to_editor extends WP_Ajax_UnitTestCase {

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

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_send-link-to-editor', 'wp_ajax_send_link_to_editor', 1 );

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
	 * Tests success for wp_ajax_send_link_to_editor() with a regular link.
	 *
	 * @ticket 65252
	 */
	public function test_send_link_to_editor_regular_link_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce']     = wp_create_nonce( 'media-send-to-editor' );
		$_POST['src']       = 'http://example.com/test.txt';
		$_POST['link_text'] = 'Example Text';

		try {
			$this->_handleAjax( 'send-link-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( 'href="http://example.com/test.txt"', $response['data'] );
		$this->assertStringContainsString( 'Example Text', $response['data'] );
	}

	/**
	 * Tests success for wp_ajax_send_link_to_editor() with an embeddable link.
	 *
	 * @ticket 65252
	 */
	public function test_send_link_to_editor_embed_link_success(): void {
		wp_set_current_user( self::$admin_id );

		// YouTube is a default oEmbed provider.
		$_POST['nonce']     = wp_create_nonce( 'media-send-to-editor' );
		$_POST['src']       = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
		$_POST['link_text'] = 'Rickroll';

		try {
			$this->_handleAjax( 'send-link-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( '[embed]', $response['data'] );
		$this->assertStringContainsString( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $response['data'] );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_send_link_to_editor().
	 *
	 * @ticket 65252
	 */
	public function test_send_link_to_editor_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce'] = 'invalid-nonce';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'send-link-to-editor' );
	}

	/**
	 * Tests failure with missing source for wp_ajax_send_link_to_editor().
	 *
	 * @ticket 65252
	 */
	public function test_send_link_to_editor_missing_src(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce'] = wp_create_nonce( 'media-send-to-editor' );
		$_POST['src']   = '';

		try {
			$this->_handleAjax( 'send-link-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
