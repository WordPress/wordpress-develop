<?php
/**
 * Direct tests for WP_Secrets_Broken_Store and WP_Secrets_Broken_Keyring: the
 * sentinels _wp_secrets_get_store()/_wp_secrets_get_key_manager() fall back to when
 * a secrets.php drop-in exists but left an invalid override.
 *
 * @group secrets
 */
class Tests_Secrets_BrokenDropinFallbacks extends WP_UnitTestCase {

	public function test_broken_store_implements_the_interface() {
		$this->assertInstanceOf( WP_Secrets_Store::class, new WP_Secrets_Broken_Store() );
	}

	public function test_broken_store_get_fails() {
		$result = ( new WP_Secrets_Broken_Store() )->get( 'myplugin/api-key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_broken_store_set_fails() {
		$result = ( new WP_Secrets_Broken_Store() )->set( 'myplugin/api-key', array() );

		$this->assertWPError( $result );
	}

	public function test_broken_store_delete_fails() {
		$result = ( new WP_Secrets_Broken_Store() )->delete( 'myplugin/api-key' );

		$this->assertWPError( $result );
	}

	public function test_broken_store_list_names_fails() {
		$result = ( new WP_Secrets_Broken_Store() )->list_names();

		$this->assertWPError( $result );
	}


	public function test_broken_keyring_implements_the_interface() {
		$this->assertInstanceOf( WP_Secrets_Keyring::class, new WP_Secrets_Broken_Keyring() );
	}

	public function test_broken_keyring_wrap_fails() {
		$result = ( new WP_Secrets_Broken_Keyring() )->wrap( 'material' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_broken_keyring_unwrap_fails() {
		$result = ( new WP_Secrets_Broken_Keyring() )->unwrap( 'wrapped' );

		$this->assertWPError( $result );
	}

	public function test_broken_keyring_reports_a_key_source() {
		$this->assertIsString( ( new WP_Secrets_Broken_Keyring() )->get_key_source() );
	}

	public function test_wp_using_secrets_dropin_is_false_by_default() {
		$this->assertFalse( wp_using_secrets_dropin() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_using_secrets_dropin_reports_true_once_the_loader_flag_is_set() {
		// Simulates what wp_load_secrets_dropin() sets when a drop-in file is
		// found, without depending on an actual file on disk -- see
		// tests/phpunit/test-secrets-extension-points.php for why.
		$GLOBALS['wp_secrets_dropin_loaded'] = true;

		$this->assertTrue( wp_using_secrets_dropin() );
	}
}
