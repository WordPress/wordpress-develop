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

	/**
	 * sanitize_locale_name() applies preg_replace(), which maps over an array
	 * subject and returns an array, so `wp-login.php?wp_lang[]=de_DE` reaches
	 * the return statement with an array. No authentication is needed.
	 *
	 * @dataProvider data_array_request_value
	 *
	 * @param array $value Array request value.
	 */
	public function test_wp_login_get_param_on_login_page_array( $value ) {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET['wp_lang']    = $value;

		$this->assertSame( 'en_US', determine_locale() );
	}

	/**
	 * @dataProvider data_array_request_value
	 *
	 * @param array $value Array request value.
	 */
	public function test_wp_login_cookie_on_login_page_array( $value ) {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_COOKIE['wp_lang'] = $value;

		$this->assertSame( 'en_US', determine_locale() );
	}

	/**
	 * @dataProvider data_array_request_value
	 *
	 * @param array $value Array request value.
	 */
	public function test_language_param_installing_array( $value ) {
		$_REQUEST['language'] = $value;
		wp_installing( true );

		$this->assertSame( 'en_US', determine_locale() );
	}

	/**
	 * An array locale reaches WP_Textdomain_Registry::set(), which uses it as
	 * an array key and throws a TypeError, so translating any string for an
	 * unloaded text domain takes down the login page for an anonymous visitor.
	 */
	public function test_array_wp_lang_param_does_not_fatal_in_the_textdomain_registry() {
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_GET['wp_lang']    = array( 'de_DE' );

		$this->assertSame( 'Some text', __( 'Some text', 'my-login-plugin' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_request_value() {
		// An empty array is falsy, so it never reaches sanitize_locale_name().
		return array(
			'a list' => array( array( 'de_DE' ) ),
			'a map'  => array( array( 'lang' => 'de_DE' ) ),
		);
	}

	/**
	 * The `$wp_local_package` global is untyped and only checked for truthiness.
	 *
	 * @dataProvider data_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_wp_local_package_global_installing_non_string( $value ) {
		$GLOBALS['wp_local_package'] = $value;
		wp_installing( true );
		$this->assertSame( 'en_US', determine_locale() );
	}

	/**
	 * The `determine_locale` filter result is returned unchecked, unlike
	 * `pre_determine_locale`, which is guarded with is_string().
	 *
	 * @dataProvider data_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_ignores_a_non_string_determine_locale_filter( $value ) {
		add_filter(
			'determine_locale',
			static function () use ( $value ) {
				return $value;
			}
		);

		$this->assertSame( 'en_US', determine_locale() );
	}

	/**
	 * An array `locale` user meta row reaches determine_locale() through
	 * get_user_locale() on every admin request.
	 *
	 * @dataProvider data_non_string_user_locale_meta
	 *
	 * @param mixed $meta_value Value stored in the `locale` user meta row.
	 */
	public function test_returns_a_string_for_a_non_string_user_locale_meta( $meta_value ) {
		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );
		update_user_meta( self::$user_id, 'locale', $meta_value );

		$this->assertSame( 'en_US', determine_locale() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_non_string_locale() {
		return array(
			'a list'         => array( array( 'de_DE' ) ),
			'a map'          => array( array( 'locale' => 'de_DE' ) ),
			'an empty array' => array( array() ),
			'an object'      => array( new stdClass() ),
			'an integer'     => array( 1234 ),
			'a float'        => array( 1.5 ),
			'true'           => array( true ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_non_string_user_locale_meta() {
		// Scalars survive the meta round trip as strings, so only arrays and
		// objects can come back from get_user_meta() with the wrong type.
		return array(
			'a list'         => array( array( 'de_DE' ) ),
			'a map'          => array( array( 'locale' => 'de_DE' ) ),
			'an empty array' => array( array() ),
			'an object'      => array( new stdClass() ),
		);
	}
}
