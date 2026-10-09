<?php

/**
 * Tests for the WP_Site class.
 *
 * @group ms-required
 * @group ms-site
 * @group multisite
 *
 * @covers WP_Site::get_instance
 */
class Tests_Multisite_wpSite extends WP_UnitTestCase {

	/**
	 * ID of a site which exists in the database.
	 */
	protected static int $site_id;

	/**
	 * ID of a site which does not exist in the database.
	 */
	protected static int $nonexistent_site_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$site_id = $factory->blog->create(
			array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			)
		);

		self::$nonexistent_site_id = self::$site_id + 1000;
	}

	public static function wpTearDownAfterClass(): void {
		/*
		 * The object cache is flushed before each test, but not between the last test
		 * and here. A poisoned value left in the 'sites' group would make the get_site()
		 * call inside wp_delete_site() consider the site to no longer exist.
		 */
		wp_cache_delete( self::$site_id, 'sites' );

		wp_delete_site( self::$site_id );

		wp_update_network_site_counts();
	}

	/**
	 * Tests that a site ID which cannot reference a site returns false without querying the database.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @dataProvider data_get_instance_returns_false_for_an_empty_site_id
	 *
	 * @param mixed $site_id Site ID to look up.
	 */
	public function test_get_instance_returns_false_for_an_empty_site_id( $site_id ): void {
		global $wpdb;

		$num_queries = $wpdb->num_queries;

		$this->assertFalse( WP_Site::get_instance( $site_id ), 'A site object was returned.' ); // @phpstan-ignore argument.type (Intentionally passing a value which is not an integer.)
		$this->assertSame( $num_queries, $wpdb->num_queries, 'The database was queried.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ mixed }>
	 */
	public function data_get_instance_returns_false_for_an_empty_site_id(): array {
		return array(
			'zero as an integer'   => array( 0 ),
			'zero as a string'     => array( '0' ),
			'a non-numeric string' => array( 'not-a-site-id' ),
			'false'                => array( false ),
			'null'                 => array( null ),
		);
	}

	/**
	 * Tests that an uncached site is fetched from the database and then added to the object cache.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_get_instance_queries_the_database_when_the_site_is_not_cached(): void {
		global $wpdb;

		wp_cache_delete( self::$site_id, 'sites' );

		$num_queries = $wpdb->num_queries;

		$site = WP_Site::get_instance( self::$site_id );

		$this->assertInstanceOf( WP_Site::class, $site, 'A site object was not returned.' );
		$this->assertSame( (string) self::$site_id, $site->blog_id, 'The wrong site was returned.' );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries, 'The site was not fetched from the database.' );

		$cached = wp_cache_get( self::$site_id, 'sites' );

		$this->assertInstanceOf( stdClass::class, $cached, 'The database row was not added to the object cache.' );
		$this->assertSame( (string) self::$site_id, $cached->blog_id, 'The wrong site was added to the object cache.' );
	}

	/**
	 * Tests that a cached site is returned without querying the database.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_get_instance_does_not_query_the_database_when_the_site_is_cached(): void {
		global $wpdb;

		wp_cache_delete( self::$site_id, 'sites' );

		// Prime the object cache.
		WP_Site::get_instance( self::$site_id );

		$num_queries = $wpdb->num_queries;

		$site = WP_Site::get_instance( self::$site_id );

		$this->assertInstanceOf( WP_Site::class, $site, 'A site object was not returned.' );
		$this->assertSame( (string) self::$site_id, $site->blog_id, 'The wrong site was returned.' );
		$this->assertSame( $num_queries, $wpdb->num_queries, 'The database was queried.' );
	}

	/**
	 * Tests that a cached WP_Site object is treated as a cache hit.
	 *
	 * The object cache is normally primed with the raw database row, but a WP_Site
	 * instance may be cached by other means.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_get_instance_treats_a_cached_wp_site_object_as_a_cache_hit(): void {
		global $wpdb;

		wp_cache_set( self::$site_id, WP_Site::get_instance( self::$site_id ), 'sites' );

		$num_queries = $wpdb->num_queries;

		$site = WP_Site::get_instance( self::$site_id );

		$this->assertInstanceOf( WP_Site::class, $site, 'A site object was not returned.' );
		$this->assertSame( (string) self::$site_id, $site->blog_id, 'The wrong site was returned.' );
		$this->assertSame( $num_queries, $wpdb->num_queries, 'The database was queried.' );
	}

	/**
	 * Tests that a site which is not in the database returns false and that the miss is cached.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_get_instance_returns_false_for_a_nonexistent_site_and_caches_the_miss(): void {
		global $wpdb;

		wp_cache_delete( self::$nonexistent_site_id, 'sites' );

		$num_queries = $wpdb->num_queries;

		$this->assertFalse( WP_Site::get_instance( self::$nonexistent_site_id ), 'A site object was returned.' );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries, 'The database was not queried.' );
		$this->assertSame( -1, wp_cache_get( self::$nonexistent_site_id, 'sites' ), 'The miss was not cached as -1.' );
	}

	/**
	 * Tests that a cached miss is not looked up in the database again.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_get_instance_does_not_query_the_database_for_a_cached_miss(): void {
		global $wpdb;

		wp_cache_delete( self::$nonexistent_site_id, 'sites' );

		// Prime the object cache with the miss.
		WP_Site::get_instance( self::$nonexistent_site_id );

		$num_queries = $wpdb->num_queries;

		$this->assertFalse( WP_Site::get_instance( self::$nonexistent_site_id ), 'A site object was returned.' );
		$this->assertSame( $num_queries, $wpdb->num_queries, 'The database was queried.' );
	}

	/**
	 * Tests that a cached value which is neither a site object nor the miss sentinel is treated as a cache miss.
	 *
	 * @ticket 65962
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss( $cache_value ): void {
		global $wpdb;

		wp_cache_set( self::$site_id, $cache_value, 'sites' );

		$num_queries = $wpdb->num_queries;

		$site = WP_Site::get_instance( self::$site_id );

		$this->assertInstanceOf( WP_Site::class, $site, 'A site object was not returned.' );
		$this->assertSame( (string) self::$site_id, $site->blog_id, 'The wrong site was returned.' );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries, 'The site was not fetched from the database.' );
	}

	/**
	 * Tests that the refetched site replaces the poisoned cache value.
	 *
	 * Otherwise the poisoned value survives and every subsequent lookup queries the database again.
	 *
	 * @ticket 65962
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_replaces_a_poisoned_cache_value( $cache_value ): void {
		global $wpdb;

		wp_cache_set( self::$site_id, $cache_value, 'sites' );

		// Prime the object cache, replacing the poisoned value.
		WP_Site::get_instance( self::$site_id );

		$cached = wp_cache_get( self::$site_id, 'sites' );

		$this->assertInstanceOf( stdClass::class, $cached, 'The poisoned value was not replaced in the object cache.' );
		$this->assertSame( (string) self::$site_id, $cached->blog_id, 'The wrong site was added to the object cache.' );

		$num_queries = $wpdb->num_queries;

		$site = WP_Site::get_instance( self::$site_id );

		$this->assertInstanceOf( WP_Site::class, $site, 'A site object was not returned.' );
		$this->assertSame( (string) self::$site_id, $site->blog_id, 'The wrong site was returned.' );
		$this->assertSame( $num_queries, $wpdb->num_queries, 'The database was queried again.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ mixed }>
	 */
	public function data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss(): array {
		return array(
			'true'                      => array( true ),
			'a non-numeric string'      => array( 'not-a-site' ),
			'an empty array'            => array( array() ),
			'an array of site data'     => array(
				array(
					'blog_id' => '1',
					'domain'  => 'wordpress.org',
					'path'    => '/',
				),
			),
			'an object without blog_id' => array(
				(object) array(
					'domain' => 'wordpress.org',
					'path'   => '/',
				),
			),
		);
	}

	/**
	 * Tests that any numeric cached value is treated as a cached miss.
	 *
	 * Only -1 is written to the cache to record a miss, but every numeric value is
	 * currently trusted as one, so an existing site is reported as not found.
	 *
	 * @ticket 65962
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @dataProvider data_get_instance_treats_a_numeric_cache_value_as_a_cached_miss
	 *
	 * @param int|numeric-string $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_treats_a_numeric_cache_value_as_a_cached_miss( $cache_value ): void {
		global $wpdb;

		wp_cache_set( self::$site_id, $cache_value, 'sites' );

		$num_queries = $wpdb->num_queries;

		$this->assertFalse( WP_Site::get_instance( self::$site_id ), 'A site object was returned.' );
		$this->assertSame( $num_queries, $wpdb->num_queries, 'The database was queried.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ int|numeric-string }>
	 */
	public function data_get_instance_treats_a_numeric_cache_value_as_a_cached_miss(): array {
		return array(
			'the -1 miss sentinel' => array( -1 ),
			'-1 as a string'       => array( '-1' ),
			'zero'                 => array( 0 ),
			'a positive integer'   => array( 42 ),
			'a float as a string'  => array( '3.5' ),
		);
	}
}
