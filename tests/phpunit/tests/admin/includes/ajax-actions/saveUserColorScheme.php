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
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_user_color_scheme
 */
class Tests_Admin_Includes_AjaxActions_saveUserColorScheme extends WP_Ajax_UnitTestCase {

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
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
		update_user_meta( self::$admin_id, 'admin_color', 'fresh' );
	}

	/**
	 * @ticket 65252
	 *
	 * @dataProvider data_color_schemes
	 *
	 * @param string $color_scheme Posted color scheme.
	 * @param string $expected     Expected saved color scheme.
	 */
	public function test_save_user_color_scheme_saves_scheme( string $color_scheme, string $expected ): void {
		$_POST = array(
			'nonce'        => wp_create_nonce( 'save-color-scheme' ),
			'color_scheme' => $color_scheme,
		);

		$response = json_decode( $this->get_ajax_response( 'save-user-color-scheme' ), true );

		$this->assertSame(
			array(
				'success' => true,
				'data'    => array(
					'previousScheme' => 'admin-color-fresh',
					'currentScheme'  => 'admin-color-' . $expected,
				),
			),
			$response
		);
		$this->assertSame( $expected, get_user_meta( self::$admin_id, 'admin_color', true ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: string }>
	 */
	public static function data_color_schemes(): array {
		return array(
			'light'                  => array( 'light', 'light' ),
			'coffee'                 => array( 'coffee', 'coffee' ),
			'same as current'        => array( 'fresh', 'fresh' ),
			'uppercase is sanitized' => array( 'Midnight', 'midnight' ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_user_color_scheme_rejects_unregistered_scheme(): void {
		$_POST = array(
			'nonce'        => wp_create_nonce( 'save-color-scheme' ),
			'color_scheme' => 'non-existent-scheme',
		);

		$this->assertSame( array( 'success' => false ), json_decode( $this->get_ajax_response( 'save-user-color-scheme' ), true ) );
		$this->assertSame( 'fresh', get_user_meta( self::$admin_id, 'admin_color', true ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_user_color_scheme_rejects_invalid_nonce(): void {
		$_POST = array(
			'nonce'        => 'invalid-nonce',
			'color_scheme' => 'light',
		);

		$this->assertSame( '-1', $this->get_ajax_response( 'save-user-color-scheme' ) );
		$this->assertSame( 'fresh', get_user_meta( self::$admin_id, 'admin_color', true ) );
	}
}
