<?php

/**
 * @group l10n
 * @group i18n
 */
class Ginger_MO_Integration_Tests extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function tear_down() {
		$generated_translation_files = array(
			DIR_TESTDATA . '/pomo/simple.mo.php',
			DIR_TESTDATA . '/pomo/simple.mo.json',
			DIR_TESTDATA . '/pomo/simple.mo.json',
			DIR_TESTDATA . '/pomo/context.mo.php',
			WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo.php',
			WP_LANG_DIR . '/themes/internationalized-theme-de_DE.mo.php',
			WP_LANG_DIR . '/de_DE.mo.php',
		);

		foreach ( $generated_translation_files as $file ) {
			if ( file_exists( $file ) ) {
				$this->unlink( $file );
			}
		}

		remove_all_filters( 'convert_translation_files' );
		remove_all_filters( 'translation_file_format' );

		remove_all_filters( 'filesystem_method' );

		unload_textdomain( 'wp-tests-domain' );
	}

	/**
	 * @covers ::load_textdomain
	 * @covers Ginger_MO::get_entries
	 * @covers Ginger_MO::get_headers
	 * @covers Ginger_MO::normalize_header
	 *
	 * @return void
	 */
	public function test_load_textdomain() {
		global $l10n;

		$loaded_before_load = is_textdomain_loaded( 'wp-tests-domain' );

		$load_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$loaded_after_load = is_textdomain_loaded( 'wp-tests-domain' );

		$compat_instance = $l10n['wp-tests-domain'] ?? null;

		$is_loaded = Ginger_MO::instance()->is_loaded( 'wp-tests-domain' );
		$headers   = Ginger_MO::instance()->get_headers( 'wp-tests-domain' );
		$entries   = Ginger_MO::instance()->get_entries( 'wp-tests-domain' );

		$unload_successful = unload_textdomain( 'wp-tests-domain' );

		$loaded_after_unload = is_textdomain_loaded( 'wp-tests-domain' );

		$this->assertFalse( $loaded_before_load, 'Text domain was already loaded at beginning of the test' );
		$this->assertTrue( $load_successful, 'Text domain not successfully loaded' );
		$this->assertTrue( $loaded_after_load, 'Text domain is not considered loaded' );
		$this->assertInstanceOf( Ginger_MO_Translations::class, $compat_instance, 'No compat provider instance used' );
		$this->assertTrue( $unload_successful, 'Text domain not successfully unloaded' );
		$this->assertFalse( $loaded_after_unload, 'Text domain still considered loaded after unload' );
		$this->assertTrue( $is_loaded, 'Text domain not considered loaded in Ginger-MO' );
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
	 * @covers Ginger_MO::get_entries
	 * @covers Ginger_MO::get_headers
	 * @covers Ginger_MO::normalize_header
	 *
	 * @return void
	 */
	public function test_load_textdomain_existing_override() {
		add_filter( 'override_load_textdomain', '__return_true' );

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$is_loaded_wp = is_textdomain_loaded( 'wp-tests-domain' );

		$is_loaded = Ginger_MO::instance()->is_loaded( 'wp-tests-domain' );

		remove_filter( 'override_load_textdomain', '__return_true' );

		$this->assertFalse( $is_loaded_wp );
		$this->assertFalse( $is_loaded );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
	 */
	public function test_load_textdomain_mo_files() {
		add_filter(
			'translation_file_format',
			static function () {
				return 'mo';
			}
		);

		$load_mo_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_mo_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_mo_successful, 'MO file not successfully loaded' );
		$this->assertTrue( $unload_mo_successful );
		$this->assertFileDoesNotExist( DIR_TESTDATA . '/pomo/simple.mo.php' );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
	 */
	public function test_load_textdomain_creates_and_reads_php_files() {
		$load_mo_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_mo_successful = unload_textdomain( 'wp-tests-domain' );

		$load_php_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo.php' );

		$unload_php_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_mo_successful, 'MO file not successfully loaded' );
		$this->assertTrue( $unload_mo_successful );
		$this->assertFileExists( DIR_TESTDATA . '/pomo/simple.mo.php' );
		$this->assertTrue( $load_php_successful, 'PHP file not successfully loaded' );
		$this->assertTrue( $unload_php_successful );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
	 */
	public function test_load_textdomain_creates_and_reads_php_files_if_filtered_format_is_unsupported() {
		add_filter(
			'translation_file_format',
			static function () {
				return 'unknown-format';
			}
		);

		$load_mo_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_mo_successful = unload_textdomain( 'wp-tests-domain' );

		$load_php_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo.php' );

		$unload_php_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_mo_successful, 'MO file not successfully loaded' );
		$this->assertTrue( $unload_mo_successful );
		$this->assertFileExists( DIR_TESTDATA . '/pomo/simple.mo.php' );
		$this->assertTrue( $load_php_successful, 'PHP file not successfully loaded' );
		$this->assertTrue( $unload_php_successful );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
	 */
	public function test_load_textdomain_creates_and_reads_php_files_no_wp_filesystem() {
		add_filter( 'filesystem_method', '__return_empty_string' );

		$load_mo_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_mo_successful = unload_textdomain( 'wp-tests-domain' );

		$load_php_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo.php' );

		$unload_php_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_mo_successful, 'MO file not successfully loaded' );
		$this->assertTrue( $unload_mo_successful );
		$this->assertFileExists( DIR_TESTDATA . '/pomo/simple.mo.php' );
		$this->assertTrue( $load_php_successful, 'PHP file not successfully loaded' );
		$this->assertTrue( $unload_php_successful );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
	 */
	public function test_load_textdomain_does_not_create_php_files_if_disabled() {
		add_filter( 'convert_translation_files', '__return_false' );

		$load_mo_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_mo_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_mo_successful, 'MO file not successfully loaded' );
		$this->assertTrue( $unload_mo_successful );
		$this->assertFileDoesNotExist( DIR_TESTDATA . '/pomo/simple.mo.php' );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
	 */
	public function test_load_textdomain_creates_and_reads_json_files() {
		add_filter(
			'translation_file_format',
			static function () {
				return 'json';
			}
		);

		$load_mo_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_mo_successful = unload_textdomain( 'wp-tests-domain' );

		$load_json_successful = load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo.json' );

		$unload_json_successful = unload_textdomain( 'wp-tests-domain' );

		$this->assertTrue( $load_mo_successful, 'MO file not successfully loaded' );
		$this->assertTrue( $unload_mo_successful );
		$this->assertFileExists( DIR_TESTDATA . '/pomo/simple.mo.json' );
		$this->assertTrue( $load_json_successful, 'JSON file not successfully loaded' );
		$this->assertTrue( $unload_json_successful );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
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
	 *
	 * @return void
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
		$this->assertInstanceOf( Ginger_MO_Translations::class, $l10n['wp-tests-domain'] );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
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
		$this->assertInstanceOf( Ginger_MO_Translations::class, $l10n['wp-tests-domain'] );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
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
		$this->assertInstanceOf( Ginger_MO_Translations::class, $l10n['wp-tests-domain'] );
	}

	/**
	 * @param string $domain
	 * @param string $file
	 * @return void
	 */
	public function _on_load_textdomain( $domain, $file ) {
		remove_action( 'load_textdomain', array( $this, '_on_load_textdomain' ) );
		load_textdomain( $domain, $file );
	}

	/**
	 * @covers ::load_textdomain
	 *
	 * @return void
	 */
	public function test_load_textdomain_inception_does_not_create_duplicate_files() {
		add_action( 'load_textdomain', array( $this, '_on_load_textdomain' ), 10, 2 );

		// Just to ensure the PHP files exist.
		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );
		unload_textdomain( 'wp-tests-domain' );

		$this->assertFileExists( DIR_TESTDATA . '/pomo/simple.mo.php' );
		$this->assertFileDoesNotExist( DIR_TESTDATA . '/pomo/simple.mo.php.php' );
	}

	/**
	 * @covers ::unload_textdomain
	 * @covers Ginger_MO::get_entries
	 * @covers Ginger_MO::get_headers
	 * @covers Ginger_MO::normalize_header
	 *
	 * @return void
	 */
	public function test_unload_textdomain() {
		global $l10n;

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_successful = unload_textdomain( 'wp-tests-domain' );

		$loaded_after_unload = is_textdomain_loaded( 'wp-tests-domain' );

		$compat_instance = $l10n['wp-tests-domain'] ?? null;

		$is_loaded = Ginger_MO::instance()->is_loaded( 'wp-tests-domain' );
		$headers   = Ginger_MO::instance()->get_headers( 'wp-tests-domain' );
		$entries   = Ginger_MO::instance()->get_entries( 'wp-tests-domain' );

		$this->assertNull( $compat_instance, 'Compat instance was not removed' );
		$this->assertTrue( $unload_successful, 'Text domain not successfully unloaded' );
		$this->assertFalse( $loaded_after_unload, 'Text domain still considered loaded after unload' );
		$this->assertFalse( $is_loaded, 'Text domain still considered loaded in Ginger-MO' );
		$this->assertEmpty( $headers, 'Actual translation headers are not empty' );
		$this->assertEmpty( $entries, 'Actual translation entries are not empty' );
	}

	/**
	 * @covers ::unload_textdomain
	 *
	 * @return void
	 */
	public function test_unload_textdomain_existing_override() {
		add_filter( 'override_unload_textdomain', '__return_true' );

		load_textdomain( 'wp-tests-domain', DIR_TESTDATA . '/pomo/simple.mo' );

		$unload_successful = unload_textdomain( 'wp-tests-domain' );

		$is_loaded = Ginger_MO::instance()->is_loaded( 'wp-tests-domain' );

		remove_filter( 'override_unload_textdomain', '__return_true' );

		$unload_successful_after = unload_textdomain( 'wp-tests-domain' );

		$is_loaded_after = Ginger_MO::instance()->is_loaded( 'wp-tests-domain' );

		$this->assertTrue( $unload_successful );
		$this->assertTrue( $is_loaded );
		$this->assertTrue( $unload_successful_after );
		$this->assertFalse( $is_loaded_after );
	}

	/**
	 * @covers ::load_textdomain
	 * @covers ::unload_textdomain
	 *
	 * @return void
	 */
	public function test_switch_to_locale_translations_stay_loaded_default_textdomain() {
		switch_to_locale( 'es_ES' );

		$actual = __( 'Invalid parameter.' );

		$this->assertTrue( Ginger_MO::instance()->is_loaded() );
		$this->assertTrue( Ginger_MO::instance()->is_loaded( 'default', 'es_ES' ) );

		restore_previous_locale();

		$actual_2 = __( 'Invalid parameter.' );

		$this->assertTrue( Ginger_MO::instance()->is_loaded( 'default', 'es_ES' ) );

		$this->assertSame( 'Parámetro no válido. ', $actual );
		$this->assertSame( 'Invalid parameter.', $actual_2 );
	}

	/**
	 * @covers ::load_textdomain
	 * @covers ::unload_textdomain
	 * @covers ::change_locale
	 *
	 * @return void
	 */
	public function test_switch_to_locale_translations_stay_loaded_custom_textdomain() {
		$this->assertSame( 'en_US', Ginger_MO::instance()->get_locale() );

		require_once DIR_TESTDATA . '/plugins/internationalized-plugin.php';

		$before = i18n_plugin_test();

		switch_to_locale( 'es_ES' );

		$actual = i18n_plugin_test();

		$this->assertSame( 'es_ES', Ginger_MO::instance()->get_locale() );
		$this->assertTrue( Ginger_MO::instance()->is_loaded( 'internationalized-plugin', 'es_ES' ) );
		$this->assertTrue( Ginger_MO::instance()->is_loaded( 'default', 'es_ES' ) );
		$this->assertFalse( Ginger_MO::instance()->is_loaded( 'foo-bar', 'es_ES' ) );

		restore_previous_locale();

		$after = i18n_plugin_test();

		$this->assertTrue( Ginger_MO::instance()->is_loaded( 'internationalized-plugin', 'es_ES' ) );

		$this->assertSame( 'This is a dummy plugin', $before );
		$this->assertSame( 'Este es un plugin dummy', $actual );
		$this->assertSame( 'This is a dummy plugin', $after );
	}

	/**
	 * @covers ::upgrader_process_complete
	 *
	 * @return void
	 */
	public function test_create_translation_files_after_translations_update() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php';
		require_once DIR_TESTROOT . '/includes/class-dummy-upgrader-skin.php';
		require_once DIR_TESTROOT . '/includes/class-dummy-language-pack-upgrader.php';

		$upgrader = new Dummy_Language_Pack_Upgrader( new Dummy_Upgrader_Skin() );

		// These translations exist in the core test suite.
		// See https://github.com/WordPress/wordpress-develop/tree/e3d345800d3403f3902dc7b18c1ddb07158b0bd3/tests/phpunit/data/languages.
		$result = $upgrader->bulk_upgrade(
			array(
				(object) array(
					'type'     => 'plugin',
					'slug'     => 'internationalized-plugin',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
				(object) array(
					'type'     => 'theme',
					'slug'     => 'internationalized-theme',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
				(object) array(
					'type'     => 'core',
					'slug'     => 'default',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
			)
		);

		$this->assertIsNotBool( $result );
		$this->assertNotWPError( $result );
		$this->assertNotEmpty( $result );

		$this->assertFileExists( WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo.php' );
		$this->assertFileExists( WP_LANG_DIR . '/themes/internationalized-theme-de_DE.mo.php' );
		$this->assertFileExists( WP_LANG_DIR . '/de_DE.mo.php' );
	}

	/**
	 * @covers ::upgrader_process_complete
	 *
	 * @return void
	 */
	public function test_create_translation_files_after_translations_update_if_filtered_format_is_unsupported() {
		add_filter(
			'translation_file_format',
			static function () {
				return 'unknown-format';
			}
		);

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php';

		$upgrader = new Dummy_Language_Pack_Upgrader( new Dummy_Upgrader_Skin() );

		// These translations exist in the core test suite.
		// See https://github.com/WordPress/wordpress-develop/tree/e3d345800d3403f3902dc7b18c1ddb07158b0bd3/tests/phpunit/data/languages.
		$result = $upgrader->bulk_upgrade(
			array(
				(object) array(
					'type'     => 'plugin',
					'slug'     => 'internationalized-plugin',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
				(object) array(
					'type'     => 'theme',
					'slug'     => 'internationalized-theme',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
				(object) array(
					'type'     => 'core',
					'slug'     => 'default',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
			)
		);

		$this->assertIsNotBool( $result );
		$this->assertNotWPError( $result );
		$this->assertNotEmpty( $result );

		$this->assertFileExists( WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo.php' );
		$this->assertFileExists( WP_LANG_DIR . '/themes/internationalized-theme-de_DE.mo.php' );
		$this->assertFileExists( WP_LANG_DIR . '/de_DE.mo.php' );
	}

	/**
	 * @covers ::upgrader_process_complete
	 *
	 * @return void
	 */
	public function test_create_translation_files_after_translations_update_no_wp_filesystem() {
		$callback = static function () {
			add_filter( 'filesystem_method', '__return_empty_string' );
		};

		add_action( 'upgrader_process_complete', $callback, 1 );

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php';
		require_once DIR_TESTROOT . '/includes/class-dummy-upgrader-skin.php';
		require_once DIR_TESTROOT . '/includes/class-dummy-language-pack-upgrader.php';

		$upgrader = new Dummy_Language_Pack_Upgrader( new Dummy_Upgrader_Skin() );

		// These translations exist in the core test suite.
		// See https://github.com/WordPress/wordpress-develop/tree/e3d345800d3403f3902dc7b18c1ddb07158b0bd3/tests/phpunit/data/languages.
		$result = $upgrader->bulk_upgrade(
			array(
				(object) array(
					'type'     => 'plugin',
					'slug'     => 'internationalized-plugin',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
				(object) array(
					'type'     => 'theme',
					'slug'     => 'internationalized-theme',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
				(object) array(
					'type'     => 'core',
					'slug'     => 'default',
					'language' => 'de_DE',
					'version'  => '99.9.9',
					'package'  => '/tmp/notused.zip',
				),
			)
		);

		remove_action( 'upgrader_process_complete', $callback, 1 );

		$this->assertIsNotBool( $result );
		$this->assertNotWPError( $result );
		$this->assertNotEmpty( $result );

		$this->assertFileExists( WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo.php' );
		$this->assertFileExists( WP_LANG_DIR . '/themes/internationalized-theme-de_DE.mo.php' );
		$this->assertFileExists( WP_LANG_DIR . '/de_DE.mo.php' );
	}

	/**
	 * @covers ::upgrader_process_complete
	 *
	 * @return void
	 */
	public function test_do_not_create_translations_after_plugin_update() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once DIR_TESTROOT . '/includes/class-dummy-upgrader-skin.php';
		require_once DIR_TESTROOT . '/includes/class-dummy-plugin-upgrader.php';

		$upgrader = new Dummy_Plugin_Upgrader( new Dummy_Upgrader_Skin() );

		set_site_transient(
			'update_plugins',
			(object) array(
				'response' => array(
					'custom-internationalized-plugin/custom-internationalized-plugin.php' => (object) array(
						'package' => 'https://urltozipfile.local',
					),
				),
			)
		);

		$result = $upgrader->bulk_upgrade(
			array(
				'custom-internationalized-plugin/custom-internationalized-plugin.php',
			)
		);

		$this->assertNotFalse( $result );
		$this->assertFileDoesNotExist( WP_LANG_DIR . '/plugins/custom-internationalized-plugin-de_DE.php' );
		$this->assertFileDoesNotExist( WP_PLUGIN_DIR . '/plugins/custom-internationalized-plugin/custom-internationalized-plugin-de_DE.php' );
	}
}
