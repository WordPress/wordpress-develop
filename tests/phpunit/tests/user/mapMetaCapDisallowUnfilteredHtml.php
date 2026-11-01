<?php

/**
 * Tests capability mapping with DISALLOW_UNFILTERED_HTML enabled.
 *
 * A separate process prevents the constant from affecting other tests. A separate
 * class prevents the child process's class teardown from deleting database fixtures
 * still needed by Tests_User_MapMetaCap in the parent process.
 *
 * @group user
 * @group capabilities
 * @covers ::map_meta_cap
 */
class Tests_User_MapMetaCapDisallowUnfilteredHtml extends WP_UnitTestCase {

	/**
	 * @ticket 20488
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_file_edit_caps_not_reliant_on_unfiltered_html_constant() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}

		$this->assertFalse( defined( 'DISALLOW_FILE_MODS' ) );
		$this->assertFalse( defined( 'DISALLOW_FILE_EDIT' ) );

		if ( ! defined( 'DISALLOW_UNFILTERED_HTML' ) ) {
			define( 'DISALLOW_UNFILTERED_HTML', true );
		}

		$this->assertTrue( DISALLOW_UNFILTERED_HTML );
		$this->assertSame( array( 'update_core' ), map_meta_cap( 'update_core', $user_id ) );
		$this->assertSame( array( 'edit_plugins' ), map_meta_cap( 'edit_plugins', $user_id ) );
	}
}
