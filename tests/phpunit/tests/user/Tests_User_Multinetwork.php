<?php

namespace phpunit\tests\user;

/**
 * Tests specific to users in multinetwork.
 *
 * @group user
 * @group ms-required
 * @group ms-user
 * @group multisite
 * @group multinetwork
 */
class Tests_User_Multinetwork extends \WP_UnitTestCase {

	public function test_super_admin_privileges() {
		[$network1_id, $network2_id] = self::factory()->network->create_many( 2 );

		// Test that the first network has the default super admin.
		$network1_admins = get_super_admins( $network1_id );
		$this->assertIsArray( $network1_admins, 'Network one should have an array of super admins.' );
		$this->assertContains( 'admin', $network1_admins, 'Network one should have the super admin "admin".' );
		$this->assertCount( 1, $network1_admins, 'Network one should have only one super admin.' );

		// Test that the second network has the default super admin.
		$network2_admins = get_super_admins( $network2_id );
		$this->assertIsArray( $network2_admins, 'Network two should have an array of super admins.' );
		$this->assertContains( 'admin', $network2_admins, 'Network two should have the super admin "admin".' );
		$this->assertCount( 1, $network2_admins, 'Network two should have only one super admin.' );

		// Test that the new user not a super admin on either network.
		$user1 = self::factory()->user->create_and_get();
		$this->assertFalse( is_super_admin( $user1->ID, $network1_id ), 'User one should not be a super admin of network one' );
		$this->assertFalse( is_super_admin( $user1->ID, $network2_id ), 'User one should not be a super admin of network two' );

		// Grant and revoke super admin privileges for user one on network one.
		$this->assertTrue( grant_super_admin( $user1->ID, $network1_id ), 'User one should be granted super admin privileges on network one.' );
		$this->assertTrue( is_super_admin( $user1->ID, $network1_id ), 'User one should be a super admin of network one' );
		$this->assertFalse( is_super_admin( $user1->ID, $network2_id ), 'User one should not be a super admin of network two' );
		$this->assertTrue( revoke_super_admin( $user1->ID, $network1_id ), 'User one should have super admin privileges revoked on network one.' );
		$this->assertFalse( is_super_admin( $user1->ID, $network1_id ), 'User one should not be a super admin of network one' );

		// Test that new user two is not a super admin on either network.
		$user2 = self::factory()->user->create_and_get();
		$this->assertFalse( is_super_admin( $user2->ID, $network1_id ), 'User two should not be a super admin of network one' );
		$this->assertFalse( is_super_admin( $user2->ID, $network2_id ), 'User two should not be a super admin of network two' );

		// Grant and revoke super admin privileges for user two on network two.
		$this->assertTrue( grant_super_admin( $user2->ID, $network2_id ), 'User two should be granted super admin privileges on network two.' );
		$this->assertFalse( is_super_admin( $user2->ID, $network1_id ), 'User two should not be a super admin of network one' );
		$this->assertTrue( is_super_admin( $user2->ID, $network2_id ), 'User two should be a super admin of network two' );
		$this->assertTrue( revoke_super_admin( $user2->ID, $network2_id ), 'User two should have super admin privileges revoked on network two.' );
		$this->assertFalse( is_super_admin( $user2->ID, $network2_id ), 'User two should not be a super admin of network two' );
	}

}
