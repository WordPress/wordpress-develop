<?php
/**
 * Tests for wp_import_option_as_secret().
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WpImportOptionAsSecret extends WP_UnitTestCase {

	/**
	 * Ensure that wp_import_option_as_secret() stores the option's value as a secret.
	 *
	 * @covers ::wp_import_option_as_secret
	 */
	public function test_imports_an_existing_option_value(): void {
		update_option( 'my_plugin_api_key', 'sk_live_from_an_option' );

		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing an existing option should succeed.' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The imported secret should be a WP_Secret.' );
		$this->assertSame( 'sk_live_from_an_option', $secret->reveal(), 'The imported secret should decrypt to the option value.' );
	}

	/**
	 * Ensure that wp_import_option_as_secret() does not modify or delete the source option.
	 *
	 * @covers ::wp_import_option_as_secret
	 */
	public function test_leaves_the_source_option_untouched(): void {
		update_option( 'my_plugin_api_key', 'sk_live_from_an_option' );

		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing my_plugin_api_key as myplugin/api-key should succeed.' );

		$this->assertSame( 'sk_live_from_an_option', get_option( 'my_plugin_api_key' ), 'The source option should be left unchanged.' );
	}

	/**
	 * Ensure that a secret imported from an option is flagged as needing rotation.
	 *
	 * @covers ::wp_import_option_as_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_flags_the_imported_secret_for_rotation(): void {
		update_option( 'my_plugin_api_key', 'value' );
		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing my_plugin_api_key as myplugin/api-key should succeed.' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );
		$this->assertTrue( $record['current']['needs_rotation'], 'An imported secret should be flagged as needing rotation.' );
	}

	/**
	 * Ensure that a secret written by wp_set_secret() is not flagged as needing rotation.
	 *
	 * The counterpart to the import test above: it proves the flag comes from the import,
	 * not from a default applied to every write.
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_an_ordinary_write_does_not_flag_needs_rotation(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );
		$this->assertFalse( $record['current']['needs_rotation'], 'An ordinary write should not be flagged as needing rotation.' );
	}

	/**
	 * Ensure that wp_import_option_as_secret() rejects an option that does not exist.
	 *
	 * @covers ::wp_import_option_as_secret
	 */
	public function test_rejects_a_nonexistent_option(): void {
		$result = wp_import_option_as_secret( 'this_option_was_never_set', 'myplugin/api-key' );

		$this->assertWPError( $result, 'A nonexistent option should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code(), 'A nonexistent option should produce the invalid value error code.' );
	}

	/**
	 * Ensure that wp_import_option_as_secret() rejects an option whose value is not a string.
	 *
	 * @covers ::wp_import_option_as_secret
	 */
	public function test_rejects_a_non_string_option_value(): void {
		update_option( 'my_plugin_settings', array( 'not' => 'a string' ) );

		$result = wp_import_option_as_secret( 'my_plugin_settings', 'myplugin/api-key' );

		$this->assertWPError( $result, 'A non-string option value should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code(), 'A non-string option value should produce the invalid value error code.' );
	}

	/**
	 * Ensure that wp_import_option_as_secret() rejects an invalid destination secret name.
	 *
	 * @covers ::wp_import_option_as_secret
	 */
	public function test_rejects_an_invalid_destination_name(): void {
		update_option( 'my_plugin_api_key', 'value' );

		$result = wp_import_option_as_secret( 'my_plugin_api_key', 'Not A Valid Name' );

		$this->assertWPError( $result, 'An invalid destination name should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code(), 'An invalid destination name should produce the invalid name error code.' );
	}

	/**
	 * Ensure that importing an option fires wp_secret_changed with the imported action.
	 *
	 * @covers ::wp_import_option_as_secret
	 */
	public function test_fires_the_change_hook_with_the_imported_action(): void {
		update_option( 'my_plugin_api_key', 'value' );

		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			7
		);

		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing my_plugin_api_key as myplugin/api-key should succeed.' );

		$this->assertIsArray( $captured, 'The wp_secret_changed action should have fired.' );
		list( $name, $action ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name, 'The hook should receive the destination secret name.' );
		$this->assertSame( 'imported', $action, 'The hook should receive the imported action.' );
	}

	/**
	 * An import onto an existing secret behaves like any other write, including
	 * demotion – there is nothing special about the destination just because the
	 * new value's source happens to be an option.
	 *
	 * @covers ::wp_import_option_as_secret
	 */
	public function test_importing_over_an_existing_secret_demotes_it_like_any_write(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'original-value' ), 'Setting myplugin/api-key to original-value should succeed.' );
		update_option( 'my_plugin_api_key', 'imported-value' );

		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing my_plugin_api_key as myplugin/api-key should succeed.' );

		$current  = wp_get_secret( 'myplugin/api-key' );
		$previous = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );

		$this->assertInstanceOf( WP_Secret::class, $current, 'The current version should be a WP_Secret.' );
		$this->assertInstanceOf( WP_Secret::class, $previous, 'The previous version should be a WP_Secret.' );
		$this->assertSame( 'imported-value', $current->reveal(), 'The current version should decrypt to the imported value.' );
		$this->assertSame( 'original-value', $previous->reveal(), 'The previous version should decrypt to the original value.' );
	}
}
