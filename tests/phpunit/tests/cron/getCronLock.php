<?php

/**
 * Test the `_get_cron_lock()` function.
 *
 * @group cron
 * @covers ::_get_cron_lock
 */
class Tests_Cron_getCronLock extends WP_UnitTestCase {

	/**
	 * Whether the external object cache was enabled before the test.
	 *
	 * @var bool
	 */
	private $initial_ext_object_cache;

	/**
	 * The cron event used to load the executable wp-cron.php file.
	 *
	 * @var int
	 */
	private $cron_event_timestamp;

	/**
	 * Sets up the cache state and clears any existing cron lock.
	 */
	public function set_up() {
		parent::set_up();

		$this->initial_ext_object_cache = wp_using_ext_object_cache();
		delete_option( '_transient_doing_cron' );
		wp_cache_delete( 'doing_cron', 'transient' );

		$this->cron_event_timestamp = time() - 1;
		wp_schedule_single_event( $this->cron_event_timestamp, __CLASS__ );
		set_transient( 'doing_cron', 'different-lock' );
		$doing_wp_cron = 'test-lock';
		require_once ABSPATH . 'wp-cron.php';
		delete_option( '_transient_doing_cron' );
		wp_cache_delete( 'doing_cron', 'transient' );
	}

	/**
	 * Restores the cache state and clears the cron lock after the test.
	 */
	public function tear_down() {
		wp_unschedule_event( $this->cron_event_timestamp, __CLASS__ );
		delete_option( '_transient_doing_cron' );
		wp_cache_delete( 'doing_cron', 'transient' );
		wp_using_ext_object_cache( $this->initial_ext_object_cache );

		parent::tear_down();
	}

	/**
	 * Tests that `_get_cron_lock()` returns a stored lock from either storage mechanism.
	 *
	 * @ticket 65958
	 *
	 * @dataProvider data_get_cron_lock_returns_stored_lock
	 *
	 * @param bool   $use_external_cache Whether to use the external object cache.
	 * @param string $expected           The expected cron lock.
	 */
	public function test_get_cron_lock_returns_stored_lock( $use_external_cache, $expected ) {
		wp_using_ext_object_cache( $use_external_cache );
		update_option( '_transient_doing_cron', 'database-lock' );
		wp_cache_set( 'doing_cron', $expected, 'transient' );

		$this->assertSame( $expected, _get_cron_lock() );
	}

	/**
	 * Data provider for test_get_cron_lock_returns_stored_lock().
	 *
	 * @return array<string, array{
	 *     use_external_cache: bool,
	 *     expected: string,
	 * }>
	 */
	public function data_get_cron_lock_returns_stored_lock(): array {
		return array(
			'database'       => array(
				'use_external_cache' => false,
				'expected'           => 'database-lock',
			),
			'external cache' => array(
				'use_external_cache' => true,
				'expected'           => 'external-cache-lock',
			),
		);
	}

	/**
	 * Tests that `_get_cron_lock()` returns zero when no lock is stored.
	 *
	 * @ticket 65958
	 */
	public function test_get_cron_lock_returns_zero_when_not_set() {
		$this->assertSame( 0, _get_cron_lock() );
	}
}
