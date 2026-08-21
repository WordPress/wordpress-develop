<?php

/**
 * @group l10n
 * @group i18n
 *
 * @coversDefaultClass WP_Textdomain_Registry
 */
class Tests_L10n_wpTextdomainRegistry extends WP_UnitTestCase {
	/**
	 * @var WP_Textdomain_Registry
	 */
	protected $instance;

	public function set_up() {
		parent::set_up();

		$this->instance = new WP_Textdomain_Registry();
	}

	public function tear_down() {
		wp_cache_delete( md5( WP_LANG_DIR . '/foobar/' ), 'translation_files' );
		wp_cache_delete( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' );
		wp_cache_delete( md5( WP_LANG_DIR . '/themes/' ), 'translation_files' );
		wp_cache_delete( md5( WP_LANG_DIR . '/' ), 'translation_files' );
		wp_cache_delete( md5( WP_PLUGIN_DIR . '/custom-internationalized-plugin/languages/' ), 'translation_files' );

		parent::tear_down();
	}

	/**
	 * @covers ::has
	 * @covers ::get
	 * @covers ::set_custom_path
	 */
	public function test_set_custom_path() {
		$this->instance->set_custom_path( 'foo', WP_LANG_DIR . '/bar' );

		$this->assertTrue(
			$this->instance->has( 'foo' ),
			'Incorrect availability status for textdomain with custom path'
		);
		$this->assertSame(
			WP_LANG_DIR . '/bar/',
			$this->instance->get( 'foo', 'en_US' ),
			'Should return custom path for textdomain and en_US locale'
		);
		$this->assertSame(
			WP_LANG_DIR . '/bar/',
			$this->instance->get( 'foo', 'de_DE' ),
			'Custom path for textdomain not returned'
		);
		$this->assertNotFalse(
			wp_cache_get( md5( WP_LANG_DIR . '/bar/' ), 'translation_files' ),
			'List of files in custom path not cached'
		);
	}

	/**
	 * @covers ::get
	 * @dataProvider data_domains_locales
	 */
	public function test_get( $domain, $locale, $expected ) {
		$actual = $this->instance->get( $domain, $locale );
		$this->assertSame(
			$expected,
			$actual,
			'Expected languages directory path not matching actual one'
		);
	}

	/**
	 * @covers ::set
	 * @covers ::get
	 */
	public function test_set_populates_cache() {
		$this->instance->set( 'foo-plugin', 'de_DE', '/foo/bar' );

		$this->assertSame(
			'/foo/bar/',
			$this->instance->get( 'foo-plugin', 'de_DE' )
		);
	}

	/**
	 * @covers ::get_language_files_from_path
	 */
	public function test_get_language_files_from_path_caches_results() {
		$this->instance->get_language_files_from_path( WP_LANG_DIR . '/foobar/' );
		$this->instance->get_language_files_from_path( WP_LANG_DIR . '/plugins/' );
		$this->instance->get_language_files_from_path( WP_LANG_DIR . '/themes/' );
		$this->instance->get_language_files_from_path( WP_LANG_DIR . '/' );

		$this->assertNotFalse( wp_cache_get( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' ) );
		$this->assertNotFalse( wp_cache_get( md5( WP_LANG_DIR . '/themes/' ), 'translation_files' ) );
		$this->assertNotFalse( wp_cache_get( md5( WP_LANG_DIR . '/foobar/' ), 'translation_files' ) );
		$this->assertNotFalse( wp_cache_get( md5( WP_LANG_DIR . '/' ), 'translation_files' ) );
	}

	/**
	 * @covers ::get_language_files_from_path
	 */
	public function test_get_language_files_from_path_short_circuit() {
		add_filter( 'pre_get_language_files_from_path', '__return_empty_array' );
		$result = $this->instance->get_language_files_from_path( WP_LANG_DIR . '/plugins/' );
		remove_filter( 'pre_get_language_files_from_path', '__return_empty_array' );

		$cache = wp_cache_get( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' );

		$this->assertEmpty( $result );
		$this->assertFalse( $cache );
	}

	/**
	 * @covers ::invalidate_mo_files_cache
	 */
	public function test_invalidate_mo_files_cache() {
		$this->instance->get_language_files_from_path( WP_LANG_DIR . '/plugins/' );
		$this->instance->get_language_files_from_path( WP_LANG_DIR . '/themes/' );
		$this->instance->get_language_files_from_path( WP_LANG_DIR . '/' );

		$this->instance->invalidate_mo_files_cache(
			null,
			array(
				'type'         => 'translation',
				'translations' => array(
					(object) array(
						'type'     => 'plugin',
						'slug'     => 'internationalized-plugin',
						'language' => 'de_DE',
						'version'  => '99.9.9',
					),
					(object) array(
						'type'     => 'theme',
						'slug'     => 'internationalized-theme',
						'language' => 'de_DE',
						'version'  => '99.9.9',
					),
					(object) array(
						'type'     => 'core',
						'slug'     => 'default',
						'language' => 'es_ES',
						'version'  => '99.9.9',
					),
				),
			)
		);

		$this->assertFalse( wp_cache_get( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' ) );
		$this->assertFalse( wp_cache_get( md5( WP_LANG_DIR . '/themes/' ), 'translation_files' ) );
		$this->assertFalse( wp_cache_get( md5( WP_LANG_DIR . '/' ), 'translation_files' ) );
	}

	/**
	 * The registry answers "yes" for a text domain it has never looked up,
	 * so that _load_textdomain_just_in_time() gets a chance to resolve it.
	 *
	 * @ticket 62348
	 *
	 * @covers ::has
	 */
	public function test_has_returns_true_for_unknown_text_domain() {
		$this->assertTrue( $this->instance->has( 'unknown-plugin' ) );
	}

	/**
	 * A lookup that found nothing still records the (negative) result for the
	 * current locale, which keeps has() truthy.
	 *
	 * @ticket 62348
	 *
	 * @covers ::has
	 * @covers ::get
	 */
	public function test_has_returns_true_after_unsuccessful_lookup() {
		$this->assertFalse(
			$this->instance->get( 'unknown-plugin', 'de_DE' ),
			'A text domain without translations should not resolve to a path'
		);
		$this->assertTrue(
			$this->instance->has( 'unknown-plugin' ),
			'The negative result for the current locale should still be reported as available'
		);
	}

	/**
	 * Registering a custom path late must not make previously found translations
	 * unreachable.
	 *
	 * load_plugin_textdomain() and load_theme_textdomain() only register a custom
	 * path and hand the actual loading off to _load_textdomain_just_in_time(),
	 * which bails early when has() returns false.
	 *
	 * @ticket 62348
	 *
	 * @covers ::has
	 * @covers ::set_custom_path
	 */
	public function test_has_after_custom_path_is_registered_following_a_successful_lookup() {
		// A first locale resolves to the WordPress languages directory.
		$this->assertSame(
			WP_LANG_DIR . '/plugins/',
			$this->instance->get( 'internationalized-plugin', 'de_DE' ),
			'de_DE translations should be found in the WordPress languages directory'
		);

		// A second locale has no translations at all, so 'current' becomes false.
		$this->assertFalse(
			$this->instance->get( 'internationalized-plugin', 'fr_FR' ),
			'There should be no fr_FR translations'
		);

		// Only now does the plugin call load_plugin_textdomain().
		$this->instance->set_custom_path(
			'internationalized-plugin',
			WP_PLUGIN_DIR . '/custom-internationalized-plugin/languages'
		);

		$this->assertTrue(
			$this->instance->has( 'internationalized-plugin' ),
			'Registering a custom path should not hide the already known translations'
		);
		$this->assertSame(
			WP_LANG_DIR . '/plugins/',
			$this->instance->get( 'internationalized-plugin', 'de_DE' ),
			'de_DE translations should still be found after registering a custom path'
		);
	}

	/**
	 * Same as above, except that no locale ever resolved, so there is nothing
	 * left to remember once the negative results have been discarded.
	 *
	 * @ticket 62348
	 *
	 * @covers ::has
	 * @covers ::set_custom_path
	 */
	public function test_has_after_custom_path_is_registered_following_an_unsuccessful_lookup() {
		$this->assertFalse(
			$this->instance->get( 'unknown-plugin', 'de_DE' ),
			'There should be no de_DE translations'
		);

		$this->instance->set_custom_path(
			'unknown-plugin',
			WP_PLUGIN_DIR . '/custom-internationalized-plugin/languages'
		);

		$this->assertTrue(
			$this->instance->has( 'unknown-plugin' ),
			'The newly registered custom path should be given a chance'
		);
	}

	/**
	 * Text domains are matched in full, not by prefix.
	 *
	 * "internationalized-plugin-de_DE.mo" must not count as a translation for the
	 * "internationalized" text domain just because the file name starts with it.
	 *
	 * @ticket 62348
	 *
	 * @covers ::get
	 * @covers ::has
	 * @dataProvider data_text_domains_sharing_a_prefix
	 *
	 * @param string $domain Text domain that has no translations of its own.
	 * @param string $locale Locale to look up.
	 */
	public function test_translations_are_not_shared_between_text_domains_with_a_common_prefix( $domain, $locale ) {
		$this->assertFalse(
			$this->instance->get( $domain, $locale ),
			'A text domain sharing a prefix with a translated one should not resolve to a path'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_text_domains_sharing_a_prefix() {
		return array(
			// "internationalized-plugin-{de_DE,es_ES}.mo" exist, "internationalized-*" do not.
			'prefix of a domain with .mo files'       => array( 'internationalized', 'de_DE' ),
			'prefix of a domain with .mo files, es'   => array( 'internationalized', 'es_ES' ),
			// "internationalized-plugin-2-de_DE.l10n.php" exists, but is not a
			// translation of "internationalized-plugin".
			'domain whose sibling adds a suffix'      => array( 'internationalized-plugin', 'fr_FR' ),
		);
	}

	/**
	 * Once a custom path is registered, the domain must stay resolvable even when
	 * an unrelated text domain shares its prefix.
	 *
	 * @ticket 62348
	 *
	 * @covers ::has
	 * @covers ::set_custom_path
	 */
	public function test_has_with_custom_path_is_unaffected_by_text_domains_sharing_a_prefix() {
		$this->assertFalse(
			$this->instance->get( 'internationalized', 'de_DE' ),
			'The "internationalized" text domain has no translations of its own'
		);

		$this->instance->set_custom_path(
			'internationalized',
			WP_PLUGIN_DIR . '/custom-internationalized-plugin/languages'
		);

		$this->assertTrue(
			$this->instance->has( 'internationalized' ),
			'The newly registered custom path should be given a chance'
		);
	}

	public function data_domains_locales() {
		return array(
			'Non-existent plugin'                      => array(
				'unknown-plugin',
				'en_US',
				false,
			),
			'Non-existent plugin with de_DE'           => array(
				'unknown-plugin',
				'de_DE',
				false,
			),
			'Available de_DE translations'             => array(
				'internationalized-plugin',
				'de_DE',
				WP_LANG_DIR . '/plugins/',
			),
			'Available es_ES translations'             => array(
				'internationalized-plugin',
				'es_ES',
				WP_LANG_DIR . '/plugins/',
			),
			'Unavailable fr_FR translations'           => array(
				'internationalized-plugin',
				'fr_FR',
				false,
			),
			'Unavailable en_US translations'           => array(
				'internationalized-plugin',
				'en_US',
				false,
			),
			'Available de_DE translations (.l10n.php)' => array(
				'internationalized-plugin-2',
				'de_DE',
				WP_LANG_DIR . '/plugins/',
			),
			'Available es_ES translations (.l10n.php)' => array(
				'internationalized-plugin-2',
				'es_ES',
				WP_LANG_DIR . '/plugins/',
			),
		);
	}
}
