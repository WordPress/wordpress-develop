<?php

/**
 * @group ms-required
 * @group multisite
 *
 * @covers ::update_network_option_new_admin_email
 */
class Tests_Multisite_UpdateNetworkOptionNewAdminEmail extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	/**
	 * @ticket 43706
	 */
	public function test_new_network_admin_email_content_includes_new_email_address() {
		update_network_option_new_admin_email( 'old@example.com', 'new@example.com' );

		$body = str_replace( "\r\n", "\n", tests_retrieve_phpmailer_instance()->get_sent()->body );

		$this->assertStringContainsString( "network changed to:\nnew@example.com", $body );
		$this->assertStringNotContainsString( '###', $body, 'The email should not contain unreplaced placeholders.' );
	}

	/**
	 * @ticket 43706
	 *
	 * @dataProvider data_new_network_admin_email_content_placeholders
	 *
	 * @param string $placeholder The placeholder used in the filtered email content.
	 */
	public function test_new_network_admin_email_content_filter_replaces_email_placeholders( $placeholder ) {
		add_filter(
			'new_network_admin_email_content',
			static function () use ( $placeholder ) {
				return "Confirm {$placeholder}";
			}
		);

		update_network_option_new_admin_email( 'old@example.com', 'new@example.com' );

		$this->assertSame( 'Confirm new@example.com', trim( tests_retrieve_phpmailer_instance()->get_sent()->body ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_new_network_admin_email_content_placeholders() {
		return array(
			'new placeholder'        => array( '###NEW_EMAIL###' ),
			'deprecated placeholder' => array( '###EMAIL###' ),
		);
	}
}
