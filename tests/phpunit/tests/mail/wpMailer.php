<?php
/**
 * @group mail
 * @covers WP_Mailer
 */
class Tests_Mail_WPMailer extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	public function tear_down() {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	/**
	 * Tests the render method.
	 */
	public function test_render() {
		$template = 'Hello {{name}}, welcome to {{site}}!';
		$data     = array(
			'name' => 'Alice',
			'site' => 'WordPress',
		);
		$expected = 'Hello Alice, welcome to WordPress!';
		$this->assertSame( $expected, WP_Mailer::render( $template, $data ) );
	}

	/**
	 * Tests the render method with missing data.
	 */
	public function test_render_missing_data() {
		$template = 'Hello {{name}}, welcome to {{site}}!';
		$data     = array(
			'name' => 'Alice',
		);
		$expected = 'Hello Alice, welcome to {{site}}!';
		$this->assertSame( $expected, WP_Mailer::render( $template, $data ) );
	}

	/**
	 * Tests sending an email with registered template.
	 */
	public function test_send_registered_email() {
		WP_Mailer::register_email(
			'test_email',
			'test_group',
			array(
				'subject' => 'Subject for {{name}}',
				'body'    => 'Body for {{name}} at {{site}}',
			)
		);

		WP_Mailer::send(
			'test_email',
			array( 'to' => 'test@example.com' ),
			array(
				'name' => 'Bob',
				'site' => 'Example Site',
			)
		);

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSame( 'Subject for Bob', $mailer->Subject );
		$this->assertStringContainsString( 'Body for Bob at Example Site', $mailer->get_sent()->body );
	}

	/**
	 * Tests the Reply-To fix in wp_mail.
	 * 
	 * @ticket 49661
	 */
	public function test_wp_mail_reply_to_quoting_fix() {
		$to       = 'recipient@example.com';
		$subject  = 'Testing Reply-To';
		$message  = 'Message body';
		$headers  = 'Reply-To: "John Doe" <john@example.com>';

		wp_mail( $to, $subject, $message, $headers );

		$mailer = tests_retrieve_phpmailer_instance();
		$reply_tos = $mailer->getReplyToAddresses();
		
		$this->assertCount( 1, $reply_tos );
		$reply_to = reset( $reply_tos );
		
		// PHPMailer will add its own quotes if needed, but we should not have double quotes here.
		$this->assertSame( 'john@example.com', $reply_to[0] );
		$this->assertSame( 'John Doe', $reply_to[1] );
	}

	/**
	 * Tests the retrieve_password template logic.
	 */
	public function test_retrieve_password_template() {
		$site_name = 'My Awesome Site';
		$user_login = 'alice';
		$reset_url = 'https://example.com/reset';
		$ip = '127.0.0.1';

		WP_Mailer::register_email(
			'retrieve_password',
			'user',
			array(
				'subject' => '[{{sitename}}] Password Reset',
				'body'    => 'Username: {{user_login}} Reset: {{reset_url}} IP: {{ip_notification}}',
			)
		);

		WP_Mailer::send(
			'retrieve_password',
			array( 'to' => 'alice@example.com' ),
			array(
				'sitename'        => $site_name,
				'user_login'      => $user_login,
				'reset_url'       => $reset_url,
				'ip_notification' => $ip,
			)
		);

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSame( '[' . $site_name . '] Password Reset', $mailer->Subject );
		$this->assertStringContainsString( 'Username: alice', $mailer->get_sent()->body );
		$this->assertStringContainsString( 'Reset: https://example.com/reset', $mailer->get_sent()->body );
		$this->assertStringContainsString( 'IP: 127.0.0.1', $mailer->get_sent()->body );
	}

	/**
	 * Tests the new_user templates.
	 */
	public function test_new_user_templates() {
		// Admin notification
		WP_Mailer::register_email( 'new_user_admin', 'admin', array(
			'subject' => '[{{sitename}}] New User',
			'body'    => 'User: {{user_login}} Email: {{user_email}}',
		));

		WP_Mailer::send( 'new_user_admin', array( 'to' => 'admin@example.com' ), array(
			'sitename'   => 'Site',
			'user_login' => 'bob',
			'user_email' => 'bob@example.com',
		) );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSame( '[Site] New User', $mailer->Subject );
		$this->assertStringContainsString( 'User: bob', $mailer->get_sent()->body );

		// User notification
		reset_phpmailer_instance();
		WP_Mailer::register_email( 'new_user', 'user', array(
			'subject' => '[{{sitename}}] Login Details',
			'body'    => 'Login: {{user_login}} URL: {{set_password_url}}',
		));

		WP_Mailer::send( 'new_user', array( 'to' => 'bob@example.com' ), array(
			'sitename'         => 'Site',
			'user_login'       => 'bob',
			'set_password_url' => 'http://example.com/set-pw',
		) );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSame( '[Site] Login Details', $mailer->Subject );
		$this->assertStringContainsString( 'URL: http://example.com/set-pw', $mailer->get_sent()->body );
	}
}
