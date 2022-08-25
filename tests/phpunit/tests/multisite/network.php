<?php

if ( is_multisite() ) :

	/**
	 * Tests specific to networks in multisite.
	 *
	 * @group ms-network
	 * @group multisite
	 */
	class Tests_Multisite_Network extends WP_UnitTestCase {
		protected $plugin_hook_count = 0;

		protected static $different_network_id;
		protected static $different_site_ids = array();

		public function tear_down() {
			global $current_site;
			$current_site->id = 1;
			parent::tear_down();
		}

		public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
			self::$different_network_id = $factory->network->create(
				array(
					'domain' => 'wordpress.org',
					'path'   => '/',
				)
			);

			$sites = array(
				array(
					'domain'     => 'wordpress.org',
					'path'       => '/',
					'network_id' => self::$different_network_id,
				),
				array(
					'domain'     => 'wordpress.org',
					'path'       => '/foo/',
					'network_id' => self::$different_network_id,
				),
				array(
					'domain'     => 'wordpress.org',
					'path'       => '/bar/',
					'network_id' => self::$different_network_id,
				),
			);

			foreach ( $sites as $site ) {
				self::$different_site_ids[] = $factory->blog->create( $site );
			}
		}

		public static function wpTearDownAfterClass() {
			global $wpdb;

			foreach ( self::$different_site_ids as $id ) {
				wp_delete_site( $id );
			}

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", self::$different_network_id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id= %d", self::$different_network_id ) );

			wp_update_network_site_counts();
		}

		/**
		 * By default, only one network exists and has a network ID of 1.
		 */
		public function test_get_main_network_id_default() {
			$this->assertSame( 1, get_main_network_id() );
		}

		/**
		 * If a second network is created, network ID 1 should still be returned
		 * as the main network ID.
		 */
		public function test_get_main_network_id_two_networks() {
			self::factory()->network->create();

			$this->assertSame( 1, get_main_network_id() );
		}

		/**
		 * When the `$current_site` global is populated with another network, the
		 * main network should still return as 1.
		 */
		public function test_get_main_network_id_after_network_switch() {
			global $current_site;

			$id = self::factory()->network->create();

			$current_site->id = (int) $id;

			$this->assertSame( 1, get_main_network_id() );
		}

		/**
		 * When the first network is removed, the next should return as the main
		 * network ID.
		 *
		 * @todo In the future, we'll have a smarter way of deleting a network. For now,
		 * fake the process with UPDATE queries.
		 */
		public function test_get_main_network_id_after_network_delete() {
			global $wpdb, $current_site;

			$temp_id = self::$different_network_id + 1;

			$current_site->id = (int) self::$different_network_id;
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->site} SET id=%d WHERE id=1", $temp_id ) );
			$main_network_id = get_main_network_id();
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->site} SET id=1 WHERE id=%d", $temp_id ) );

			$this->assertSame( self::$different_network_id, $main_network_id );
		}

		public function test_get_main_network_id_filtered() {
			add_filter( 'get_main_network_id', array( $this, 'get_main_network_id' ) );
			$this->assertSame( 3, get_main_network_id() );
			remove_filter( 'get_main_network_id', array( $this, 'get_main_network_id' ) );
		}

		public function get_main_network_id() {
			return 3;
		}

		/**
		 * @ticket 37050
		 */
		public function test_wp_network_object_id_property_is_int() {
			$id = self::factory()->network->create();

			$network = WP_Network::get_instance( $id );

			$this->assertSame( (int) $id, $network->id );
		}

		/**
		 * @ticket 22917
		 */
		public function test_get_blog_count_no_filter_applied() {
			wp_update_network_counts();
			$site_count_start = get_blog_count();

			$site_ids = self::factory()->blog->create_many( 1 );
			$actual   = (int) get_blog_count(); // Count only updated when cron runs, so should be unchanged.

			foreach ( $site_ids as $site_id ) {
				wp_delete_site( $site_id );
			}
			wp_update_network_counts();

			$this->assertSame( $site_count_start + 1, $actual );
		}

		/**
		 * @ticket 22917
		 */
		public function test_get_blog_count_enable_live_network_counts_false() {
			wp_update_network_counts();
			$site_count_start = get_blog_count();

			add_filter( 'enable_live_network_counts', '__return_false' );
			$site_ids = self::factory()->blog->create_many( 1 );
			$actual   = (int) get_blog_count(); // Count only updated when cron runs, so should be unchanged.
			remove_filter( 'enable_live_network_counts', '__return_false' );

			foreach ( $site_ids as $site_id ) {
				wp_delete_site( $site_id );
			}
			wp_update_network_counts();

			$this->assertEquals( $site_count_start, $actual );
		}

		/**
		 * @ticket 22917
		 */
		public function test_get_blog_count_enabled_live_network_counts_true() {
			wp_update_network_counts();
			$site_count_start = get_blog_count();

			add_filter( 'enable_live_network_counts', '__return_true' );
			$site_ids = self::factory()->blog->create_many( 1 );
			$actual   = get_blog_count();
			remove_filter( 'enable_live_network_counts', '__return_true' );

			foreach ( $site_ids as $site_id ) {
				wp_delete_site( $site_id );
			}
			wp_update_network_counts();

			$this->assertEquals( $site_count_start + 1, $actual );
		}

		/**
		 * @ticket 37865
		 */
		public function test_get_blog_count_on_different_network() {
			wp_update_network_site_counts( self::$different_network_id );

			$site_count = get_blog_count( self::$different_network_id );

			$this->assertEquals( count( self::$different_site_ids ), $site_count );
		}



		public function test_active_network_plugins() {
			$path = 'hello.php';

			// Local activate, should be invisible for the network.
			activate_plugin( $path ); // Enable the plugin for the current site.
			$active_plugins = wp_get_active_network_plugins();
			$this->assertSame( array(), $active_plugins );

			add_action( 'deactivated_plugin', array( $this, 'helper_deactivate_hook' ) );

			// Activate the plugin sitewide.
			activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
			$active_plugins = wp_get_active_network_plugins();
			$this->assertSame( array( WP_PLUGIN_DIR . '/hello.php' ), $active_plugins );

			// Deactivate the plugin.
			deactivate_plugins( $path );
			$active_plugins = wp_get_active_network_plugins();
			$this->assertSame( array(), $active_plugins );

			$this->assertSame( 1, $this->plugin_hook_count ); // Testing actions and silent mode.

			activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
			deactivate_plugins( $path, true );  // Silent mode.

			$this->assertSame( 1, $this->plugin_hook_count ); // Testing actions and silent mode.
		}

		/**
		 * @ticket 28651
		 */
		public function test_duplicate_network_active_plugin() {
			$path = 'hello.php';
			$mock = new MockAction();
			add_action( 'activate_' . $path, array( $mock, 'action' ) );

			// Should activate on the first try.
			activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
			$active_plugins = wp_get_active_network_plugins();
			$this->assertCount( 1, $active_plugins );
			$this->assertSame( 1, $mock->get_call_count() );

			// Should do nothing on the second try.
			activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
			$active_plugins = wp_get_active_network_plugins();
			$this->assertCount( 1, $active_plugins );
			$this->assertSame( 1, $mock->get_call_count() );

			remove_action( 'activate_' . $path, array( $mock, 'action' ) );
		}

		public function test_is_plugin_active_for_network_true() {
			activate_plugin( 'hello.php', '', true );
			$this->assertTrue( is_plugin_active_for_network( 'hello.php' ) );
		}

		public function test_is_plugin_active_for_network_false() {
			deactivate_plugins( 'hello.php', false, true );
			$this->assertFalse( is_plugin_active_for_network( 'hello.php' ) );
		}

		public function helper_deactivate_hook() {
			$this->plugin_hook_count++;
		}

		public function test_wp_schedule_update_network_counts() {
			$this->assertFalse( wp_next_scheduled( 'update_network_counts' ) );

			// We can't use wp_schedule_update_network_counts() because WP_INSTALLING is set.
			wp_schedule_event( time(), 'twicedaily', 'update_network_counts' );

			$this->assertIsInt( wp_next_scheduled( 'update_network_counts' ) );
		}

		/**
		 * @expectedDeprecated get_dashboard_blog
		 */
		public function test_get_dashboard_blog() {
			// If there is no dashboard blog set, current blog is used.
			$dashboard_blog = get_dashboard_blog();
			$this->assertEquals( 1, $dashboard_blog->blog_id );

			$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
			$blog_id = self::factory()->blog->create( array( 'user_id' => $user_id ) );
			$this->assertIsInt( $blog_id );

			// Set the dashboard blog to another one.
			update_site_option( 'dashboard_blog', $blog_id );
			$dashboard_blog = get_dashboard_blog();
			$this->assertEquals( $blog_id, $dashboard_blog->blog_id );
		}

		/**
		 * @ticket 37528
		 */
		public function test_wp_update_network_site_counts() {
			update_network_option( null, 'blog_count', 40 );

			$expected = get_sites(
				array(
					'network_id' => get_current_network_id(),
					'spam'       => 0,
					'deleted'    => 0,
					'archived'   => 0,
					'count'      => true,
				)
			);

			wp_update_network_site_counts();

			$result = get_blog_count();
			$this->assertSame( $expected, $result );
		}

		/**
		 * @ticket 37528
		 */
		public function test_wp_update_network_site_counts_on_different_network() {
			update_network_option( self::$different_network_id, 'blog_count', 40 );

			wp_update_network_site_counts( self::$different_network_id );

			$result = get_blog_count( self::$different_network_id );
			$this->assertSame( 3, $result );
		}

		/**
		 * @ticket 40349
		 */
		public function test_wp_update_network_user_counts() {
			global $wpdb;

			update_network_option( null, 'user_count', 40 );

			$expected = (int) $wpdb->get_var( "SELECT COUNT(ID) as c FROM $wpdb->users WHERE spam = '0' AND deleted = '0'" );

			wp_update_network_user_counts();

			$result = get_user_count();
			$this->assertSame( $expected, $result );
		}

		/**
		 * @ticket 40349
		 */
		public function test_wp_update_network_user_counts_on_different_network() {
			global $wpdb;

			update_network_option( self::$different_network_id, 'user_count', 40 );

			$expected = (int) $wpdb->get_var( "SELECT COUNT(ID) as c FROM $wpdb->users WHERE spam = '0' AND deleted = '0'" );

			wp_update_network_user_counts( self::$different_network_id );

			$result = get_user_count( self::$different_network_id );
			$this->assertSame( $expected, $result );
		}

		/**
		 * @ticket 40386
		 */
		public function test_wp_update_network_counts() {
			delete_network_option( null, 'blog_count' );
			delete_network_option( null, 'user_count' );

			wp_update_network_counts();

			$site_count = (int) get_blog_count();
			$user_count = (int) get_user_count();

			$this->assertGreaterThan( 0, $site_count );
			$this->assertGreaterThan( 0, $user_count );
		}

		/**
		 * @ticket 40386
		 */
		public function test_wp_update_network_counts_on_different_network() {
			delete_network_option( self::$different_network_id, 'blog_count' );
			delete_network_option( self::$different_network_id, 'user_count' );

			wp_update_network_counts( self::$different_network_id );

			$site_count = (int) get_blog_count( self::$different_network_id );
			$user_count = (int) get_user_count( self::$different_network_id );

			$this->assertGreaterThan( 0, $site_count );
			$this->assertGreaterThan( 0, $user_count );
		}

		/**
		 * @ticket 40489
		 * @dataProvider data_wp_is_large_network
		 */
		public function test_wp_is_large_network( $using, $count, $expected, $different_network ) {
			$network_id     = $different_network ? self::$different_network_id : null;
			$network_option = 'users' === $using ? 'user_count' : 'blog_count';

			update_network_option( $network_id, $network_option, $count );

			$result = wp_is_large_network( $using, $network_id );
			if ( $expected ) {
				$this->assertTrue( $result );
			} else {
				$this->assertFalse( $result );
			}
		}

		public function data_wp_is_large_network() {
			return array(
				array( 'sites', 10000, false, false ),
				array( 'sites', 10001, true, false ),
				array( 'users', 10000, false, false ),
				array( 'users', 10001, true, false ),
				array( 'sites', 10000, false, true ),
				array( 'sites', 10001, true, true ),
				array( 'users', 10000, false, true ),
				array( 'users', 10001, true, true ),
			);
		}

		/**
		 * @ticket 40489
		 * @dataProvider data_wp_is_large_network_filtered_by_component
		 */
		public function test_wp_is_large_network_filtered_by_component( $using, $count, $expected, $different_network ) {
			$network_id     = $different_network ? self::$different_network_id : null;
			$network_option = 'users' === $using ? 'user_count' : 'blog_count';

			update_network_option( $network_id, $network_option, $count );

			add_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_for_users' ), 10, 3 );
			$result = wp_is_large_network( $using, $network_id );
			remove_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_for_users' ), 10 );

			if ( $expected ) {
				$this->assertTrue( $result );
			} else {
				$this->assertFalse( $result );
			}
		}

		public function data_wp_is_large_network_filtered_by_component() {
			return array(
				array( 'sites', 10000, false, false ),
				array( 'sites', 10001, true, false ),
				array( 'users', 1000, false, false ),
				array( 'users', 1001, true, false ),
				array( 'sites', 10000, false, true ),
				array( 'sites', 10001, true, true ),
				array( 'users', 1000, false, true ),
				array( 'users', 1001, true, true ),
			);
		}

		public function filter_wp_is_large_network_for_users( $is_large_network, $using, $count ) {
			if ( 'users' === $using ) {
				return $count > 1000;
			}

			return $is_large_network;
		}

		/**
		 * @ticket 40489
		 * @dataProvider data_wp_is_large_network_filtered_by_network
		 */
		public function test_wp_is_large_network_filtered_by_network( $using, $count, $expected, $different_network ) {
			$network_id     = $different_network ? self::$different_network_id : null;
			$network_option = 'users' === $using ? 'user_count' : 'blog_count';

			update_network_option( $network_id, $network_option, $count );

			add_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_on_different_network' ), 10, 4 );
			$result = wp_is_large_network( $using, $network_id );
			remove_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_on_different_network' ), 10 );

			if ( $expected ) {
				$this->assertTrue( $result );
			} else {
				$this->assertFalse( $result );
			}
		}

		public function data_wp_is_large_network_filtered_by_network() {
			return array(
				array( 'sites', 10000, false, false ),
				array( 'sites', 10001, true, false ),
				array( 'users', 10000, false, false ),
				array( 'users', 10001, true, false ),
				array( 'sites', 1000, false, true ),
				array( 'sites', 1001, true, true ),
				array( 'users', 1000, false, true ),
				array( 'users', 1001, true, true ),
			);
		}

		public function filter_wp_is_large_network_on_different_network( $is_large_network, $using, $count, $network_id ) {
			if ( $network_id === (int) self::$different_network_id ) {
				return $count > 1000;
			}

			return $is_large_network;
		}

		/**
		 * @ticket 38699
		 */
		public function test_wpmu_create_blog_updates_correct_network_site_count() {
			global $wpdb;

			$original_count = get_blog_count( self::$different_network_id );

			$suppress = $wpdb->suppress_errors();
			$site_id  = wpmu_create_blog( 'example.org', '/', '', 1, array(), self::$different_network_id );
			$wpdb->suppress_errors( $suppress );

			$result = get_blog_count( self::$different_network_id );

			wpmu_delete_blog( $site_id, true );

			$this->assertSame( $original_count + 1, $result );
		}

		/**
		 * @ticket 29684
		 */
		public function test_network_blog_id_set() {
			$network = get_network( self::$different_network_id );

			$this->assertSame( (string) self::$different_site_ids[0], $network->blog_id );
		}

		/**
		 * @ticket 42251
		 */
		public function test_get_network_not_found_cache() {
			global $wpdb;

			$new_network_id = $this->_get_next_network_id();
			$this->assertNull( get_network( $new_network_id ) );

			$num_queries = $wpdb->num_queries;
			$this->assertNull( get_network( $new_network_id ) );
			$this->assertSame( $num_queries, $wpdb->num_queries );
		}

		/**
		 * @ticket 42251
		 */
		public function test_get_network_not_found_cache_clear() {
			$new_network_id = $this->_get_next_network_id();
			$this->assertNull( get_network( $new_network_id ) );

			$new_network = $this->factory()->network->create_and_get();

			// Double-check we got the ID of the new network correct.
			$this->assertSame( $new_network_id, $new_network->id );

			// Verify that if we fetch the network now, it's no longer false.
			$fetched_network = get_network( $new_network_id );
			$this->assertInstanceOf( 'WP_Network', $fetched_network );
			$this->assertSame( $new_network_id, $fetched_network->id );
		}

		/**
		 * Gets the ID of the site with the highest ID.
		 * @return int
		 */
		protected function _get_next_network_id() {
			global $wpdb;
			// Create an extra network, just to make sure we know the ID of the following one.
			static::factory()->network->create();
			return (int) $wpdb->get_var( 'SELECT id FROM ' . $wpdb->site . ' ORDER BY id DESC LIMIT 1' ) + 1;
		}
	}

endif;
