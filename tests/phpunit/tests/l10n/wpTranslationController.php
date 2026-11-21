<?php

/**
 * @group l10n
 * @group i18n
 */
class WP_Translation_Controller_Tests extends WP_UnitTestCase {
	public function tear_down() {
		remove_all_filters( 'translation_file_format' );
		unload_textdomain( 'wp-tests-domain' );
		unload_textdomain( 'internationalized-plugin' );

		parent::tear_down();
	}

	/**
	 * @covers ::load_textdomain
	 * @covers WP_Translation_Controller::get_entries
	 * @covers WP_Translation_Controller::get_headers
	 * @covers WP_Translation_Controller::normalize_header
	 */
	public function test_load_textdomain() {
		global $l10n;

		$loaded_before_load = is_textdomain_loaded( 'wp-tests-domain' );

		$load_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$loaded_after_load = is_textdomain_loaded( 'wp-tests-domain' );

		$compat_instance = $l10n['wp-tests-domain'] ?? null;

		$is_loaded = WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'wp-tests-domain' );
		$headers   = WP_Translation_Controller::get_instance()->get_headers( 'wp-tests-domain' );
		$entries   = WP_Translation_Controller::get_instance()->get_entries( 'wp-tests-domain' );

		$unload_successful = unload_textdomain( 'wp-tests-domain' );

		$loaded_after_unload = is_textdomain_loaded( 'wp-tests-domain' );

