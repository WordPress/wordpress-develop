<?php

/**
 * @group admin
 *
 * @covers ::wp_check_php_version
 */
class Tests_Admin_Includes_Misc_WpCheckPhpVersion_Test extends WP_UnitTestCase {

	/**
	 * Cleans up transients after each test.
	 */
	public function tear_down() {
		$key = md5( PHP_VERSION );
		delete_site_transient( 'php_check_' . $key );
		parent::tear_down();
	}

	/**
	 * @ticket 65203
	 */
	public function test_wp_check_php_version_returns_false_on_api_failure() {
		add_filter( 'pre_http_request', array( $this, 'mock_api_failure' ), 10, 3 );

		$result = wp_check_php_version();

		remove_filter( 'pre_http_request', array( $this, 'mock_api_failure' ) );

		$this->assertFalse( $result, 'wp_check_php_version() should return false on API failure.' );
	}

	/**
	 * @ticket 65203
	 */
	public function test_wp_check_php_version_successful_response() {
		add_filter( 'pre_http_request', array( $this, 'mock_api_success' ), 10, 3 );

		$result = wp_check_php_version();

		remove_filter( 'pre_http_request', array( $this, 'mock_api_success' ) );

		$this->assertIsArray( $result, 'wp_check_php_version() should return an array on successful API response.' );
		$this->assertSame( '8.2', $result['recommended_version'] );
		$this->assertSame( '7.4', $result['minimum_version'] );
		$this->assertTrue( $result['is_supported'] );
		$this->assertTrue( $result['is_secure'] );
	}

	/**
	 * @ticket 65203
	 */
	public function test_wp_check_php_version_caches_result_in_transient() {
		add_filter( 'pre_http_request', array( $this, 'mock_api_success' ), 10, 3 );

		wp_check_php_version();

		remove_filter( 'pre_http_request', array( $this, 'mock_api_success' ) );

		$key    = md5( PHP_VERSION );
		$cached = get_site_transient( 'php_check_' . $key );

		$this->assertIsArray( $cached, 'Result should be cached in a site transient.' );
		$this->assertSame( '8.2', $cached['recommended_version'] );
	}

	/**
	 * @ticket 65203
	 */
	public function test_wp_check_php_version_uses_cached_result() {
		$key    = md5( PHP_VERSION );
		$cached = array(
			'recommended_version' => '8.3',
			'minimum_version'     => '7.4',
			'is_supported'        => true,
			'is_secure'           => true,
			'is_acceptable'       => true,
		);
		set_site_transient( 'php_check_' . $key, $cached );

		// If it hits the API, it will return the mocked success version (8.2) instead of 8.3.
		add_filter( 'pre_http_request', array( $this, 'mock_api_success' ), 10, 3 );

		$result = wp_check_php_version();

		remove_filter( 'pre_http_request', array( $this, 'mock_api_success' ) );

		$this->assertSame( '8.3', $result['recommended_version'], 'wp_check_php_version() should use the cached result if available.' );
	}

	/**
	 * @ticket 65203
	 *
	 * @requires PHP >= 8.0
	 */
	public function test_wp_is_php_version_acceptable_filter() {
		add_filter( 'pre_http_request', array( $this, 'mock_api_success' ), 10, 3 );
		add_filter( 'wp_is_php_version_acceptable', '__return_false' );

		$result = wp_check_php_version();

		remove_filter( 'pre_http_request', array( $this, 'mock_api_success' ) );
		remove_filter( 'wp_is_php_version_acceptable', '__return_false' );

		$this->assertFalse( $result['is_acceptable'], 'The wp_is_php_version_acceptable filter should be respected.' );
	}

	/**
	 * @ticket 65203
	 *
	 * @requires PHP < 8.0
	 */
	public function test_wp_check_php_version_future_minimum_logic() {
		add_filter( 'pre_http_request', array( $this, 'mock_api_success' ), 10, 3 );

		$result = wp_check_php_version();

		remove_filter( 'pre_http_request', array( $this, 'mock_api_success' ) );

		$this->assertTrue( $result['is_lower_than_future_minimum'], 'is_lower_than_future_minimum should be true for PHP < 8.0.' );
		$this->assertFalse( $result['is_acceptable'], 'is_acceptable should be false for PHP < 8.0 regardless of API response.' );
	}

	/**
	 * Mock HTTP request for API success.
	 *
	 * @return array{
	 *     response: array{code: int},
	 *     body:     string,
	 * }
	 */
	public function mock_api_success(): array {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'recommended_version' => '8.2',
					'minimum_version'     => '7.4',
					'is_supported'        => true,
					'is_secure'           => true,
					'is_acceptable'       => true,
				)
			),
		);
	}

	/**
	 * Mock HTTP request for API failure.
	 *
	 * @return array{
	 *     response: array{code: int},
	 * }
	 */
	public function mock_api_failure(): array {
		return array(
			'response' => array( 'code' => 500 ),
		);
	}
}
