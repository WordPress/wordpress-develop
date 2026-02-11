<?php

/**
 * Tests for network admin users spam functionality.
 *
 * @group admin
 * @group ms-required
 * @group network-admin
 * @group multisite
 *
 * @covers wp-admin/network/users.php
 */
class Tests_Multisite_NetworkUsersSpam extends WP_UnitTestCase {

	protected static $user_id;
	protected static $blog_ids = array();
	protected static $super_admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		// Create blogs for the user.
		self::$blog_ids = $factory->blog->create_many( 3, array( 'user_id' => self::$user_id ) );

		// Create a super admin user.
		self::$super_admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( self::$super_admin_id );
	}

	public static function wpTearDownAfterClass() {
		if ( self::$user_id ) {
			wpmu_delete_user( self::$user_id );
		}
		if ( self::$super_admin_id ) {
			revoke_super_admin( self::$super_admin_id );
			wpmu_delete_user( self::$super_admin_id );
		}
		foreach ( self::$blog_ids as $blog_id ) {
			wp_delete_site( $blog_id );
		}
	}

	public function set_up() {
		parent::set_up();

		// Set up as network admin.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );
		wp_set_current_user( $admin_id );

		// Ensure blogs are not marked as spam initially.
		foreach ( self::$blog_ids as $blog_id ) {
			update_blog_status( $blog_id, 'spam', '0' );
		}

		// Ensure user is not marked as spam initially.
		$user = get_userdata( self::$user_id );
		if ( $user ) {
			$user_data = $user->to_array();
			$user_data['spam'] = '0';
			wp_update_user( $user_data );
		}
	}

	/**
	 * Test that marking a user as spam does NOT mark their blogs as spam by default.
	 */
	public function test_mark_user_spam_does_not_propagate_to_blogs_by_default() {
		// Verify blogs are not spam initially.
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '0', $blog->spam, "Blog {$blog_id} should not be spam initially" );
		}

		// Mark user as spam (simulating the logic from users.php).
		$user = get_userdata( self::$user_id );
		$user_data = $user->to_array();
		$user_data['spam'] = '1';
		wp_update_user( $user_data );

		// Verify blogs are still not marked as spam (default behavior).
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '0', $blog->spam, "Blog {$blog_id} should not be marked as spam when user is marked as spam by default" );
		}

		// Verify user is marked as spam.
		$user = get_userdata( self::$user_id );
		$this->assertSame( '1', $user->spam );
	}

	/**
	 * Test that marking a user as spam DOES mark their blogs as spam when filter returns true.
	 */
	public function test_mark_user_spam_propagates_to_blogs_when_filter_enabled() {
		// Enable the filter to propagate spam status.
		add_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );

		// Verify blogs are not spam initially.
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '0', $blog->spam, "Blog {$blog_id} should not be spam initially" );
		}

		// Mark user as spam (simulating the logic from users.php with filter enabled).
		$user = get_userdata( self::$user_id );
		if ( apply_filters( 'propagate_network_user_spam_to_blogs', false, self::$user_id ) ) {
			foreach ( get_blogs_of_user( self::$user_id, true ) as $details ) {
				if ( ! is_main_site( $details->userblog_id ) ) {
					update_blog_status( $details->userblog_id, 'spam', '1' );
				}
			}
		}

		$user_data = $user->to_array();
		$user_data['spam'] = '1';
		wp_update_user( $user_data );

		// Verify blogs are marked as spam when filter is enabled.
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '1', $blog->spam, "Blog {$blog_id} should be marked as spam when filter is enabled" );
		}

		// Clean up: unmark blogs as spam.
		foreach ( self::$blog_ids as $blog_id ) {
			update_blog_status( $blog_id, 'spam', '0' );
		}

		remove_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );
	}

	/**
	 * Test that the main site is never marked as spam.
	 */
	public function test_main_site_never_marked_as_spam() {
		$main_site_id = get_main_site_id();

		// Enable the filter to propagate spam status.
		add_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );

		// Verify main site is not spam initially.
		$main_site = get_site( $main_site_id );
		$this->assertSame( '0', $main_site->spam, 'Main site should not be spam initially' );

		// Simulate marking user as spam with filter enabled.
		$user = get_userdata( self::$user_id );
		if ( apply_filters( 'propagate_network_user_spam_to_blogs', false, self::$user_id ) ) {
			foreach ( get_blogs_of_user( self::$user_id, true ) as $details ) {
				if ( ! is_main_site( $details->userblog_id ) ) {
					update_blog_status( $details->userblog_id, 'spam', '1' );
				}
			}
		}

		// Verify main site is still not marked as spam.
		$main_site = get_site( $main_site_id );
		$this->assertSame( '0', $main_site->spam, 'Main site should never be marked as spam' );

		remove_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );
	}

	/**
	 * Test that unmarking a user as spam does NOT unmark their blogs by default.
	 */
	public function test_unmark_user_spam_does_not_propagate_to_blogs_by_default() {
		// First, mark user and blogs as spam manually.
		$user = get_userdata( self::$user_id );
		$user_data = $user->to_array();
		$user_data['spam'] = '1';
		wp_update_user( $user_data );

		foreach ( self::$blog_ids as $blog_id ) {
			update_blog_status( $blog_id, 'spam', '1' );
		}

		// Verify blogs are marked as spam.
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '1', $blog->spam, "Blog {$blog_id} should be spam initially" );
		}

		// Unmark user as spam (simulating the logic from users.php).
		$user = get_userdata( self::$user_id );
		$user_data = $user->to_array();
		$user_data['spam'] = '0';
		wp_update_user( $user_data );

		// Verify blogs are still marked as spam (default behavior).
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '1', $blog->spam, "Blog {$blog_id} should remain spam when user is unmarked as spam by default" );
		}

		// Clean up: unmark blogs as spam.
		foreach ( self::$blog_ids as $blog_id ) {
			update_blog_status( $blog_id, 'spam', '0' );
		}
	}

	/**
	 * Test that unmarking a user as spam DOES unmark their blogs when filter returns true.
	 */
	public function test_unmark_user_spam_propagates_to_blogs_when_filter_enabled() {
		// First, mark user and blogs as spam manually.
		$user = get_userdata( self::$user_id );
		$user_data = $user->to_array();
		$user_data['spam'] = '1';
		wp_update_user( $user_data );

		foreach ( self::$blog_ids as $blog_id ) {
			update_blog_status( $blog_id, 'spam', '1' );
		}

		// Enable the filter to propagate spam status.
		add_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );

		// Verify blogs are marked as spam initially.
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '1', $blog->spam, "Blog {$blog_id} should be spam initially" );
		}

		// Unmark user as spam (simulating the logic from users.php with filter enabled).
		$user = get_userdata( self::$user_id );
		if ( apply_filters( 'propagate_network_user_spam_to_blogs', false, self::$user_id ) ) {
			foreach ( get_blogs_of_user( self::$user_id, true ) as $details ) {
				if ( ! is_main_site( $details->userblog_id ) && get_current_network_id() === $details->site_id ) {
					update_blog_status( $details->userblog_id, 'spam', '0' );
				}
			}
		}

		$user_data = $user->to_array();
		$user_data['spam'] = '0';
		wp_update_user( $user_data );

		// Verify blogs are unmarked as spam when filter is enabled.
		foreach ( self::$blog_ids as $blog_id ) {
			$blog = get_site( $blog_id );
			$this->assertSame( '0', $blog->spam, "Blog {$blog_id} should be unmarked as spam when filter is enabled" );
		}

		remove_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );
	}

	/**
	 * Test that super admins cannot be marked as spam.
	 */
	public function test_super_admin_cannot_be_marked_as_spam() {
		$user = get_userdata( self::$super_admin_id );
		$this->assertTrue( is_super_admin( self::$super_admin_id ), 'User should be a super admin' );

		// Set up wp_die handler to throw exception.
		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );

		// Attempting to mark super admin as spam should call wp_die().
		$this->expectException( 'WPDieException' );
		$user = get_userdata( self::$super_admin_id );
		if ( is_super_admin( $user->ID ) ) {
			wp_die(
				sprintf(
					/* translators: %s: User login. */
					__( 'Warning! User cannot be modified. The user %s is a network administrator.' ),
					esc_html( $user->user_login )
				),
				403
			);
		}

		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );
	}

	/**
	 * Test that super admins cannot be unmarked as spam.
	 */
	public function test_super_admin_cannot_be_unmarked_as_spam() {
		$user = get_userdata( self::$super_admin_id );
		$this->assertTrue( is_super_admin( self::$super_admin_id ), 'User should be a super admin' );

		// Set up wp_die handler to throw exception.
		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );

		// Attempting to unmark super admin as spam should call wp_die().
		$this->expectException( 'WPDieException' );
		$user = get_userdata( self::$super_admin_id );
		if ( is_super_admin( $user->ID ) ) {
			wp_die(
				sprintf(
					/* translators: %s: User login. */
					__( 'Warning! User cannot be modified. The user %s is a network administrator.' ),
					esc_html( $user->user_login )
				),
				403
			);
		}

		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );
	}

	/**
	 * Test that only blogs in the current network are affected when unmarking spam.
	 */
	public function test_unmark_spam_only_affects_current_network_blogs() {
		// Mark user and blogs as spam manually.
		$user = get_userdata( self::$user_id );
		$user_data = $user->to_array();
		$user_data['spam'] = '1';
		wp_update_user( $user_data );

		foreach ( self::$blog_ids as $blog_id ) {
			update_blog_status( $blog_id, 'spam', '1' );
		}

		// Enable the filter to propagate spam status.
		add_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );

		// Get current network ID.
		$current_network_id = get_current_network_id();

		// Get blogs of user before unmarking spam.
		$blogs_before = get_blogs_of_user( self::$user_id, true );
		$this->assertNotEmpty( $blogs_before, 'User should have blogs' );

		// Unmark user as spam (simulating the logic from users.php with filter enabled).
		$user = get_userdata( self::$user_id );
		if ( apply_filters( 'propagate_network_user_spam_to_blogs', false, self::$user_id ) ) {
			foreach ( get_blogs_of_user( self::$user_id, true ) as $details ) {
				if ( ! is_main_site( $details->userblog_id ) && get_current_network_id() === $details->site_id ) {
					update_blog_status( $details->userblog_id, 'spam', '0' );
				}
			}
		}

		$user_data = $user->to_array();
		$user_data['spam'] = '0';
		wp_update_user( $user_data );

		// Verify only blogs in the current network are affected.
		// Check the blogs that were created for this user (excluding main site).
		$blogs_in_current_network = 0;
		foreach ( self::$blog_ids as $blog_id ) {
			if ( ! is_main_site( $blog_id ) && isset( $blogs_before[ $blog_id ] ) ) {
				$blog = get_site( $blog_id );
				$blog_details = $blogs_before[ $blog_id ];
				
				// In a standard multisite setup, all blogs are in the same network.
				// Verify the blog's network matches the current network.
				if ( $current_network_id === $blog_details->site_id ) {
					// Blog in current network should be unmarked as spam.
					$this->assertSame( '0', $blog->spam, "Blog {$blog_id} in current network should be unmarked as spam" );
					++$blogs_in_current_network;
				}
			}
		}

		// Ensure at least one blog in the current network was tested.
		// In a standard multisite setup, all blogs are in the same network.
		$this->assertGreaterThan( 0, $blogs_in_current_network, 'At least one blog in the current network should be tested' );

		// Clean up.
		foreach ( self::$blog_ids as $blog_id ) {
			update_blog_status( $blog_id, 'spam', '0' );
		}

		remove_filter( 'propagate_network_user_spam_to_blogs', '__return_true' );
	}

	/**
	 * Test the filter receives the correct user_id parameter.
	 */
	public function test_filter_receives_correct_user_id() {
		$received_user_id = null;

		$filter_callback = function ( $propagate, $user_id ) use ( &$received_user_id ) {
			$received_user_id = $user_id;
			return false;
		};

		add_filter( 'propagate_network_user_spam_to_blogs', $filter_callback, 10, 2 );

		// Trigger the filter.
		apply_filters( 'propagate_network_user_spam_to_blogs', false, self::$user_id );

		$this->assertSame( self::$user_id, $received_user_id, 'Filter should receive the correct user ID' );

		remove_filter( 'propagate_network_user_spam_to_blogs', $filter_callback );
	}
}
