<?php

/**
 * @group admin
 * @group upgrade
 *
 * @covers ::wp_get_translation_update_data
 * @covers ::wp_get_translation_update_language
 * @covers ::wp_get_translation_update_name
 */
class Tests_Admin_IncludesUpdate extends WP_UnitTestCase {
	/**
	 * Sets up shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	public function tear_down() {
		delete_site_transient( 'available_translations' );
		delete_site_transient( 'update_core' );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		remove_all_filters( 'all_plugins' );

		parent::tear_down();
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_translation_update_data_returns_display_ready_translation_updates() {
		set_site_transient(
			'available_translations',
			array(
				'de_DE' => array(
					'native_name' => 'Deutsch',
				),
			)
		);

		set_site_transient(
			'update_core',
			(object) array(
				'translations' => array(
					array(
						'type'     => 'core',
						'slug'     => 'default',
						'language' => 'de_DE',
						'version'  => '6.7-beta3',
					),
				),
			)
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array(
					array(
						'type'     => 'plugin',
						'slug'     => 'custom-internationalized-plugin',
						'language' => 'de_DE',
						'version'  => '1.0.0',
					),
				),
			)
		);

		set_site_transient(
			'update_themes',
			(object) array(
				'translations' => array(
					array(
						'type'     => 'theme',
						'slug'     => 'custom-internationalized-theme',
						'language' => 'de_DE',
						'version'  => '1.0.0',
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'language'      => 'Deutsch (de_DE)',
					'language_code' => 'de_DE',
					'name'          => 'WordPress',
					'slug'          => 'default',
					'type'          => 'core',
					'version'       => '6.7-beta3',
				),
				array(
					'language'      => 'Deutsch (de_DE)',
					'language_code' => 'de_DE',
					'name'          => 'Custom Dummy Plugin',
					'slug'          => 'custom-internationalized-plugin',
					'type'          => 'plugin',
					'version'       => '1.0.0',
				),
				array(
					'language'      => 'Deutsch (de_DE)',
					'language_code' => 'de_DE',
					'name'          => 'Custom Internationalized Theme',
					'slug'          => 'custom-internationalized-theme',
					'type'          => 'theme',
					'version'       => '1.0.0',
				),
			),
			wp_get_translation_update_data()
		);
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_translation_update_data_falls_back_to_locale_and_slug() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array(
					array(
						'type'     => 'plugin',
						'slug'     => 'missing-plugin',
						'language' => 'it_IT',
						'version'  => '2.0.0',
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'language'      => 'it_IT',
					'language_code' => 'it_IT',
					'name'          => 'missing-plugin',
					'slug'          => 'missing-plugin',
					'type'          => 'plugin',
					'version'       => '2.0.0',
				),
			),
			wp_get_translation_update_data()
		);
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_translation_update_data_matches_plugin_update_slug_to_plugin_file() {
		add_filter(
			'all_plugins',
			static function () {
				return array(
					'hello.php' => array(
						'Name' => 'Hello Dolly',
					),
				);
			}
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array(
					array(
						'type'     => 'plugin',
						'slug'     => 'hello-dolly',
						'language' => 'de_DE',
						'version'  => '1.7.2',
					),
				),
				'no_update'    => array(
					'hello.php' => (object) array(
						'slug' => 'hello-dolly',
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'language'      => 'de_DE',
					'language_code' => 'de_DE',
					'name'          => 'Hello Dolly',
					'slug'          => 'hello-dolly',
					'type'          => 'plugin',
					'version'       => '1.7.2',
				),
			),
			wp_get_translation_update_data()
		);
	}
}
