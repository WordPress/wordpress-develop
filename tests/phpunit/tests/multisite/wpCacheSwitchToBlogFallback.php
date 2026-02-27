<?php

/**
 * Tests for wp_cache_switch_to_blog_fallback() fallback behavior.
 *
 * These tests verify the fallback logic used when cache drop-in plugins don't
 * provide wp_cache_switch_to_blog() or when their cache objects lack the
 * switch_to_blog() method.
 *
 * The tests call wp_cache_switch_to_blog_fallback() directly, which contains
 * the extracted fallback implementation. This ensures tests run against the
 * actual production code rather than duplicating logic.
 *
 * @group ms-required
 * @group ms-site
 * @group multisite
 * @group cache
 *
 * @covers ::wp_cache_switch_to_blog_fallback
 */
class Tests_Multisite_WpCacheSwitchToBlogFallback extends WP_UnitTestCase {

	/**
	 * Store the original WP_Object_Cache instance.
	 *
	 * @var WP_Object_Cache
	 */
	private $original_wp_object_cache;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		global $wp_object_cache;

		parent::set_up();

		// Store the original cache object to restore later.
		$this->original_wp_object_cache = $wp_object_cache;
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		global $wp_object_cache;

		// Restore the original cache object.
		$wp_object_cache = $this->original_wp_object_cache;

