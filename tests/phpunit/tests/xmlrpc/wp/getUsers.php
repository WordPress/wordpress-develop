<?php

/**
 * @group xmlrpc
 * @group user
 *
 * @covers wp_xmlrpc_server::wp_getUsers
 */
class Tests_XMLRPC_wp_getUsers extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$results = $this->myxmlrpcserver->wp_getUsers( array( 1, 'username', 'password' ) );
		$this->assertIXRError( $results );
		$this->assertSame( 403, $results->code );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$results = $this->myxmlrpcserver->wp_getUsers( array( 1, 'subscriber', 'subscriber' ) );
		$this->assertIXRError( $results );
		$this->assertSame( 401, $results->code );
	}

	public function test_capable_user() {
		$this->make_user_by_role( 'administrator' );

		$result = $this->myxmlrpcserver->wp_getUsers( array( 1, 'administrator', 'administrator' ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertIsString( $result[0]['user_id'] );
		$this->assertStringMatchesFormat( '%d', $result[0]['user_id'] );
		$this->assertIsString( $result[0]['username'] );
		$this->assertIsString( $result[0]['first_name'] );
		$this->assertIsString( $result[0]['last_name'] );
		$this->assertInstanceOf( 'IXR_Date', $result[0]['registered'] );
		$this->assertIsString( $result[0]['bio'] );
		$this->assertIsString( $result[0]['email'] );
		$this->assertIsString( $result[0]['nickname'] );
		$this->assertIsString( $result[0]['nicename'] );
		$this->assertIsString( $result[0]['url'] );
		$this->assertIsString( $result[0]['display_name'] );
		$this->assertIsArray( $result[0]['roles'] );
	}

	public function test_invalid_role() {
		$administrator_id = $this->make_user_by_role( 'administrator' );
		if ( is_multisite() ) {
			grant_super_admin( $administrator_id );
		}

		$filter  = array( 'role' => 'invalidrole' );
		$results = $this->myxmlrpcserver->wp_getUsers( array( 1, 'administrator', 'administrator', $filter ) );
		$this->assertIXRError( $results );
		$this->assertSame( 403, $results->code );
	}

	/**
	 * @expectedDeprecated WP_User_Query
	 */
	public function test_role_filter() {
		$author_id        = $this->make_user_by_role( 'author' );
		$editor_id        = $this->make_user_by_role( 'editor' );
		$administrator_id = $this->make_user_by_role( 'administrator' );
		if ( is_multisite() ) {
			grant_super_admin( $administrator_id );
		}

		// Test a single role ('editor').
		$filter  = array( 'role' => 'editor' );
		$results = $this->myxmlrpcserver->wp_getUsers( array( 1, 'administrator', 'administrator', $filter ) );
		$this->assertNotIXRError( $results );
		$this->assertCount( 1, $results );
		$this->assertEquals( $editor_id, $results[0]['user_id'] );

		// Test 'authors', which should return all non-subscribers.
		$filter2  = array( 'who' => 'authors' );
		$results2 = $this->myxmlrpcserver->wp_getUsers( array( 1, 'administrator', 'administrator', $filter2 ) );
		$this->assertNotIXRError( $results2 );
		$this->assertCount( 3, array_intersect( array( $author_id, $editor_id, $administrator_id ), wp_list_pluck( $results2, 'user_id' ) ) );
	}

	public function test_paging_filters() {
		$administrator_id = $this->make_user_by_role( 'administrator' );
		if ( is_multisite() ) {
			grant_super_admin( $administrator_id );
		}

		self::factory()->user->create_many( 5 );

		$user_ids = get_users( array( 'fields' => 'ID' ) );

		$users_found = array();
		$page_size   = 2;

		$filter = array(
			'number' => $page_size,
			'offset' => 0,
		);
		do {
			$presults = $this->myxmlrpcserver->wp_getUsers( array( 1, 'administrator', 'administrator', $filter ) );
			foreach ( $presults as $user ) {
				$users_found[] = $user['user_id'];
			}
			$filter['offset'] += $page_size;
		} while ( count( $presults ) > 0 );

		// Verify that $user_ids matches $users_found.
		$this->assertCount( 0, array_diff( $user_ids, $users_found ) );
	}

	public function test_order_filters() {
		$this->make_user_by_role( 'administrator' );

		$filter  = array(
			'orderby' => 'email',
			'order'   => 'ASC',
		);
		$results = $this->myxmlrpcserver->wp_getUsers( array( 1, 'administrator', 'administrator', $filter ) );
		$this->assertNotIXRError( $results );

		$last_email = '';
		foreach ( $results as $user ) {
			$this->assertLessThanOrEqual( 0, strcmp( $last_email, $user['email'] ) );
			$last_email = $user['email'];
		}
	}
}
