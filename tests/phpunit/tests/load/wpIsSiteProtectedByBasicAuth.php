<?php

/**
 * Tests for wp_is_site_protected_by_basic_auth().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.2.0
 *
 * @group load
 *
 * @covers ::wp_is_site_protected_by_basic_auth
 * @covers ::wp_is_basic_auth_enforced_by_server
 */
class Tests_Load_wpIsSiteProtectedByBasicAuth extends WP_UnitTestCase {

	/**
	 * Backup of the Basic Auth related $_SERVER keys.
	 *
	 * @var array
	 */
	private $server_backup = array();

	public function set_up() {
		parent::set_up();

		foreach ( array( 'PHP_AUTH_USER', 'PHP_AUTH_PW' ) as $key ) {
			$this->server_backup[ $key ] = isset( $_SERVER[ $key ] ) ? $_SERVER[ $key ] : null;
			unset( $_SERVER[ $key ] );
		}

		delete_transient( 'wp_basic_auth_enforced' );
	}

	public function tear_down() {
		foreach ( $this->server_backup as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}

		delete_transient( 'wp_basic_auth_enforced' );

		parent::tear_down();
	}

	/**
	 * Short-circuits the loopback request with a given response.
	 *
	 * @param int   $status  Response status code.
	 * @param array $headers Response headers. Default empty array.
	 */
	private function mock_loopback_response( $status, $headers = array() ) {
		add_filter(
			'pre_http_request',
			static function () use ( $status, $headers ) {
				return array(
					'headers'  => $headers,
					'body'     => '',
					'response' => array(
						'code'    => $status,
						'message' => get_status_header_desc( $status ),
					),
				);
			}
		);
	}

	/**
	 * @ticket 66000
	 */
	public function test_should_return_false_when_no_credentials_are_present() {
		$this->assertFalse( wp_is_site_protected_by_basic_auth( 'front' ) );
	}

	/**
	 * @ticket 66000
	 */
	public function test_should_not_make_a_loopback_request_when_no_credentials_are_present() {
		$requests = 0;

		add_filter(
			'pre_http_request',
			static function ( $preempt ) use ( &$requests ) {
				++$requests;
				return $preempt;
			}
		);

		wp_is_site_protected_by_basic_auth( 'front' );

		$this->assertSame( 0, $requests );
	}

	/**
	 * @ticket 66000
	 *
	 * @dataProvider data_credentials
	 *
	 * @param array $server   Values to set on $_SERVER.
	 * @param bool  $expected Expected result.
	 */
	public function test_should_detect_credentials( $server, $expected ) {
		foreach ( $server as $key => $value ) {
			$_SERVER[ $key ] = $value;
		}

		$this->mock_loopback_response( 401, array( 'www-authenticate' => 'Basic realm="Restricted"' ) );

		$this->assertSame( $expected, wp_is_site_protected_by_basic_auth( 'front' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_credentials() {
		return array(
			'a user only'         => array( array( 'PHP_AUTH_USER' => 'user' ), true ),
			'a password only'     => array( array( 'PHP_AUTH_PW' => 'pass' ), true ),
			'a user and password' => array(
				array(
					'PHP_AUTH_USER' => 'user',
					'PHP_AUTH_PW'   => 'pass',
				),
				true,
			),
			'an empty user'       => array( array( 'PHP_AUTH_USER' => '' ), false ),
			'an empty password'   => array( array( 'PHP_AUTH_PW' => '' ), false ),
		);
	}

	/**
	 * Stale credentials replayed by the browser must not count as protection.
	 *
	 * @ticket 66000
	 */
	public function test_should_return_false_for_stale_credentials() {
		$_SERVER['PHP_AUTH_USER'] = 'staleuser';
		$_SERVER['PHP_AUTH_PW']   = 'stalepass';

		$this->mock_loopback_response( 200 );

		$this->assertFalse( wp_is_site_protected_by_basic_auth( 'front' ) );
	}

	/**
	 * A 401 without a Basic challenge is some other authentication scheme.
	 *
	 * @ticket 66000
	 */
	public function test_should_return_false_for_a_non_basic_challenge() {
		$_SERVER['PHP_AUTH_USER'] = 'user';

		$this->mock_loopback_response( 401, array( 'www-authenticate' => 'Bearer realm="api"' ) );

		$this->assertFalse( wp_is_site_protected_by_basic_auth( 'front' ) );
	}

	/**
	 * @ticket 66000
	 */
	public function test_should_return_true_when_the_server_sends_a_basic_challenge() {
		$_SERVER['PHP_AUTH_USER'] = 'user';

		$this->mock_loopback_response( 401, array( 'www-authenticate' => 'Basic realm="Restricted"' ) );

		$this->assertTrue( wp_is_site_protected_by_basic_auth( 'front' ) );
	}

	/**
	 * A blocked loopback request must not unlock Application Passwords.
	 *
	 * @ticket 66000
	 */
	public function test_should_return_true_when_the_loopback_request_fails() {
		$_SERVER['PHP_AUTH_USER'] = 'user';

		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Connection refused.' );
			}
		);

		$this->assertTrue( wp_is_site_protected_by_basic_auth( 'front' ) );
		$this->assertFalse( get_transient( 'wp_basic_auth_enforced' ), 'A failed request should not be cached.' );
	}

	/**
	 * @ticket 66000
	 */
	public function test_should_only_make_one_loopback_request() {
		$_SERVER['PHP_AUTH_USER'] = 'user';
		$requests                 = 0;

		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;
				return array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			}
		);

		wp_is_site_protected_by_basic_auth( 'front' );
		wp_is_site_protected_by_basic_auth( 'front' );

		$this->assertSame( 1, $requests );
	}

	/**
	 * The documented escape hatch must keep working.
	 *
	 * @ticket 66000
	 */
	public function test_filter_should_override_a_positive_detection() {
		$_SERVER['PHP_AUTH_USER'] = 'user';

		$this->mock_loopback_response( 401, array( 'www-authenticate' => 'Basic realm="Restricted"' ) );

		add_filter( 'wp_is_site_protected_by_basic_auth', '__return_false' );

		$this->assertFalse( wp_is_site_protected_by_basic_auth( 'front' ) );
	}

	/**
	 * @ticket 66000
	 */
	public function test_filter_should_override_a_negative_detection() {
		add_filter( 'wp_is_site_protected_by_basic_auth', '__return_true' );

		$this->assertTrue( wp_is_site_protected_by_basic_auth( 'front' ) );
	}

	/**
	 * @ticket 66000
	 */
	public function test_filter_should_receive_the_explicit_context() {
		$context = null;

		add_filter(
			'wp_is_site_protected_by_basic_auth',
			static function ( $is_protected, $passed_context ) use ( &$context ) {
				$context = $passed_context;
				return $is_protected;
			},
			10,
			2
		);

		wp_is_site_protected_by_basic_auth( 'login' );

		$this->assertSame( 'login', $context );
	}

	/**
	 * @ticket 66000
	 */
	public function test_context_should_default_to_login_on_the_login_screen() {
		global $pagenow;

		$original = $pagenow;
		$pagenow  = 'wp-login.php';
		$context  = null;

		add_filter(
			'wp_is_site_protected_by_basic_auth',
			static function ( $is_protected, $passed_context ) use ( &$context ) {
				$context = $passed_context;
				return $is_protected;
			},
			10,
			2
		);

		wp_is_site_protected_by_basic_auth();

		$pagenow = $original;

		$this->assertSame( 'login', $context );
	}
}
