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
	 * Rejects the write that stores the result of the check, leaving the stored value untouched.
	 *
	 * This reproduces a database that cannot store the payload, for instance a `wp_options`
	 * column using utf8mb3 and an update response containing a four byte character. Only the
	 * result payload is rejected: it is the one carrying a `checked` property, while the object
	 * written when the lock is taken, and the rolled back copy of it, are not.
	 *
	 * @param mixed $value     The new option value.
	 * @param mixed $old_value The old option value.
	 * @return mixed The value to store.
	 */
	public function reject_update_result( $value, $old_value ) {
		if ( is_object( $value ) && isset( $value->checked ) ) {
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
	 * When the result cannot be stored, the lock must not be left armed on stale data.
	 *
	 * Without this, `last_checked` stays at the time of a check whose result was never saved,
	 * so the site reports itself up to date until the timeout expires.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_failed_write_resets_the_lock() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		add_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10, 2 );

		// Note: $this->expectWarning() is deprecated and will be removed in PHPUnit 10.
		$warnings = array();
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$warnings ) {
				$warnings[] = compact( 'errno', 'errstr' );
				return true;
			},
			E_USER_WARNING
		);

		wp_update_plugins();

		restore_error_handler();

		remove_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10 );

		$this->assertCount( 1, $warnings, 'A warning should be triggered when the result could not be stored.' );

		$transient = get_site_transient( 'update_plugins' );

		$this->assertIsObject( $transient, 'The update transient was not stored.' );
		$this->assertSame(
			0,
			$transient->last_checked,
			'last_checked was left armed after the result could not be stored.'
		);
	}

	/**
	 * After a failed write the next request must actually run the check again.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_failed_write_allows_the_next_check_to_run() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		add_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10, 2 );

		// Note: $this->expectWarning() is deprecated and will be removed in PHPUnit 10.
		$warnings = array();
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$warnings ) {
				$warnings[] = compact( 'errno', 'errstr' );
				return true;
			},
			E_USER_WARNING
		);

		wp_update_plugins();

		restore_error_handler();

		remove_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10 );

		$this->assertCount( 1, $warnings, 'A warning should be triggered when the result could not be stored.' );

		// The second check is the one that would never happen while the lock stayed armed.
		wp_update_plugins();

		$this->assertSame( 2, $this->request_count, 'The update check did not run again after a failed write.' );

		$transient = get_site_transient( 'update_plugins' );

		$this->assertArrayHasKey(
			'hello.php',
			$transient->response,
			'The update response was not stored once the write succeeded again.'
		);
	}

	/**
	 * An unchanged value must not be mistaken for a failed write.
	 *
	 * update_site_option() also returns false when the value it is asked to store is already
	 * the stored value, which happens whenever a check that found nothing new completes within
	 * the same second the lock was taken. Rolling the lock back in that case would send the
	 * site out to the API on every request.
	 *
	 * @ticket 64550
	 *
	 * @covers ::_wp_maybe_reset_update_check_lock
	 */
	public function test_unchanged_value_does_not_reset_the_lock() {
		$lock  = (object) array( 'last_checked' => 1234567890 );
		$value = (object) array( 'last_checked' => 1234567890 );

		$this->assertFalse(
			_wp_maybe_reset_update_check_lock( 'update_plugins', $value, $lock ),
			'An unchanged value was treated as a failed write.'
		);
		$this->assertSame( 1234567890, $lock->last_checked, 'last_checked was reset for an unchanged value.' );
	}

	/**
	 * A value that differs from the lock must roll the lock back.
	 *
	 * @ticket 64550
	 *
	 * @covers ::_wp_maybe_reset_update_check_lock
	 */
	public function test_changed_value_resets_the_lock() {
		$lock  = (object) array( 'last_checked' => 1234567890 );
		$value = (object) array(
			'last_checked' => 1234567890,
			'checked'      => array( 'hello.php' => '1.0' ),
		);

		$this->assertTrue(
			_wp_maybe_reset_update_check_lock( 'update_plugins', $value, $lock ),
			'A failed write did not roll the lock back.'
		);
		$this->assertSame( 0, $lock->last_checked, 'last_checked was not reset after a failed write.' );
		$this->assertSame(
			0,
			get_site_transient( 'update_plugins' )->last_checked,
			'The rolled back lock was not stored.'
		);
	}

	/**
	 * The reset reports whether it actually persisted.
	 *
	 * The write that rolls the lock back can itself fail. When it does, the caller must not
	 * announce that the check will run again, because the armed lock is still in place. The
	 * helper therefore returns the result of storing the reset rather than assuming it stuck.
	 *
	 * @ticket 64550
	 *
	 * @covers ::_wp_maybe_reset_update_check_lock
	 */
	public function test_reset_reports_whether_the_write_persisted() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		// Store the lock the reset will write, so re-storing it is a no-op that returns false.
		$lock = (object) array( 'last_checked' => 0 );
		set_site_transient( 'update_plugins', $lock );

		$value = (object) array(
			'last_checked' => 0,
			'checked'      => array( 'hello.php' => '1.0' ),
		);

		$this->assertFalse(
			_wp_maybe_reset_update_check_lock( 'update_plugins', $value, $lock ),
			'A reset whose write did not persist was reported as done.'
		);
	}
}
