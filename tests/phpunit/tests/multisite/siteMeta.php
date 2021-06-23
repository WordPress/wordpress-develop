<?php

if ( is_multisite() ) :

	/**
	 * @group ms-site
	 * @group multisite
	 * @group meta
	 * @ticket 37923
	 */
	class Tests_Multisite_Site_Meta extends WP_UnitTestCase {
		protected static $site_id;
		protected static $site_id2;
		protected static $flag_was_set;

		public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
			self::$site_id  = $factory->blog->create(
				array(
					'domain' => 'wordpress.org',
					'path'   => '/',
				)
			);
			self::$site_id2 = $factory->blog->create(
				array(
					'domain' => 'wordpress.org',
					'path'   => '/foo/',
				)
			);

			// Populate the main network flag as necessary.
			self::$flag_was_set = true;
			if ( false === get_network_option( get_main_network_id(), 'site_meta_supported', false ) ) {
				self::$flag_was_set = false;
				is_site_meta_supported();
			}
		}

		public static function wpTearDownAfterClass() {
			// Delete the possibly previously populated main network flag.
			if ( ! self::$flag_was_set ) {
				delete_network_option( get_main_network_id(), 'site_meta_supported' );
			}

			wp_delete_site( self::$site_id );
			wp_delete_site( self::$site_id2 );

			wp_update_network_site_counts();
		}

		public function test_is_site_meta_supported() {
			$this->assertTrue( is_site_meta_supported() );
		}

		public function test_is_site_meta_supported_filtered() {
			add_filter( 'pre_site_option_site_meta_supported', '__return_zero' );
			$this->assertFalse( is_site_meta_supported() );
		}

		public function test_add() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			$this->assertNotEmpty( add_site_meta( self::$site_id, 'foo', 'bar' ) );
			$this->assertSame( 'bar', get_site_meta( self::$site_id, 'foo', true ) );
		}

		public function test_add_unique() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			$this->assertNotEmpty( add_site_meta( self::$site_id, 'foo', 'bar' ) );
			$this->assertFalse( add_site_meta( self::$site_id, 'foo', 'bar', true ) );
		}

		public function test_delete() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );

			$this->assertTrue( delete_site_meta( self::$site_id, 'foo' ) );
			$this->assertEmpty( get_site_meta( self::$site_id, 'foo', true ) );
		}

		public function test_delete_with_invalid_meta_key_should_return_false() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			$this->assertFalse( delete_site_meta( self::$site_id, 'foo' ) );
		}

		public function test_delete_should_respect_meta_value() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );
			add_site_meta( self::$site_id, 'foo', 'baz' );

			$this->assertTrue( delete_site_meta( self::$site_id, 'foo', 'bar' ) );

			$metas = get_site_meta( self::$site_id, 'foo' );
			$this->assertSame( array( 'baz' ), $metas );
		}

		public function test_get_with_no_key_should_fetch_all_keys() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );
			add_site_meta( self::$site_id, 'foo1', 'baz' );

			$found    = get_site_meta( self::$site_id );
			$expected = array(
				'foo'  => array( 'bar' ),
				'foo1' => array( 'baz' ),
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_get_with_key_should_fetch_all_for_key() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );
			add_site_meta( self::$site_id, 'foo', 'baz' );
			add_site_meta( self::$site_id, 'foo1', 'baz' );

			$found    = get_site_meta( self::$site_id, 'foo' );
			$expected = array( 'bar', 'baz' );

			$this->assertSameSets( $expected, $found );
		}

		public function test_get_should_respect_single_true() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );
			add_site_meta( self::$site_id, 'foo', 'baz' );

			$found = get_site_meta( self::$site_id, 'foo', true );
			$this->assertSame( 'bar', $found );
		}

		public function test_update_should_pass_to_add_when_no_value_exists_for_key() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			$actual = update_site_meta( self::$site_id, 'foo', 'bar' );
			$this->assertIsInt( $actual );
			$this->assertNotEmpty( $actual );

			$meta = get_site_meta( self::$site_id, 'foo', true );
			$this->assertSame( 'bar', $meta );
		}

		public function test_update_should_return_true_when_updating_existing_value_for_key() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );

			$actual = update_site_meta( self::$site_id, 'foo', 'baz' );
			$this->assertTrue( $actual );

			$meta = get_site_meta( self::$site_id, 'foo', true );
			$this->assertSame( 'baz', $meta );
		}

		public function test_delete_by_key() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'unique_delete_by_key', 'value', true );
			add_site_meta( self::$site_id2, 'unique_delete_by_key', 'value', true );

			$this->assertSame( 'value', get_site_meta( self::$site_id, 'unique_delete_by_key', true ) );
			$this->assertSame( 'value', get_site_meta( self::$site_id2, 'unique_delete_by_key', true ) );

			$this->assertTrue( delete_site_meta_by_key( 'unique_delete_by_key' ) );

			$this->assertSame( '', get_site_meta( self::$site_id, 'unique_delete_by_key', true ) );
			$this->assertSame( '', get_site_meta( self::$site_id2, 'unique_delete_by_key', true ) );
		}

		public function test_site_meta_should_be_deleted_when_site_is_deleted() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			$site_id = self::factory()->blog->create(
				array(
					'domain' => 'foo.org',
					'path'   => '/',
				)
			);

			add_site_meta( $site_id, 'foo', 'bar' );
			add_site_meta( $site_id, 'foo1', 'bar' );

			$this->assertSame( 'bar', get_site_meta( $site_id, 'foo', true ) );
			$this->assertSame( 'bar', get_site_meta( $site_id, 'foo1', true ) );

			wp_delete_site( $site_id );

			$this->assertSame( '', get_site_meta( $site_id, 'foo', true ) );
			$this->assertSame( '', get_site_meta( $site_id, 'foo1', true ) );
		}

		public function test_update_site_meta_cache() {
			global $wpdb;

			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			update_site_meta( self::$site_id, 'foo', 'bar' );
			update_sitemeta_cache( array( self::$site_id ) );

			$num_queries = $wpdb->num_queries;
			get_site_meta( self::$site_id, 'foo', true );
			$this->assertSame( $num_queries, $wpdb->num_queries );
		}

		public function test_query_update_site_meta_cache_true() {
			global $wpdb;

			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			update_site_meta( self::$site_id, 'foo', 'bar' );

			// Do not include 'update_site_meta_cache' as true as its the default.
			new WP_Site_Query(
				array(
					'ID' => self::$site_id,
				)
			);

			$num_queries = $wpdb->num_queries;
			get_site_meta( self::$site_id, 'foo', true );
			$this->assertSame( $num_queries, $wpdb->num_queries );
		}

		public function test_query_update_site_meta_cache_false() {
			global $wpdb;

			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			update_site_meta( self::$site_id, 'foo', 'bar' );

			new WP_Site_Query(
				array(
					'ID'                     => self::$site_id,
					'update_site_meta_cache' => false,
				)
			);

			$num_queries = $wpdb->num_queries;
			get_site_meta( self::$site_id, 'foo', true );
			$this->assertSame( $num_queries + 1, $wpdb->num_queries );
		}

		/**
		 * @ticket 40229
		 */
		public function test_add_site_meta_should_bust_get_sites_cache() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );

			// Prime cache.
			$found = get_sites(
				array(
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'foo',
							'value' => 'bar',
						),
					),
				)
			);

			$this->assertSameSets( array( self::$site_id ), $found );

			add_site_meta( self::$site_id2, 'foo', 'bar' );

			$found = get_sites(
				array(
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'foo',
							'value' => 'bar',
						),
					),
				)
			);

			$this->assertSameSets( array( self::$site_id, self::$site_id2 ), $found );
		}

		/**
		 * @ticket 40229
		 */
		public function test_update_site_meta_should_bust_get_sites_cache() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );
			add_site_meta( self::$site_id2, 'foo', 'baz' );

			// Prime cache.
			$found = get_sites(
				array(
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'foo',
							'value' => 'bar',
						),
					),
				)
			);

			$this->assertSameSets( array( self::$site_id ), $found );

			update_site_meta( self::$site_id2, 'foo', 'bar' );

			$found = get_sites(
				array(
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'foo',
							'value' => 'bar',
						),
					),
				)
			);

			$this->assertSameSets( array( self::$site_id, self::$site_id2 ), $found );
		}

		/**
		 * @ticket 40229
		 */
		public function test_delete_site_meta_should_bust_get_sites_cache() {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_id, 'foo', 'bar' );
			add_site_meta( self::$site_id2, 'foo', 'bar' );

			// Prime cache.
			$found = get_sites(
				array(
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'foo',
							'value' => 'bar',
						),
					),
				)
			);

			$this->assertSameSets( array( self::$site_id, self::$site_id2 ), $found );

			delete_site_meta( self::$site_id2, 'foo', 'bar' );

			$found = get_sites(
				array(
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'foo',
							'value' => 'bar',
						),
					),
				)
			);

			$this->assertSameSets( array( self::$site_id ), $found );
		}
	}

endif;
