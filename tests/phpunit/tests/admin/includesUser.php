<?php

/**
 * @group admin
 * @group user
 *
 * @covers ::_wp_send_password_reset_to_users
 */
class Tests_Admin_IncludesUser extends WP_UnitTestCase {
	protected $admin_id;

	public function set_up() {
		parent::set_up();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $this->admin_id );
		}
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * @ticket 57233
	 */
	public function test_sends_password_reset_links_and_skips_current_user() {
		$user_id    = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$mail_count = 0;

		add_filter(
			'pre_wp_mail',
			static function () use ( &$mail_count ) {
				++$mail_count;
				return true;
			}
		);

		$this->assertSame( 1, _wp_send_password_reset_to_users( array( $user_id, $this->admin_id ) ) );
		$this->assertSame( 1, $mail_count );
	}

	/**
	 * @ticket 57233
	 */
	public function test_returns_error_for_invalid_user_id() {
		$result = _wp_send_password_reset_to_users( PHP_INT_MAX );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_user_id', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}
}
