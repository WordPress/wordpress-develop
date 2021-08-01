<?php

/**
 * @group cache
 */
class Tests_Cache extends WP_UnitTestCase {
	public $cache = null;

	function setUp() {
		parent::setUp();
		// Create two cache objects with a shared cache directory.
		// This simulates a typical cache situation, two separate requests interacting.
		$this->cache =& $this->init_cache();
	}

	function tearDown() {
		$this->flush_cache();
		parent::tearDown();
	}

	function &init_cache() {
		global $wp_object_cache;
		$cache_class = get_class( $wp_object_cache );
		$cache       = new $cache_class();
		$cache->add_global_groups( array( 'global-cache-test', 'users', 'userlogins', 'usermeta', 'user_meta', 'useremail', 'userslugs', 'site-transient', 'site-options', 'blog-lookup', 'blog-details', 'rss', 'global-posts', 'blog-id-cache', 'networks', 'sites', 'site-details' ) );
		return $cache;
	}

	function test_miss() {
		$this->assertFalse( $this->cache->get( 'test_miss' ) );
	}

	function test_add_get() {
		$key = __FUNCTION__;
		$val = 'val';

		$this->cache->add( $key, $val );
		$this->assertSame( $val, $this->cache->get( $key ) );
	}

	function test_add_get_0() {
		$key = __FUNCTION__;
		$val = 0;

		// You can store zero in the cache.
		$this->assertTrue( $this->cache->add( $key, $val ) );
		$this->assertSame( $val, $this->cache->get( $key ) );
	}

	/**
	 * @ticket 20004
	 */
	function test_add_get_null() {
		$key = __FUNCTION__;
		$val = null;

		// You can store `null` in the cache.
		$this->assertTrue( $this->cache->add( $key, $val ) );
		$this->assertSame( $val, $this->cache->get( $key ) );
	}

	/**
	 * @ticket 20004
	 */
	function test_add_get_false() {
		$key = __FUNCTION__;
		$val = false;

		// You can store `false` in the cache.
		$this->assertTrue( $this->cache->add( $key, $val ) );
		$this->assertSame( $val, $this->cache->get( $key ) );
	}

	function test_add() {
		$key  = __FUNCTION__;
		$val1 = 'val1';
		$val2 = 'val2';

		// Add $key to the cache.
		$this->assertTrue( $this->cache->add( $key, $val1 ) );
		$this->assertSame( $val1, $this->cache->get( $key ) );
		// $key is in the cache, so reject new calls to add().
		$this->assertFalse( $this->cache->add( $key, $val2 ) );
		$this->assertSame( $val1, $this->cache->get( $key ) );
	}

	function test_replace() {
		$key  = __FUNCTION__;
		$val  = 'val1';
		$val2 = 'val2';

		// memcached rejects replace() if the key does not exist.
		$this->assertFalse( $this->cache->replace( $key, $val ) );
		$this->assertFalse( $this->cache->get( $key ) );
		$this->assertTrue( $this->cache->add( $key, $val ) );
		$this->assertSame( $val, $this->cache->get( $key ) );
		$this->assertTrue( $this->cache->replace( $key, $val2 ) );
		$this->assertSame( $val2, $this->cache->get( $key ) );
	}

	function test_set() {
		$key  = __FUNCTION__;
		$val1 = 'val1';
		$val2 = 'val2';

		// memcached accepts set() if the key does not exist.
		$this->assertTrue( $this->cache->set( $key, $val1 ) );
		$this->assertSame( $val1, $this->cache->get( $key ) );
		// Second set() with same key should be allowed.
		$this->assertTrue( $this->cache->set( $key, $val2 ) );
		$this->assertSame( $val2, $this->cache->get( $key ) );
	}

