<?php

/**
 * @group xmlrpc
 * @group user
 */
class Tests_XMLRPC_wp_editProfile extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_editProfile( array( 1, 'username', 'password', array() ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_subscriber_profile() {
		$subscriber_id = $this->make_user_by_role( 'subscriber' );

		$new_data = array(
			'first_name'   => 'firstname',
			'last_name'    => 'lastname',
			'url'          => 'http://www.example.org/subscriber',
			'display_name' => 'displayname',
			'nickname'     => 'nickname',
			'nicename'     => 'nicename',
			'bio'          => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
		);
		$result   = $this->myxmlrpcserver->wp_editProfile( array( 1, 'subscriber', 'subscriber', $new_data ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		// Verify that the new values were stored.
		$user_data = get_userdata( $subscriber_id );
		$this->assertSame( $new_data['first_name'], $user_data->first_name );
		$this->assertSame( $new_data['last_name'], $user_data->last_name );
		$this->assertSame( $new_data['url'], $user_data->user_url );
		$this->assertSame( $new_data['display_name'], $user_data->display_name );
		$this->assertSame( $new_data['nickname'], $user_data->nickname );
		$this->assertSame( $new_data['nicename'], $user_data->user_nicename );
		$this->assertSame( $new_data['bio'], $user_data->description );
	}

	public function test_ignore_password_change() {
		$this->make_user_by_role( 'author' );
		$new_pass = 'newpassword';
		$new_data = array( 'password' => $new_pass );

		$result = $this->myxmlrpcserver->wp_editProfile( array( 1, 'author', 'author', $new_data ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$auth_old = wp_authenticate( 'author', 'author' );
		$auth_new = wp_authenticate( 'author', $new_pass );
		$this->assertInstanceOf( 'WP_User', $auth_old );
		$this->assertWPError( $auth_new );
	}

	public function test_ignore_email_change() {
		$editor_id = $this->make_user_by_role( 'editor' );
		$new_email = 'notaneditor@example.com';
		$new_data  = array( 'email' => $new_email );

		$result = $this->myxmlrpcserver->wp_editProfile( array( 1, 'editor', 'editor', $new_data ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$user_data = get_userdata( $editor_id );
		$this->assertNotEquals( $new_email, $user_data->email );
	}
}
