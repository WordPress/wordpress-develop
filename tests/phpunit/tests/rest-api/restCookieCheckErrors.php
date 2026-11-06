<?php

/**
 * Tests for rest_cookie_check_errors().
 *
 * @group restapi
 *
 * @covers ::rest_cookie_check_errors
 */
class Tests_REST_API_RestCookieCheckErrors extends WP_UnitTestCase {

	public function tear_down() {
		$GLOBALS['wp_rest_server'] = null;
		unset( $GLOBALS['wp_rest_auth_cookie'] );
		unset( $_SERVER['HTTP_X_WP_NONCE'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_existing_result_unchanged() {
		$result = new WP_Error( 'existing', 'Existing error' );

		$this->assertSame( $result, rest_cookie_check_errors( $result ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_and_clears_user_when_nonce_missing() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$GLOBALS['wp_rest_auth_cookie'] = true;

		$this->assertTrue( rest_cookie_check_errors( null ) );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_nonce_sources
	 *
	 * @param string $source Where the nonce is provided from.
	 */
	public function test_returns_error_for_invalid_nonce( $source ) {
		$GLOBALS['wp_rest_auth_cookie'] = true;
		$this->set_nonce_source( $source, 'invalid-nonce' );

		$result = rest_cookie_check_errors( null );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_cookie_invalid_nonce', $result->get_error_code() );
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_nonce_sources
	 *
	 * @param string $source Where the nonce is provided from.
	 */
	public function test_returns_true_for_valid_nonce( $source ) {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$GLOBALS['wp_rest_auth_cookie'] = true;
		$this->set_nonce_source( $source, wp_create_nonce( 'wp_rest' ) );

		$this->assertTrue( rest_cookie_check_errors( null ) );
		$this->assertSame( $user_id, get_current_user_id() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_skips_check_when_another_auth_method_is_in_use() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$GLOBALS['wp_rest_auth_cookie'] = 'malformed';

		$this->assertNull( rest_cookie_check_errors( null ) );
		$this->assertSame( $user_id, get_current_user_id() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_nonce_sources() {
		return array(
			'request param' => array( 'request' ),
			'header'        => array( 'header' ),
		);
	}

	/**
	 * Sets the nonce on the requested source.
	 *
	 * @param string $source Nonce source: 'request' or 'header'.
	 * @param string $nonce  Nonce value.
	 */
	private function set_nonce_source( $source, $nonce ) {
		if ( 'header' === $source ) {
			$_SERVER['HTTP_X_WP_NONCE'] = $nonce;
			return;
		}

		$_REQUEST['_wpnonce'] = $nonce;
	}
}