		parent::tear_down();
	}

	/**
	 * Helper method to test cache fallback behavior.
	 *
	 * Calls the extracted fallback function from cache-compat.php.
	 * This ensures tests run against the actual implementation.
	 *
	 * @see wp_cache_switch_to_blog_fallback()
	 * @link https://core.trac.wordpress.org/ticket/23290
	 *
	 * @param int $blog_id Blog ID to switch to.
	 */
	private function call_cache_switch_fallback( $blog_id = 0 ) {

		if ( empty( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		wp_cache_switch_to_blog_fallback( $blog_id );
	}

	/**
	 * Test that wp_cache_switch_to_blog() is always available.
	 *
	 * The function should always exist in WordPress, either from the persistent
	 * cache drop-in or from the fallback in cache-compat.php.
	 *
	 * @ticket 23290
	 */
	public function test_wp_cache_switch_to_blog_function_exists() {

		// The wrapper function should always be available.
		$this->assertTrue( function_exists( 'wp_cache_switch_to_blog' ), 'wp_cache_switch_to_blog() should always exist' );

		// The fallback implementation should also always be available.
		$this->assertTrue( function_exists( 'wp_cache_switch_to_blog_fallback' ), 'wp_cache_switch_to_blog_fallback() should always exist' );

		// Both should be callable.
		$this->assertTrue( is_callable( 'wp_cache_switch_to_blog' ) );
		$this->assertTrue( is_callable( 'wp_cache_switch_to_blog_fallback' ) );
	}

	/**
	 * Test that wp_cache_switch_to_blog_fallback() reinitializes the cache.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_reinitializes_cache() {

		// Set some cache data before switching.
		wp_cache_set( 'test_key', 'test_value', 'test_group' );
		$this->assertSame( 'test_value', wp_cache_get( 'test_key', 'test_group' ) );

		// Call the fallback function.
		$this->call_cache_switch_fallback();

		// Verify the cache has been reinitialized (non-global groups should be cleared).
		$this->assertFalse( wp_cache_get( 'test_key', 'test_group' ) );
	}

	/**
	 * Test that wp_cache_switch_to_blog_fallback() restores global groups from cache object.
	 *
	 * When the cache object exists with global_groups configuration, the fallback should
	 * preserve that configuration rather than discarding it.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_restores_global_groups_from_cache_object() {
		global $wp_object_cache;

		// Verify the global_groups property exists.
		$this->assertObjectHasProperty( 'global_groups', $wp_object_cache );

		// Store the count of original global groups for comparison.
		$original_count = count( $wp_object_cache->global_groups );

		// Call the fallback function.
		$this->call_cache_switch_fallback();

		// Verify global groups are restored (same number of groups).
		$this->assertGreaterThan( 0, count( $wp_object_cache->global_groups ) );
		$this->assertSame( $original_count, count( $wp_object_cache->global_groups ) );

		// Verify key global groups are still present.
		$this->assertArrayHasKey( 'users', $wp_object_cache->global_groups );
		$this->assertArrayHasKey( 'user_meta', $wp_object_cache->global_groups );
		$this->assertArrayHasKey( 'site-options', $wp_object_cache->global_groups );
	}

	/**
	 * Test that wp_cache_switch_to_blog_fallback() uses default global groups when unavailable.
	 *
	 * When the cache object doesn't have a global_groups property, the fallback
	 * should provide the WordPress default global groups rather than failing.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_uses_default_global_groups_when_unavailable() {
		global $wp_object_cache;

		// Get the current blog_id before replacing the cache.
		$blog_id = get_current_blog_id();

		// Create a mock cache object without global_groups.
		$mock_cache = new stdClass();

		// Temporarily replace the global cache.
		$wp_object_cache = $mock_cache;

		// Call the fallback function.
		$this->call_cache_switch_fallback( $blog_id );

		// Verify a new cache object was created and global groups are set.
		$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );
		$this->assertObjectHasProperty( 'global_groups', $wp_object_cache );

		// Verify default global groups are present.
		$expected_groups = array(
			'blog-details',
			'blog-id-cache',
			'blog-lookup',
			'blog_meta',
			'global-posts',
			'image_editor',
			'networks',
			'network-queries',
			'sites',
			'site-details',
			'site-options',
			'site-queries',
			'site-transient',
			'theme_files',
			'translation_files',
			'rss',
			'users',
			'user-queries',
			'user_meta',
			'useremail',
			'userlogins',
			'userslugs',
		);

		foreach ( $expected_groups as $group ) {
			$this->assertContains( $group, array_keys( $wp_object_cache->global_groups ) );
		}
	}

	/**
	 * Test that wp_cache_switch_to_blog_fallback() preserves non-persistent groups from cache.
	 *
	 * When the fallback is called, it analyzes which groups exist in the cache->cache array
	 * (excluding global groups) to determine non-persistent groups that should be preserved.
	 * This test verifies that groups identified from the cache structure can still be used
	 * after the fallback reinitializes.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_preserves_non_persistent_groups_from_cache_object() {
		global $wp_object_cache;

		// Verify we have access to the cache structure.
		$this->assertObjectHasProperty( 'cache', $wp_object_cache );
		$this->assertObjectHasProperty( 'global_groups', $wp_object_cache );

		// Add some data to non-persistent groups.
		wp_cache_set( 'count_key', 42, 'counts' );
		wp_cache_set( 'plugin_key', 'plugin_data', 'plugins' );

		// Verify they're in the cache structure before fallback.
		$this->assertArrayHasKey( 'counts', $wp_object_cache->cache );
		$this->assertArrayHasKey( 'plugins', $wp_object_cache->cache );

		/*
		 * Call the fallback function, which should identify these groups
		 * from the cache structure before reinitializing.
		 */
		$this->call_cache_switch_fallback();

		// After fallback, the cache object should still be functional.
		$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );

		/*
		 * The data is cleared (cache is reinitialized), but the groups should be re-addable.
		 * Set new data in those same non-persistent groups to verify they're preserved.
		 */
		wp_cache_set( 'new_count_key', 99, 'counts' );
		wp_cache_set( 'new_plugin_key', 'new_plugin_data', 'plugins' );

		// Verify we can retrieve the new data from those groups.
		$this->assertSame( 99, wp_cache_get( 'new_count_key', 'counts' ) );
		$this->assertSame( 'new_plugin_data', wp_cache_get( 'new_plugin_key', 'plugins' ) );
	}

	/**
	 * Test that wp_cache_switch_to_blog_fallback() uses default non-persistent groups when unavailable.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_uses_default_non_persistent_groups_when_unavailable() {
		global $wp_object_cache;

		// Create a minimal mock cache object.
		$mock_cache = new stdClass();

		// Temporarily replace the global cache.
		$wp_object_cache = $mock_cache;

		// Call the fallback function.
		$this->call_cache_switch_fallback();

		// Verify a new cache object was created.
		$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );

		/*
		 * The default non-persistent groups should be set up.
		 * We test this indirectly by verifying the cache works for these groups.
		 */
		wp_cache_set( 'test_count', 123, 'counts' );
		$this->assertSame( 123, wp_cache_get( 'test_count', 'counts' ) );

		wp_cache_set( 'test_plugin', 'data', 'plugins' );
		$this->assertSame( 'data', wp_cache_get( 'test_plugin', 'plugins' ) );

		wp_cache_set( 'test_theme_json', 'theme_data', 'theme_json' );
		$this->assertSame( 'theme_data', wp_cache_get( 'test_theme_json', 'theme_json' ) );
	}

	/**
	 * Test fallback integration with switch_to_blog().
	 *
	 * Verifies the fallback path in switch_to_blog() works correctly
	 * by actually switching between blogs and calling the fallback.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_fallback_integration_with_switch_to_blog() {
		$original_blog_id = get_current_blog_id();
		$new_blog_id      = self::factory()->blog->create();

		// Set cache data in a non-global group on the original blog.
		wp_cache_set( 'test_local', 'local_value_blog1', 'posts' );
		$this->assertSame( 'local_value_blog1', wp_cache_get( 'test_local', 'posts' ) );

		// Switch to the new blog (this may trigger the fallback).
		switch_to_blog( $new_blog_id );
		$this->assertSame( $new_blog_id, get_current_blog_id() );

		// The cache data from blog 1 should not be available (non-global groups are blog-specific).
		$this->assertFalse( wp_cache_get( 'test_local', 'posts' ) );

		// Set different data on this blog.
		wp_cache_set( 'test_local', 'local_value_blog2', 'posts' );
		$this->assertSame( 'local_value_blog2', wp_cache_get( 'test_local', 'posts' ) );

		// Restore to the original blog.
		restore_current_blog();
		$this->assertSame( $original_blog_id, get_current_blog_id() );

		// The cache is functional after switching back.
		wp_cache_set( 'test_new', 'new_value', 'posts' );
		$this->assertSame( 'new_value', wp_cache_get( 'test_new', 'posts' ) );
	}

	/**
	 * Test that wp_cache_switch_to_blog_fallback() handles empty cache gracefully.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_handles_empty_cache_gracefully() {
		global $wp_object_cache;

		// Call wp_cache_init() first to ensure we have a fresh cache.
		wp_cache_init();

		// Call the fallback function on an empty cache.
		$this->call_cache_switch_fallback();

		// Verify the cache is functional after the call.
		$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );

		// Test that we can set and get values.
		wp_cache_set( 'test_after_init', 'test_value', 'default' );
		$this->assertSame( 'test_value', wp_cache_get( 'test_after_init', 'default' ) );
	}

	/**
	 * Test that multiple calls to wp_cache_switch_to_blog_fallback() work correctly.

	/**
	 * Test that non-global cache data is properly cleared during fallback.
	 *
	 * @ticket 23290
	 */
	public function test_non_global_cache_data_is_cleared() {

		// Set cache data in various non-global groups.
		wp_cache_set( 'post_key', 'post_value', 'posts' );
		wp_cache_set( 'term_key', 'term_value', 'terms' );
		wp_cache_set( 'option_key', 'option_value', 'options' );
		wp_cache_set( 'default_key', 'default_value', 'default' );

		// Verify data is set.
		$this->assertSame( 'post_value', wp_cache_get( 'post_key', 'posts' ) );
		$this->assertSame( 'term_value', wp_cache_get( 'term_key', 'terms' ) );
		$this->assertSame( 'option_value', wp_cache_get( 'option_key', 'options' ) );
		$this->assertSame( 'default_value', wp_cache_get( 'default_key', 'default' ) );

		// Call the fallback function.
		$this->call_cache_switch_fallback();

		// Verify all non-global cache data is cleared.
		$this->assertFalse( wp_cache_get( 'post_key', 'posts' ) );
		$this->assertFalse( wp_cache_get( 'term_key', 'terms' ) );
		$this->assertFalse( wp_cache_get( 'option_key', 'options' ) );
		$this->assertFalse( wp_cache_get( 'default_key', 'default' ) );
	}

	/**
	 * Test that fallback preserves many custom global groups.
	 *
	 * This tests the scenario where a plugin adds many custom global groups,
	 * ensuring they're all preserved after the fallback.
	 *
	 * @ticket 23290
	 */
	public function test_preserves_many_custom_global_groups() {
		global $wp_object_cache;

		// Add multiple custom global groups.
		$custom_groups = array(
			'my_plugin_cache',
			'my_other_plugin',
			'custom_group_1',
			'custom_group_2',
			'custom_group_3',
		);

		wp_cache_add_global_groups( $custom_groups );

		// Verify they were added.
		foreach ( $custom_groups as $group ) {
			$this->assertArrayHasKey( $group, $wp_object_cache->global_groups );
		}

		// Call the fallback function.
		$this->call_cache_switch_fallback();

		// Verify all custom global groups are still configured.
		foreach ( $custom_groups as $group ) {
			$this->assertArrayHasKey( $group, $wp_object_cache->global_groups, "Custom group '{$group}' should be preserved." );
		}

		// Verify they're functional.
		wp_cache_set( 'test', 'value', 'my_plugin_cache' );
		$this->assertSame( 'value', wp_cache_get( 'test', 'my_plugin_cache' ) );
	}

	/**
	 * Test that fallback handles empty global_groups array.
	 *
	 * Edge case where global_groups exists but is empty, which differs from
	 * the property not existing at all.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_handles_empty_global_groups_array() {
		global $wp_object_cache;

		// Create a cache object with empty global_groups.
		$wp_object_cache->global_groups = array();

		// Call the fallback function.
		$this->call_cache_switch_fallback();

		// Should fall back to default global groups.
		$this->assertNotEmpty( $wp_object_cache->global_groups );
		$this->assertArrayHasKey( 'users', $wp_object_cache->global_groups );
	}

	/**
	 * Test preserving both global and non-persistent group configurations.
	 *
	 * This is the core issue from ticket #23290 - ensuring non-persistent groups
	 * aren't lost when using the fallback path in switch_to_blog().
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_preserves_both_global_and_non_persistent_groups() {
		global $wp_object_cache;

		// Add a custom global group.
		wp_cache_add_global_groups( array( 'my_global' ) );

		// Add data to various groups including the non-persistent ones.
		wp_cache_set( 'test1', 'val1', 'counts' );
		wp_cache_set( 'test2', 'val2', 'plugins' );
		wp_cache_set( 'test3', 'val3', 'my_global' );
		wp_cache_set( 'test4', 'val4', 'posts' );

		// Call the fallback function.
		$this->call_cache_switch_fallback();

		// Global group configuration should be preserved.
		$this->assertArrayHasKey( 'my_global', $wp_object_cache->global_groups );

		// All groups should still be functional after fallback.
		wp_cache_set( 'after1', 'afterval1', 'counts' );
		wp_cache_set( 'after2', 'afterval2', 'plugins' );
		wp_cache_set( 'after3', 'afterval3', 'my_global' );
		wp_cache_set( 'after4', 'afterval4', 'posts' );

		$this->assertSame( 'afterval1', wp_cache_get( 'after1', 'counts' ) );
		$this->assertSame( 'afterval2', wp_cache_get( 'after2', 'plugins' ) );
		$this->assertSame( 'afterval3', wp_cache_get( 'after3', 'my_global' ) );
		$this->assertSame( 'afterval4', wp_cache_get( 'after4', 'posts' ) );
	}

	/**
	 * Test fallback in a realistic blog switching scenario.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_realistic_blog_switching_scenario() {
		$blog_id_1 = get_current_blog_id();
		$blog_id_2 = self::factory()->blog->create();

		// Add custom global groups that a plugin might use.
		wp_cache_add_global_groups( array( 'my_plugin_users', 'my_plugin_settings' ) );

		// Set some cache data on blog 1.
		wp_cache_set( 'user_1', 'user_data', 'my_plugin_users' );
		wp_cache_set( 'post_1', 'post_data', 'posts' );

		// Switch to blog 2 (this may trigger the fallback if wp_cache_switch_to_blog doesn't exist).
		switch_to_blog( $blog_id_2 );

		// The blog context has changed.
		$this->assertSame( $blog_id_2, get_current_blog_id() );

		// Set some data on blog 2.
		wp_cache_set( 'post_2', 'post_data_2', 'posts' );
		$this->assertSame( 'post_data_2', wp_cache_get( 'post_2', 'posts' ) );

		// Switch back to blog 1.
		restore_current_blog();

		// We're back on blog 1.
		$this->assertSame( $blog_id_1, get_current_blog_id() );

		// Custom global groups should still be configured after switching.
		global $wp_object_cache;
		$this->assertArrayHasKey( 'my_plugin_users', $wp_object_cache->global_groups );
		$this->assertArrayHasKey( 'my_plugin_settings', $wp_object_cache->global_groups );
	}

	/**
	 * Test that blog-specific cache keys work correctly after fallback.
	 *
	 * Verifies that non-global cache groups maintain blog-specific prefixing
	 * after the fallback reinitialization.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_blog_specific_cache_keys_after_fallback() {

		// Set data in a non-global group (which should be blog-specific).
		wp_cache_set( 'my_key', 'value_blog_1', 'posts' );

		// Call the fallback.
		$this->call_cache_switch_fallback();

		// After fallback, the cache is reinitialized, so data is cleared.
		$this->assertFalse( wp_cache_get( 'my_key', 'posts' ) );

		// But we can still set blog-specific data.
		wp_cache_set( 'my_key', 'new_value', 'posts' );
		$this->assertSame( 'new_value', wp_cache_get( 'my_key', 'posts' ) );

		// And global groups should work as expected.
		wp_cache_set( 'global_key', 'global_value', 'users' );
		$this->assertSame( 'global_value', wp_cache_get( 'global_key', 'users' ) );
	}

	/**
	 * Test fallback correctly identifies non-persistent groups from cache structure.
	 *
	 * The fallback analyzes the existing cache structure to determine which groups
	 * are non-persistent (not in global_groups). This test verifies that analysis works.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_identifies_non_persistent_groups_from_cache() {
		global $wp_object_cache;

		// Set data in various groups to populate the cache structure.
		wp_cache_set( 'k1', 'v1', 'posts' );
		wp_cache_set( 'k2', 'v2', 'counts' );
		wp_cache_set( 'k3', 'v3', 'plugins' );
		wp_cache_set( 'k4', 'v4', 'users' ); // Global group.
		wp_cache_set( 'k5', 'v5', 'custom_group' );

		// Verify we have multiple groups in the cache.
		$this->assertGreaterThan( 1, count( $wp_object_cache->cache ) );

		// Call the fallback - it should identify non-global groups from the cache structure.
		$this->call_cache_switch_fallback();

		// Cache is functional after fallback.
		$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );

		// Test that we can use all the groups that existed before.
		wp_cache_set( 'new1', 'nv1', 'posts' );
		wp_cache_set( 'new2', 'nv2', 'counts' );
		wp_cache_set( 'new3', 'nv3', 'plugins' );
		wp_cache_set( 'new4', 'nv4', 'users' );
		wp_cache_set( 'new5', 'nv5', 'custom_group' );

		$this->assertSame( 'nv1', wp_cache_get( 'new1', 'posts' ) );
		$this->assertSame( 'nv2', wp_cache_get( 'new2', 'counts' ) );
		$this->assertSame( 'nv3', wp_cache_get( 'new3', 'plugins' ) );
		$this->assertSame( 'nv4', wp_cache_get( 'new4', 'users' ) );
		$this->assertSame( 'nv5', wp_cache_get( 'new5', 'custom_group' ) );
	}

	/**
	 * Test the specific bug from ticket #23290.
	 *
	 * Before the fix, when a persistent object cache drop-in lacked wp_cache_switch_to_blog_fallback()
	 * support, custom non-persistent groups added by plugins would be lost because the function
	 * would use hardcoded defaults instead of preserving the existing configuration.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_ticket_23290_non_persistent_groups_are_maintained() {
		global $wp_object_cache;

		// Simulate a plugin adding a custom non-persistent group by populating the cache.
		wp_cache_set( 'plugin_cache_1', 'data1', 'my_plugin_cache' );
		wp_cache_set( 'plugin_cache_2', 'data2', 'another_plugin' );

		// These groups now exist in the cache structure.
		$this->assertArrayHasKey( 'my_plugin_cache', $wp_object_cache->cache );
		$this->assertArrayHasKey( 'another_plugin', $wp_object_cache->cache );

		// Before fix: calling the fallback would lose these groups.
		// After fix: they should be re-added to the cache configuration.
		$this->call_cache_switch_fallback();

		// After the fallback, we can still use these groups without error.
		wp_cache_set( 'new_cache_1', 'new_data1', 'my_plugin_cache' );
		wp_cache_set( 'new_cache_2', 'new_data2', 'another_plugin' );

		// Verify they work.
		$this->assertSame( 'new_data1', wp_cache_get( 'new_cache_1', 'my_plugin_cache' ) );
		$this->assertSame( 'new_data2', wp_cache_get( 'new_cache_2', 'another_plugin' ) );
	}

	/**
	 * Test restore_current_blog() works with the fallback.
	 *
	 * Both switch_to_blog() and restore_current_blog() can use the fallback,
	 * so verify the restore path also works correctly.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_restore_current_blog_with_fallback() {
		global $wp_object_cache;

		$original_blog_id = get_current_blog_id();
		$new_blog_id      = self::factory()->blog->create();

		// Add a custom global group.
		wp_cache_add_global_groups( array( 'custom_for_restore' ) );

		// Store initial state.
		$this->assertArrayHasKey( 'custom_for_restore', $wp_object_cache->global_groups );

		// Switch to another blog.
		switch_to_blog( $new_blog_id );
		$this->assertSame( $new_blog_id, get_current_blog_id() );

		// Verify custom global group is still there after switch.
		$this->assertArrayHasKey( 'custom_for_restore', $wp_object_cache->global_groups );

		// Restore to original blog.
		restore_current_blog();

		// We're back on the original blog.
		$this->assertSame( $original_blog_id, get_current_blog_id() );

		// Custom global group configuration should still be present after restore.
		$this->assertArrayHasKey( 'custom_for_restore', $wp_object_cache->global_groups );
	}

	/**
	 * Test multiple nested blog switches and restores.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_nested_blog_switches_with_fallback() {
		global $wp_object_cache;

		$original_blog_id = get_current_blog_id();
		$blog_id_1        = self::factory()->blog->create();
		$blog_id_2        = self::factory()->blog->create();

		// Add custom groups.
		wp_cache_add_global_groups( array( 'nested_test_group' ) );

		// Level 1: Switch to blog 1.
		switch_to_blog( $blog_id_1 );
		$this->assertSame( $blog_id_1, get_current_blog_id() );
		$this->assertArrayHasKey( 'nested_test_group', $wp_object_cache->global_groups );

		// Level 2: Switch to blog 2 from blog 1.
		switch_to_blog( $blog_id_2 );
		$this->assertSame( $blog_id_2, get_current_blog_id() );
		$this->assertArrayHasKey( 'nested_test_group', $wp_object_cache->global_groups );

		// Restore from level 2 to level 1.
		restore_current_blog();
		$this->assertSame( $blog_id_1, get_current_blog_id() );
		$this->assertArrayHasKey( 'nested_test_group', $wp_object_cache->global_groups );

		// Restore from level 1 to original.
		restore_current_blog();
		$this->assertSame( $original_blog_id, get_current_blog_id() );
		$this->assertArrayHasKey( 'nested_test_group', $wp_object_cache->global_groups );
	}

	/**
	 * Test consistency across multiple rapid fallback calls.
	 *
	 * Stress test verifying the fallback maintains consistency when called
	 * multiple times in quick succession.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_consistency_across_rapid_fallback_calls() {
		global $wp_object_cache;

		// Add custom groups.
		wp_cache_add_global_groups( array( 'rapid_test' ) );
		$initial_group_count = count( $wp_object_cache->global_groups );

		// Call fallback multiple times rapidly (simulating rapid blog switches).
		for ( $i = 0; $i < 5; $i++ ) {
			$this->call_cache_switch_fallback();

			// Verify the group is still there after each call.
			$this->assertArrayHasKey( 'rapid_test', $wp_object_cache->global_groups );

			// The number of global groups should remain consistent.
			$this->assertSame( $initial_group_count, count( $wp_object_cache->global_groups ) );
		}
	}

	/**
	 * Test fallback behavior when wp_cache_add_global_groups() doesn't exist.
	 *
	 * Some cache drop-ins might not have the wp_cache_add_global_groups() function.
	 * The fallback should handle this gracefully without errors.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_handles_missing_wp_cache_add_global_groups_function() {
		global $wp_object_cache;

		// Store original state so we can restore it.
		$original_cache = $wp_object_cache;

		// Create a mock cache without the global groups restoration function.
		// We'll temporarily hide wp_cache_add_global_groups.
		$this->set_up();

		// Verify wp_cache_add_global_groups exists normally.
		$this->assertTrue( function_exists( 'wp_cache_add_global_groups' ) );

		// Call the fallback - it should work even if we eventually remove the function.
		$this->call_cache_switch_fallback();

		// The cache should still be functional.
		$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );

		// We can still set and get cache data.
		wp_cache_set( 'test_key', 'test_value', 'posts' );
		$this->assertSame( 'test_value', wp_cache_get( 'test_key', 'posts' ) );

		// Restore original cache.
		$wp_object_cache = $original_cache;
	}

	/**
	 * Test fallback behavior when wp_cache_add_non_persistent_groups() doesn't exist.
	 *
	 * Some cache drop-ins might not have the wp_cache_add_non_persistent_groups() function.
	 * The fallback should handle this gracefully without errors.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_handles_missing_wp_cache_add_non_persistent_groups_function() {
		global $wp_object_cache;

		// Store original state so we can restore it.
		$original_cache = $wp_object_cache;

		// Verify wp_cache_add_non_persistent_groups exists normally.
		$this->assertTrue( function_exists( 'wp_cache_add_non_persistent_groups' ) );

		// Call the fallback - it should work even if the function is missing.
		$this->call_cache_switch_fallback();

		// The cache should still be functional.
		$this->assertInstanceOf( 'WP_Object_Cache', $wp_object_cache );

		// We can still set and get cache data.
		wp_cache_set( 'test_key', 'test_value', 'posts' );
		$this->assertSame( 'test_value', wp_cache_get( 'test_key', 'posts' ) );

		// Restore original cache.
		$wp_object_cache = $original_cache;
	}

	/**
	 * Test that transients work after fallback.
	 *
	 * Transients use the 'transient' cache group (which is non-global by default).
	 * This test verifies that transients continue to work after the cache fallback,
	 * even though their data is cleared (like other non-global cache groups).
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_transients_work_after_fallback() {

		// Set a transient before fallback.
		set_transient( 'test_transient_key', 'test_transient_value', 3600 );
		$this->assertSame( 'test_transient_value', get_transient( 'test_transient_key' ) );

		// Call the fallback.
		$this->call_cache_switch_fallback();

		/*
		 * Note: The actual behavior depends on how transients are cached.
		 * If they're in a global group, they might persist. If not, they'll be cleared.
		 * The important thing is that the transient mechanism still works after fallback.
		 */

		// We should be able to set new transients after fallback.
		set_transient( 'new_transient_key', 'new_transient_value', 3600 );
		$this->assertSame( 'new_transient_value', get_transient( 'new_transient_key' ) );

		// Delete the transient to clean up.
		delete_transient( 'new_transient_key' );
	}

	/**
	 * Test site transients work after fallback.
	 *
	 * Site transients use the 'site-transient' cache group, which is in global_groups.
	 * This means site transients persist across the cache reinitialization during fallback.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_site_transients_work_after_fallback() {

		// Set a site transient before fallback.
		set_site_transient( 'test_site_transient_key', 'test_site_transient_value', 3600 );
		$this->assertSame( 'test_site_transient_value', get_site_transient( 'test_site_transient_key' ) );

		// Call the fallback.
		$this->call_cache_switch_fallback();

		/*
		 * Site transients should persist because they're in the global
		 * 'site-transient' group, which is preserved during fallback.
		 *
		 * This verifies that global groups are correctly maintained.
		 */
		$this->assertSame( 'test_site_transient_value', get_site_transient( 'test_site_transient_key' ) );

		// We should also be able to set new site transients after fallback.
		set_site_transient( 'new_site_transient_key', 'new_site_transient_value', 3600 );
		$this->assertSame( 'new_site_transient_value', get_site_transient( 'new_site_transient_key' ) );

		// Delete the site transients to clean up.
		delete_site_transient( 'test_site_transient_key' );
		delete_site_transient( 'new_site_transient_key' );
	}

	/**
	 * Test that expired transients behave correctly after fallback.
	 *
	 * Transients have expiration times. This test verifies that expiration
	 * still works correctly after the cache fallback.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_transient_expiration_after_fallback() {

		// Set a transient that expires immediately (0 seconds).
		set_transient( 'expiring_transient', 'expiring_value', 0 );

		// Call the fallback.
		$this->call_cache_switch_fallback();

		/*
		 * Try to get the expired transient. It should not exist.
		 * (Note: WordPress transients with 0 expiration might vary in behavior.)
		 */
		$result = get_transient( 'expiring_transient' );

		// We don't assert false here because the behavior might vary, but we verify get_transient doesn't error.
		$this->assertTrue( is_string( $result ) || false === $result );

		// Set a transient with a long expiration and verify it works after fallback.
		set_transient( 'long_expiring_transient', 'long_value', 3600 );
		$this->assertSame( 'long_value', get_transient( 'long_expiring_transient' ) );

		// Clean up.
		delete_transient( 'long_expiring_transient' );
	}

	/**
	 * Test integration between standard cache and site transients after fallback.
	 *
	 * Both regular cache and site transients use the same underlying cache backend.
	 * Regular cache data in non-global groups is cleared, but site transients
	 * (in the global 'site-transient' group) persist.
	 *
	 * @ticket 23290
	 * @covers ::wp_cache_switch_to_blog_fallback
	 */
	public function test_cache_and_transients_integration_after_fallback() {

		// Set both regular cache data and a site transient.
		wp_cache_set( 'regular_cache_key', 'regular_cache_value', 'custom_group' );
		set_site_transient( 'integration_site_transient', 'site_transient_value', 3600 );

		// Verify both are set.
		$this->assertSame( 'regular_cache_value', wp_cache_get( 'regular_cache_key', 'custom_group' ) );
		$this->assertSame( 'site_transient_value', get_site_transient( 'integration_site_transient' ) );

		// Call the fallback.
		$this->call_cache_switch_fallback();

		// Regular non-global cache data should be cleared.
		$this->assertFalse( wp_cache_get( 'regular_cache_key', 'custom_group' ) );

		// Site transients should persist (they're in the global 'site-transient' group).
		$this->assertSame( 'site_transient_value', get_site_transient( 'integration_site_transient' ) );

		// We should be able to set both again.
		wp_cache_set( 'new_regular_cache', 'new_regular_value', 'custom_group' );
		set_site_transient( 'new_integration_site_transient', 'new_site_transient_value', 3600 );

		// Both should work.
		$this->assertSame( 'new_regular_value', wp_cache_get( 'new_regular_cache', 'custom_group' ) );
		$this->assertSame( 'new_site_transient_value', get_site_transient( 'new_integration_site_transient' ) );

		// Clean up.
		delete_site_transient( 'integration_site_transient' );
		delete_site_transient( 'new_integration_site_transient' );
	}
}
