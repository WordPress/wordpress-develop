<?php

/**
 * @group xmlrpc
 * @group user
 */
class Tests_XMLRPC_wp_getUser extends WP_XMLRPC_UnitTestCase {
	protected $administrator_id;

	public function set_up() {
		parent::set_up();

		// Create a super admin.
		$this->administrator_id = $this->make_user_by_role( 'administrator' );
		if ( is_multisite() ) {
			grant_super_admin( $this->administrator_id );
		}
	}

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'username', 'password', 1 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_invalid_user() {
		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'administrator', 'administrator', 34902348908234 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );
		$editor_id = $this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'subscriber', 'subscriber', $editor_id ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_subscriber_self() {
		$subscriber_id = $this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'subscriber', 'subscriber', $subscriber_id ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $subscriber_id, $result['user_id'] );
	}

	public function test_valid_user() {
		$registered_date = strtotime( '-1 day' );
		$user_data       = array(
			'user_login'      => 'getusertestuser',
			'user_pass'       => 'password',
			'first_name'      => 'First',
			'last_name'       => 'Last',
			'description'     => 'I love WordPress',
			'user_email'      => 'getUserTestUser@example.com',
			'nickname'        => 'nickname',
			'user_nicename'   => 'nicename',
			'display_name'    => 'First Last',
			'user_url'        => 'http://www.example.com/testuser',
			'role'            => 'author',
			'aim'             => 'wordpress',
			'user_registered' => date_format( date_create( "@{$registered_date}" ), 'Y-m-d H:i:s' ),
		);
		$user_id         = wp_insert_user( $user_data );

		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'administrator', 'administrator', $user_id ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertIsString( $result['user_id'] );
		$this->assertStringMatchesFormat( '%d', $result['user_id'] );
		$this->assertIsString( $result['username'] );
		$this->assertIsString( $result['first_name'] );
		$this->assertIsString( $result['last_name'] );
		$this->assertInstanceOf( 'IXR_Date', $result['registered'] );
		$this->assertIsString( $result['bio'] );
		$this->assertIsString( $result['email'] );
		$this->assertIsString( $result['nickname'] );
		$this->assertIsString( $result['nicename'] );
		$this->assertIsString( $result['url'] );
		$this->assertIsString( $result['display_name'] );
		$this->assertIsArray( $result['roles'] );

		// Check expected values.
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertSame( $user_data['user_login'], $result['username'] );
		$this->assertSame( $user_data['first_name'], $result['first_name'] );
		$this->assertSame( $user_data['last_name'], $result['last_name'] );
		$this->assertSame( $registered_date, $result['registered']->getTimestamp() );
		$this->assertSame( $user_data['description'], $result['bio'] );
		$this->assertSame( $user_data['user_email'], $result['email'] );
		$this->assertSame( $user_data['nickname'], $result['nickname'] );
		$this->assertSame( $user_data['user_nicename'], $result['nicename'] );
		$this->assertSame( $user_data['user_url'], $result['url'] );
		$this->assertSame( $user_data['display_name'], $result['display_name'] );
		$this->assertSame( $user_data['user_login'], $result['username'] );
		$this->assertContains( $user_data['role'], $result['roles'] );

		wp_delete_user( $user_id );
	}

	public function test_no_fields() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'administrator', 'administrator', $editor_id, array() ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $editor_id, $result['user_id'] );

		$expected_fields = array( 'user_id' );
		$this->assertSame( $expected_fields, array_keys( $result ) );
	}

	public function test_basic_fields() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'administrator', 'administrator', $editor_id, array( 'basic' ) ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $editor_id, $result['user_id'] );

		$expected_fields = array( 'user_id', 'username', 'email', 'registered', 'display_name', 'nicename' );
		$keys            = array_keys( $result );
		sort( $expected_fields );
		sort( $keys );
		$this->assertSameSets( $expected_fields, $keys );
	}

	public function test_arbitrary_fields() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$fields = array( 'email', 'bio', 'user_contacts' );

		$result = $this->myxmlrpcserver->wp_getUser( array( 1, 'administrator', 'administrator', $editor_id, $fields ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $editor_id, $result['user_id'] );

		$expected_fields = array( 'user_id', 'email', 'bio' );
		$keys            = array_keys( $result );
		sort( $expected_fields );
		sort( $keys );
		$this->assertSameSets( $expected_fields, $keys );
	}
}
