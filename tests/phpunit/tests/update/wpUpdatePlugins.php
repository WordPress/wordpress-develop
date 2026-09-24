<?php

/**
 * @group upgrade
 *
 * @covers ::wp_update_plugins
 */
class Tests_Update_WpUpdatePlugins extends WP_UnitTestCase {

	/**
	 * Number of update checks sent to the API.
	 *
	 * @var int
	 */
	private $request_count = 0;

	public function set_up() {
		parent::set_up();

		$this->request_count = 0;

		// Clear any leftover database error so a prior test cannot leak into this one.
		$GLOBALS['wpdb']->last_error = '';

		// Start from a clean slate so the first write creates the option rather than updating it.
		delete_site_transient( 'update_plugins' );

		add_filter( 'pre_http_request', array( $this, 'mock_update_check_response' ), 10, 3 );
	}

	public function tear_down() {
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Answers the update check without contacting api.wordpress.org, and counts the calls.
	 *
	 * @param false|array|WP_Error $response A preemptive return value of an HTTP request.
	 * @param array                $args     HTTP request arguments.
	 * @param string               $url      The request URL.
	 * @return array|false A mocked response for the plugin update check, false otherwise.
	 */
	public function mock_update_check_response( $response, $args, $url ) {
		if ( false === strpos( $url, '://api.wordpress.org/plugins/update-check/' ) ) {
			return $response;
		}

		++$this->request_count;

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'plugins'      => array(
						'hello.php' => array(
							'slug'        => 'hello-dolly',
							'plugin'      => 'hello.php',
							'new_version' => '99.0',
							'url'         => 'https://wordpress.org/plugins/hello-dolly/',
							'package'     => 'https://downloads.wordpress.org/plugin/hello-dolly.99.0.zip',
						),
					),
					'translations' => array(),
					'no_update'    => array(),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Simulates a database that rejects the write storing the result of the check.
	 *
	 * This reproduces the failure the ticket is about, for instance a `wp_options` column
	 * using utf8mb3 and an update response carrying a four byte character: the write does not
	 * store the value and $wpdb->last_error is set. Only the result payload is rejected, since
	 * it is the one carrying a `response` property; the object written when the lock is taken
	 * is left alone.
	 *
	 * @param mixed $value     The new option value.
	 * @param mixed $old_value The old option value.
	 * @return mixed The value to store.
	 */
	public function reject_update_result( $value, $old_value ) {
		if ( is_object( $value ) && ! empty( $value->response ) ) {
			$GLOBALS['wpdb']->last_error = 'Simulated storage failure.';
			return $old_value;
		}

		return $value;
	}

	/**
	 * The happy path must be untouched: the result is stored and the lock is left armed.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_successful_check_stores_result_and_keeps_lock() {
		wp_update_plugins();

		$transient = get_site_transient( 'update_plugins' );

		$this->assertIsObject( $transient, 'The update transient was not stored.' );
		$this->assertNotEquals( 0, $transient->last_checked, 'last_checked was reset after a successful check.' );
		$this->assertArrayHasKey(
			'hello.php',
			$transient->response,
			'The update response was not stored.'
		);
		$this->assertSame( 1, $this->request_count, 'The update check did not run exactly once.' );
	}

	/**
	 * A successful check must not send the site back to the API on the next request.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_successful_check_is_not_repeated() {
		wp_update_plugins();
		wp_update_plugins();

		$this->assertSame( 1, $this->request_count, 'The update check ran again after it had succeeded.' );
	}

	/**
	 * A genuine storage failure is surfaced with a warning rather than failing silently.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_failed_write_triggers_a_warning() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		add_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10, 2 );

		$warnings = $this->collect_warnings_from( 'wp_update_plugins' );

		remove_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10 );

		$this->assertCount( 1, $warnings, 'A warning should be triggered when the result could not be stored.' );
		$this->assertStringContainsString(
			'could not be stored',
			$warnings[0]['errstr'],
			'The warning did not describe the storage failure.'
		);
	}

	/**
	 * A failed write must not reset the lock, so the check does not run on every request.
	 *
	 * Resetting `last_checked` to 0 here would make a persistent storage failure re-run the
	 * check on every single request, so the lock is deliberately left in place; it expires on
	 * its own timeout as it always has.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_failed_write_keeps_the_lock_and_does_not_busy_loop() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		add_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10, 2 );

		$this->collect_warnings_from( 'wp_update_plugins' );
		// A second request while the lock is still fresh must not send another API request.
		wp_update_plugins();

		remove_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10 );

		$this->assertSame(
			1,
			$this->request_count,
			'The failed write re-ran the check instead of leaving the lock in place.'
		);

		$transient = get_site_transient( 'update_plugins' );
		$this->assertIsObject( $transient, 'The lock transient was not stored.' );
		$this->assertNotEquals(
			0,
			$transient->last_checked,
			'last_checked was reset after a failed write, which busy-loops the check.'
		);
	}

	/**
	 * A write that stores nothing new must not be mistaken for a failure.
	 *
	 * set_site_transient() returns false both when a write fails and when the value is already
	 * the stored value, which happens whenever a check that found nothing new completes within
	 * the same second the lock was taken. Keying the warning on $wpdb->last_error rather than on
	 * that return value means an unchanged write, which runs no query and sets no error, stays
	 * silent.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_unchanged_write_does_not_warn() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		// Reject the write without setting an error, standing in for update_option()
		// returning false because the value it was asked to store was already stored.
		add_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'keep_stored_value' ), 10, 2 );

		$warnings = $this->collect_warnings_from( 'wp_update_plugins' );

		remove_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'keep_stored_value' ), 10 );

		$this->assertCount( 0, $warnings, 'An unchanged write was mistaken for a storage failure.' );
	}

	/**
	 * Rejects the write while leaving $wpdb->last_error clear, standing in for an unchanged value.
	 *
	 * @param mixed $value     The new option value.
	 * @param mixed $old_value The old option value.
	 * @return mixed The value to store.
	 */
	public function keep_stored_value( $value, $old_value ) {
		if ( is_object( $value ) && ! empty( $value->response ) ) {
			return $old_value;
		}

		return $value;
	}

	/**
	 * Runs a callback and returns the E_USER_WARNING errors it triggered.
	 *
	 * @param callable $callback The function to run.
	 * @return array[] The collected warnings, each with `errno` and `errstr` keys.
	 */
	private function collect_warnings_from( $callback ) {
		$warnings = array();

		// Note: $this->expectWarning() is deprecated and will be removed in PHPUnit 10.
		set_error_handler(
			static function ( $errno, $errstr ) use ( &$warnings ) {
				$warnings[] = compact( 'errno', 'errstr' );
				return true;
			},
			E_USER_WARNING
		);

		try {
			call_user_func( $callback );
		} finally {
			restore_error_handler();
		}

		return $warnings;
	}
}
