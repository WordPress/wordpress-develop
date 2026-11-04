<?php
/**
 * Direct tests for WP_Secrets_Broken_Store and WP_Secrets_Broken_Keyring: the
 * sentinels _wp_secrets_get_store()/_wp_secrets_get_key_manager() fall back to when
 * a secrets.php drop-in exists but left an invalid override.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_BrokenDropinFallbacks extends WP_UnitTestCase {

	/**
	 * Ensure that WP_Secrets_Broken_Store implements the WP_Secrets_Store interface.
	 *
	 * @coversNothing
	 */
	public function test_broken_store_implements_the_interface(): void {
		$this->assertInstanceOf( WP_Secrets_Store::class, new WP_Secrets_Broken_Store() ); // @phpstan-ignore method.alreadyNarrowedType (Intentionally checking the class against its interface at runtime.)
	}

	/**
	 * Ensure that WP_Secrets_Broken_Store::get() fails with a store-unavailable error.
	 *
	 * @covers WP_Secrets_Broken_Store::get
	 */
	public function test_broken_store_get_fails(): void {
		$result = ( new WP_Secrets_Broken_Store() )->get( 'myplugin/api-key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * Ensure that WP_Secrets_Broken_Store::set() returns a WP_Error rather than storing a record.
	 *
	 * @covers WP_Secrets_Broken_Store::set
	 */
	public function test_broken_store_set_fails(): void {
		$result = ( new WP_Secrets_Broken_Store() )->set( 'myplugin/api-key', array() );

		$this->assertWPError( $result );
	}

	/**
	 * Ensure that WP_Secrets_Broken_Store::delete() returns a WP_Error.
	 *
	 * @covers WP_Secrets_Broken_Store::delete
	 */
	public function test_broken_store_delete_fails(): void {
		$result = ( new WP_Secrets_Broken_Store() )->delete( 'myplugin/api-key' );

		$this->assertWPError( $result );
	}

	/**
	 * Ensure that WP_Secrets_Broken_Store::list_names() returns a WP_Error rather than a list.
	 *
	 * @covers WP_Secrets_Broken_Store::list_names
	 */
	public function test_broken_store_list_names_fails(): void {
		$result = ( new WP_Secrets_Broken_Store() )->list_names();

		$this->assertWPError( $result );
	}

	/**
	 * Ensure that WP_Secrets_Broken_Keyring implements the WP_Secrets_Keyring interface.
	 *
	 * @coversNothing
	 */
	public function test_broken_keyring_implements_the_interface(): void {
		$this->assertInstanceOf( WP_Secrets_Keyring::class, new WP_Secrets_Broken_Keyring() ); // @phpstan-ignore method.alreadyNarrowedType (Intentionally checking the class against its interface at runtime.)
	}

	/**
	 * Ensure that WP_Secrets_Broken_Keyring::wrap() fails with a key-unavailable error.
	 *
	 * @covers WP_Secrets_Broken_Keyring::wrap
	 */
	public function test_broken_keyring_wrap_fails(): void {
		$result = ( new WP_Secrets_Broken_Keyring() )->wrap( 'material' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * Ensure that WP_Secrets_Broken_Keyring::unwrap() returns a WP_Error.
	 *
	 * @covers WP_Secrets_Broken_Keyring::unwrap
	 */
	public function test_broken_keyring_unwrap_fails(): void {
		$result = ( new WP_Secrets_Broken_Keyring() )->unwrap( 'wrapped' );

		$this->assertWPError( $result );
	}

	/**
	 * Ensure that WP_Secrets_Broken_Keyring::get_key_source() returns a description for Site Health.
	 *
	 * @covers WP_Secrets_Broken_Keyring::get_key_source
	 */
	public function test_broken_keyring_reports_a_key_source(): void {
		$this->assertIsString( ( new WP_Secrets_Broken_Keyring() )->get_key_source() );
	}

	/**
	 * Ensure that wp_using_secrets_dropin() returns false when no drop-in has been loaded.
	 *
	 * @covers ::wp_using_secrets_dropin
	 */
	public function test_wp_using_secrets_dropin_is_false_by_default(): void {
		$this->assertFalse( wp_using_secrets_dropin() );
	}

	/**
	 * Ensure that wp_using_secrets_dropin() returns true once the drop-in loader flag is set.
	 *
	 * @covers ::wp_using_secrets_dropin
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_using_secrets_dropin_reports_true_once_the_loader_flag_is_set(): void {
		// Simulates what wp_load_secrets_dropin() sets when a drop-in file is
		// found, without depending on an actual file on disk – see
		// tests/phpunit/test-secrets-extension-points.php for why.
		$GLOBALS['wp_secrets_dropin_loaded'] = true;

		$this->assertTrue( wp_using_secrets_dropin() );
	}
}
