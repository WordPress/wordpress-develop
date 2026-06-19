<?php

/**
 * @group ms-required
 * @group multisite
 *
 * @covers ::wpmu_activate_signup
 */
class Tests_Multisite_wpmuActivateSignup extends WP_UnitTestCase {

	/**
	 * Signs up a user and captures the plain-text activation key and signup ID
	 * via the after_signup_user action (before hashing occurs in the DB).
	 *
	 * @param string $login
	 * @param string $email
	 * @return array{ key: string, signup_id: int }
	 */
	private function signup_user( $login, $email ) {
		$data     = array(
			'key'       => null,
			'signup_id' => null,
		);
		$listener = static function ( $u, $e, $key, $meta, $signup_id ) use ( &$data ) {
			$data['key']       = $key;
			$data['signup_id'] = $signup_id;
		};
		add_filter( 'wpmu_signup_user_notification', '__return_false' );
		add_action( 'after_signup_user', $listener, 10, 5 );
		wpmu_signup_user( $login, $email );
		remove_action( 'after_signup_user', $listener, 10 );
		remove_filter( 'wpmu_signup_user_notification', '__return_false' );
		return $data;
	}

	/**
	 * @ticket 38474
	 */
	public function test_signup_user_stores_hashed_key() {
		global $wpdb;

		$this->signup_user( 'tuser38474a', 'tuser38474a@example.com' );

		$stored = $wpdb->get_var( "SELECT activation_key FROM $wpdb->signups WHERE user_login = 'tuser38474a'" );

		$this->assertStringContainsString( ':', $stored, 'Stored activation key must be in timestamp:hash format.' );
	}

	/**
	 * @ticket 38474
	 */
	public function test_activate_signup_succeeds_with_valid_key_and_signup_id() {
		add_filter( 'wpmu_welcome_user_notification', '__return_false' );

		$data   = $this->signup_user( 'tuser38474b', 'tuser38474b@example.com' );
		$result = wpmu_activate_signup( $data['key'], $data['signup_id'] );

		remove_filter( 'wpmu_welcome_user_notification', '__return_false' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'user_id', $result );
	}

	/**
	 * @ticket 38474
	 */
	public function test_activate_signup_fails_with_wrong_key() {
		$data   = $this->signup_user( 'tuser38474c', 'tuser38474c@example.com' );
		$result = wpmu_activate_signup( 'thisisnottherightkey', $data['signup_id'] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_key', $result->get_error_code() );
	}

	/**
	 * @ticket 38474
	 */
	public function test_activate_signup_fails_with_wrong_signup_id() {
		$data   = $this->signup_user( 'tuser38474d', 'tuser38474d@example.com' );
		$result = wpmu_activate_signup( $data['key'], 0 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_key', $result->get_error_code() );
	}

	/**
	 * Legacy plain-text keys (stored before the hashing upgrade) must still activate
	 * successfully so that users with pending activation emails are not broken by the upgrade.
	 *
	 * @ticket 38474
	 */
	public function test_activate_signup_allows_legacy_plain_text_key() {
		global $wpdb;

		add_filter( 'wpmu_welcome_user_notification', '__return_false' );

		$plain_key = 'abc123legacykey38474';
		$wpdb->insert(
			$wpdb->signups,
			array(
				'domain'         => '',
				'path'           => '',
				'title'          => '',
				'user_login'     => 'legacyuser38474',
				'user_email'     => 'legacy38474@example.com',
				'registered'     => current_time( 'mysql', true ),
				'activation_key' => $plain_key,
				'meta'           => serialize( array() ),
			)
		);
		$signup_id = $wpdb->insert_id;

		$result = wpmu_activate_signup( $plain_key, $signup_id );

		remove_filter( 'wpmu_welcome_user_notification', '__return_false' );

		$this->assertIsArray( $result, 'Legacy plain-text activation keys must still work after upgrade.' );
		$this->assertArrayHasKey( 'user_id', $result );
	}

	/**
	 * A wrong key against a legacy plain-text row must still be rejected.
	 *
	 * @ticket 38474
	 */
	public function test_activate_signup_rejects_wrong_key_against_legacy_row() {
		global $wpdb;

		$plain_key = 'correctlegacykey38474';
		$wpdb->insert(
			$wpdb->signups,
			array(
				'domain'         => '',
				'path'           => '',
				'title'          => '',
				'user_login'     => 'legacyuser38474b',
				'user_email'     => 'legacy38474b@example.com',
				'registered'     => current_time( 'mysql', true ),
				'activation_key' => $plain_key,
				'meta'           => serialize( array() ),
			)
		);
		$signup_id = $wpdb->insert_id;

		$result = wpmu_activate_signup( 'wrongkey', $signup_id );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_key', $result->get_error_code() );
	}

	/**
	 * @ticket 38474
	 */
	public function test_activate_signup_rejects_expired_key() {
		$data = $this->signup_user( 'tuser38474e', 'tuser38474e@example.com' );

		add_filter(
			'activate_signup_expiration',
			static function () {
				return -1;
			}
		);
		$result = wpmu_activate_signup( $data['key'], $data['signup_id'] );
		remove_all_filters( 'activate_signup_expiration' );

		$this->assertWPError( $result );
		$this->assertSame( 'expired_key', $result->get_error_code() );
	}

	/**
	 * @ticket 38474
	 */
	public function test_activate_signup_expiration_filter_is_applied() {
		$data          = $this->signup_user( 'tuser38474f', 'tuser38474f@example.com' );
		$filter_called = false;
		$filter        = static function ( $duration ) use ( &$filter_called ) {
			$filter_called = true;
			return $duration;
		};

		add_filter( 'activate_signup_expiration', $filter );
		wpmu_activate_signup( $data['key'], $data['signup_id'] );
		remove_filter( 'activate_signup_expiration', $filter );

		$this->assertTrue( $filter_called );
	}

	/**
	 * @ticket 38474
	 *
	 * @covers ::wpmu_signup_user_notification
	 */
	public function test_signup_user_notification_includes_signup_id_in_url() {
		$data = $this->signup_user( 'tuser38474g', 'tuser38474g@example.com' );

		$captured = '';
		$capture  = static function ( $args ) use ( &$captured ) {
			$captured = $args['message'];
			return $args;
		};

		add_filter( 'wp_mail', $capture );
		wpmu_signup_user_notification( 'tuser38474g', 'tuser38474g@example.com', $data['key'], array(), $data['signup_id'] );
		remove_filter( 'wp_mail', $capture );

		$this->assertStringContainsString( 'signup_id=' . $data['signup_id'], $captured );
	}
}
