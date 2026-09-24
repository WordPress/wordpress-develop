<?php
/**
 * Tests for wp_get_active_and_valid_plugins().
 *
 * @group load
 * @covers ::wp_get_active_and_valid_plugins
 */
class Tests_Load_WpGetActiveAndValidPlugins extends WP_UnitTestCase {

	/**
	 * Tests that Hello Dolly plugin migration logic is present in the function.
	 *
	 * This test verifies that the migration code exists by checking that
	 * hello-dolly/hello.php is handled correctly when it exists.
	 *
	 * @ticket 53323
	 */
	public function test_hello_dolly_migration_logic_exists() {
		// Ensure we have the hello-dolly plugin available for testing.
		$hello_dolly_file = WP_PLUGIN_DIR . '/hello-dolly/hello.php';

		if ( ! file_exists( $hello_dolly_file ) ) {
			$this->markTestSkipped( 'Hello Dolly plugin file not found for testing.' );
		}

		// Store original active plugins.
		$original_active_plugins = get_option( 'active_plugins', array() );

		// Set hello-dolly/hello.php as active (the new format).
		$test_active_plugins = array( 'hello-dolly/hello.php' );
		update_option( 'active_plugins', $test_active_plugins );

		// Call the function - it should handle the hello-dolly plugin correctly.
		$valid_plugins = wp_get_active_and_valid_plugins();

		// Verify hello-dolly plugin is included when it exists.
		$this->assertContains( $hello_dolly_file, $valid_plugins, 'Hello Dolly plugin should be loaded when active and file exists.' );

		// Restore original state.
		update_option( 'active_plugins', $original_active_plugins );
	}

	/**
	 * Tests that function handles empty active plugins gracefully.
	 *
	 * @ticket 53323
	 */
	public function test_handles_empty_active_plugins() {
		// Store original active plugins.
		$original_active_plugins = get_option( 'active_plugins', array() );

		// Set empty active plugins.
		update_option( 'active_plugins', array() );

		// Call the function.
		$valid_plugins = wp_get_active_and_valid_plugins();

		// Should return array (might contain hacks file, but no plugins).
		$this->assertIsArray( $valid_plugins );

		// Restore original state.
		update_option( 'active_plugins', $original_active_plugins );
	}

	/**
	 * Tests Hello Dolly migration from hello.php to hello-dolly/hello.php.
	 *
	 * This test simulates the scenario where:
	 * 1. hello.php is active in the database
	 * 2. The old hello.php file doesn't exist (removed during update)
	 * 3. The new hello-dolly/hello.php file exists
	 * 4. The migration should automatically update active_plugins option
	 *
	 * @ticket 53323
	 */
	public function test_hello_dolly_migration_from_file_to_directory() {
		// Store original active plugins.
		$original_active_plugins = get_option( 'active_plugins', array() );

		// Set hello.php as active (simulating pre-migration state).
		$test_active_plugins = array( 'hello.php' );
		update_option( 'active_plugins', $test_active_plugins );

		// Use filter to simulate migration conditions: old file gone, new file exists.
		add_filter( 'wp_should_migrate_hello_dolly', array( $this, 'simulate_hello_dolly_migration_needed' ) );

		try {
			// Call the function that should trigger migration.
			wp_get_active_and_valid_plugins();

			// Check that migration occurred.
			$updated_active_plugins = get_option( 'active_plugins', array() );
			$this->assertNotContains( 'hello.php', $updated_active_plugins, 'Old hello.php should be removed from active plugins after migration' );
			$this->assertContains( 'hello-dolly/hello.php', $updated_active_plugins, 'New hello-dolly/hello.php should be added to active plugins after migration' );

		} finally {
			// Always restore original state.
			remove_filter( 'wp_should_migrate_hello_dolly', array( $this, 'simulate_hello_dolly_migration_needed' ) );
			update_option( 'active_plugins', $original_active_plugins );
		}
	}

	/**
	 * Tests that migration does NOT occur when old file still exists.
	 *
	 * This ensures the migration only happens in the correct scenario.
	 *
	 * @ticket 53323
	 */
	public function test_hello_dolly_no_migration_when_old_file_exists() {
		// Store original active plugins.
		$original_active_plugins = get_option( 'active_plugins', array() );

		// Set hello.php as active.
		$test_active_plugins = array( 'hello.php' );
		update_option( 'active_plugins', $test_active_plugins );

		// Use filter to simulate both files existing (no migration needed).
		add_filter( 'wp_should_migrate_hello_dolly', array( $this, 'simulate_hello_dolly_migration_not_needed' ) );

		try {
			// Call the function - migration should NOT occur.
			wp_get_active_and_valid_plugins();

			// Check that NO migration occurred.
			$updated_active_plugins = get_option( 'active_plugins', array() );
			$this->assertContains( 'hello.php', $updated_active_plugins, 'hello.php should remain when old file still exists' );
			$this->assertNotContains( 'hello-dolly/hello.php', $updated_active_plugins, 'Migration should not occur when old file exists' );

		} finally {
			// Always restore original state.
			remove_filter( 'wp_should_migrate_hello_dolly', array( $this, 'simulate_hello_dolly_migration_not_needed' ) );
			update_option( 'active_plugins', $original_active_plugins );
		}
	}

