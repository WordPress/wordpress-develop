<?php

/**
 * @group admin
 *
 * @covers ::update_option_new_admin_email
 */
class Admin_Includes_Misc_UpdateOptioNewAdminEmail_Test extends WP_UnitTestCase {

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
}
