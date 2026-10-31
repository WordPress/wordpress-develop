<?php

/**
 * Tests for wp_schedule_update_checks().
 *
 * @group functions
 * @group plugins
 * @group upgrade
 * @group cron
 *
 * @covers ::wp_schedule_update_checks
 */
class Tests_Functions_WpScheduleUpdateChecks extends WP_UnitTestCase {

	/**
	 * The update check events.
	 *
	 * @var string[]
	 */
	private static $hooks = array( 'wp_version_check', 'wp_update_plugins', 'wp_update_themes' );

	/**
	 * The original cron array.
	 *
	 * @var array|false
	 */
	private $original_cron;

	/**
	 * Empties the cron array.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_cron = _get_cron_array();
		_set_cron_array( array() );
	}

	/**
	 * Restores the cron array.
	 */
	public function tear_down() {
		_set_cron_array( $this->original_cron );

		parent::tear_down();
	}

	/**
	 * Tests that the function runs on init.
	 *
	 * @ticket 65819
	 */
	public function test_should_be_hooked_to_init() {
		$this->assertSame( 10, has_action( 'init', 'wp_schedule_update_checks' ) );
	}

	/**
	 * Tests that each update check is scheduled twice daily.
	 *
	 * @ticket 65819
	 */
	public function test_should_schedule_update_checks() {
		$before = time();

		wp_schedule_update_checks();

		foreach ( self::$hooks as $hook ) {
			$event = wp_get_scheduled_event( $hook );

			$this->assertIsObject( $event, "{$hook} should be scheduled." );
			$this->assertSame( 'twicedaily', $event->schedule, "{$hook} should run twice daily." );
			$this->assertGreaterThanOrEqual( $before, $event->timestamp, "{$hook} should be scheduled from now." );
			$this->assertLessThanOrEqual( time(), $event->timestamp, "{$hook} should not be scheduled later." );
		}
	}

	/**
	 * Tests that an update check that is already scheduled is left alone.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_hooks
	 *
	 * @param string $scheduled The update check that is already scheduled.
	 */
	public function test_should_not_reschedule_scheduled_update_check( $scheduled ) {
		wp_schedule_event( 1000, 'daily', $scheduled );

		wp_schedule_update_checks();

		$event = wp_get_scheduled_event( $scheduled );

		$this->assertSame( 1000, $event->timestamp, 'The existing event should keep its time.' );
		$this->assertSame( 'daily', $event->schedule, 'The existing event should keep its schedule.' );
		$this->assertCount( 3, $this->get_update_check_events(), 'Each update check should be scheduled once.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_hooks() {
		return self::text_array_to_dataprovider( self::$hooks );
	}

	/**
	 * Tests that nothing is scheduled while WordPress is installing.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_schedule_update_checks_while_installing() {
		// The option cache is not updated while installing, so count the scheduling attempts.
		$filter = new MockAction();
		add_filter( 'pre_schedule_event', array( $filter, 'filter' ) );

		wp_installing( true );

		try {
			wp_schedule_update_checks();
		} finally {
			wp_installing( false );
		}

		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * Returns the scheduled update check events.
	 *
	 * @return string[] The hook names of the scheduled update check events.
	 */
	private function get_update_check_events() {
		$events = array();
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( array_keys( $hooks ) as $hook ) {
				if ( in_array( $hook, self::$hooks, true ) ) {
					$events[] = $hook;
				}
			}
		}

		return $events;
	}
}
