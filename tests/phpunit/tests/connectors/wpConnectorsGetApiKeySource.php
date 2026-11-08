<?php
/**
 * Tests for _wp_connectors_get_api_key_source().
 *
 * @covers ::_wp_connectors_get_api_key_source
 *
 * @group connectors
 */
class Tests_Connectors_WpConnectorsGetApiKeySource extends WP_UnitTestCase {

	/**
	 * @ticket 64957
	 */
	public function test_returns_none_when_no_key_found() {
		$result = _wp_connectors_get_api_key_source( 'connectors_ai_nonexistent_api_key' );

		$this->assertSame( 'none', $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_returns_database_when_option_set() {
		$setting_name = 'connectors_ai_test_source_api_key';
		update_option( $setting_name, 'sk-test-key-123' );

		$result = _wp_connectors_get_api_key_source( $setting_name );

		delete_option( $setting_name );

		$this->assertSame( 'database', $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_returns_env_when_env_var_set() {
		$env_var = 'WP_TEST_CONNECTOR_API_KEY';
		putenv( "{$env_var}=sk-from-env" );

		$result = _wp_connectors_get_api_key_source( 'connectors_ai_test_api_key', $env_var );

		putenv( $env_var );

		$this->assertSame( 'env', $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_returns_constant_when_constant_defined() {
		$constant_name = 'WP_TEST_CONNECTOR_CONST_KEY';
		if ( ! defined( $constant_name ) ) {
			define( $constant_name, 'sk-from-constant' );
		}

		$result = _wp_connectors_get_api_key_source( 'connectors_ai_test_api_key', '', $constant_name );

		$this->assertSame( 'constant', $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_env_takes_priority_over_constant_and_database() {
		$setting_name  = 'connectors_ai_priority_test_api_key';
		$env_var       = 'WP_TEST_PRIORITY_ENV_KEY';
		$constant_name = 'WP_TEST_PRIORITY_CONST_KEY';

		update_option( $setting_name, 'sk-from-db' );
		putenv( "{$env_var}=sk-from-env" );
		if ( ! defined( $constant_name ) ) {
			define( $constant_name, 'sk-from-constant' );
		}

		$result = _wp_connectors_get_api_key_source( $setting_name, $env_var, $constant_name );

		putenv( $env_var );
		delete_option( $setting_name );

		$this->assertSame( 'env', $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_constant_takes_priority_over_database() {
		$setting_name  = 'connectors_ai_const_priority_api_key';
		$constant_name = 'WP_TEST_CONST_PRIORITY_KEY';

		update_option( $setting_name, 'sk-from-db' );
		if ( ! defined( $constant_name ) ) {
			define( $constant_name, 'sk-from-constant' );
		}

		$result = _wp_connectors_get_api_key_source( $setting_name, '', $constant_name );

		delete_option( $setting_name );

		$this->assertSame( 'constant', $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_skips_env_check_when_env_var_name_empty() {
		$env_var      = 'WP_TEST_SKIP_ENV_KEY';
		$setting_name = 'connectors_ai_skip_env_api_key';

		putenv( "{$env_var}=sk-from-env" );
		update_option( $setting_name, 'sk-from-db' );

		// Empty env_var_name means env is not checked, falls through to database.
		$result = _wp_connectors_get_api_key_source( $setting_name, '', '' );

		putenv( $env_var );
		delete_option( $setting_name );

		$this->assertSame( 'database', $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_skips_constant_check_when_constant_name_empty() {
		$constant_name = 'WP_TEST_SKIP_CONST_KEY';
		$setting_name  = 'connectors_ai_skip_const_api_key';

		if ( ! defined( $constant_name ) ) {
			define( $constant_name, 'sk-from-constant' );
		}
		update_option( $setting_name, 'sk-from-db' );

		// Empty constant_name means constant is not checked, falls through to database.
		$result = _wp_connectors_get_api_key_source( $setting_name, '' );

		delete_option( $setting_name );

		$this->assertSame( 'database', $result );
	}
}