		$this->assertFalse( $loaded_before_load, 'Text domain was already loaded at beginning of the test' );
		$this->assertTrue( $load_successful, 'Text domain not successfully loaded' );
		$this->assertTrue( $loaded_after_load, 'Text domain is not considered loaded' );
		$this->assertInstanceOf( WP_Translations::class, $compat_instance, 'No compat provider instance used' );
		$this->assertTrue( $unload_successful, 'Text domain not successfully unloaded' );
		$this->assertFalse( $loaded_after_unload, 'Text domain still considered loaded after unload' );
		$this->assertTrue( $is_loaded, 'Text domain not considered loaded' );
		$this->assertEqualSetsWithIndex(
			array(
				'Project-Id-Version'   => 'WordPress 2.6-bleeding',
				'Report-Msgid-Bugs-To' => 'wp-polyglots@lists.automattic.com',
			),
			$headers,
			'Actual translation headers do not match expected ones'
		);
		$this->assertEqualSetsWithIndex(
			array(
				'baba'       => 'dyado',
				"kuku\nruku" => 'yes',
			),
			$entries,
			'Actual translation entries do not match expected ones'
		);
	}

	/**
	 * @covers ::load_textdomain
	 * @covers WP_Translation_Controller::get_entries
	 * @covers WP_Translation_Controller::get_headers
	 * @covers WP_Translation_Controller::normalize_header
	 */
	public function test_load_textdomain_existing_override() {
		add_filter( 'override_load_textdomain', '__return_true' );

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$is_loaded_wp = is_textdomain_loaded( 'wp-tests-domain' );

		$is_loaded = WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'wp-tests-domain' );

		remove_filter( 'override_load_textdomain', '__return_true' );

		$this->assertFalse( $is_loaded_wp );
		$this->assertFalse( $is_loaded );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_php_files() {
		$load_php_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.l10n.php' );

		$unload_php_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_php_successful, 'PHP file not successfully loaded' );
		$this->assertTrue( $unload_php_successful );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_prefers_php_files_by_default() {
		$load_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$instance = WP_Translation_Controller::get_instance();

		$is_loaded = $instance->is_textdomain_loaded( 'wp-tests-domain', 'en_US' );

		$unload_mo  = $instance->unload_file( DIR_TESTDATA . '/pomo/simple.mo', 'wp-tests-domain' );
		$unload_php = $instance->unload_file( DIR_TESTDATA . '/pomo/simple.l10n.php', 'wp-tests-domain' );

		$unload_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_successful, 'Translation not successfully loaded' );
		$this->assertTrue( $is_loaded );
		$this->assertFalse( $unload_mo );
		$this->assertTrue( $unload_php );
		$this->assertTrue( $unload_successful );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_reads_php_files_if_filtered_format_is_unsupported() {
		add_filter(
			'translation_file_format',
			static function () {
				return 'unknown-format';
			}
		);

		$load_mo_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_mo_successful = unload_textdomain( 'wp-tests-domain' );

		$load_php_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.l10n.php' );

		$unload_php_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_mo_successful, 'MO file not successfully loaded' );
		$this->assertTrue( $unload_mo_successful );
		$this->assertTrue( $load_php_successful, 'PHP file not successfully loaded' );
		$this->assertTrue( $unload_php_successful );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_existing_translation_is_kept() {
		global $l10n;

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/context.mo' );

		$mo = new MO();
		$mo->import_from_file( DIR_TESTDATA . '/pomo/context.mo' );
		$mo->merge_with( $l10n['wp-tests-domain'] );
		$l10n['wp-tests-domain'] = $mo;

		$simple  = __( 'baba', 'wp-tests-domain' );
		$context = _x( 'one dragon', 'not so dragon', 'wp-tests-domain' );

		$this->assertSame( 'dyado', $simple );
		$this->assertSame( 'oney dragoney', $context );
		$this->assertInstanceOf( Translations::class, $l10n['wp-tests-domain'] );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_loads_existing_translation() {
		global $l10n;

		$mo = new MO();
		$mo->import_from_file( DIR_TESTDATA . '/pomo/simple.mo' );
		$l10n['wp-tests-domain'] = $mo;

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/context.mo' );

		$simple  = __( 'baba', 'wp-tests-domain' );
		$context = _x( 'one dragon', 'not so dragon', 'wp-tests-domain' );

		$this->assertSame( 'dyado', $simple );
		$this->assertSame( 'oney dragoney', $context );
		$this->assertInstanceOf( WP_Translations::class, $l10n['wp-tests-domain'] );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_loads_existing_translation_mo_files() {
		global $l10n;

		add_filter(
			'translation_file_format',
			static function () {
				return 'mo';
			}
		);

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$mo = new MO();
		$mo->import_from_file( DIR_TESTDATA . '/pomo/simple.mo' );
		$l10n['wp-tests-domain'] = $mo;

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/context.mo' );

		$simple  = __( 'baba', 'wp-tests-domain' );
		$context = _x( 'one dragon', 'not so dragon', 'wp-tests-domain' );

		$this->assertSame( 'dyado', $simple );
		$this->assertSame( 'oney dragoney', $context );
		$this->assertInstanceOf( WP_Translations::class, $l10n['wp-tests-domain'] );
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_loads_existing_translation_php_files() {
		global $l10n;

		// Just to ensure the PHP files exist.
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/context.mo' );
		unload_textdomain( 'wp-tests-domain' );

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$mo = new MO();
		$mo->import_from_file( DIR_TESTDATA . '/pomo/simple.mo' );
		$l10n['wp-tests-domain'] = $mo;

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/context.mo' );

		$simple  = __( 'baba', 'wp-tests-domain' );
		$context = _x( 'one dragon', 'not so dragon', 'wp-tests-domain' );

		$this->assertSame( 'dyado', $simple );
		$this->assertSame( 'oney dragoney', $context );
		$this->assertInstanceOf( WP_Translations::class, $l10n['wp-tests-domain'] );
	}

	/**
	 * @covers ::unload_textdomain
	 * @covers WP_Translation_Controller::get_entries
	 * @covers WP_Translation_Controller::get_headers
	 * @covers WP_Translation_Controller::normalize_header
	 */
	public function test_unload_textdomain() {
		global $l10n;

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_successful = unload_textdomain( 'wp-tests-domain' );

		$loaded_after_unload = is_textdomain_loaded( 'wp-tests-domain' );

		$compat_instance = $l10n['wp-tests-domain'] ?? null;

		$is_loaded = WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'wp-tests-domain' );
		$headers   = WP_Translation_Controller::get_instance()->get_headers( 'wp-tests-domain' );
		$entries   = WP_Translation_Controller::get_instance()->get_entries( 'wp-tests-domain' );

		$this->assertNull( $compat_instance, 'Compat instance was not removed' );
		$this->assertTrue( $unload_successful, 'Text domain not successfully unloaded' );
		$this->assertFalse( $loaded_after_unload, 'Text domain still considered loaded after unload' );
		$this->assertFalse( $is_loaded, 'Text domain still considered loaded' );
		$this->assertEmpty( $headers, 'Actual translation headers are not empty' );
		$this->assertEmpty( $entries, 'Actual translation entries are not empty' );
	}

	/**
	 * @covers ::unload_textdomain
	 */
	public function test_unload_textdomain_existing_override() {
		add_filter( 'override_unload_textdomain', '__return_true' );

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_successful = unload_textdomain( 'wp-tests-domain' );

		$is_loaded = WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'wp-tests-domain' );

		remove_filter( 'override_unload_textdomain', '__return_true' );

		$unload_successful_after = unload_textdomain( 'wp-tests-domain' );

		$is_loaded_after = WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'wp-tests-domain' );

		$this->assertTrue( $unload_successful );
		$this->assertTrue( $is_loaded );
		$this->assertTrue( $unload_successful_after );
		$this->assertFalse( $is_loaded_after );
	}

	/**
	 * @covers ::unload_file
	 * @covers ::unload_textdomain
	 */
	public function test_unload_non_existent_files_and_textdomains() {
		$controller = new WP_Translation_Controller();
		$this->assertFalse( $controller->unload_textdomain( 'foobarbaz' ) );
		$this->assertFalse( $controller->unload_textdomain( 'foobarbaz', 'es_ES' ) );
		$this->assertFalse( $controller->unload_textdomain( 'default', 'es_ES' ) );
		$this->assertFalse( $controller->unload_file( DIR_TESTDATA . '/l10n/fa_IR.mo' ) );
		$this->assertFalse( $controller->unload_file( DIR_TESTDATA . '/l10n/fa_IR.mo', 'es_ES' ) );
	}

	/**
	 * @covers ::load_textdomain
	 * @covers ::unload_textdomain
	 */
	public function test_switch_to_locale_translations_stay_loaded_default_textdomain() {
		switch_to_locale( 'es_ES' );

		$actual = __( 'Invalid parameter.' );

		$this->assertTrue( WP_Translation_Controller::get_instance()->is_textdomain_loaded() );
		$this->assertTrue( WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'default', 'es_ES' ) );

		restore_previous_locale();

		$actual_2 = __( 'Invalid parameter.' );

		$this->assertTrue( WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'default', 'es_ES' ) );

		$this->assertSame( 'Parámetro no válido. ', $actual );
		$this->assertSame( 'Invalid parameter.', $actual_2 );
	}

	/**
	 * @covers ::load_textdomain
	 * @covers ::unload_textdomain
	 * @covers ::change_locale
	 */
	public function test_switch_to_locale_translations_stay_loaded_custom_textdomain() {
		$this->assertSame( 'en_US', WP_Translation_Controller::get_instance()->get_locale() );

		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		$before = i18n_plugin_test();

		switch_to_locale( 'es_ES' );

		$actual = i18n_plugin_test();

		$this->assertSame( 'es_ES', WP_Translation_Controller::get_instance()->get_locale() );
		$this->assertTrue( WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'internationalized-plugin', 'es_ES' ) );
		$this->assertTrue( WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'default', 'es_ES' ) );
		$this->assertFalse( WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'foo-bar', 'es_ES' ) );

		restore_previous_locale();

		$after = i18n_plugin_test();

		$this->assertTrue( WP_Translation_Controller::get_instance()->is_textdomain_loaded( 'internationalized-plugin', 'es_ES' ) );

		$this->assertSame( 'This is a dummy plugin', $before );
		$this->assertSame( 'Este es un plugin dummy', $actual );
		$this->assertSame( 'This is a dummy plugin', $after );
	}

	/**
	 * @ticket 52696
	 * @covers ::has_translation
	 */
	public function test_has_translation_with_existing_translation() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );
		$this->assertTrue( WP_Translation_Controller::get_instance()->has_translation( 'baba', 'wp-tests-domain', 'en_US' ) );
	}

	/**
	 * @ticket 52696
	 * @covers ::has_translation
	 */
	public function test_has_translation_with_no_translation() {
		$this->assertFalse( WP_Translation_Controller::get_instance()->has_translation( 'Goodbye', 'wp-tests-domain', 'en_US' ) );
	}

	/**
	 * @ticket 52696
	 * @covers ::has_translation
	 */
	public function test_has_translation_with_different_textdomain() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );
		$this->assertFalse( WP_Translation_Controller::get_instance()->has_translation( 'baba', 'custom-domain', 'en_US' ) );
	}

	/**
	 * @ticket 52696
	 * @covers ::has_translation
	 */
	public function test_has_translation_with_different_locale() {
		switch_to_locale( 'es_ES' );
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );
		$actual = WP_Translation_Controller::get_instance()->has_translation( 'baba', 'wp-tests-domain', 'es_ES' );
		restore_previous_locale();
		$this->assertTrue( $actual );
	}

	/**
	 * @ticket 52696
	 * @covers ::has_translation
	 */
	public function test_has_translation_with_no_locale_provided() {
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );
		$this->assertTrue( WP_Translation_Controller::get_instance()->has_translation( 'baba', 'wp-tests-domain' ) );
	}
}