	function test_flush() {
		global $_wp_using_ext_object_cache;

		if ( $_wp_using_ext_object_cache ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		$key = __FUNCTION__;
		$val = 'val';

		$this->cache->add( $key, $val );
		// Item is visible to both cache objects.
		$this->assertSame( $val, $this->cache->get( $key ) );
		$this->cache->flush();
		// If there is no value get returns false.
		$this->assertFalse( $this->cache->get( $key ) );
	}

	// Make sure objects are cloned going to and from the cache.
	function test_object_refs() {
		$key           = __FUNCTION__ . '_1';
		$object_a      = new stdClass;
		$object_a->foo = 'alpha';
		$this->cache->set( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b      = $this->cache->get( $key );
		$this->assertSame( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertSame( 'bravo', $object_a->foo );

		$key           = __FUNCTION__ . '_2';
		$object_a      = new stdClass;
		$object_a->foo = 'alpha';
		$this->cache->add( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b      = $this->cache->get( $key );
		$this->assertSame( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertSame( 'bravo', $object_a->foo );
	}

	function test_incr() {
		$key = __FUNCTION__;

		$this->assertFalse( $this->cache->incr( $key ) );

		$this->cache->set( $key, 0 );
		$this->cache->incr( $key );
		$this->assertSame( 1, $this->cache->get( $key ) );

		$this->cache->incr( $key, 2 );
		$this->assertSame( 3, $this->cache->get( $key ) );
	}

	function test_wp_cache_incr() {
		$key = __FUNCTION__;

		$this->assertFalse( wp_cache_incr( $key ) );

		wp_cache_set( $key, 0 );
		wp_cache_incr( $key );
		$this->assertSame( 1, wp_cache_get( $key ) );

		wp_cache_incr( $key, 2 );
		$this->assertSame( 3, wp_cache_get( $key ) );
	}

	function test_decr() {
		$key = __FUNCTION__;

		$this->assertFalse( $this->cache->decr( $key ) );

		$this->cache->set( $key, 0 );
		$this->cache->decr( $key );
		$this->assertSame( 0, $this->cache->get( $key ) );

		$this->cache->set( $key, 3 );
		$this->cache->decr( $key );
		$this->assertSame( 2, $this->cache->get( $key ) );

		$this->cache->decr( $key, 2 );
		$this->assertSame( 0, $this->cache->get( $key ) );
	}

	/**
	 * @ticket 21327
	 */
	function test_wp_cache_decr() {
		$key = __FUNCTION__;

		$this->assertFalse( wp_cache_decr( $key ) );

		wp_cache_set( $key, 0 );
		wp_cache_decr( $key );
		$this->assertSame( 0, wp_cache_get( $key ) );

		wp_cache_set( $key, 3 );
		wp_cache_decr( $key );
		$this->assertSame( 2, wp_cache_get( $key ) );

		wp_cache_decr( $key, 2 );
		$this->assertSame( 0, wp_cache_get( $key ) );
	}

	function test_delete() {
		$key = __FUNCTION__;
		$val = 'val';

		// Verify set.
		$this->assertTrue( $this->cache->set( $key, $val ) );
		$this->assertSame( $val, $this->cache->get( $key ) );

		// Verify successful delete.
		$this->assertTrue( $this->cache->delete( $key ) );
		$this->assertFalse( $this->cache->get( $key ) );

		$this->assertFalse( $this->cache->delete( $key, 'default' ) );
	}

	function test_wp_cache_delete() {
		$key = __FUNCTION__;
		$val = 'val';

		// Verify set.
		$this->assertTrue( wp_cache_set( $key, $val ) );
		$this->assertSame( $val, wp_cache_get( $key ) );

		// Verify successful delete.
		$this->assertTrue( wp_cache_delete( $key ) );
		$this->assertFalse( wp_cache_get( $key ) );

		// wp_cache_delete() does not have a $force method.
		// Delete returns (bool) true when key is not set and $force is true.
		// $this->assertTrue( wp_cache_delete( $key, 'default', true ) );

		$this->assertFalse( wp_cache_delete( $key, 'default' ) );
	}

	function test_switch_to_blog() {
		if ( ! method_exists( $this->cache, 'switch_to_blog' ) ) {
			$this->markTestSkipped( 'This test requires a switch_to_blog() method on the cache object.' );
		}

		$key  = __FUNCTION__;
		$val  = 'val1';
		$val2 = 'val2';

		if ( ! is_multisite() ) {
			// Single site ingnores switch_to_blog().
			$this->assertTrue( $this->cache->set( $key, $val ) );
			$this->assertSame( $val, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( 999 );
			$this->assertSame( $val, $this->cache->get( $key ) );
			$this->assertTrue( $this->cache->set( $key, $val2 ) );
			$this->assertSame( $val2, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( get_current_blog_id() );
			$this->assertSame( $val2, $this->cache->get( $key ) );
		} else {
			// Multisite should have separate per-blog caches.
			$this->assertTrue( $this->cache->set( $key, $val ) );
			$this->assertSame( $val, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( 999 );
			$this->assertFalse( $this->cache->get( $key ) );
			$this->assertTrue( $this->cache->set( $key, $val2 ) );
			$this->assertSame( $val2, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( get_current_blog_id() );
			$this->assertSame( $val, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( 999 );
			$this->assertSame( $val2, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( get_current_blog_id() );
			$this->assertSame( $val, $this->cache->get( $key ) );
		}

		// Global group.
		$this->assertTrue( $this->cache->set( $key, $val, 'global-cache-test' ) );
		$this->assertSame( $val, $this->cache->get( $key, 'global-cache-test' ) );
		$this->cache->switch_to_blog( 999 );
		$this->assertSame( $val, $this->cache->get( $key, 'global-cache-test' ) );
		$this->assertTrue( $this->cache->set( $key, $val2, 'global-cache-test' ) );
		$this->assertSame( $val2, $this->cache->get( $key, 'global-cache-test' ) );
		$this->cache->switch_to_blog( get_current_blog_id() );
		$this->assertSame( $val2, $this->cache->get( $key, 'global-cache-test' ) );
	}

	function test_wp_cache_init() {
		$new_blank_cache_object = new WP_Object_Cache();
		wp_cache_init();

		global $wp_object_cache;

		if ( wp_using_ext_object_cache() ) {
			// External caches will contain property values that contain non-matching resource IDs.
			$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );
		} else {
			$this->assertEquals( $wp_object_cache, $new_blank_cache_object );
		}
	}

	function test_wp_cache_replace() {
		$key  = 'my-key';
		$val1 = 'first-val';
		$val2 = 'second-val';

		$fake_key = 'my-fake-key';

		// Save the first value to cache and verify.
		wp_cache_set( $key, $val1 );
		$this->assertSame( $val1, wp_cache_get( $key ) );

		// Replace the value and verify.
		wp_cache_replace( $key, $val2 );
		$this->assertSame( $val2, wp_cache_get( $key ) );

		// Non-existent key should fail.
		$this->assertFalse( wp_cache_replace( $fake_key, $val1 ) );

		// Make sure $fake_key is not stored.
		$this->assertFalse( wp_cache_get( $fake_key ) );
	}

	/**
	 * @ticket 20875
	 */
	public function test_get_multiple() {
		wp_cache_set( 'foo1', 'bar', 'group1' );
		wp_cache_set( 'foo2', 'bar', 'group1' );
		wp_cache_set( 'foo1', 'bar', 'group2' );

		$found = wp_cache_get_multiple( array( 'foo1', 'foo2', 'foo3' ), 'group1' );

		$expected = array(
			'foo1' => 'bar',
			'foo2' => 'bar',
			'foo3' => false,
		);

		$this->assertSame( $expected, $found );
	}
}
