<?php

/**
 * Tests for the wp_cache_set_posts_last_changed() function.
 *
 * @group post
 * @group cache
 *
 * @covers ::wp_cache_set_posts_last_changed
 */
class Tests_Post_wpCacheSetPostsLastChanged extends WP_UnitTestCase {

	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
	}

	public function tear_down() {
		unset( $GLOBALS['__suspend_posts_last_changed_update'] );
		parent::tear_down();
	}

	/**
	 * Verifies that the function updates the 'posts' cache group.
	 */
	public function test_sets_last_changed_for_posts_group() {
		wp_cache_delete( 'last_changed', 'posts' );

		wp_cache_set_posts_last_changed();

		$this->assertNotFalse( wp_cache_get( 'last_changed', 'posts' ) );
	}

	/**
	 * Verifies that the function is suspended when the global counter is set.
	 */
	public function test_suspended_when_global_is_set() {
		wp_cache_delete( 'last_changed', 'posts' );

		$GLOBALS['__suspend_posts_last_changed_update'] = 1;

		wp_cache_set_posts_last_changed();

		$this->assertFalse( wp_cache_get( 'last_changed', 'posts' ) );
	}

	/**
	 * Verifies that the function resumes after the global counter reaches zero.
	 */
	public function test_resumes_when_global_reaches_zero() {
		wp_cache_delete( 'last_changed', 'posts' );

		$GLOBALS['__suspend_posts_last_changed_update'] = 0;

		wp_cache_set_posts_last_changed();

		$this->assertNotFalse( wp_cache_get( 'last_changed', 'posts' ) );
	}

	/**
	 * Verifies that add_post_meta updates last_changed when not suspended.
	 */
	public function test_add_post_meta_updates_last_changed() {
		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// Ensure the microtime value changes.
		usleep( 1 );

		add_post_meta( self::$post_id, '_test_meta_add', 'value' );

		$after = wp_cache_get( 'last_changed', 'posts' );
		$this->assertNotSame( $before, $after, 'add_post_meta should update last_changed.' );
	}

	/**
	 * Verifies that add_post_meta does not update last_changed when suspended.
	 */
	public function test_add_post_meta_suspended() {
		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// Ensure the microtime value changes.
		usleep( 1 );

		$GLOBALS['__suspend_posts_last_changed_update'] = 1;
		add_post_meta( self::$post_id, '_test_meta_add_suspended', 'value' );
		$GLOBALS['__suspend_posts_last_changed_update'] = 0;

		$after = wp_cache_get( 'last_changed', 'posts' );
		$this->assertSame( $before, $after, 'add_post_meta should not update last_changed when suspended.' );
	}

	/**
	 * Verifies that update_post_meta updates last_changed when not suspended.
	 */
	public function test_update_post_meta_updates_last_changed() {
		add_post_meta( self::$post_id, '_test_meta_update', 'old' );

		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// Ensure the microtime value changes.
		usleep( 1 );

		update_post_meta( self::$post_id, '_test_meta_update', 'new' );

		$after = wp_cache_get( 'last_changed', 'posts' );
		$this->assertNotSame( $before, $after, 'update_post_meta should update last_changed.' );
	}

	/**
	 * Verifies that update_post_meta does not update last_changed when suspended.
	 */
	public function test_update_post_meta_suspended() {
		add_post_meta( self::$post_id, '_test_meta_update_suspended', 'old' );

		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// Ensure the microtime value changes.
		usleep( 1 );

		$GLOBALS['__suspend_posts_last_changed_update'] = 1;
		update_post_meta( self::$post_id, '_test_meta_update_suspended', 'new' );
		$GLOBALS['__suspend_posts_last_changed_update'] = 0;

		$after = wp_cache_get( 'last_changed', 'posts' );
		$this->assertSame( $before, $after, 'update_post_meta should not update last_changed when suspended.' );
	}

	/**
	 * Verifies that delete_post_meta updates last_changed when not suspended.
	 */
	public function test_delete_post_meta_updates_last_changed() {
		add_post_meta( self::$post_id, '_test_meta_delete', 'value' );

		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// Ensure the microtime value changes.
		usleep( 1 );

		delete_post_meta( self::$post_id, '_test_meta_delete' );

		$after = wp_cache_get( 'last_changed', 'posts' );
		$this->assertNotSame( $before, $after, 'delete_post_meta should update last_changed.' );
	}

	/**
	 * Verifies that delete_post_meta does not update last_changed when suspended.
	 */
	public function test_delete_post_meta_suspended() {
		add_post_meta( self::$post_id, '_test_meta_delete_suspended', 'value' );

		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// Ensure the microtime value changes.
		usleep( 1 );

		$GLOBALS['__suspend_posts_last_changed_update'] = 1;
		delete_post_meta( self::$post_id, '_test_meta_delete_suspended' );
		$GLOBALS['__suspend_posts_last_changed_update'] = 0;

		$after = wp_cache_get( 'last_changed', 'posts' );
		$this->assertSame( $before, $after, 'delete_post_meta should not update last_changed when suspended.' );
	}

	/**
	 * Verifies that an embedded post meta operation triggered by a hook during
	 * a suspended call does not prematurely unsuspend the counter.
	 *
	 * Simulates: outer add_post_meta (suspended) triggers a hook that calls
	 * update_post_meta internally. The inner operation should not reset the
	 * counter, and last_changed should remain unchanged after both complete.
	 */
	public function test_nested_post_meta_via_hook_respects_suspension_counter() {
		// When the outer add_post_meta fires 'added_post_meta', this hook
		// performs its own update_post_meta while the counter is still active.
		$nested_callback = function ( $meta_id, $post_id, $meta_key ) {
			if ( '_test_outer_meta' !== $meta_key ) {
				return;
			}

			// Increment the counter to simulate a nested suspended operation.
			++$GLOBALS['__suspend_posts_last_changed_update'];
			try {
				update_post_meta( $post_id, '_test_inner_meta', 'inner_value' );
			} finally {
				--$GLOBALS['__suspend_posts_last_changed_update'];
			}
		};

		add_action( 'added_post_meta', $nested_callback, 10, 3 );

		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// Ensure the microtime value changes.
		usleep( 1 );

		// Outer suspended operation.
		$GLOBALS['__suspend_posts_last_changed_update'] = 1;
		add_post_meta( self::$post_id, '_test_outer_meta', 'outer_value' );
		$GLOBALS['__suspend_posts_last_changed_update'] = 0;

		$after = wp_cache_get( 'last_changed', 'posts' );

		remove_action( 'added_post_meta', $nested_callback, 10 );

		$this->assertSame( $before, $after, 'Nested post meta via hook should not update last_changed while suspended.' );
		$this->assertSame( 'inner_value', get_post_meta( self::$post_id, '_test_inner_meta', true ), 'Inner meta should still be written.' );
	}

	/**
	 * Verifies that last_changed is updated normally after suspension ends,
	 * even when a nested operation occurred during the suspended window.
	 */
	public function test_last_changed_updates_after_nested_suspension_ends() {
		$nested_callback = function ( $mid, $post_id, $meta_key ) {
			if ( '_test_outer_meta_2' !== $meta_key ) {
				return;
			}
			++$GLOBALS['__suspend_posts_last_changed_update'];
			try {
				update_post_meta( $post_id, '_test_inner_meta_2', 'inner' );
			} finally {
				--$GLOBALS['__suspend_posts_last_changed_update'];
			}
		};

		add_action( 'added_post_meta', $nested_callback, 10, 3 );

		// Suspended operation with nested hook.
		$GLOBALS['__suspend_posts_last_changed_update'] = 1;
		add_post_meta( self::$post_id, '_test_outer_meta_2', 'outer' );
		$GLOBALS['__suspend_posts_last_changed_update'] = 0;

		remove_action( 'added_post_meta', $nested_callback, 10 );

		// Record the cache value after the suspended window.
		wp_cache_set_posts_last_changed();
		$before = wp_cache_get( 'last_changed', 'posts' );

		// A normal (unsuspended) meta operation should update last_changed.
		usleep( 1 );

		add_post_meta( self::$post_id, '_test_after_nested', 'value' );

		$after = wp_cache_get( 'last_changed', 'posts' );
		$this->assertNotSame( $before, $after, 'last_changed should update normally after suspension ends.' );
	}
}
