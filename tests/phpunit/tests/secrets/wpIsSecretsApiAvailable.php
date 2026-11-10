<?php
/**
 * Tests for wp_is_secrets_api_available().
 *
 * Every test runs in a separate process: the provider and key manager are cached for
 * the request, and the test bootstrap defines WP_SECRETS_KEY only outside separate
 * processes.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WpIsSecretsApiAvailable extends WP_UnitTestCase {

	/**
	 * Ensure that the API is available with a WP_SECRETS_KEY in the recommended form.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_available_with_a_base64_key(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$this->assertTrue( wp_is_secrets_api_available(), 'The API should be available with a base64-encoded 32-byte key.' );
	}

	/**
	 * Ensure that the API is available with a usable WP_SECRETS_KEY in the legacy form.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_available_with_a_legacy_key(): void {
		define( 'WP_SECRETS_KEY', 'a long legacy key that is not base64' );

		$this->assertTrue( wp_is_secrets_api_available(), 'The API should be available with a usable legacy-form key.' );
	}

	/**
	 * Ensure that the API is unavailable when WP_SECRETS_KEY is not defined.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unavailable_without_a_key(): void {
		$this->assertFalse( wp_is_secrets_api_available(), 'The API should be unavailable without WP_SECRETS_KEY.' );
		$this->assertWPError( wp_set_secret( 'myplugin/api-key', 'value' ), 'A write should fail when the API reports itself unavailable.' );
	}

	/**
	 * Ensure that the API is unavailable when WP_SECRETS_KEY is the wp-config-sample.php placeholder.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unavailable_with_the_placeholder(): void {
		define( 'WP_SECRETS_KEY', 'put your unique phrase here' );

		$this->assertFalse( wp_is_secrets_api_available(), 'The API should be unavailable when WP_SECRETS_KEY is the placeholder.' );
	}

	/**
	 * Ensure that the API is available when WP_SECRETS_KEY unwraps the stored root key.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_available_when_the_key_unwraps_the_root_key(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Storing a secret should succeed and create the root key.' );
		$this->assertNotFalse( get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'A root key should be stored after the first write.' );
		$this->assertTrue( wp_is_secrets_api_available(), 'The API should be available when the key unwraps the stored root key.' );
	}

	/**
	 * Ensure that the API is unavailable when WP_SECRETS_KEY cannot unwrap the stored root key.
	 *
	 * This is a site that stored secrets under one key, then defined another without
	 * re-wrapping the root key.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unavailable_when_the_key_cannot_unwrap_the_root_key(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'p', 32 ) ) );

		$wrapped = ( new WP_Secrets_Config_Key_Provider( true ) )->wrap( random_bytes( 32 ) );

		$this->assertIsString( $wrapped, 'Wrapping a root key under the previous key should succeed.' );

		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $wrapped );

		$this->assertFalse( wp_is_secrets_api_available(), 'The API should be unavailable when the key cannot unwrap the stored root key.' );
		$this->assertWPError( wp_set_secret( 'myplugin/api-key', 'value' ), 'A write should fail when the API reports itself unavailable.' );
		$this->assertSame( $wrapped, get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'The stored root key should be left unchanged.' );
	}

	/**
	 * Ensure that the API is unavailable when the secrets.php drop-in failed to load.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unavailable_with_a_broken_dropin(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$this->assertFalse( wp_is_secrets_api_available(), 'The API should be unavailable when the drop-in failed to load.' );
	}

	/**
	 * Ensure that a keyring from a drop-in makes the API available without WP_SECRETS_KEY.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_available_with_a_dropin_keyring_and_no_key(): void {
		$GLOBALS['wp_secrets_keyring'] = new Mock_Keyring();

		$this->assertTrue( wp_is_secrets_api_available(), 'The API should be available with a drop-in keyring.' );
	}

	/**
	 * Ensure that a provider from a drop-in makes the API available without WP_SECRETS_KEY.
	 *
	 * @covers ::wp_is_secrets_api_available
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_available_with_a_dropin_provider_and_no_key(): void {
		$GLOBALS['wp_secrets_provider'] = new WP_Secrets_Libsodium_Provider(
			new Mock_Store(),
			new WP_Secrets_Key_Manager( new Mock_Keyring() )
		);

		$this->assertTrue( wp_is_secrets_api_available(), 'The API should be available with a drop-in provider.' );
	}
}
