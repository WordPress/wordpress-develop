<?php
/**
 * Test cases for network admin email change functionality.
 *
 * @group multisite
 * @group ms-required
 */
class Tests_Multisite_NetworkAdminEmailChange extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		reset_phpmailer_instance();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
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
		$original_admin_email = get_site_option( 'admin_email' );
		update_site_option( 'new_admin_email', $original_admin_email );

		$this->assertFalse( get_site_option( 'network_admin_hash' ), 'The network_admin_hash option should not be set when the email is unchanged.' );
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEmpty( $mailer->mock_sent, 'No confirmation email should be sent when the email is unchanged.' );
	}

	/**
	 * Tests that updating new_admin_email with an invalid email does nothing.
	 *
	 * @ticket 66300
	 * @covers ::update_network_option_new_admin_email
	 */
	public function test_update_network_option_new_admin_email_ignores_invalid_email() {
		update_site_option( 'new_admin_email', 'not-a-valid-email' );

		$this->assertFalse( get_site_option( 'network_admin_hash' ), 'The network_admin_hash option should not be set for an invalid email.' );
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEmpty( $mailer->mock_sent, 'No confirmation email should be sent for an invalid email.' );
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
		$this->assertIsArray( $hash_details, 'The network_admin_hash option should be an array.' );
		$this->assertSame( $new_email, $hash_details['newemail'], 'The hash details should contain the new admin email.' );
		$this->assertNotEmpty( $hash_details['hash'], 'The hash details should contain a non-empty hash.' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertNotEmpty( $mailer->mock_sent, 'A confirmation email should be sent.' );
		$this->assertSame( $new_email, $mailer->mock_sent[0]['to'][0][0], 'The confirmation email should be addressed to the new email.' );
		$this->assertStringContainsString( 'network_admin_hash=' . $hash_details['hash'], $mailer->mock_sent[0]['body'], 'The confirmation email body should contain the confirmation hash query arg.' );
	}
}
