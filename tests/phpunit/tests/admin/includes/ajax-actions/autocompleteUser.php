<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_autocomplete_user() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_autocomplete_user
 */
class Tests_wp_ajax_autocomplete_user extends WP_Ajax_UnitTestCase {

	/**
	 * User ID of a test user.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Setup test fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$user_id = $factory->user->create(
			array(
				'user_login' => 'testuser',
				'user_email' => 'testuser@example.com',
				'first_name' => 'Test',
				'last_name'  => 'User',
			)
		);
	}

	/**
	 * Tests user autocomplete via AJAX.
	 *
	 * @ticket 65252
	 *
	 * @dataProvider data_autocomplete_user
	 *
	 * @param array $request_params Request parameters.
	 * @param array $expected       Expected results.
	 * @param bool  $is_member      Whether the user should be a member of the blog.
	 */
	public function test_autocomplete_user( array $request_params, array $expected, bool $is_member = true ): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'wp_ajax_autocomplete_user() is multisite only.' );
		}

		// Mock the user with necessary capabilities.
		$this->_setRole( 'administrator' );
		wp_set_current_user( self::$user_id );
		grant_super_admin( self::$user_id );

		if ( ! $is_member ) {
			remove_user_from_blog( self::$user_id, get_current_blog_id() );
		} else {
			add_user_to_blog( get_current_blog_id(), self::$user_id, 'subscriber' );
		}

		$_GET     = array_merge( $_GET, $request_params );
		$_POST    = array_merge( $_POST, $request_params );
		$_REQUEST = array_merge( $_REQUEST, $request_params );

		try {
			add_action( 'admin_init', 'wp_ajax_autocomplete_user', 1 );
			$this->_handleAjax( 'autocomplete_user' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = (string) $e->getMessage();
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response, 'Response should be a JSON array' );

		foreach ( $expected as $index => $expected_item ) {
			$this->assertArrayHasKey( $index, $response, "Response should have index $index" );
			$this->assertSame( $expected_item['label'], $response[ $index ]['label'], "Label mismatch at index $index" );
			$this->assertSame( $expected_item['value'], $response[ $index ]['value'], "Value mismatch at index $index" );
		}
	}

	/**
	 * Data provider for test_autocomplete_user.
	 *
	 * @return array<string, array{
	 *     request_params: array,
	 *     expected: array,
	 *     is_member?: bool,
	 * }>
	 */
	public function data_autocomplete_user(): array {
		return array(
			'search by login'                   => array(
				'request_params' => array(
					'term'              => 'testuser',
					'autocomplete_type' => 'search',
				),
				'expected'       => array(
					array(
						'label' => 'testuser (testuser@example.com)',
						'value' => 'testuser',
					),
				),
			),
			'add by login (exclude existing)'   => array(
				'request_params' => array(
					'term'              => 'testuser',
					'autocomplete_type' => 'add',
				),
				'expected'       => array(),
			),
			'add by login (include non-member)' => array(
				'request_params' => array(
					'term'              => 'testuser',
					'autocomplete_type' => 'add',
				),
				'expected'       => array(
					array(
						'label' => 'testuser (testuser@example.com)',
						'value' => 'testuser',
					),
				),
				'is_member'      => false,
			),
			'search by email'                   => array(
				'request_params' => array(
					'term'               => 'testuser@example.com',
					'autocomplete_type'  => 'search',
					'autocomplete_field' => 'user_email',
				),
				'expected'       => array(
					array(
						'label' => 'testuser (testuser@example.com)',
						'value' => 'testuser@example.com',
					),
				),
			),
		);
	}
}
