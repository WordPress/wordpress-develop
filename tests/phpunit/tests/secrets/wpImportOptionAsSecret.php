<?php
/**
 * Tests for wp_import_option_as_secret().
 *
 * @group secrets
 */
class Tests_Secrets_WpImportOptionAsSecret extends WP_UnitTestCase {

	public function test_imports_an_existing_option_value(): void {
		update_option( 'my_plugin_api_key', 'sk_live_from_an_option' );

		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ) );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'sk_live_from_an_option', $secret->reveal() );
	}

	public function test_leaves_the_source_option_untouched(): void {
		update_option( 'my_plugin_api_key', 'sk_live_from_an_option' );

		wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' );

		$this->assertSame( 'sk_live_from_an_option', get_option( 'my_plugin_api_key' ) );
	}

	public function test_flags_the_imported_secret_for_rotation(): void {
		update_option( 'my_plugin_api_key', 'value' );
		wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'current', $record );
		$this->assertIsArray( $record['current'] );
		$this->assertTrue( $record['current']['needs_rotation'] );
	}

	public function test_an_ordinary_write_does_not_flag_needs_rotation(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'current', $record );
		$this->assertIsArray( $record['current'] );
		$this->assertFalse( $record['current']['needs_rotation'] );
	}

	public function test_rejects_a_nonexistent_option(): void {
		$result = wp_import_option_as_secret( 'this_option_was_never_set', 'myplugin/api-key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	public function test_rejects_a_non_string_option_value(): void {
		update_option( 'my_plugin_settings', array( 'not' => 'a string' ) );

		$result = wp_import_option_as_secret( 'my_plugin_settings', 'myplugin/api-key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	public function test_rejects_an_invalid_destination_name(): void {
		update_option( 'my_plugin_api_key', 'value' );

		$result = wp_import_option_as_secret( 'my_plugin_api_key', 'Not A Valid Name' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

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

		wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' );

		$this->assertIsArray( $captured );
		list( $name, $action ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name );
		$this->assertSame( 'imported', $action );
	}

	/**
	 * An import onto an existing secret behaves like any other write, including
	 * demotion – there is nothing special about the destination just because the
	 * new value's source happens to be an option.
	 */
	public function test_importing_over_an_existing_secret_demotes_it_like_any_write(): void {
		wp_set_secret( 'myplugin/api-key', 'original-value' );
		update_option( 'my_plugin_api_key', 'imported-value' );

		wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' );

		$current  = wp_get_secret( 'myplugin/api-key' );
		$previous = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );

		$this->assertInstanceOf( WP_Secret::class, $current );
		$this->assertInstanceOf( WP_Secret::class, $previous );
		$this->assertSame( 'imported-value', $current->reveal() );
		$this->assertSame( 'original-value', $previous->reveal() );
	}
}
