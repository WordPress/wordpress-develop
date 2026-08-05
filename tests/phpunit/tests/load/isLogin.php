<?php

/**
 * Tests for is_login().
 *
 * @group load
 *
 * @covers ::is_login
 */
class Tests_Load_IsLogin extends WP_UnitTestCase {

	/**
	 * Original SCRIPT_NAME value, restored after each test.
	 *
	 * @var string|null
	 */
	private $original_script_name;

	/**
	 * Original $pagenow value, restored after each test.
	 *
	 * @var string|null
	 */
	private $original_pagenow;

	public function set_up() {
		parent::set_up();
		$this->original_script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : null;
		$this->original_pagenow     = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : null;
	}

	public function tear_down() {
		if ( null === $this->original_script_name ) {
			unset( $_SERVER['SCRIPT_NAME'] );
		} else {
			$_SERVER['SCRIPT_NAME'] = $this->original_script_name;
		}

		if ( null === $this->original_pagenow ) {
			unset( $GLOBALS['pagenow'] );
		} else {
			$GLOBALS['pagenow'] = $this->original_pagenow;
		}

		parent::tear_down();
	}

	/**
	 * @ticket 19898
	 */
	public function test_is_login() {
		$this->assertFalse( is_login() );

		$_SERVER['SCRIPT_NAME'] = '/wp-login.php';

		$this->assertTrue( is_login() );
	}

	/**
	 * The default location should be detected regardless of install depth or filename case.
	 *
	 * @ticket 65820
	 *
	 * @dataProvider data_default_location_script_names
	 *
	 * @param string $script_name SCRIPT_NAME to simulate.
	 */
	public function test_should_detect_default_location( $script_name ) {
		$_SERVER['SCRIPT_NAME'] = $script_name;

		$this->assertTrue( is_login() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_default_location_script_names() {
		return array(
			'a root install'         => array( '/wp-login.php' ),
			'a subdirectory install' => array( '/blog/wp-login.php' ),
			'an uppercase filename'  => array( '/WP-LOGIN.PHP' ),
		);
	}

	/**
	 * Scripts that merely resemble wp-login.php should not be detected.
	 *
	 * @ticket 65820
	 *
	 * @dataProvider data_non_login_script_names
	 *
	 * @param string $script_name SCRIPT_NAME to simulate.
	 */
	public function test_should_not_detect_similar_script_names( $script_name ) {
		$_SERVER['SCRIPT_NAME'] = $script_name;

		$this->assertFalse( is_login() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_non_login_script_names() {
		return array(
			'the front controller'   => array( '/index.php' ),
			'a similarly named file' => array( '/my-wp-login.php' ),
			'a custom entry point without a matching login_url' => array( '/custom-login.php' ),
		);
	}

	/**
	 * Direct execution of wp-login.php is the login screen even when
	 * the `login_url` filter points somewhere else entirely.
	 *
	 * @ticket 65820
	 */
	public function test_should_detect_default_location_when_login_url_is_filtered() {
		add_filter(
			'login_url',
			static function () {
				return 'https://example.org/vip-entrance/';
			}
		);

		$_SERVER['SCRIPT_NAME'] = '/wp-login.php';

		$this->assertTrue( is_login() );
	}

	/**
	 * Login-relocation plugins (WPS Hide Login, SF Move Login, Rename wp-admin
	 * login, Admin Login URL Change) serve the login screen through the front
	 * controller: SCRIPT_NAME stays index.php, `$pagenow` is forced to
	 * wp-login.php, wp-login.php is `require`d, and `login_url` is filtered to
	 * the custom slug. is_login() has never detected this context and must
	 * continue to return false: the default-location check keys on SCRIPT_NAME
	 * (not `$pagenow`), and the login URL comparison does not match the front
	 * controller's script name.
	 *
	 * @ticket 65820
	 */
	public function test_should_not_detect_login_relocated_behind_front_controller() {
		add_filter(
			'login_url',
			static function () {
				return 'https://example.org/secret-login/';
			}
		);

		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$GLOBALS['pagenow']     = 'wp-login.php';

		$this->assertFalse( is_login() );
	}

	/**
	 * A renamed login entry point is not matched by the default-location check
	 * and must still be detected via the login URL comparison.
	 *
	 * @ticket 65820
	 */
	public function test_should_fall_through_to_login_url_comparison_for_custom_entry_point() {
		add_filter(
			'login_url',
			static function () {
				return 'https://example.org/custom-login.php';
			}
		);

		$_SERVER['SCRIPT_NAME'] = '/custom-login.php';

		$this->assertTrue( is_login() );
	}
}
