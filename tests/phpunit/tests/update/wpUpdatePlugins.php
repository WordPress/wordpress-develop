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
	 * A failed write must leave `last_checked` alone rather than reset it to 0.
	 *
	 * An earlier version of this fix reset `last_checked` to 0 so the next request would retry.
	 * On a persistent storage failure that turns into a re-check on every single request, so the
	 * reset was dropped: the lock is left exactly as it was and expires on its own timeout.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_failed_write_does_not_reset_the_lock() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		add_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10, 2 );

		$this->collect_warnings_from( 'wp_update_plugins' );

		remove_filter( 'pre_update_site_option__site_transient_update_plugins', array( $this, 'reject_update_result' ), 10 );

		$transient = get_site_transient( 'update_plugins' );
		$this->assertIsObject( $transient, 'The lock transient was not stored.' );
		$this->assertNotEquals(
			0,
			$transient->last_checked,
			'last_checked was reset after a failed write instead of being left in place.'
		);
	}

	/**
	 * The warning fires on the real storage path, not just a simulated one.
	 *
	 * This drives the exact failure from the ticket: a four byte character in the update
	 * payload and a storage column that can't hold it. `pre_get_col_charset` makes wpdb treat
	 * the column as utf8mb3, so `wpdb::update()` strips the character in `process_fields()`,
	 * sets `$wpdb->last_error`, and returns false before running a query. No schema change is
	 * needed, and the assertion on wpdb's own error message confirms the warning is reacting
	 * to a genuine rejection rather than a value set by the test.
	 *
	 * @ticket 64550
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_real_charset_rejection_triggers_a_warning() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		// Make wpdb treat the storage column as utf8mb3, so it strips four byte characters
		// exactly as it would on a real utf8 (non-utf8mb4) column.
		add_filter( 'pre_get_col_charset', array( $this, 'force_utf8mb3_storage' ), 10, 3 );

		// Carry a four byte character into the stored payload, as an update response can.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'add_four_byte_char' ) );

		$warnings = $this->collect_warnings_from( 'wp_update_plugins' );

		remove_filter( 'pre_set_site_transient_update_plugins', array( $this, 'add_four_byte_char' ) );
		remove_filter( 'pre_get_col_charset', array( $this, 'force_utf8mb3_storage' ), 10 );

		$this->assertCount( 1, $warnings, 'A real charset rejection should surface a storage warning.' );
		$this->assertStringContainsString(
			'could not be stored',
			$warnings[0]['errstr'],
			'The warning did not describe the storage failure.'
		);
		$this->assertStringContainsString(
			'Processing the value',
			$warnings[0]['errstr'],
			"The warning should carry wpdb's own error, confirming it reacts to a real rejection."
		);
	}

	/**
	 * Reports the options/sitemeta storage column as utf8mb3 regardless of its real charset.
	 *
	 * @param string|null|false|WP_Error $charset The character set to use. Default null.
	 * @param string                     $table   The name of the table being checked.
	 * @param string                     $column  The name of the column being checked.
	 * @return string|null|false|WP_Error utf8mb3 for the value column, otherwise the given charset.
	 */
	public function force_utf8mb3_storage( $charset, $table, $column ) {
		if ( in_array( $column, array( 'option_value', 'meta_value' ), true ) ) {
			return 'utf8mb3';
		}

		return $charset;
	}

	/**
	 * Adds a four byte character to the stored update payload.
	 *
	 * @param mixed $value The site transient value about to be stored.
	 * @return mixed The value with a four byte character attached.
	 */
	public function add_four_byte_char( $value ) {
		if ( is_object( $value ) ) {
			$value->four_byte_probe = "Update available \xF0\x9F\x8E\x89";
		}

		return $value;
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
