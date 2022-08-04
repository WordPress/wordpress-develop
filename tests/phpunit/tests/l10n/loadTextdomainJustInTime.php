<?php

/**
 * @group l10n
 * @group i18n
 */
class Tests_L10n_LoadTextdomainJustInTime extends WP_UnitTestCase {
	protected $orig_theme_dir;
	protected $theme_root;
	protected static $user_id;
	private $locale_count;

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

		$this->theme_root     = DIR_TESTDATA . '/themedir1';
		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];
		$this->locale_count   = 0;

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );
		add_filter( 'theme_root', array( $this, 'filter_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_theme_root' ) );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		unset( $GLOBALS['l10n'] );
		unset( $GLOBALS['l10n_unloaded'] );
		_get_path_to_translation( null, true );
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		unset( $GLOBALS['l10n'] );
		unset( $GLOBALS['l10n_unloaded'] );
		_get_path_to_translation( null, true );

		parent::tear_down();
	}

	/**
	 * Replace the normal theme root dir with our pre-made test dir.
	 */
	public function filter_theme_root() {
		return $this->theme_root;
	}

	public function filter_set_locale_to_german() {
		return 'de_DE';
	}

	/**
	 * @ticket 34114
	 */
	public function test_plugin_translation_should_be_translated_without_calling_load_plugin_textdomain() {
		add_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		$is_textdomain_loaded_before = is_textdomain_loaded( 'internationalized-plugin' );
		$actual_output               = i18n_plugin_test();
		$is_textdomain_loaded_after  = is_textdomain_loaded( 'internationalized-plugin' );

		remove_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		$this->assertFalse( $is_textdomain_loaded_before );
		$this->assertSame( 'Das ist ein Dummy Plugin', $actual_output );
		$this->assertTrue( $is_textdomain_loaded_after );
	}

	/**
	 * @ticket 34114
	 */
	public function test_theme_translation_should_be_translated_without_calling_load_theme_textdomain() {
		add_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		switch_theme( 'internationalized-theme' );

		require_once get_stylesheet_directory() . '/functions.php';

		$is_textdomain_loaded_before = is_textdomain_loaded( 'internationalized-theme' );
		$actual_output               = i18n_theme_test();
		$is_textdomain_loaded_after  = is_textdomain_loaded( 'internationalized-theme' );

		remove_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		$this->assertFalse( $is_textdomain_loaded_before );
		$this->assertSame( 'Das ist ein Dummy Theme', $actual_output );
		$this->assertTrue( $is_textdomain_loaded_after );
	}

	/**
	 * @ticket 34114
	 */
	public function test_get_translations_for_domain_does_not_return_null_if_override_load_textdomain_is_used() {
		add_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );
		add_filter( 'override_load_textdomain', '__return_true' );
		$translations = get_translations_for_domain( 'internationalized-plugin' );
		remove_filter( 'override_load_textdomain', '__return_true' );
		remove_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		$this->assertInstanceOf( 'NOOP_Translations', $translations );
	}

	/**
	 * @ticket 37113
	 */
	public function test_should_allow_unloading_of_text_domain() {
		add_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		$actual_output_before        = i18n_plugin_test();
		$is_textdomain_loaded_before = is_textdomain_loaded( 'internationalized-plugin' );

		unload_textdomain( 'internationalized-plugin' );
		remove_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		$actual_output_after        = i18n_plugin_test();
		$is_textdomain_loaded_after = is_textdomain_loaded( 'internationalized-plugin' );

		add_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );
		load_textdomain( 'internationalized-plugin', WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo' );

		$actual_output_final        = i18n_plugin_test();
		$is_textdomain_loaded_final = is_textdomain_loaded( 'internationalized-plugin' );

		unload_textdomain( 'internationalized-plugin' );
		remove_filter( 'locale', array( $this, 'filter_set_locale_to_german' ) );

		// Text domain loaded just in time.
		$this->assertSame( 'Das ist ein Dummy Plugin', $actual_output_before );
		$this->assertTrue( $is_textdomain_loaded_before );

		// Text domain unloaded.
		$this->assertSame( 'This is a dummy plugin', $actual_output_after );
		$this->assertFalse( $is_textdomain_loaded_after );

		// Text domain loaded manually again.
		$this->assertSame( 'Das ist ein Dummy Plugin', $actual_output_final );
		$this->assertTrue( $is_textdomain_loaded_final );
	}

	/**
	 * @ticket 26511
	 */
	public function test_plugin_translation_after_switching_locale() {
		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		switch_to_locale( 'de_DE' );
		$actual = i18n_plugin_test();
		restore_previous_locale();

		$this->assertSame( 'Das ist ein Dummy Plugin', $actual );
	}

	/**
	 * @ticket 37997
	 */
	public function test_plugin_translation_after_switching_locale_twice() {
		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		switch_to_locale( 'de_DE' );
		$actual_de_de = i18n_plugin_test();

		switch_to_locale( 'es_ES' );
		$actual_es_es = i18n_plugin_test();

		restore_current_locale();

		$this->assertSame( 'Das ist ein Dummy Plugin', $actual_de_de );
		$this->assertSame( 'This is a dummy plugin', $actual_es_es );
	}

	/**
	 * @ticket 26511
	 */
	public function test_theme_translation_after_switching_locale() {
		switch_theme( 'internationalized-theme' );

		require_once get_stylesheet_directory() . '/functions.php';

		switch_to_locale( 'de_DE' );
		$actual = i18n_theme_test();
		restore_previous_locale();

		switch_theme( WP_DEFAULT_THEME );

		$this->assertSame( 'Das ist ein Dummy Theme', $actual );
	}

	/**
	 * @ticket 38485
	 */
	public function test_plugin_translation_with_user_locale() {
		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );

		$actual = i18n_plugin_test();

		$this->assertSame( 'Das ist ein Dummy Plugin', $actual );
	}

	/**
	 * @ticket 38485
	 */
	public function test_theme_translation_with_user_locale() {
		switch_theme( 'internationalized-theme' );
		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );

		require_once get_stylesheet_directory() . '/functions.php';

		$actual = i18n_theme_test();

		switch_theme( WP_DEFAULT_THEME );

		$this->assertSame( 'Das ist ein Dummy Theme', $actual );
	}

	/**
	 * @ticket 37997
	 */
	public function test_get_locale_is_called_only_once_per_textdomain() {
		$textdomain = 'foo-bar-baz';

		add_filter( 'locale', array( $this, '_filter_locale_count' ) );

		__( 'Foo', $textdomain );
		__( 'Bar', $textdomain );
		__( 'Baz', $textdomain );
		__( 'Foo Bar', $textdomain );
		__( 'Foo Bar Baz', $textdomain );

		remove_filter( 'locale', array( $this, '_filter_locale_count' ) );

		$this->assertFalse( is_textdomain_loaded( $textdomain ) );
		$this->assertSame( 1, $this->locale_count );
	}

	public function _filter_locale_count( $locale ) {
		++$this->locale_count;

		return $locale;
	}
}
