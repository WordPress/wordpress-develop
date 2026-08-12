<?php

/**
 * Test wp-cron.php functionality.
 *
 * @group cron
 */
class Tests_Cron_WPCron extends WP_UnitTestCase {
	/**
	 * Set up the test fixture.
	 */
	public function set_up() {
		parent::set_up();
		_set_cron_array( array() );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		_set_cron_array( array() );
		delete_transient( 'doing_cron' );
		parent::tear_down();
	}

	/**
	 * Test that scheduled events are rescheduled correctly when wp-cron.php is called.
	 *
	 * @ticket 47590
	 */
	public function test_scheduled_events_are_rescheduled() {
		$timestamp = time();
		$hook      = 'test_schedule_event';
		wp_schedule_event( $timestamp, 'hourly', $hook );

		$initial_timestamp = wp_next_scheduled( $hook );
		$this->assertNotFalse( $initial_timestamp, 'Event should be scheduled' );

		$this->simulate_wp_cron();

		$next_timestamp = wp_next_scheduled( $hook );
		$this->assertNotFalse( $next_timestamp, 'Event should be rescheduled' );

		$this->assertEqualsWithDelta(
			$initial_timestamp + HOUR_IN_SECONDS,
			$next_timestamp,
			60,
			'Next run should be scheduled approximately one hour after the initial run'
		);
	}

	/**
	 * Test that single events are removed after running.
	 *
	 * @ticket 47590
	 */
	public function test_single_events_are_removed() {
		$timestamp = time();
		$hook      = 'test_single_event';
		wp_schedule_single_event( $timestamp, $hook );

		$this->assertNotFalse( wp_next_scheduled( $hook ), 'Event should be scheduled initially' );
		$this->simulate_wp_cron();
		$this->assertFalse( wp_next_scheduled( $hook ), 'Single event should be removed after running' );
	}

	/**
	 * Test that future events are not changed when wp-cron.php runs.
	 *
	 * @ticket 47590
	 */
	public function test_future_events_unchanged() {
		$timestamp = time() + DAY_IN_SECONDS;
		$hook      = 'test_future_event';
		wp_schedule_event( $timestamp, 'daily', $hook );

		$scheduled_time = wp_next_scheduled( $hook );
		$this->assertNotFalse( $scheduled_time, 'Event should be scheduled' );

		$this->simulate_wp_cron();

		$this->assertEquals(
			$scheduled_time,
			wp_next_scheduled( $hook ),
			'Future event time should remain unchanged'
		);
	}

	/**
	 * Test that the doing_cron transient is deleted after wp-cron.php runs.
	 *
	 * @ticket 47590
	 */
	public function test_doing_cron_transient_deleted() {
		set_transient( 'doing_cron', microtime( true ) );
		$this->simulate_wp_cron();
		$this->assertFalse( get_transient( 'doing_cron' ) );
	}

	/**
	 * Helper function to simulate wp-cron.php running.
	 */
	private function simulate_wp_cron() {
		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		$doing_cron_transient = get_transient( 'doing_cron' );

		if ( ! $doing_cron_transient ) {
			$doing_cron_transient = sprintf( '%.22F', microtime( true ) );
			set_transient( 'doing_cron', $doing_cron_transient );
		}

		$crons = wp_get_ready_cron_jobs();

		if ( empty( $crons ) ) {
			delete_transient( 'doing_cron' );
			return;
		}

		$gmt_time = microtime( true );
		$keys     = array_keys( $crons );

		if ( isset( $keys[0] ) && $keys[0] > $gmt_time ) {
			delete_transient( 'doing_cron' );
			return;
		}

		// Process cron jobs.
		foreach ( $crons as $timestamp => $cronhooks ) {
			if ( $timestamp > $gmt_time ) {
				continue;
			}

			foreach ( $cronhooks as $hook => $keys ) {
				foreach ( $keys as $k => $v ) {
					$schedule = $v['schedule'];
					$args     = $v['args'];

					do_action_ref_array( $hook, $args );

					wp_unschedule_event( $timestamp, $hook, $args );

					if ( $schedule ) {
						wp_reschedule_event( $timestamp, $schedule, $hook, $args );
					}
				}
			}
		}

		delete_transient( 'doing_cron' );
	}
}
