<?php

/**
 * @group admin
 *
 * @covers ::update_option_new_admin_email
 */
class Tests_Admin_Includes_Misc_UpdateOptionNewAdminEmail_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	/**
	 * @ticket 59520
	 */
	public function test_new_admin_email_subject_filter() {
		// Default value.
		$mailer = tests_retrieve_phpmailer_instance();
		update_option_new_admin_email( 'old@example.com', 'new@example.com' );
		$this->assertSame( '[Test Blog] New Admin Email Address', $mailer->get_sent()->subject );

		// Filtered value.
		add_filter(
			'new_admin_email_subject',
			function () {
				return 'Filtered Admin Email Address';
			},
			10,
			1
		);

		$mailer->mock_sent = array();

		$mailer = tests_retrieve_phpmailer_instance();
		update_option_new_admin_email( 'old@example.com', 'new@example.com' );
		$this->assertSame( 'Filtered Admin Email Address', $mailer->get_sent()->subject );
	}

	/**
	 * @ticket 43706
	 */
	public function test_new_admin_email_content_includes_new_email_address() {
		update_option_new_admin_email( 'old@example.com', 'new@example.com' );

		$body = str_replace( "\r\n", "\n", tests_retrieve_phpmailer_instance()->get_sent()->body );

		$this->assertStringContainsString( "The new administration email address will be:\nnew@example.com", $body );
		$this->assertStringNotContainsString( '###', $body, 'The email should not contain unreplaced placeholders.' );
	}

	/**
	 * @ticket 43706
	 *
	 * @dataProvider data_new_admin_email_content_placeholders
	 *
	 * @param string $placeholder The placeholder used in the filtered email content.
	 */
	public function test_new_admin_email_content_filter_replaces_email_placeholders( $placeholder ) {
		add_filter(
			'new_admin_email_content',
			static function () use ( $placeholder ) {
				return "Confirm {$placeholder}";
			}
		);

		update_option_new_admin_email( 'old@example.com', 'new@example.com' );

		$this->assertSame( 'Confirm new@example.com', trim( tests_retrieve_phpmailer_instance()->get_sent()->body ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_new_admin_email_content_placeholders() {
		return array(
			'new placeholder'        => array( '###NEW_EMAIL###' ),
			'deprecated placeholder' => array( '###EMAIL###' ),
		);
	}
}