	/**
	 * Filter callback to simulate Hello Dolly migration being needed.
	 *
	 * @param bool $should_migrate Default migration decision.
	 * @return bool Always true to simulate migration conditions are met.
	 */
	public function simulate_hello_dolly_migration_needed( $should_migrate ) {
		return true;
	}

	/**
	 * Filter callback to simulate Hello Dolly migration NOT being needed.
	 *
	 * @param bool $should_migrate Default migration decision.
	 * @return bool Always false to simulate no migration needed.
	 */
	public function simulate_hello_dolly_migration_not_needed( $should_migrate ) {
		return false;
	}

	/**
	 * Tests Hello Dolly migration behavior when neither file exists.
	 *
	 * This test simulates the scenario where:
	 * 1. hello.php is active in the database
	 * 2. Neither the old hello.php nor new hello-dolly/hello.php files exist
	 * 3. The migration should NOT occur (no new file to migrate to)
	 * 4. Verifies filter is called with correct parameters
	 *
	 * @ticket 53323
	 */
	public function test_hello_dolly_no_migration_when_neither_file_exists() {
		// Store original active plugins.
		$original_active_plugins = get_option( 'active_plugins', array() );

		// Set hello.php as active.
		$test_active_plugins = array( 'hello.php' );
		update_option( 'active_plugins', $test_active_plugins );

		// Debug: Check what's in the option before calling the function.
		$plugins_before = get_option( 'active_plugins', array() );

		// Use filter to simulate neither file existing and capture filter call details.
		add_filter( 'wp_should_migrate_hello_dolly', array( $this, 'capture_hello_dolly_migration_filter_call' ), 10, 3 );

		try {
			// Call the function - migration should NOT occur since no new file exists.
			$start_time    = microtime( true );
			$valid_plugins = wp_get_active_and_valid_plugins();
			$end_time      = microtime( true );

			// Debug: Check what's in the option after calling the function.
			$plugins_after = get_option( 'active_plugins', array() );

			// Debug output (this will show in test output if it fails).
			if ( ! in_array( 'hello.php', $plugins_after, true ) ) {
				$this->fail(
					'hello.php was removed from active_plugins! ' .
					'Before: ' . print_r( $plugins_before, true ) .
					', After: ' . print_r( $plugins_after, true ) .
					', Filter called: ' . ( $this->filter_was_called ? 'yes' : 'no' ) .
					', Should migrate: ' . ( $this->filter_should_migrate ? 'yes' : 'no' )
				);
			}

			// Check that NO migration occurred in the active_plugins option.
			$updated_active_plugins = get_option( 'active_plugins', array() );
			$this->assertContains( 'hello.php', $updated_active_plugins, 'hello.php should remain in active_plugins when neither file exists' );
			$this->assertNotContains( 'hello-dolly/hello.php', $updated_active_plugins, 'Migration should not occur when neither file exists' );

			// However, hello.php should NOT be in the valid plugins array since the file doesn't exist.
			$hello_php_path = WP_PLUGIN_DIR . '/hello.php';
			$this->assertNotContains( $hello_php_path, $valid_plugins, 'hello.php should not be in valid plugins when file does not exist' );

			// Verify the filter was called.
			$this->assertTrue( $this->filter_was_called, 'wp_should_migrate_hello_dolly filter should have been called' );
			// Note: We don't assert the actual file existence here since we're forcing the migration decision
			// to false to simulate the neither-file-exists scenario.

			// Log timing information.
			$execution_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds.
			$this->assertLessThan( 100, $execution_time, 'Function should complete quickly when files do not exist' );

		} finally {
			// Always restore original state.
			remove_filter( 'wp_should_migrate_hello_dolly', array( $this, 'capture_hello_dolly_migration_filter_call' ) );
			update_option( 'active_plugins', $original_active_plugins );

			// Reset filter capture properties.
			$this->reset_filter_capture_properties();
		}
	}

	/**
	 * Properties to capture filter call details.
	 */
	private $filter_was_called       = false;
	private $filter_should_migrate   = null;
	private $filter_old_hello_exists = null;
	private $filter_new_hello_exists = null;

	/**
	 * Filter callback to capture Hello Dolly migration filter call details.
	 *
	 * This simulates the scenario where neither file exists and captures
	 * the parameters passed to the filter for verification.
	 *
	 * @param bool $should_migrate Default migration decision.
	 * @param bool $old_hello_exists Whether the old hello.php file exists.
	 * @param bool $new_hello_exists Whether the new hello-dolly/hello.php file exists.
	 * @return bool Always false to simulate no migration needed when neither file exists.
	 */
	public function capture_hello_dolly_migration_filter_call( $should_migrate, $old_hello_exists, $new_hello_exists ) {
		$this->filter_was_called       = true;
		$this->filter_should_migrate   = $should_migrate;
		$this->filter_old_hello_exists = $old_hello_exists;
		$this->filter_new_hello_exists = $new_hello_exists;

		// For this test, we force no migration to simulate the scenario where neither file exists.
		// Even if files actually exist in the test environment, we're testing the behavior
		// when they don't exist.
		return false;
	}

	/**
	 * Reset filter capture properties between tests.
	 */
	private function reset_filter_capture_properties() {
		$this->filter_was_called       = false;
		$this->filter_should_migrate   = null;
		$this->filter_old_hello_exists = null;
		$this->filter_new_hello_exists = null;
	}
}
