<?php

/**
 * Tests for wp_is_site_protected_by_basic_auth().
 *
 * @group load
 *
 * @covers ::wp_is_site_protected_by_basic_auth
 */
class Tests_Load_WpIsSiteProtectedByBasicAuth extends WP_UnitTestCase {

	/**
	 * Original PHP_AUTH_USER value, or null if unset.
	 *
	 * @var string|null
	 */
	private $original_php_auth_user;

	/**
	 * Original PHP_AUTH_PW value, or null if unset.
	 *
	 * @var string|null
	 */
	private $original_php_auth_pw;

	/**
	 * Original $pagenow value.
	 *
	 * @var string|null
	 */
	private $original_pagenow;

	public function set_up() {
		parent::set_up();

		$this->original_php_auth_user = $_SERVER['PHP_AUTH_USER'] ?? null;
		$this->original_php_auth_pw   = $_SERVER['PHP_AUTH_PW'] ?? null;

		global $pagenow;
		$this->original_pagenow = $pagenow ?? null;

		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
	}

	public function tear_down() {
		if ( null !== $this->original_php_auth_user ) {
			$_SERVER['PHP_AUTH_USER'] = $this->original_php_auth_user;
		} else {
			unset( $_SERVER['PHP_AUTH_USER'] );
		}

		if ( null !== $this->original_php_auth_pw ) {
			$_SERVER['PHP_AUTH_PW'] = $this->original_php_auth_pw;
		} else {
			unset( $_SERVER['PHP_AUTH_PW'] );
		}

		global $pagenow;
		if ( null !== $this->original_pagenow ) {
			$pagenow = $this->original_pagenow;
		} else {
			unset( $GLOBALS['pagenow'] );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_auth_server_vars_are_unset() {
		$this->assertFalse( wp_is_site_protected_by_basic_auth() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_when_php_auth_user_is_set() {
		$_SERVER['PHP_AUTH_USER'] = 'admin';

		$this->assertTrue( wp_is_site_protected_by_basic_auth() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_when_php_auth_pw_is_set() {
		$_SERVER['PHP_AUTH_PW'] = 'secret';

		$this->assertTrue( wp_is_site_protected_by_basic_auth() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_explicit_context() {
		$received_context = null;

		add_filter(
			'wp_is_site_protected_by_basic_auth',
			static function ( $is_protected, $context ) use ( &$received_context ) {
				$received_context = $context;
				return $is_protected;
			},
			10,
			2
		);

		wp_is_site_protected_by_basic_auth( 'login' );

		$this->assertSame( 'login', $received_context );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_override_detection() {
		$_SERVER['PHP_AUTH_USER'] = 'admin';

		add_filter( 'wp_is_site_protected_by_basic_auth', '__return_false' );

		$this->assertFalse( wp_is_site_protected_by_basic_auth() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_empty_context_detects_login_from_pagenow() {
		global $pagenow;
		$pagenow = 'wp-login.php';

		$received_context = null;

		add_filter(
			'wp_is_site_protected_by_basic_auth',
			static function ( $is_protected, $context ) use ( &$received_context ) {
				$received_context = $context;
				return $is_protected;
			},
			10,
			2
		);

		wp_is_site_protected_by_basic_auth();

		$this->assertSame( 'login', $received_context );
	}

	/**
	 * @ticket 65819
	 */
	public function test_empty_context_detects_admin_when_in_admin() {
		set_current_screen( 'dashboard' );

		global $pagenow;
		$pagenow = 'index.php';

		$received_context = null;

		add_filter(
			'wp_is_site_protected_by_basic_auth',
			static function ( $is_protected, $context ) use ( &$received_context ) {
				$received_context = $context;
				return $is_protected;
			},
			10,
			2
		);

		wp_is_site_protected_by_basic_auth();

		$this->assertSame( 'admin', $received_context );
	}

	/**
	 * @ticket 65819
	 */
	public function test_empty_context_detects_front_by_default() {
		unset( $GLOBALS['current_screen'] );

		global $pagenow;
		$pagenow = 'index.php';

		$received_context = null;

		add_filter(
			'wp_is_site_protected_by_basic_auth',
			static function ( $is_protected, $context ) use ( &$received_context ) {
				$received_context = $context;
				return $is_protected;
			},
			10,
			2
		);

		wp_is_site_protected_by_basic_auth();

		$this->assertSame( 'front', $received_context );
	}
}
