<?php
/**
 * Tests for the wp_site_admin_email_change_notification function.
 *
 * @group functions.php
 *
 * @covers ::wp_site_admin_email_change_notification
 */#
class Tests_functions_wpSiteAdminEmailChangeNotification extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	public function tear_down() {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	/**
	 * Test the wp_site_admin_email_change_notification function does not send if $old_email is you@example.com
	 *
	 * @ticket 59710
	 */
	public function test_wp_site_admin_email_change_notification_old_is_default_email() {

		wp_site_admin_email_change_notification( 'you@example.com', '', '' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEmpty( $mailer->get_sent() );
	}

	/**
	 * Test the wp_site_admin_email_change_notification function does not send if filered to false
	 *
	 * @ticket 59710
	 */
	public function test_wp_site_admin_email_change_notification_skip_is_filtered() {

		add_filter( 'send_site_admin_email_change_email', '__return_false' );

		wp_site_admin_email_change_notification( 'you@example.com', '', '' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEmpty( $mailer->get_sent() );

		remove_filter( 'send_site_admin_email_change_email', '__return_false' );
	}


	/**
	 * Test the wp_site_admin_email_change_notification function does send
	 *
	 * @ticket 59710
	 */
	public function test_wp_site_admin_email_change_notification() {

		wp_site_admin_email_change_notification( 'address@tld.com', 'address@new.com', '' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSame( 'address@tld.com', $mailer->get_recipient( 'to' )->address );
		$this->assertSame( '[Test Blog] Admin Email Changed', $mailer->get_sent()->subject );

		$message = 'Hi,

This notice confirms that the admin email address was changed on Test Blog.

The new admin email address is address@new.com.

This email has been sent to address@tld.com

Regards,
All at Test Blog
http://example.org';
		$this->assertSameIgnoreEOL( $message . "\n", $mailer->get_sent()->body );
	}

	/**
	 * Test the wp_site_admin_email_change_notification function does send with filter message
	 *
	 * @ticket 59710
	 */
	public function test_wp_site_admin_email_change_notification_message_is_filtered() {

		add_filter( 'site_admin_email_change_email', array( $this, 'site_admin_email_change_email_filter' ), 10, 3 );

		wp_site_admin_email_change_notification( 'address@tld.com', 'address@new.com', '' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSameIgnoreEOL( 'filtered_message', trim( $mailer->get_sent()->body ) );

		remove_filter( 'site_admin_email_change_email', array( $this, 'site_admin_email_change_email_filter' ), 10, 3 );
	}

	/**
	 * test filter to return filtered message
	 *
	 * @param $email_change_email
	 * @param $old_email
	 * @param $new_email
	 *
	 * @return string
	 */
	public function site_admin_email_change_email_filter( $email_change_email, $old_email, $new_email ) {

		$email_change_email['message'] = 'filtered_message';

		return $email_change_email;
	}
}
