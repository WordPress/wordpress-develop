<?php

/**
 * @group user
 *
 * @covers ::wp_insert_user
 */
class Tests_User_wpInsertUserEmail extends WP_UnitTestCase {
	/**
	 * @ticket 54102
	 */
	public function test_email_with_ampersand_is_stored_without_html_entities() {
		$email   = 'test&123@example.org';
		$user_id = self::factory()->user->create( array( 'user_email' => $email ) );

		$this->assertSame( $email, get_userdata( $user_id )->user_email );
		$this->assertSame( $user_id, email_exists( $email ) );

		$updated_email = 'updated&123@example.org';
		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $updated_email,
			)
		);

		$this->assertSame( $updated_email, get_userdata( $user_id )->user_email );
		$this->assertSame( $user_id, email_exists( $updated_email ) );
	}
}
