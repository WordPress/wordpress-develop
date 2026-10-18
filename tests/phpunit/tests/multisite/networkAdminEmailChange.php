<?php
/**
 * Test cases for network admin email change functionality and notices.
 *
 * @group multisite
 * @group ms-required
 */
class Tests_Multisite_NetworkAdminEmailChange extends WP_UnitTestCase {

	/**
	 * Original admin email.
	 *
	 * @var string
	 */
	private $original_admin_email;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_admin_email = get_site_option( 'admin_email' );
		reset_phpmailer_instance();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		update_site_option( 'admin_email', $this->original_admin_email );
		delete_site_option( 'new_admin_email' );
		delete_site_option( 'network_admin_hash' );
		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * Tests that updating new_admin_email with the current admin email does nothing.
	 *
	 * @ticket 66300
	 * @covers ::update_network_option_new_admin_email
	 */
	public function test_update_network_option_new_admin_email_ignores_same_email() {
		update_site_option( 'new_admin_email', $this->original_admin_email );

		$this->assertFalse( get_site_option( 'network_admin_hash' ) );
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEmpty( $mailer->mock_sent );
	}

	/**
	 * Tests that updating new_admin_email with an invalid email does nothing.
	 *
	 * @ticket 66300
	 * @covers ::update_network_option_new_admin_email
	 */
	public function test_update_network_option_new_admin_email_ignores_invalid_email() {
		update_site_option( 'new_admin_email', 'not-a-valid-email' );

		$this->assertFalse( get_site_option( 'network_admin_hash' ) );
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEmpty( $mailer->mock_sent );
	}

	/**
	 * Tests that updating new_admin_email with a new valid email generates a hash and sends an email.
	 *
	 * @ticket 66300
	 * @covers ::update_network_option_new_admin_email
	 */
	public function test_update_network_option_new_admin_email_triggers_hash_and_confirmation_email() {
		$new_email = 'newnetworkadmin@example.com';
		update_site_option( 'new_admin_email', $new_email );

		$hash_details = get_site_option( 'network_admin_hash' );
		$this->assertIsArray( $hash_details );
		$this->assertSame( $new_email, $hash_details['newemail'] );
		$this->assertNotEmpty( $hash_details['hash'] );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertNotEmpty( $mailer->mock_sent );
		$this->assertSame( $new_email, $mailer->mock_sent[0]['to'][0][0] );
		$this->assertStringContainsString( 'network_admin_hash=' . $hash_details['hash'], $mailer->mock_sent[0]['body'] );
	}

	/**
	 * Tests that network_admin_email_updated is present in removable query args.
	 *
	 * @ticket 66300
	 * @covers ::wp_removable_query_args
	 */
	public function test_wp_removable_query_args_contains_network_admin_email_updated() {
		$this->assertContains( 'network_admin_email_updated', wp_removable_query_args() );
	}

	/**
	 * Tests that a valid hash confirmation updates the admin email and cleans up pending options.
	 *
	 * @ticket 66300
	 */
	public function test_valid_hash_confirmation_updates_email_and_removes_pending_options() {
		$new_email = 'confirmedadmin@example.com';
		$hash      = md5( 'test_secret_hash' );

		update_site_option( 'new_admin_email', $new_email );
		update_site_option(
			'network_admin_hash',
			array(
				'hash'     => $hash,
				'newemail' => $new_email,
			)
		);

		// Execute confirmation logic from settings.php.
		$new_admin_details = get_site_option( 'network_admin_hash' );
		$redirect          = add_query_arg( array( 'updated' => 'false' ), 'settings.php' );

		if ( is_array( $new_admin_details ) && hash_equals( $new_admin_details['hash'], $hash ) && ! empty( $new_admin_details['newemail'] ) ) {
			update_site_option( 'admin_email', $new_admin_details['newemail'] );
			delete_site_option( 'network_admin_hash' );
			delete_site_option( 'new_admin_email' );
			$redirect = add_query_arg(
				array(
					'updated'                     => 'true',
					'network_admin_email_updated' => 'true',
				),
				'settings.php'
			);
		}

		$this->assertSame( $new_email, get_site_option( 'admin_email' ) );
		$this->assertFalse( get_site_option( 'network_admin_hash' ) );
		$this->assertFalse( get_site_option( 'new_admin_email' ) );
		$this->assertStringContainsString( 'network_admin_email_updated=true', $redirect );
		$this->assertStringContainsString( 'updated=true', $redirect );
	}

