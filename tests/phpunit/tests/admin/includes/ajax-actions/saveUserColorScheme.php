<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_save_user_color_scheme() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.8.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_user_color_scheme
 */
class Tests_wp_ajax_save_user_color_scheme extends WP_Ajax_UnitTestCase {

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
		add_action( 'wp_ajax_save-color-scheme', 'wp_ajax_save_user_color_scheme', 1 );

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
	 * Tests success for wp_ajax_save_user_color_scheme().
	 *
	 * @ticket 65252
	 *
	 * @dataProvider data_save_user_color_scheme_success
	 *
	 * @param string $color_scheme The color scheme to save.
	 */
	public function test_save_user_color_scheme_success( string $color_scheme ): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce']        = wp_create_nonce( 'save-color-scheme' );
		$_POST['color_scheme'] = $color_scheme;

		// Set an initial color scheme to check if it changes correctly.
		update_user_meta( self::$admin_id, 'admin_color', 'fresh' );

		try {
			$this->_handleAjax( 'save-color-scheme' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertSame( 'admin-color-fresh', $response['data']['previousScheme'] );
		$this->assertSame( 'admin-color-' . $color_scheme, $response['data']['currentScheme'] );
		$this->assertSame( $color_scheme, get_user_meta( self::$admin_id, 'admin_color', true ) );
	}

	/**
	 * Data provider for test_save_user_color_scheme_success.
	 *
	 * @return array<string, array{
	 *     color_scheme: string,
	 * }>
	 */
	public function data_save_user_color_scheme_success(): array {
		return array(
			'default scheme' => array(
				'color_scheme' => 'fresh',
			),
			'light scheme'   => array(
				'color_scheme' => 'light',
			),
			'coffee scheme'  => array(
				'color_scheme' => 'coffee',
			),
		);
	}

	/**
	 * Tests failure for wp_ajax_save_user_color_scheme() with invalid color scheme.
	 *
	 * @ticket 65252
	 */
	public function test_save_user_color_scheme_invalid_scheme(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce']        = wp_create_nonce( 'save-color-scheme' );
		$_POST['color_scheme'] = 'non-existent-scheme';

		try {
			$this->_handleAjax( 'save-color-scheme' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful for invalid color scheme' );
	}

	/**
	 * Tests failure for wp_ajax_save_user_color_scheme() with invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_save_user_color_scheme_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce']        = 'invalid-nonce';
		$_POST['color_scheme'] = 'fresh';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'save-color-scheme' );
	}
}
