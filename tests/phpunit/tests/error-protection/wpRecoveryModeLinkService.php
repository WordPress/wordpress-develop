<?php

/**
 * Test the WP_Recovery_Mode_Link_Service class.
 *
 * @group error-protection
 *
 * @covers WP_Recovery_Mode_Link_Service
 */
class Tests_Error_Protection_wpRecoveryModeLinkService extends WP_UnitTestCase {

	/**
	 * Original $pagenow value.
	 *
	 * @var string|null
	 */
	private $orig_pagenow;

	/**
	 * Original $_GET superglobal.
	 *
	 * @var array
	 */
	private $orig_get;

	/**
	 * Cookie service instance.
	 *
	 * @var WP_Recovery_Mode_Cookie_Service
	 */
	private $cookie_service;

	/**
	 * Key service instance.
	 *
	 * @var WP_Recovery_Mode_Key_Service
	 */
	private $key_service;

	/**
	 * Link service instance under test.
	 *
	 * @var WP_Recovery_Mode_Link_Service
	 */
	private $service;

	/**
	 * Sets up each test method.
	 */
	public function set_up() {
		parent::set_up();

		$this->orig_pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : null;
		$this->orig_get     = $_GET;
		$_GET               = array();

		$this->cookie_service = new WP_Recovery_Mode_Cookie_Service();
		$this->key_service    = new WP_Recovery_Mode_Key_Service();
		$this->service        = new WP_Recovery_Mode_Link_Service( $this->cookie_service, $this->key_service );
	}

	/**
	 * Tears down each test method.
	 */
	public function tear_down() {
		if ( null !== $this->orig_pagenow ) {
			$GLOBALS['pagenow'] = $this->orig_pagenow;
		} else {
			unset( $GLOBALS['pagenow'] );
		}

		$_GET = $this->orig_get;
		delete_option( 'recovery_keys' );

		parent::tear_down();
	}

	/**
	 * Tests that generate_url creates a valid recovery mode URL with required query parameters.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::generate_url
	 */
	public function test_generate_url() {
		$url = $this->service->generate_url();

		$this->assertStringStartsWith( wp_login_url(), $url );

		$query = wp_parse_url( $url, PHP_URL_QUERY );
		$this->assertNotEmpty( $query );

		wp_parse_str( $query, $query_args );

		$this->assertArrayHasKey( 'action', $query_args );
		$this->assertSame( WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER, $query_args['action'] );

		$this->assertArrayHasKey( 'rm_token', $query_args );
		$this->assertNotEmpty( $query_args['rm_token'] );

		$this->assertArrayHasKey( 'rm_key', $query_args );
		$this->assertNotEmpty( $query_args['rm_key'] );

		// Verify the generated key is stored and can be validated.
		$validated = $this->key_service->validate_recovery_mode_key( $query_args['rm_token'], $query_args['rm_key'], HOUR_IN_SECONDS );
		$this->assertTrue( $validated );
	}

	/**
	 * Tests that the recovery_mode_begin_url filter can modify the generated recovery mode URL.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::generate_url
	 */
	public function test_generate_url_filter() {
		$filter_captured = array();

		$filter_callback = function ( $url, $token, $key ) use ( &$filter_captured ) {
			$filter_captured = array(
				'url'   => $url,
				'token' => $token,
				'key'   => $key,
			);

			return add_query_arg( 'custom_param', 'wp_test', $url );
		};

		add_filter( 'recovery_mode_begin_url', $filter_callback, 10, 3 );
		$url = $this->service->generate_url();
		remove_filter( 'recovery_mode_begin_url', $filter_callback, 10 );

		$this->assertStringContainsString( 'custom_param=wp_test', $url );
		$this->assertNotEmpty( $filter_captured['token'] );
		$this->assertNotEmpty( $filter_captured['key'] );
		$this->assertStringContainsString( 'action=' . WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER, $filter_captured['url'] );
	}