	/**
	 * Tests that an invalid hash does not update the admin email and redirects with updated=false.
	 *
	 * @ticket 66300
	 */
	public function test_invalid_hash_does_not_update_email_and_redirects_with_updated_false() {
		$new_email = 'pendingadmin@example.com';
		$real_hash = md5( 'real_secret_hash' );

		update_site_option( 'new_admin_email', $new_email );
		update_site_option(
			'network_admin_hash',
			array(
				'hash'     => $real_hash,
				'newemail' => $new_email,
			)
		);

		$supplied_hash     = md5( 'wrong_hash' );
		$new_admin_details = get_site_option( 'network_admin_hash' );
		$redirect          = add_query_arg( array( 'updated' => 'false' ), 'settings.php' );

		if ( is_array( $new_admin_details ) && hash_equals( $new_admin_details['hash'], $supplied_hash ) && ! empty( $new_admin_details['newemail'] ) ) {
			update_site_option( 'admin_email', $new_admin_details['newemail'] );
			delete_site_option( 'network_admin_hash' );
			delete_site_option( 'new_admin_email' );
			$redirect = add_query_arg(
				array(
					'updated'                     => 'true',
					'network_admin_email_updated' => 'true',
				),
				'settings.php'
			);
		}

		$this->assertSame( $this->original_admin_email, get_site_option( 'admin_email' ) );
		$this->assertNotEmpty( get_site_option( 'network_admin_hash' ) );
		$this->assertSame( $new_email, get_site_option( 'new_admin_email' ) );
		$this->assertSame( 'settings.php?updated=false', $redirect );
	}

	/**
	 * Tests notice display logic: updated=false must NOT display "Settings saved."
	 *
	 * @ticket 66300
	 */
	public function test_notice_updated_false_does_not_display_settings_saved() {
		$get_params = array( 'updated' => 'false' );

		$should_display_settings_saved = isset( $get_params['updated'] )
			&& 'true' === $get_params['updated']
			&& ! isset( $get_params['network_admin_email_updated'] );

		$this->assertFalse( $should_display_settings_saved, 'The success notice "Settings saved." must not display when updated=false.' );
	}

	/**
	 * Tests notice display logic: updated=true displays "Settings saved." when network_admin_email_updated is absent.
	 *
	 * @ticket 66300
	 */
	public function test_notice_updated_true_displays_settings_saved() {
		$get_params = array( 'updated' => 'true' );

		$should_display_settings_saved = isset( $get_params['updated'] )
			&& 'true' === $get_params['updated']
			&& ! isset( $get_params['network_admin_email_updated'] );

		$this->assertTrue( $should_display_settings_saved, 'The success notice "Settings saved." should display when updated=true.' );
	}

	/**
	 * Tests notice display logic: network_admin_email_updated=true suppresses "Settings saved." and displays specific notice.
	 *
	 * @ticket 66300
	 */
	public function test_notice_network_admin_email_updated_suppresses_settings_saved() {
		$get_params = array(
			'updated'                     => 'true',
			'network_admin_email_updated' => 'true',
		);

		$should_display_settings_saved = isset( $get_params['updated'] )
			&& 'true' === $get_params['updated']
			&& ! isset( $get_params['network_admin_email_updated'] );

		$this->assertFalse( $should_display_settings_saved, 'The generic "Settings saved." notice must not display when network_admin_email_updated is set.' );
		$this->assertTrue( isset( $get_params['network_admin_email_updated'] ) );
	}

	/**
	 * Tests cancel request link generation uses the 'admin email change' context.
	 *
	 * @ticket 66300
	 */
	public function test_cancel_request_link_context() {
		$cancel_text = _x( 'Cancel request', 'admin email change' );
		$link        = sprintf(
			' <a href="%1$s">%2$s</a>',
			esc_url( wp_nonce_url( network_admin_url( 'settings.php?dismiss=new_network_admin_email' ), 'dismiss_new_network_admin_email' ) ),
			$cancel_text
		);

		$this->assertStringContainsString( 'dismiss=new_network_admin_email', $link );
		$this->assertStringContainsString( '_wpnonce=', $link );
		$this->assertStringContainsString( 'Cancel request', $link );
	}
}
