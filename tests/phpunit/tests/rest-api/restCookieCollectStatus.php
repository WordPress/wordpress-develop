<?php

/**
 * Tests for rest_cookie_collect_status().
 *
 * @group restapi
 *
 * @covers ::rest_cookie_collect_status
 */
class Tests_REST_API_RestCookieCollectStatus extends WP_UnitTestCase {

	public function tear_down() {
		unset( $GLOBALS['wp_rest_auth_cookie'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_sets_true_when_auth_cookie_valid() {
		do_action( 'auth_cookie_valid' );

		$this->assertTrue( $GLOBALS['wp_rest_auth_cookie'] );
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_auth_cookie_failure_actions
	 *
	 * @param string $action   Auth cookie action name.
	 * @param string $expected Stored failure status.
	 */
	public function test_stores_failure_status_from_action( $action, $expected ) {
		do_action( $action );

		$this->assertSame( $expected, $GLOBALS['wp_rest_auth_cookie'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_auth_cookie_failure_actions() {
		return array(
			'malformed'    => array( 'auth_cookie_malformed', 'malformed' ),
			'expired'      => array( 'auth_cookie_expired', 'expired' ),
			'bad username' => array( 'auth_cookie_bad_username', 'bad_username' ),
			'bad hash'     => array( 'auth_cookie_bad_hash', 'bad_hash' ),
		);
	}
}
