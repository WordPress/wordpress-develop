<?php

/**
 * @group user
 * @covers ::get_user_count
 */
class Tests_User_GetUserCount extends WP_UnitTestCase {
	/**
	 * @ticket 40386
	 * @group multisite
	 * @group ms-required
	 */
	public function test_wp_update_network_counts_on_different_network() {
		$this->skipWithoutMultisite();
		$different_network_id = self::factory()->network->create(
			array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			)
		);

		delete_network_option( $different_network_id, 'user_count' );

		wp_update_network_counts( $different_network_id );

		$user_count = get_user_count( $different_network_id );

		$this->assertGreaterThan( 0, $user_count );
	}

	/**
	 * @ticket 37866
	 * @group multisite
	 * @group ms-required
	 */
	public function test_get_user_count_on_different_network() {
		$this->skipWithoutMultisite();
		$different_network_id = self::factory()->network->create(
			array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			)
		);
		wp_update_network_user_counts();
		$current_network_user_count = get_user_count();

		// Add another user to fake the network user count to be different.
		wpmu_create_user( 'user', 'pass', 'email' );

		wp_update_network_user_counts( $different_network_id );

		$user_count = get_user_count( $different_network_id );

		$this->assertSame( $current_network_user_count + 1, $user_count );
	}

	/**
	 * @ticket 22917
	 * @group multisite
	 * @group ms-required
	 */
	public function test_enable_live_network_user_counts_filter() {
		$this->skipWithoutMultisite();
		// False for large networks by default.
		add_filter( 'enable_live_network_counts', '__return_false' );

		// Refresh the cache.
		wp_update_network_counts();
		$start_count = get_user_count();

		wpmu_create_user( 'user', 'pass', 'email' );

		// No change, cache not refreshed.
		$count = get_user_count();

		$this->assertSame( $start_count, $count );

		wp_update_network_counts();
		$start_count = get_user_count();

		add_filter( 'enable_live_network_counts', '__return_true' );

		self::factory()->user->create( array( 'role' => 'administrator' ) );

		$count = get_user_count();
		$this->assertSame( $start_count + 1, $count );

	}

	/**
	 * @ticket 38741
	 */
	public function test_get_user_count_update() {
		wp_update_user_counts();
		$current_network_user_count = get_user_count();

		self::factory()->user->create( array( 'role' => 'administrator' ) );

		$user_count = get_user_count();

		$this->assertSame( $current_network_user_count + 1, $user_count );
	}

	/**
	 * @group ms-excluded
	 * @ticket 38741
	 */
	public function test_get_user_count_update_on_delete() {
		$this->skipWithMultisite();
		wp_update_user_counts();
		$current_network_user_count = get_user_count();

		$u1 = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$user_count = get_user_count();

		$this->assertSame( $current_network_user_count + 1, $user_count );

		wp_delete_user( $u1 );

		$user_count_after_delete = get_user_count();

		$this->assertSame( $user_count - 1, $user_count_after_delete );
	}

	/**
	 * @group ms-required
	 * @ticket 38741
	 */
	public function test_get_user_count_update_on_delete_multisite() {
		$this->skipWithoutMultisite();
		wp_update_user_counts();
		$current_network_user_count = get_user_count();

		$u1 = wpmu_create_user( 'user', 'pass', 'email' );

		$user_count = get_user_count();

		$this->assertSame( $current_network_user_count + 1, $user_count );

		wpmu_delete_user( $u1 );

		$user_count_after_delete = get_user_count();

		$this->assertSame( $user_count - 1, $user_count_after_delete );
	}

	/**
	 * @group multisite
	 * @group ms-required
	 * @ticket 38741
	 */
	public function test_get_user_count() {
		$this->skipWithoutMultisite();
		// Refresh the cache.
		wp_update_network_counts();
		$start_count = get_user_count();

		// Only false for large networks as of 3.7.
		add_filter( 'enable_live_network_counts', '__return_false' );
		self::factory()->user->create( array( 'role' => 'administrator' ) );

		$count = get_user_count(); // No change, cache not refreshed.
		$this->assertSame( $start_count, $count );

		wp_update_network_counts(); // Magic happens here.

		$count = get_user_count();
		$this->assertSame( $start_count + 1, $count );
	}
}
