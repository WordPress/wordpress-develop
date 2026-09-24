<?php

/**
 * @group l10n
 * @group i18n
 *
 * @covers ::determine_locale
 */
class Tests_L10n_DetermineLocale extends WP_UnitTestCase {
	protected $locale;
	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role'   => 'administrator',
				'locale' => 'userLocale',
			)
		);
	}

	public function tear_down() {
		unset(
			$_SERVER['CONTENT_TYPE'],
			$_GET['_locale'],
			$_COOKIE['wp_lang'],
			$GLOBALS['pagenow'],
			$GLOBALS['wp_local_package'],
			$_REQUEST['language']
		);
		wp_installing( false );

		parent::tear_down();
	}

	public function test_short_circuit_empty() {
		add_filter( 'pre_determine_locale', '__return_false' );
		$this->assertNotFalse( determine_locale() );
	}

	public function test_short_circuit_no_string() {
		add_filter(
			'pre_determine_locale',
			static function () {
				return 1234;
			}
		);
		$this->assertNotFalse( determine_locale() );
	}

	public function test_short_circuit_string() {
		add_filter(
			'pre_determine_locale',
			static function () {
					return 'myNewLocale';
			}
		);
		$this->assertSame( 'myNewLocale', determine_locale() );
	}

	public function test_defaults_to_site_locale() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		$this->assertSame( get_locale(), determine_locale() );
	}

	public function test_is_admin_no_user() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		set_current_screen( 'dashboard' );

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_is_admin_user_locale() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );

		$this->assertSame( 'userLocale', determine_locale() );
	}

	public function test_json_request_user_locale() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$_GET['_locale']         = 'user';

		$this->assertSame( 'userLocale', determine_locale() );
	}

	public function test_json_request_user_locale_no_user() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$_GET['_locale']         = 'user';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_json_request_missing_get_param() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_json_request_incorrect_get_param() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$_GET['_locale']         = 'foo';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_get_param_but_no_json_request() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$_GET['_locale'] = 'user';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_wp_login_get_param_not_on_login_page() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$_GET['wp_lang'] = 'de_DE';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_wp_login_get_param_on_login_page() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET['wp_lang']    = 'de_DE';

		$this->assertSame( 'de_DE', determine_locale() );
	}

	public function test_wp_login_get_param_on_login_page_empty_string() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET['wp_lang']    = '';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_wp_login_get_param_on_login_page_incorrect_string() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET['wp_lang']    = '###'; // Something sanitize_locale_name() strips away.

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_wp_login_cookie_not_on_login_page() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$_COOKIE['wp_lang'] = 'de_DE';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_wp_login_cookie_on_login_page() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$GLOBALS['pagenow'] = 'wp-login.php';
		$_COOKIE['wp_lang'] = 'de_DE';

		$this->assertSame( 'de_DE', determine_locale() );
	}

	public function test_wp_login_cookie_on_login_page_empty_string() {
		add_filter(
			'locale',
			static function () {
				return 'siteLocale';
			}
		);

		wp_set_current_user( self::$user_id );

		$GLOBALS['pagenow'] = 'wp-login.php';
		$_COOKIE['wp_lang'] = '';

		$this->assertSame( 'siteLocale', determine_locale() );
	}

	public function test_language_param_not_installing() {
		$_REQUEST['language'] = 'de_DE';
		$this->assertSame( 'en_US', determine_locale() );
	}

	public function test_language_param_installing() {
		$_REQUEST['language'] = 'de_DE';
		wp_installing( true );
		$this->assertSame( 'de_DE', determine_locale() );
	}

	public function test_language_param_installing_incorrect_string() {
		$_REQUEST['language'] = '####';  // Something sanitize_locale_name() strips away.
		wp_installing( true );
		$this->assertSame( 'en_US', determine_locale() );
	}

	public function test_wp_local_package_global_not_installing() {
		$GLOBALS['wp_local_package'] = 'de_DE';
		$this->assertSame( 'en_US', determine_locale() );
	}
	public function test_wp_local_package_global_installing() {
		$GLOBALS['wp_local_package'] = 'de_DE';
		wp_installing( true );
		$this->assertSame( 'de_DE', determine_locale() );
	}
}