	/**
	 * Tests that handle_begin_link bails early if $GLOBALS['pagenow'] is not wp-login.php.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_bails_if_not_login_page() {
		$GLOBALS['pagenow'] = 'index.php';
		$_GET               = array(
			'action'   => WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER,
			'rm_token' => 'token123',
			'rm_key'   => 'key123',
		);

		$result = $this->service->handle_begin_link( HOUR_IN_SECONDS );
		$this->assertNull( $result );
	}

	/**
	 * Tests that handle_begin_link bails early if $GLOBALS['pagenow'] is not set.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_bails_if_pagenow_not_set() {
		unset( $GLOBALS['pagenow'] );
		$_GET = array(
			'action'   => WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER,
			'rm_token' => 'token123',
			'rm_key'   => 'key123',
		);

		$result = $this->service->handle_begin_link( HOUR_IN_SECONDS );
		$this->assertNull( $result );
	}

	/**
	 * Tests that handle_begin_link bails early when action parameter is missing.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_bails_if_action_missing() {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET               = array(
			'rm_token' => 'token123',
			'rm_key'   => 'key123',
		);

		$result = $this->service->handle_begin_link( HOUR_IN_SECONDS );
		$this->assertNull( $result );
	}

	/**
	 * Tests that handle_begin_link bails early when action parameter is not LOGIN_ACTION_ENTER.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_bails_if_action_invalid() {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET               = array(
			'action'   => 'login',
			'rm_token' => 'token123',
			'rm_key'   => 'key123',
		);

		$result = $this->service->handle_begin_link( HOUR_IN_SECONDS );
		$this->assertNull( $result );
	}

	/**
	 * Tests that handle_begin_link bails early when rm_token parameter is missing.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_bails_if_rm_token_missing() {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET               = array(
			'action' => WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER,
			'rm_key' => 'key123',
		);

		$result = $this->service->handle_begin_link( HOUR_IN_SECONDS );
		$this->assertNull( $result );
	}

	/**
	 * Tests that handle_begin_link bails early when rm_key parameter is missing.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_bails_if_rm_key_missing() {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET               = array(
			'action'   => WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER,
			'rm_token' => 'token123',
		);

		$result = $this->service->handle_begin_link( HOUR_IN_SECONDS );
		$this->assertNull( $result );
	}

	/**
	 * Tests that handle_begin_link dies when validation returns WP_Error due to missing token.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_dies_on_unrecognized_token() {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET               = array(
			'action'   => WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER,
			'rm_token' => 'nonexistent_token',
			'rm_key'   => 'some_key',
		);

		$this->expectException( 'WPDieException' );
		$this->service->handle_begin_link( HOUR_IN_SECONDS );
	}

	/**
	 * Tests that handle_begin_link dies when validation returns WP_Error due to expired key.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_dies_on_expired_key() {
		$token = $this->key_service->generate_recovery_mode_token();
		$key   = $this->key_service->generate_and_store_recovery_mode_key( $token );

		// Manually update stored key timestamp to simulate expiration.
		$records                         = get_option( 'recovery_keys', array() );
		$records[ $token ]['created_at'] = time() - HOUR_IN_SECONDS - 10;
		update_option( 'recovery_keys', $records );

		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET               = array(
			'action'   => WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER,
			'rm_token' => $token,
			'rm_key'   => $key,
		);

		$this->expectException( 'WPDieException' );
		$this->service->handle_begin_link( HOUR_IN_SECONDS );
	}

	/**
	 * Tests that handle_begin_link successfully sets the cookie and redirects when key is valid.
	 *
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Link_Service::handle_begin_link
	 */
	public function test_handle_begin_link_success() {
		$token = $this->key_service->generate_recovery_mode_token();
		$key   = $this->key_service->generate_and_store_recovery_mode_key( $token );

		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET               = array(
			'action'   => WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTER,
			'rm_token' => $token,
			'rm_key'   => $key,
		);

		$cookie_length_filtered = false;
		$cookie_length_callback = function ( $length ) use ( &$cookie_length_filtered ) {
			$cookie_length_filtered = true;
			return $length;
		};
		add_filter( 'recovery_mode_cookie_length', $cookie_length_callback );

		$redirect_location = '';
		$redirect_callback = function ( $location ) use ( &$redirect_location ) {
			$redirect_location = $location;
			// Throw an exception to halt execution before die; is reached.
			throw new Exception( 'redirect_intercepted' );
		};
		add_filter( 'wp_redirect', $redirect_callback );

		set_error_handler(
			static function ( $errno, $errstr ) {
				if ( false !== strpos( $errstr, 'Cannot modify header information' ) ) {
					return true;
				}
				return false;
			}
		);

		try {
			$this->service->handle_begin_link( HOUR_IN_SECONDS );
			$this->fail( 'Expected redirect exception was not thrown.' );
		} catch ( Exception $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			restore_error_handler();
			remove_filter( 'recovery_mode_cookie_length', $cookie_length_callback );
			remove_filter( 'wp_redirect', $redirect_callback );
		}

		// Verify cookie service attempted to set cookie.
		$this->assertTrue( $cookie_length_filtered, 'Expected recovery_mode_cookie_length filter to have fired during set_cookie.' );

		// Verify redirect destination.
		$expected_url = add_query_arg( 'action', WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTERED, wp_login_url() );
		$this->assertSame( $expected_url, $redirect_location );

		// Verify key was consumed and removed.
		$records = get_option( 'recovery_keys', array() );
		$this->assertArrayNotHasKey( $token, $records );
	}
}
