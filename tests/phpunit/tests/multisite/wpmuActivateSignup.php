<?php

/**
 * @group ms-required
 * @group multisite
 *
 * @covers ::wpmu_activate_signup
 */
class Tests_Multisite_wpmuActivateSignup extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	public function tear_down() {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	/**
	 * Returns a domain and path for a new site on the current network.
	 *
	 * @param string $slug Site slug.
	 * @return array{0: string, 1: string} Domain and path.
	 */
	private function get_site_address( $slug ) {
		$network = get_network();

		if ( is_subdomain_install() ) {
			return array( $slug . '.' . preg_replace( '|^www\.|', '', $network->domain ), $network->path );
		}

		return array( $network->domain, $network->path . $slug . '/' );
	}

	/**
	 * Creates a site signup and returns its activation key.
	 *
	 * @param string $user_login User login.
	 * @param string $user_email User email.
	 * @param string $slug       Site slug.
	 * @return string Activation key.
	 */
	private function create_site_signup( $user_login, $user_email, $slug ) {
		global $wpdb;

		list( $domain, $path ) = $this->get_site_address( $slug );
		wpmu_signup_blog( $domain, $path, 'Test Site', $user_login, $user_email );

		return $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM $wpdb->signups WHERE user_login = %s", $user_login ) );
	}

	/**
	 * Returns the body of the last email sent to an address, or an empty string.
	 *
	 * @param string $email Recipient address.
	 * @return string
	 */
	private function get_last_email_body( $email ) {
		$mailer = tests_retrieve_phpmailer_instance();
		$body   = '';

		foreach ( $mailer->mock_sent as $sent ) {
			if ( isset( $sent['to'][0][0] ) && $email === $sent['to'][0][0] ) {
				$body = $sent['body'];
			}
		}

		return $body;
	}

	/**
	 * @ticket 66193
	 */
	public function test_should_return_working_password_for_new_user_with_site() {
		$key    = $this->create_site_signup( 'newsiteuser', 'newsiteuser@example.org', 'newsiteusersite' );
		$result = wpmu_activate_signup( $key );

		$this->assertIsArray( $result );

		$user = get_userdata( $result['user_id'] );
		$this->assertTrue( wp_check_password( $result['password'], $user->user_pass, $user->ID ) );
	}

	/**
	 * @ticket 66193
	 */
	public function test_should_not_report_new_password_when_user_already_exists() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'existingsignupuser',
				'user_email' => 'existingsignupuser@example.org',
				'user_pass'  => 'original-password',
			)
		);

		$key    = $this->create_site_signup( 'existingsignupuser', 'existingsignupuser@example.org', 'existingsignupusersite' );
		$result = wpmu_activate_signup( $key );

		$this->assertIsArray( $result );
		$this->assertSame( $user_id, (int) $result['user_id'] );
		$this->assertSame( 'N/A', $result['password'], 'A password that was never set on the account should not be returned.' );

		$user = get_userdata( $user_id );
		$this->assertTrue( wp_check_password( 'original-password', $user->user_pass, $user->ID ), 'The existing password should be unchanged.' );
	}

	/**
	 * @ticket 66193
	 */
	public function test_should_not_report_new_password_on_retry_after_site_creation_failure() {
		$key = $this->create_site_signup( 'retrysignupuser', 'retrysignupuser@example.org', 'retrysignupusersite' );

		$fail_site_creation = static function ( $errors ) {
			$errors->add( 'simulated_failure', 'Simulated site creation failure.' );
		};

		add_action( 'wp_validate_site_data', $fail_site_creation );
		$first = wpmu_activate_signup( $key );
		remove_action( 'wp_validate_site_data', $fail_site_creation );

		$this->assertWPError( $first );
		$this->assertNotFalse( username_exists( 'retrysignupuser' ), 'The first attempt should have created the user.' );

		reset_phpmailer_instance();
		$retry = wpmu_activate_signup( $key );

		$this->assertIsArray( $retry );
		$this->assertSame( 'N/A', $retry['password'] );
		$this->assertStringContainsString( 'Password: N/A', $this->get_last_email_body( 'retrysignupuser@example.org' ), 'The welcome email should not contain a generated password.' );
	}
}
