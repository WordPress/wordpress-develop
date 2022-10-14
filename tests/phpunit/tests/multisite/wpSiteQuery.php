<?php

if ( is_multisite() ) :

	/**
	 * Test site query functionality in multisite.
	 *
	 * @group ms-site
	 * @group multisite
	 */
	class Tests_Multisite_wpSiteQuery extends WP_UnitTestCase {
		protected static $network_ids;
		protected static $site_ids;

		public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
			self::$network_ids = array(
				'wordpress.org/'      => array(
					'domain' => 'wordpress.org',
					'path'   => '/',
				),
				'make.wordpress.org/' => array(
					'domain' => 'make.wordpress.org',
					'path'   => '/',
				),
				'www.wordpress.net/'  => array(
					'domain' => 'www.wordpress.net',
					'path'   => '/',
				),
			);

			foreach ( self::$network_ids as &$id ) {
				$id = $factory->network->create( $id );
			}
			unset( $id );

			self::$site_ids = array(
				'wordpress.org/'          => array(
					'domain'     => 'wordpress.org',
					'path'       => '/',
					'network_id' => self::$network_ids['wordpress.org/'],
				),
				'wordpress.org/foo/'      => array(
					'domain'     => 'wordpress.org',
					'path'       => '/foo/',
					'network_id' => self::$network_ids['wordpress.org/'],
				),
				'wordpress.org/foo/bar/'  => array(
					'domain'     => 'wordpress.org',
					'path'       => '/foo/bar/',
					'network_id' => self::$network_ids['wordpress.org/'],
				),
				'make.wordpress.org/'     => array(
					'domain'     => 'make.wordpress.org',
					'path'       => '/',
					'network_id' => self::$network_ids['make.wordpress.org/'],
				),
				'make.wordpress.org/foo/' => array(
					'domain'     => 'make.wordpress.org',
					'path'       => '/foo/',
					'network_id' => self::$network_ids['make.wordpress.org/'],
				),
				'www.w.org/'              => array(
					'domain' => 'www.w.org',
					'path'   => '/',
				),
				'www.w.org/foo/'          => array(
					'domain' => 'www.w.org',
					'path'   => '/foo/',
				),
				'www.w.org/foo/bar/'      => array(
					'domain' => 'www.w.org',
					'path'   => '/foo/bar/',
				),
				'www.w.org/make/'         => array(
					'domain'  => 'www.w.org',
					'path'    => '/make/',
					'public'  => 1,
					'lang_id' => 1,
				),
			);

			foreach ( self::$site_ids as &$id ) {
				$id = $factory->blog->create( $id );
			}
			unset( $id );
		}

		public static function wpTearDownAfterClass() {
			global $wpdb;

			foreach ( self::$site_ids as $id ) {
				wp_delete_site( $id );
			}

			foreach ( self::$network_ids as $id ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", $id ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id= %d", $id ) );
			}

			wp_update_network_site_counts();
		}

		public function test_wp_site_query_by_ID() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'ID'     => self::$site_ids['www.w.org/'],
				)
			);

			$this->assertSameSets( array( self::$site_ids['www.w.org/'] ), $found );
		}

		public function test_wp_site_query_by_number() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'number' => 3,
				)
			);

			$this->assertCount( 3, $found );
		}

		public function test_wp_site_query_by_site__in_with_single_id() {
			$expected = array( self::$site_ids['wordpress.org/foo/'] );

			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'   => 'ids',
					'site__in' => $expected,
				)
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_site__in_with_multiple_ids() {
			$expected = array( self::$site_ids['wordpress.org/'], self::$site_ids['wordpress.org/foo/'] );

			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'   => 'ids',
					'site__in' => $expected,
				)
			);

			$this->assertSameSets( $expected, $found );
		}

		/**
		 * Test the `count` query var
		 */
		public function test_wp_site_query_by_site__in_and_count_with_multiple_ids() {
			$expected = array( self::$site_ids['wordpress.org/'], self::$site_ids['wordpress.org/foo/'] );

			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'   => 'ids',
					'count'    => true,
					'site__in' => $expected,
				)
			);

			$this->assertSame( 2, $found );
		}

		public function test_wp_site_query_by_site__not_in_with_single_id() {
			$excluded = array( self::$site_ids['wordpress.org/foo/'] );
			$expected = array_diff( self::$site_ids, $excluded );

			// Exclude main site since we don't have control over it here.
			$excluded[] = 1;

			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					'site__not_in' => $excluded,
				)
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_site__not_in_with_multiple_ids() {
			$excluded = array( self::$site_ids['wordpress.org/'], self::$site_ids['wordpress.org/foo/'] );
			$expected = array_diff( self::$site_ids, $excluded );

			// Exclude main site since we don't have control over it here.
			$excluded[] = 1;

			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					'site__not_in' => $excluded,
				)
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_network_id_with_order() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'ASC',
				)
			);

			$expected = array(
				self::$site_ids['wordpress.org/'],
				self::$site_ids['wordpress.org/foo/'],
				self::$site_ids['wordpress.org/foo/bar/'],
			);

			$this->assertSame( $expected, $found );

			$found = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'DESC',
				)
			);

			$this->assertSame( array_reverse( $expected ), $found );
		}

		public function test_wp_site_query_by_network_id_with_existing_sites() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['make.wordpress.org/'],
				)
			);

			$expected = array(
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_network_id_with_no_existing_sites() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['www.wordpress.net/'],
				)
			);

			$this->assertEmpty( $found );
		}

		public function test_wp_site_query_by_domain() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'domain' => 'www.w.org',
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/'],
				self::$site_ids['www.w.org/foo/'],
				self::$site_ids['www.w.org/foo/bar/'],
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_domain_and_offset() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'domain' => 'www.w.org',
					'offset' => 1,
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/foo/'],
				self::$site_ids['www.w.org/foo/bar/'],
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_domain_and_number_and_offset() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'domain' => 'www.w.org',
					'number' => 2,
					'offset' => 1,
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/foo/'],
				self::$site_ids['www.w.org/foo/bar/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_domain__in_with_single_domain() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'     => 'ids',
					'domain__in' => array( 'make.wordpress.org' ),
				)
			);

			$expected = array(
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_domain__in_with_multiple_domains() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'     => 'ids',
					'domain__in' => array( 'wordpress.org', 'make.wordpress.org' ),
				)
			);

			$expected = array(
				self::$site_ids['wordpress.org/'],
				self::$site_ids['wordpress.org/foo/'],
				self::$site_ids['wordpress.org/foo/bar/'],
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_domain__not_in_with_single_domain() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'         => 'ids',
					'domain__not_in' => array( 'www.w.org' ),
				)
			);

			$expected = array(
				get_current_blog_id(), // Account for the initial site added by the test suite.
				self::$site_ids['wordpress.org/'],
				self::$site_ids['wordpress.org/foo/'],
				self::$site_ids['wordpress.org/foo/bar/'],
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_domain__not_in_with_multiple_domains() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'         => 'ids',
					'domain__not_in' => array( 'wordpress.org', 'www.w.org' ),
				)
			);

			$expected = array(
				get_current_blog_id(), // Account for the initial site added by the test suite.
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_path_with_expected_results() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'path'   => '/foo/bar/',
				)
			);

			$expected = array(
				self::$site_ids['wordpress.org/foo/bar/'],
				self::$site_ids['www.w.org/foo/bar/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_path_with_no_expected_results() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'path'   => '/foo/bar/foo/',
				)
			);

			$this->assertEmpty( $found );
		}

		// archived, mature, spam, deleted, public.

		public function test_wp_site_query_by_archived() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					// Exclude main site since we don't have control over it here.
					'site__not_in' => array( 1 ),
					'archived'     => '0',
				)
			);

			$this->assertSameSets( array_values( self::$site_ids ), $found );
		}

		public function test_wp_site_query_by_mature() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					// Exclude main site since we don't have control over it here.
					'site__not_in' => array( 1 ),
					'mature'       => '0',
				)
			);

			$this->assertSameSets( array_values( self::$site_ids ), $found );
		}

		public function test_wp_site_query_by_spam() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					// Exclude main site since we don't have control over it here.
					'site__not_in' => array( 1 ),
					'spam'         => '0',
				)
			);

			$this->assertSameSets( array_values( self::$site_ids ), $found );
		}

		public function test_wp_site_query_by_deleted() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					// Exclude main site since we don't have control over it here.
					'site__not_in' => array( 1 ),
					'deleted'      => '0',
				)
			);

			$this->assertSameSets( array_values( self::$site_ids ), $found );
		}

		public function test_wp_site_query_by_deleted_with_no_results() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'  => 'ids',
					'deleted' => '1',
				)
			);

			$this->assertEmpty( $found );
		}

		public function test_wp_site_query_by_public() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					// Exclude main site since we don't have control over it here.
					'site__not_in' => array( 1 ),
					'public'       => '1',
				)
			);

			$this->assertSameSets( array_values( self::$site_ids ), $found );
		}

		public function test_wp_site_query_by_lang_id_with_zero() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					// Exclude main site since we don't have control over it here.
					'site__not_in' => array( 1 ),
					'lang_id'      => 0,
				)
			);

			$this->assertSameSets( array_diff( array_values( self::$site_ids ), array( self::$site_ids['www.w.org/make/'] ) ), $found );
		}

		public function test_wp_site_query_by_lang_id() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'  => 'ids',
					'lang_id' => 1,
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_lang_id_with_no_results() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'  => 'ids',
					'lang_id' => 2,
				)
			);

			$this->assertEmpty( $found );
		}

		public function test_wp_site_query_by_lang__in() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'   => 'ids',
					'lang__in' => array( 1 ),
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_lang__in_with_multiple_ids() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					// Exclude main site since we don't have control over it here.
					'site__not_in' => array( 1 ),
					'lang__in'     => array( 0, 1 ),
				)
			);

			$this->assertSameSets( array_values( self::$site_ids ), $found );
		}

		public function test_wp_site_query_by_lang__not_in() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					'lang__not_in' => array( 0 ),
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_lang__not_in_with_multiple_ids() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'       => 'ids',
					'lang__not_in' => array( 0, 1 ),
				)
			);

			$this->assertEmpty( $found );
		}

		public function test_wp_site_query_by_search_with_text_in_domain() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'search' => 'ke.wordp',
				)
			);

			$expected = array(
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_text_in_path() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'search' => 'foo',
				)
			);

			$expected = array(
				self::$site_ids['wordpress.org/foo/'],
				self::$site_ids['wordpress.org/foo/bar/'],
				self::$site_ids['make.wordpress.org/foo/'],
				self::$site_ids['www.w.org/foo/'],
				self::$site_ids['www.w.org/foo/bar/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_text_in_path_and_domain() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'search' => 'make',
				)
			);

			$expected = array(
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_text_in_path_and_domain_order_by_domain_desc() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'  => 'ids',
					'search'  => 'make',
					'order'   => 'DESC',
					'orderby' => 'domain',
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/make/'],
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSame( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_text_in_path_exclude_domain_from_search() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'         => 'ids',
					'search'         => 'make',
					'search_columns' => array( 'path' ),
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSame( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_text_in_domain_exclude_path_from_search() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'         => 'ids',
					'search'         => 'make',
					'search_columns' => array( 'domain' ),
				)
			);

			$expected = array(
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSame( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_wildcard_in_text() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields' => 'ids',
					'search' => 'm*ke',
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/make/'],
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_wildcard_in_text_exclude_path_from_search() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'         => 'ids',
					'search'         => 'm*ke',
					'search_columns' => array( 'domain' ),
				)
			);

			$expected = array(
				self::$site_ids['make.wordpress.org/'],
				self::$site_ids['make.wordpress.org/foo/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		public function test_wp_site_query_by_search_with_wildcard_in_text_exclude_domain_from_search() {
			$q     = new WP_Site_Query();
			$found = $q->query(
				array(
					'fields'         => 'ids',
					'search'         => 'm*ke',
					'search_columns' => array( 'path' ),
				)
			);

			$expected = array(
				self::$site_ids['www.w.org/make/'],
			);

			$this->assertSameSets( $expected, $found );
		}

		/**
		 * @ticket 41197
		 */
		public function test_wp_site_query_cache_with_different_fields_no_count() {
			global $wpdb;
			$q                 = new WP_Site_Query();
			$query_1           = $q->query(
				array(
					'fields'     => 'all',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'ASC',
				)
			);
			$number_of_queries = $wpdb->num_queries;

			$query_2 = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'ASC',
				)
			);

			$this->assertSame( $number_of_queries, $wpdb->num_queries );
		}

		/**
		 * @ticket 41197
		 */
		public function test_wp_site_query_cache_with_different_fields_active_count() {
			global $wpdb;
			$q = new WP_Site_Query();

			$query_1           = $q->query(
				array(
					'fields'     => 'all',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'ASC',
					'count'      => true,
				)
			);
			$number_of_queries = $wpdb->num_queries;

			$query_2 = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'ASC',
					'count'      => true,
				)
			);
			$this->assertSame( $number_of_queries, $wpdb->num_queries );
		}

		/**
		 * @ticket 41197
		 */
		public function test_wp_site_query_cache_with_same_fields_different_count() {
			global $wpdb;
			$q = new WP_Site_Query();

			$query_1 = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'ASC',
				)
			);

			$number_of_queries = get_num_queries();

			$query_2 = $q->query(
				array(
					'fields'     => 'ids',
					'network_id' => self::$network_ids['wordpress.org/'],
					'number'     => 3,
					'order'      => 'ASC',
					'count'      => true,
				)
			);
			$this->assertSame( $number_of_queries + 1, $wpdb->num_queries );
		}

		/**
		 * @ticket 55462
		 */
		public function test_wp_site_query_cache_with_same_fields_same_cache_fields() {
			$q = new WP_Site_Query();

			$query_1 = $q->query(
				array(
					'fields'                 => 'ids',
					'network_id'             => self::$network_ids['wordpress.org/'],
					'number'                 => 3,
					'order'                  => 'ASC',
					'update_site_cache'      => true,
					'update_site_meta_cache' => true,
				)
			);

			$number_of_queries = get_num_queries();

			$query_2 = $q->query(
				array(
					'fields'                 => 'ids',
					'network_id'             => self::$network_ids['wordpress.org/'],
					'number'                 => 3,
					'order'                  => 'ASC',
					'update_site_cache'      => true,
					'update_site_meta_cache' => true,
				)
			);
			$this->assertSame( $number_of_queries, get_num_queries() );
		}

		/**
		 * @ticket 55462
		 */
		public function test_wp_site_query_cache_with_same_fields_different_cache_fields() {
			$q = new WP_Site_Query();

			$query_1 = $q->query(
				array(
					'fields'                 => 'ids',
					'network_id'             => self::$network_ids['wordpress.org/'],
					'number'                 => 3,
					'order'                  => 'ASC',
					'update_site_cache'      => true,
					'update_site_meta_cache' => true,
				)
			);

			$number_of_queries = get_num_queries();

			$query_2 = $q->query(
				array(
					'fields'                 => 'ids',
					'network_id'             => self::$network_ids['wordpress.org/'],
					'number'                 => 3,
					'order'                  => 'ASC',
					'update_site_cache'      => false,
					'update_site_meta_cache' => false,
				)
			);
			$this->assertSame( $number_of_queries, get_num_queries() );
		}

		/**
		 * @ticket 40229
		 * @dataProvider data_wp_site_query_meta_query
		 */
		public function test_wp_site_query_meta_query( $query, $expected, $strict ) {
			if ( ! is_site_meta_supported() ) {
				$this->markTestSkipped( 'Test only runs with the blogmeta database table installed.' );
			}

			add_site_meta( self::$site_ids['wordpress.org/'], 'foo', 'foo' );
			add_site_meta( self::$site_ids['wordpress.org/foo/'], 'foo', 'bar' );
			add_site_meta( self::$site_ids['wordpress.org/foo/bar/'], 'foo', 'baz' );
			add_site_meta( self::$site_ids['make.wordpress.org/'], 'bar', 'baz' );
			add_site_meta( self::$site_ids['wordpress.org/'], 'numberfoo', 1 );
			add_site_meta( self::$site_ids['wordpress.org/foo/'], 'numberfoo', 2 );

			$query['fields'] = 'ids';

			$q     = new WP_Site_Query();
			$found = $q->query( $query );

			foreach ( $expected as $index => $domain_path ) {
				$expected[ $index ] = self::$site_ids[ $domain_path ];
			}

			if ( $strict ) {
				$this->assertSame( $expected, $found );
			} else {
				$this->assertSameSets( $expected, $found );
			}
		}

		public function data_wp_site_query_meta_query() {
			return array(
				array(
					array(
						'meta_key' => 'foo',
					),
					array(
						'wordpress.org/',
						'wordpress.org/foo/',
						'wordpress.org/foo/bar/',
					),
					false,
				),
				array(
					array(
						'meta_key'   => 'foo',
						'meta_value' => 'bar',
					),
					array(
						'wordpress.org/foo/',
					),
					false,
				),
				array(
					array(
						'meta_key'     => 'foo',
						'meta_value'   => array( 'bar', 'baz' ),
						'meta_compare' => 'IN',
					),
					array(
						'wordpress.org/foo/',
						'wordpress.org/foo/bar/',
					),
					false,
				),
				array(
					array(
						'meta_query' => array(
							array(
								'key'   => 'foo',
								'value' => 'bar',
							),
							array(
								'key'   => 'numberfoo',
								'value' => 2,
								'type'  => 'NUMERIC',
							),
						),
					),
					array(
						'wordpress.org/foo/',
					),
					false,
				),
				array(
					array(
						'meta_key' => 'foo',
						'orderby'  => 'meta_value',
						'order'    => 'ASC',
					),
					array(
						'wordpress.org/foo/',
						'wordpress.org/foo/bar/',
						'wordpress.org/',
					),
					true,
				),
				array(
					array(
						'meta_key' => 'foo',
						'orderby'  => 'foo',
						'order'    => 'ASC',
					),
					array(
						'wordpress.org/foo/',
						'wordpress.org/foo/bar/',
						'wordpress.org/',
					),
					true,
				),
				array(
					array(
						'meta_key' => 'numberfoo',
						'orderby'  => 'meta_value_num',
						'order'    => 'DESC',
					),
					array(
						'wordpress.org/foo/',
						'wordpress.org/',
					),
					true,
				),
				array(
					array(
						'meta_query' => array(
							array(
								'key'     => 'foo',
								'value'   => array( 'foo', 'bar' ),
								'compare' => 'IN',
							),
							array(
								'key' => 'numberfoo',
							),
						),
						'orderby'    => array( 'meta_value' => 'ASC' ),
					),
					array(
						'wordpress.org/foo/',
						'wordpress.org/',
					),
					true,
				),
				array(
					array(
						'meta_query' => array(
							array(
								'key'     => 'foo',
								'value'   => array( 'foo', 'bar' ),
								'compare' => 'IN',
							),
							array(
								'key' => 'numberfoo',
							),
						),
						'orderby'    => array( 'foo' => 'ASC' ),
					),
					array(
						'wordpress.org/foo/',
						'wordpress.org/',
					),
					true,
				),
				array(
					array(
						'meta_query' => array(
							array(
								'key'     => 'foo',
								'value'   => array( 'foo', 'bar' ),
								'compare' => 'IN',
							),
							'my_subquery' => array(
								'key' => 'numberfoo',
							),
						),
						'orderby'    => array( 'my_subquery' => 'DESC' ),
					),
					array(
						'wordpress.org/foo/',
						'wordpress.org/',
					),
					true,
				),
			);
		}

		/**
		 * @ticket 45749
		 * @ticket 47599
		 */
		public function test_sites_pre_query_filter_should_bypass_database_query() {
			global $wpdb;

			add_filter( 'sites_pre_query', array( __CLASS__, 'filter_sites_pre_query' ), 10, 2 );

			$num_queries = $wpdb->num_queries;

			$q       = new WP_Site_Query();
			$results = $q->query( array() );

			remove_filter( 'sites_pre_query', array( __CLASS__, 'filter_sites_pre_query' ), 10, 2 );

			// Make sure no queries were executed.
			$this->assertSame( $num_queries, $wpdb->num_queries );

			// We manually inserted a non-existing site and overrode the results with it.
			$this->assertSame( array( 555 ), $results );

			// Make sure manually setting found_sites doesn't get overwritten.
			$this->assertSame( 1, $q->found_sites );
		}

		public static function filter_sites_pre_query( $sites, $query ) {
			$query->found_sites = 1;

			return array( 555 );
		}

		/**
		 * @ticket 51333
		 */
		public function test_sites_pre_query_filter_should_set_sites_property() {
			add_filter( 'sites_pre_query', array( __CLASS__, 'filter_sites_pre_query_and_set_sites' ), 10, 2 );

			$q       = new WP_Site_Query();
			$results = $q->query( array() );

			remove_filter( 'sites_pre_query', array( __CLASS__, 'filter_sites_pre_query_and_set_sites' ), 10 );

			// Make sure the sites property is the same as the results.
			$this->assertSame( $results, $q->sites );

			// Make sure the site domain is `wordpress.org`.
			$this->assertSame( 'wordpress.org', $q->sites[0]->domain );
		}

		public static function filter_sites_pre_query_and_set_sites( $sites, $query ) {
			return array( get_site( self::$site_ids['wordpress.org/'] ) );
		}
	}

endif;
