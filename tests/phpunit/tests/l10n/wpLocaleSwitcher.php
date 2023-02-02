<?php

/**
 * @group l10n
 * @group i18n
 * @ticket 26511
 */
class Tests_L10n_wpLocaleSwitcher extends WP_UnitTestCase {
	/**
	 * @var string
	 */
	protected $locale = '';

	/**
	 * @var string
	 */
	protected $previous_locale = '';

	/**
	 * @var int
	 */
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

		$this->locale          = '';
		$this->previous_locale = '';

		unset( $GLOBALS['l10n'], $GLOBALS['l10n_unloaded'] );

		global $wp_textdomain_registry, $wp_locale_switcher;

		$wp_textdomain_registry = new WP_Textdomain_Registry();

		remove_filter( 'locale', array( $wp_locale_switcher, 'filter_locale' ) );
		$wp_locale_switcher = new WP_Locale_Switcher();
		$wp_locale_switcher->init();
	}

	public function tear_down() {
		unset( $GLOBALS['l10n'], $GLOBALS['l10n_unloaded'] );

		global $wp_textdomain_registry, $wp_locale_switcher;

		$wp_textdomain_registry = new WP_Textdomain_Registry();

		// Clean up after any tests that don't restore the locale afterwards,
		// before resetting $wp_locale_switcher.
		restore_current_locale();

		remove_filter( 'locale', array( $wp_locale_switcher, 'filter_locale' ) );
		$wp_locale_switcher = new WP_Locale_Switcher();
		$wp_locale_switcher->init();

		parent::tear_down();
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_non_existent_locale_returns_false() {
		$this->assertFalse( switch_to_locale( 'foo_BAR' ) );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_non_existent_locale_does_not_change_locale() {
		switch_to_locale( 'foo_BAR' );

		$this->assertSame( 'en_US', get_locale() );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_locale_returns_true() {
		$expected = switch_to_locale( 'en_GB' );

		// Cleanup.
		restore_previous_locale();

		$this->assertTrue( $expected );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_locale_changes_the_locale() {
		switch_to_locale( 'en_GB' );

		$locale = get_locale();

		// Cleanup.
		restore_previous_locale();

		$this->assertSame( 'en_GB', $locale );
	}

	/**
	 * @ticket 57123
	 *
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_locale_changes_determined_locale() {
		switch_to_locale( 'en_GB' );

		$locale = determine_locale();

		// Cleanup.
		restore_previous_locale();

		$this->assertSame( 'en_GB', $locale );
	}

	/**
	 * @covers ::switch_to_locale
	 * @covers ::translate
	 * @covers ::__
	 */
	public function test_switch_to_locale_loads_translation() {
		switch_to_locale( 'es_ES' );

		$actual = __( 'Invalid parameter.' );

		// Cleanup.
		restore_previous_locale();

		$this->assertSame( 'Parámetro no válido. ', $actual );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_locale_changes_wp_locale_global() {
		global $wp_locale;

		$expected = array(
			'thousands_sep' => '.',
			'decimal_point' => ',',
		);

		switch_to_locale( 'de_DE' );

		$wp_locale_de_de = clone $wp_locale;

		// Cleanup.
		restore_previous_locale();

		$this->assertSameSetsWithIndex( $expected, $wp_locale_de_de->number_format );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_locale_en_US() {
		switch_to_locale( 'en_GB' );
		$locale_en_gb = get_locale();
		switch_to_locale( 'en_US' );
		$locale_en_us = get_locale();

		// Cleanup.
		restore_current_locale();

		$this->assertSame( 'en_GB', $locale_en_gb );
		$this->assertSame( 'en_US', $locale_en_us );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_locale_multiple_times() {
		switch_to_locale( 'en_GB' );
		switch_to_locale( 'es_ES' );
		$locale = get_locale();

		// Cleanup.
		restore_previous_locale();
		restore_previous_locale();

		$this->assertSame( 'es_ES', $locale );
	}

	/**
	 * @covers ::switch_to_locale
	 * @covers ::__
	 * @covers ::translate
	 */
	public function test_switch_to_locale_multiple_times_loads_translation() {
		switch_to_locale( 'en_GB' );
		switch_to_locale( 'de_DE' );
		switch_to_locale( 'es_ES' );

		$actual = __( 'Invalid parameter.' );

		// Cleanup.
		restore_previous_locale();
		restore_previous_locale();
		restore_previous_locale();

		$this->assertSame( 'Parámetro no válido. ', $actual );
	}

	/**
	 * @covers ::restore_previous_locale
	 */
	public function test_restore_previous_locale_without_switching() {
		$this->assertFalse( restore_previous_locale() );
	}

	/**
	 * @covers ::restore_previous_locale
	 */
	public function test_restore_previous_locale_changes_the_locale_back() {
		switch_to_locale( 'en_GB' );

		// Cleanup.
		restore_previous_locale();

		$this->assertSame( 'en_US', get_locale() );
	}

	/**
	 * @covers ::restore_previous_locale
	 */
	public function test_restore_previous_locale_after_switching_multiple_times() {
		switch_to_locale( 'en_GB' );
		switch_to_locale( 'es_ES' );
		restore_previous_locale();

		$locale = get_locale();

		// Cleanup.
		restore_previous_locale();

		$this->assertSame( 'en_GB', $locale );
	}

	/**
	 * @covers ::restore_previous_locale
	 * @covers ::__
	 * @covers ::translate
	 */
	public function test_restore_previous_locale_restores_translation() {
		switch_to_locale( 'es_ES' );
		restore_previous_locale();

		$actual = __( 'Invalid parameter.' );

		$this->assertSame( 'Invalid parameter.', $actual );
	}

	/**
	 * @covers ::restore_previous_locale
	 */
	public function test_restore_previous_locale_action_passes_previous_locale() {
		switch_to_locale( 'en_GB' );
		switch_to_locale( 'es_ES' );

		add_action( 'restore_previous_locale', array( $this, 'store_locale' ), 10, 2 );

		restore_previous_locale();

		$previous_locale = $this->previous_locale;

		// Cleanup.
		restore_previous_locale();

		$this->assertSame( 'es_ES', $previous_locale );
	}

	/**
	 * @covers ::restore_previous_locale
	 */
	public function test_restore_previous_locale_restores_wp_locale_global() {
		global $wp_locale;

		$expected = array(
			'thousands_sep' => ',',
			'decimal_point' => '.',
		);

		switch_to_locale( 'de_DE' );
		restore_previous_locale();

		$this->assertSameSetsWithIndex( $expected, $wp_locale->number_format );
	}

	/**
	 * @covers ::restore_current_locale
	 */
	public function test_restore_current_locale_without_switching() {
		$this->assertFalse( restore_current_locale() );
	}

	/**
	 * @covers ::restore_previous_locale
	 */
	public function test_restore_current_locale_after_switching_multiple_times() {
		switch_to_locale( 'en_GB' );
		switch_to_locale( 'nl_NL' );
		switch_to_locale( 'es_ES' );

		restore_current_locale();

		$this->assertSame( 'en_US', get_locale() );
	}

	public function store_locale( $locale, $previous_locale ) {
		$this->locale          = $locale;
		$this->previous_locale = $previous_locale;
	}

	/**
	 * @covers ::is_locale_switched
	 */
	public function test_is_locale_switched_if_not_switched() {
		$this->assertFalse( is_locale_switched() );
	}

	/**
	 * @covers ::is_locale_switched
	 */
	public function test_is_locale_switched_original_locale() {
		$original_locale = get_locale();

		switch_to_locale( 'en_GB' );
		switch_to_locale( $original_locale );

		$is_locale_switched = is_locale_switched();

		restore_current_locale();

		$this->assertTrue( $is_locale_switched );
	}

	/**
	 * @covers ::is_locale_switched
	 */
	public function test_is_locale_switched() {
		switch_to_locale( 'en_GB' );
		switch_to_locale( 'nl_NL' );

		$is_locale_switched = is_locale_switched();

		restore_current_locale();

		$this->assertTrue( $is_locale_switched );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_site_locale_if_user_locale_is_set() {
		global $l10n, $wp_locale_switcher;

		$site_locale = get_locale();

		wp_set_current_user( self::$user_id );
		set_current_screen( 'dashboard' );

		// Reset $wp_locale_switcher so it thinks es_ES is the original locale.
		remove_filter( 'locale', array( $wp_locale_switcher, 'filter_locale' ) );
		$wp_locale_switcher = new WP_Locale_Switcher();
		$wp_locale_switcher->init();

		$user_locale = get_user_locale();

		$this->assertSame( 'de_DE', $user_locale );

		load_default_textdomain( $user_locale );
		$language_header_before_switch = $l10n['default']->headers['Language']; // de_DE

		$locale_switched_user_locale  = switch_to_locale( $user_locale ); // False.
		$locale_switched_site_locale  = switch_to_locale( $site_locale ); // True.
		$site_locale_after_switch     = get_locale();
		$language_header_after_switch = isset( $l10n['default'] ); // en_US

		restore_current_locale();

		$language_header_after_restore = $l10n['default']->headers['Language']; // de_DE

		$this->assertFalse( $locale_switched_user_locale );
		$this->assertTrue( $locale_switched_site_locale );
		$this->assertSame( $site_locale, $site_locale_after_switch );
		$this->assertSame( 'de_DE', $language_header_before_switch );
		$this->assertFalse( $language_header_after_switch );
		$this->assertSame( 'de_DE', $language_header_after_restore );
	}

	/**
	 * @covers ::switch_to_locale
	 */
	public function test_switch_to_different_site_locale_if_user_locale_is_set() {
		global $l10n, $wp_locale_switcher;

		// Change site locale to es_ES.
		add_filter( 'locale', array( $this, 'filter_locale' ) );

		$site_locale = get_locale();

		wp_set_current_user( self::$user_id );
		set_current_screen( 'dashboard' );

		// Reset $wp_locale_switcher so it thinks es_ES is the original locale.
		remove_filter( 'locale', array( $wp_locale_switcher, 'filter_locale' ) );
		$wp_locale_switcher = new WP_Locale_Switcher();
		$wp_locale_switcher->init();

		$user_locale = get_user_locale();

		$this->assertSame( 'de_DE', $user_locale );

		load_default_textdomain( $user_locale );
		$language_header_before_switch = $l10n['default']->headers['Language']; // de_DE

		$locale_switched_user_locale  = switch_to_locale( $user_locale ); // False.
		$locale_switched_site_locale  = switch_to_locale( $site_locale ); // True.
		$site_locale_after_switch     = get_locale();
		$language_header_after_switch = $l10n['default']->headers['Language']; // es_ES

		restore_current_locale();

		$language_header_after_restore = $l10n['default']->headers['Language']; // de_DE

		remove_filter( 'locale', array( $this, 'filter_locale' ) );

		$this->assertFalse( $locale_switched_user_locale );
		$this->assertTrue( $locale_switched_site_locale );
		$this->assertSame( $site_locale, $site_locale_after_switch );
		$this->assertSame( 'de_DE', $language_header_before_switch );
		$this->assertSame( 'es_ES', $language_header_after_switch );
		$this->assertSame( 'de_DE', $language_header_after_restore );
	}

	/**
	 * @covers ::switch_to_locale
	 * @covers ::load_default_textdomain
	 */
	public function test_multiple_switches_to_site_locale_and_user_locale() {
		$site_locale = get_locale();

		wp_set_current_user( self::$user_id );
		update_user_meta( self::$user_id, 'locale', 'en_GB' );
		set_current_screen( 'dashboard' );

		$user_locale = get_user_locale();

		load_default_textdomain( $user_locale );

		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		switch_to_locale( 'de_DE' );
		switch_to_locale( $site_locale );

		$actual = i18n_plugin_test();

		restore_current_locale();

		$this->assertSame( 'en_US', get_locale() );
		$this->assertSame( 'This is a dummy plugin', $actual );
	}

	/**
	 * @ticket 39210
	 */
	public function test_switch_reloads_plugin_translations_outside_wp_lang_dir() {
		/** @var WP_Textdomain_Registry $wp_textdomain_registry */
		global $wp_textdomain_registry;

		require_once DIR_TESTDATA . '/plugins/custom-internationalized-plugin/custom-internationalized-plugin.php';

		$actual = custom_i18n_plugin_test();

		switch_to_locale( 'es_ES' );

		$registry_value = $wp_textdomain_registry->get( 'custom-internationalized-plugin', determine_locale() );

		switch_to_locale( 'de_DE' );

		$actual_de_de = custom_i18n_plugin_test();

		restore_previous_locale();

		$actual_es_es = custom_i18n_plugin_test();

		restore_current_locale();

		$this->assertSame( 'This is a dummy plugin', $actual );
		$this->assertSame( WP_PLUGIN_DIR . '/custom-internationalized-plugin/languages/', $registry_value );
		$this->assertSame( 'Das ist ein Dummy Plugin', $actual_de_de );
		$this->assertSame( 'Este es un plugin dummy', $actual_es_es );
	}

	/**
	 * @ticket 57116
	 */
	public function test_switch_reloads_plugin_translations() {
		/** @var WP_Textdomain_Registry $wp_textdomain_registry */
		global $wp_textdomain_registry;

		$has_translations_1 = $wp_textdomain_registry->has( 'internationalized-plugin' );

		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		$actual = i18n_plugin_test();

		switch_to_locale( 'es_ES' );

		$lang_path_es_es = $wp_textdomain_registry->get( 'internationalized-plugin', determine_locale() );

		switch_to_locale( 'de_DE' );

		$actual_de_de = i18n_plugin_test();

		$has_translations_3 = $wp_textdomain_registry->has( 'internationalized-plugin' );

		restore_previous_locale();

		$actual_es_es = i18n_plugin_test();

		restore_current_locale();

		$lang_path_en_us = $wp_textdomain_registry->get( 'internationalized-plugin', determine_locale() );

		$this->assertSame( 'This is a dummy plugin', $actual );
		$this->assertSame( 'Das ist ein Dummy Plugin', $actual_de_de );
		$this->assertSame( 'Este es un plugin dummy', $actual_es_es );
		$this->assertTrue( $has_translations_1 );
		$this->assertTrue( $has_translations_3 );
		$this->assertSame( WP_LANG_DIR . '/plugins/', $lang_path_es_es );
		$this->assertFalse( $lang_path_en_us );
	}

	/**
	 * @ticket 39210
	 */
	public function test_switch_reloads_theme_translations_outside_wp_lang_dir() {
		/** @var WP_Textdomain_Registry $wp_textdomain_registry */
		global $wp_textdomain_registry;

		switch_theme( 'custom-internationalized-theme' );

		require_once get_stylesheet_directory() . '/functions.php';

		$actual = custom_i18n_theme_test();

		switch_to_locale( 'es_ES' );

		$registry_value = $wp_textdomain_registry->get( 'custom-internationalized-theme', determine_locale() );

		switch_to_locale( 'de_DE' );

		$actual_de_de = custom_i18n_theme_test();

		restore_previous_locale();

		$actual_es_es = custom_i18n_theme_test();

		restore_current_locale();

		$this->assertSame( get_template_directory() . '/languages/', $registry_value );
		$this->assertSame( 'This is a dummy theme', $actual );
		$this->assertSame( 'Das ist ein Dummy Theme', $actual_de_de );
		$this->assertSame( 'Este es un tema dummy', $actual_es_es );
	}

	/**
	 * @ticket 57116
	 */
	public function test_switch_to_locale_should_work() {
		global $wp_textdomain_registry;
		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		$has_translations = $wp_textdomain_registry->has( 'internationalized-plugin' );
		$path             = $wp_textdomain_registry->get( 'internationalized-plugin', 'es_ES' );

		$actual = i18n_plugin_test();

		switch_to_locale( 'es_ES' );

		$actual_es_es = i18n_plugin_test();

		$this->assertTrue( $has_translations );
		$this->assertNotEmpty( $path );
		$this->assertSame( 'This is a dummy plugin', $actual );
		$this->assertSame( 'Este es un plugin dummy', $actual_es_es );
	}

	/**
	 * @ticket 57123
	 *
	 * @covers ::switch_to_locale
	 * @covers ::switch_to_user_locale
	 * @covers WP_Locale_Switcher::get_switched_locale
	 * @covers WP_Locale_Switcher::get_switched_user_id
	 */
	public function test_returns_current_locale_and_user_after_switching() {
		global $wp_locale_switcher;

		$user_2 = self::factory()->user->create(
			array(
				'role'   => 'administrator',
				'locale' => 'es_ES',
			)
		);

		$locale_1  = $wp_locale_switcher->get_switched_locale();
		$user_id_1 = $wp_locale_switcher->get_switched_user_id();

		switch_to_user_locale( self::$user_id );

		$locale_2  = $wp_locale_switcher->get_switched_locale();
		$user_id_2 = $wp_locale_switcher->get_switched_user_id();

		switch_to_locale( 'en_GB' );

		$locale_3  = $wp_locale_switcher->get_switched_locale();
		$user_id_3 = $wp_locale_switcher->get_switched_user_id();

		switch_to_user_locale( $user_2 );

		$locale_4  = $wp_locale_switcher->get_switched_locale();
		$user_id_4 = $wp_locale_switcher->get_switched_user_id();

		restore_current_locale();

		$locale_5  = $wp_locale_switcher->get_switched_locale();
		$user_id_5 = $wp_locale_switcher->get_switched_user_id();

		$this->assertFalse( $locale_1, 'Locale should be false before switching' );
		$this->assertFalse( $user_id_1, 'User ID should be false before switching' );

		$this->assertSame( 'de_DE', $locale_2, 'The locale was not changed to de_DE' );
		$this->assertSame( self::$user_id, $user_id_2, 'User ID should match the main admin ID' );

		$this->assertSame( 'en_GB', $locale_3, 'The locale was not changed to en_GB' );
		$this->assertFalse( $user_id_3, 'User ID should be false after normal locale switching' );

		$this->assertSame( 'es_ES', $locale_4, 'The locale was not changed to es_ES' );
		$this->assertSame( $user_2, $user_id_4, 'User ID should match the second admin ID' );

		$this->assertFalse( $locale_5, 'Locale should be false after restoring' );
		$this->assertFalse( $user_id_5, 'User ID should be false after restoring' );
	}

	/**
	 * @ticket 57123
	 *
	 * @covers ::switch_to_locale
	 * @covers ::switch_to_user_locale
	 * @covers WP_Locale_Switcher::get_switched_locale
	 * @covers WP_Locale_Switcher::get_switched_user_id
	 */
	public function test_returns_previous_locale_and_user_after_switching() {
		global $wp_locale_switcher;

		$locale_1  = $wp_locale_switcher->get_switched_locale();
		$user_id_1 = $wp_locale_switcher->get_switched_user_id();

		switch_to_user_locale( self::$user_id );

		$locale_2  = $wp_locale_switcher->get_switched_locale();
		$user_id_2 = $wp_locale_switcher->get_switched_user_id();

		switch_to_locale( 'en_GB' );

		$locale_3  = $wp_locale_switcher->get_switched_locale();
		$user_id_3 = $wp_locale_switcher->get_switched_user_id();

		restore_previous_locale();

		$locale_4  = $wp_locale_switcher->get_switched_locale();
		$user_id_4 = $wp_locale_switcher->get_switched_user_id();

		$this->assertFalse( $locale_1, 'Locale should be false before switching' );
		$this->assertFalse( $user_id_1, 'User ID should be false before switching' );

		$this->assertSame( 'de_DE', $locale_2, 'The locale was not changed to de_DE' );
		$this->assertSame( self::$user_id, $user_id_2, 'User ID should match the main admin ID' );

		$this->assertSame( 'en_GB', $locale_3, 'The locale was not changed to en_GB' );
		$this->assertFalse( $user_id_3, 'User ID should be false after normal locale switching' );

		$this->assertSame( 'de_DE', $locale_4, 'The locale was not changed back to de_DE' );
		$this->assertSame( self::$user_id, $user_id_4, 'User ID should match the main admin ID again' );
	}

	public function filter_locale() {
		return 'es_ES';
	}
}
