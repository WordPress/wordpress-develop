<?php

/**
 * @group l10n
 * @group i18n
 */
class Tests_L10n_LoadTextdomain extends WP_UnitTestCase {
	protected $locale;
	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role'   => 'administrator',
				'locale' => 'de_DE',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		$this->locale = '';

		add_filter( 'plugin_locale', array( $this, 'store_locale' ) );
		add_filter( 'theme_locale', array( $this, 'store_locale' ) );

		/** @var WP_Textdomain_Registry $wp_textdomain_registry */
		global $wp_textdomain_registry;

		$wp_textdomain_registry = new WP_Textdomain_Registry();
	}

	public function tear_down() {

		/** @var WP_Textdomain_Registry $wp_textdomain_registry */
		global $wp_textdomain_registry;

		$wp_textdomain_registry = new WP_Textdomain_Registry();

		parent::tear_down();
	}

	public function store_locale( $locale ) {
		$this->locale = $locale;

		return $locale;
	}

	/**
	 * @covers ::is_textdomain_loaded
	 */
	public function test_is_textdomain_loaded() {
		$this->assertFalse( is_textdomain_loaded( 'wp-tests-domain' ) );
	}

	/**
	 * @covers ::unload_textdomain
	 */
	public function test_unload_textdomain() {
		$this->assertFalse( unload_textdomain( 'wp-tests-domain' ) );
	}

	/**
	 * @covers ::unload_textdomain
	 */
	public function test_load_textdomain() {
		$loaded = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $loaded );
	}

	/**
	 * @covers ::unload_textdomain
	 */
	public function test_is_textdomain_loaded_after_loading() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$loaded = is_textdomain_loaded( 'wp-tests-domain' );

		unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $loaded );
	}

	/**
	 * @covers ::unload_textdomain
	 */
	public function test_unload_textdomain_after_loading() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$this->assertTrue( unload_textdomain( 'wp-tests-domain' ) );
	}

	/**
	 * @covers ::is_textdomain_loaded
	 */
	public function test_is_textdomain_loaded_after_unloading() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		unload_textdomain( 'wp-tests-domain' );

		$this->assertFalse( is_textdomain_loaded( 'wp-tests-domain' ) );
	}

	/**
	 * @ticket 21319
	 *
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_non_existent_file() {
		$this->assertFalse( load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/non-existent-file' ) );
	}

	/**
	 * @ticket 21319
	 *
	 * @covers ::is_textdomain_loaded
	 */
	public function test_is_textdomain_loaded_non_existent_file() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/non-existent-file' );

		$this->assertFalse( is_textdomain_loaded( 'wp-tests-domain' ) );
	}

	/**
	 * @ticket 21319
	 *
	 * @covers ::get_translations_for_domain
	 */
	public function test_get_translations_for_domain_non_existent_file() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/non-existent-file' );

		$this->assertInstanceOf( 'NOOP_Translations', get_translations_for_domain( 'wp-tests-domain' ) );
	}

	/**
	 * @ticket 21319
	 *
	 * @covers ::unload_textdomain
	 */
	public function test_unload_textdomain_non_existent_file() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/non-existent-file' );

		$this->assertFalse( unload_textdomain( 'wp-tests-domain' ) );
	}

	/**
	 * @ticket 21319
	 *
	 * @covers ::is_textdomain_loaded
	 */
	public function test_is_textdomain_is_not_loaded_after_gettext_call_with_no_translations() {
		$this->assertFalse( is_textdomain_loaded( 'wp-tests-domain' ) );
		__( 'just some string', 'wp-tests-domain' );
		$this->assertFalse( is_textdomain_loaded( 'wp-tests-domain' ) );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_override_load_textdomain_noop() {
		add_filter( 'override_load_textdomain', '__return_true' );
		$load_textdomain = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/non-existent-file' );
		remove_filter( 'override_load_textdomain', '__return_true' );

		$this->assertTrue( $load_textdomain );
		$this->assertFalse( is_textdomain_loaded( 'wp-tests-domain' ) );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_override_load_textdomain_non_existent_mofile() {
		add_filter( 'override_load_textdomain', array( $this, 'override_load_textdomain_filter' ), 10, 3 );
		$load_textdomain = load_textdomain( 'wp-tests-domain', WP_LANG_DIR . '/non-existent-file.mo' );
		remove_filter( 'override_load_textdomain', array( $this, 'override_load_textdomain_filter' ) );

		$is_textdomain_loaded = is_textdomain_loaded( 'wp-tests-domain' );
		unload_textdomain( 'wp-tests-domain' );
		$is_textdomain_loaded_after = is_textdomain_loaded( 'wp-tests-domain' );

		$this->assertFalse( $load_textdomain );
		$this->assertFalse( $is_textdomain_loaded );
		$this->assertFalse( $is_textdomain_loaded_after );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_override_load_textdomain_custom_mofile() {
		add_filter( 'override_load_textdomain', array( $this, 'override_load_textdomain_filter' ), 10, 3 );
		$load_textdomain = load_textdomain( 'wp-tests-domain', WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo' );
		remove_filter( 'override_load_textdomain', array( $this, 'override_load_textdomain_filter' ) );

		$is_textdomain_loaded = is_textdomain_loaded( 'wp-tests-domain' );
		unload_textdomain( 'wp-tests-domain' );
		$is_textdomain_loaded_after = is_textdomain_loaded( 'wp-tests-domain' );

		$this->assertTrue( $load_textdomain );
		$this->assertTrue( $is_textdomain_loaded );
		$this->assertFalse( $is_textdomain_loaded_after );
	}

	/**
	 * @param bool   $override Whether to override the .mo file loading. Default false.
	 * @param string $domain   Text domain. Unique identifier for retrieving translated strings.
	 * @param string $file     Path to the MO file.
	 * @return bool
	 */
	public function override_load_textdomain_filter( $override, $domain, $file ) {
		global $l10n;

		if ( ! is_readable( $file ) ) {
			return false;
		}

		$mo = new MO();

		if ( ! $mo->import_from_file( $file ) ) {
			return false;
		}

		if ( isset( $l10n[ $domain ] ) ) {
			$mo->merge_with( $l10n[ $domain ] );
		}

		$l10n[ $domain ] = &$mo;

		return true;
	}

	/**
	 * @covers ::load_muplugin_textdomain
	 */
	public function test_load_muplugin_textdomain_site_locale() {
		load_muplugin_textdomain( 'wp-tests-domain' );

		$this->assertSame( get_locale(), $this->locale );
	}

	/**
	 * @ticket 38485
	 *
	 * @covers ::load_muplugin_textdomain
	 */
	public function test_load_muplugin_textdomain_user_locale() {
		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );

		load_muplugin_textdomain( 'wp-tests-domain' );

		$this->assertSame( get_user_locale(), $this->locale );
	}

	/**
	 * @covers ::load_plugin_textdomain
	 */
	public function test_load_plugin_textdomain_site_locale() {
		load_plugin_textdomain( 'wp-tests-domain' );

		$this->assertSame( get_locale(), $this->locale );
	}

	/**
	 * @ticket 38485
	 *
	 * @covers ::load_plugin_textdomain
	 */
	public function test_load_plugin_textdomain_user_locale() {
		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );

		load_plugin_textdomain( 'wp-tests-domain' );

		$this->assertSame( get_user_locale(), $this->locale );
	}

	/**
	 * @covers ::load_theme_textdomain
	 */
	public function test_load_theme_textdomain_site_locale() {
		load_theme_textdomain( 'wp-tests-domain' );

		$this->assertSame( get_locale(), $this->locale );
	}

	/**
	 * @ticket 38485
	 *
	 * @covers ::load_theme_textdomain
	 */
	public function test_load_theme_textdomain_user_locale() {
		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );

		load_theme_textdomain( 'wp-tests-domain' );

		$this->assertSame( get_user_locale(), $this->locale );
	}
}
