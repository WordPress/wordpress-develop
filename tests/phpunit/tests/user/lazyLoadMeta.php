<?php

/**
 * @group user
 * @covers ::wp_lazyload_user_meta
 */
class Tests_User_Lazy_Load_Meta extends WP_UnitTestCase {

	/**
	 * @ticket 63021
	 */
	public function test_lazy_load_meta() {
		$user_ids = self::factory()->user->create_many( 3 );
		// Clear any existing cache.
		wp_cache_delete_multiple( $user_ids, 'user_meta' );
		wp_lazyload_user_meta( $user_ids );
		$filter = new MockAction();
		add_filter( 'update_user_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		get_user_meta( $user_ids[0] );

		$args          = $filter->get_args();
		$first         = reset( $args );
		$load_user_ids = end( $first );
		$this->assertSameSets( $user_ids, $load_user_ids, 'Ensure all user IDs are loaded in a single batch' );
	}

	/**
	 * @ticket 63021
	 */
	public function test_lazy_load_meta_sets() {
		$user_ids1 = self::factory()->user->create_many( 3 );
		$user_ids2 = self::factory()->user->create_many( 3 );
		$user_ids  = array_merge( $user_ids1, $user_ids2 );
		// Clear any existing cache.
		wp_cache_delete_multiple( $user_ids, 'user_meta' );
		wp_lazyload_user_meta( $user_ids );
		$filter = new MockAction();
		add_filter( 'update_user_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		get_user_meta( $user_ids[0] );

		$args          = $filter->get_args();
		$first         = reset( $args );
		$load_user_ids = end( $first );
		$this->assertSameSets( $user_ids, $load_user_ids, 'Ensure all user IDs are loaded in a single batch' );
	}

	/**
	 * @ticket 63021
	 */
	public function test_lazy_load_meta_not_in_queue() {
		$user_ids1   = self::factory()->user->create_many( 3 );
		$user_ids2   = self::factory()->user->create_many( 3 );
		$user_ids    = array_merge( $user_ids1, $user_ids2 );
		$new_user_id = self::factory()->user->create();
		wp_lazyload_user_meta( $user_ids );
		// Add a user not in the lazy load queue.
		$user_ids[] = $new_user_id;
		// Clear any existing cache including the new user not in the queue.
		wp_cache_delete_multiple( $user_ids, 'user_meta' );

		$filter = new MockAction();
		add_filter( 'update_user_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		get_user_meta( $new_user_id );

		$args          = $filter->get_args();
		$first         = reset( $args );
		$load_user_ids = end( $first );
		$this->assertSameSets( $user_ids, $load_user_ids, 'Ensure all user IDs are loaded, including the one not in the queue' );
	}
}
