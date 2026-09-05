<?php

/**
 * @group admin
 * @group upgrade
 *
 * @covers ::wp_get_deferred_translation_updates
 * @covers ::wp_get_translation_update_data
 * @covers ::wp_get_translation_update_id
 * @covers ::wp_get_translation_update_language
 * @covers ::wp_get_translation_update_name
 * @covers ::wp_get_translation_update_plugin_names
 * @covers ::wp_get_translation_updates_by_id
 * @covers ::wp_is_translation_update_deferred
 * @covers ::wp_set_deferred_translation_updates
 */
class Tests_Admin_IncludesUpdate extends WP_UnitTestCase {
	/**
	 * Sets up shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	public function tear_down() {
		delete_site_option( 'deferred_translation_updates' );
		delete_site_transient( 'available_translations' );
		delete_site_transient( 'update_core' );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		remove_all_filters( 'all_plugins' );
		remove_all_filters( 'pre_site_transient_update_plugins' );

		parent::tear_down();
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_translation_update_data_loads_plugin_names_once() {
		$plugin_updates = array(
			array(
				'type'     => 'plugin',
				'slug'     => 'custom-internationalized-plugin',
				'language' => 'de_DE',
				'version'  => '1.0.0',
			),
			array(
				'type'     => 'plugin',
				'slug'     => 'hello-dolly',
				'language' => 'de_DE',
				'version'  => '1.7.2',
			),
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => $plugin_updates,
				'no_update'    => array(
					'hello.php' => (object) array(
						'slug' => 'hello-dolly',
					),
				),
			)
		);

		$transient_reads = 0;

		add_filter(
			'pre_site_transient_update_plugins',
			static function ( $value ) use ( &$transient_reads ) {
				++$transient_reads;
				return $value;
			}
		);

		wp_get_translation_update_data();

		$this->assertSame(
			2,
			$transient_reads,
			'The plugin update transient should be read once for available updates and once for plugin names.'
		);
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_translation_update_data_returns_display_ready_translation_updates() {
		$core_update = (object) array(
			'type'     => 'core',
			'slug'     => 'default',
			'language' => 'de_DE',
			'version'  => '6.7-beta3',
		);

		$plugin_update = (object) array(
			'type'     => 'plugin',
			'slug'     => 'custom-internationalized-plugin',
			'language' => 'de_DE',
			'version'  => '1.0.0',
		);

		$theme_update = (object) array(
			'type'     => 'theme',
			'slug'     => 'custom-internationalized-theme',
			'language' => 'de_DE',
			'version'  => '1.0.0',
		);

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
					(array) $core_update,
				),
			)
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array(
					(array) $plugin_update,
				),
			)
		);

		set_site_transient(
			'update_themes',
			(object) array(
				'translations' => array(
					(array) $theme_update,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'checked'       => true,
					'deferred'      => false,
					'id'            => wp_get_translation_update_id( $core_update ),
					'language'      => 'Deutsch (de_DE)',
					'language_code' => 'de_DE',
					'name'          => 'WordPress',
					'slug'          => 'default',
					'type'          => 'core',
					'version'       => '6.7-beta3',
				),
				array(
					'checked'       => true,
					'deferred'      => false,
					'id'            => wp_get_translation_update_id( $plugin_update ),
					'language'      => 'Deutsch (de_DE)',
					'language_code' => 'de_DE',
					'name'          => 'Custom Dummy Plugin',
					'slug'          => 'custom-internationalized-plugin',
					'type'          => 'plugin',
					'version'       => '1.0.0',
				),
				array(
					'checked'       => true,
					'deferred'      => false,
					'id'            => wp_get_translation_update_id( $theme_update ),
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
		$plugin_update = (object) array(
			'type'     => 'plugin',
			'slug'     => 'missing-plugin',
			'language' => 'it_IT',
			'version'  => '2.0.0',
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array(
					(array) $plugin_update,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'checked'       => true,
					'deferred'      => false,
					'id'            => wp_get_translation_update_id( $plugin_update ),
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
		$plugin_update = (object) array(
			'type'     => 'plugin',
			'slug'     => 'hello-dolly',
			'language' => 'de_DE',
			'version'  => '1.7.2',
		);

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
					(array) $plugin_update,
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
					'checked'       => true,
					'deferred'      => false,
					'id'            => wp_get_translation_update_id( $plugin_update ),
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

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_translation_updates_by_id_keys_updates_by_identifier() {
		$core_update = (object) array(
			'type'     => 'core',
			'slug'     => 'default',
			'language' => 'de_DE',
			'version'  => '6.7-beta3',
		);

		$this->assertSame(
			array(
				wp_get_translation_update_id( $core_update ) => $core_update,
			),
			wp_get_translation_updates_by_id( array( $core_update ) )
		);
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_translation_update_data_marks_deferred_translation_updates() {
		$plugin_update = (object) array(
			'type'     => 'plugin',
			'slug'     => 'deferred-plugin',
			'language' => 'de_DE',
			'version'  => '1.0.0',
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array(
					(array) $plugin_update,
				),
			)
		);

		wp_set_deferred_translation_updates( array( $plugin_update ) );

		$this->assertSame(
			array(
				array(
					'checked'       => false,
					'deferred'      => true,
					'id'            => wp_get_translation_update_id( $plugin_update ),
					'language'      => 'de_DE',
					'language_code' => 'de_DE',
					'name'          => 'deferred-plugin',
					'slug'          => 'deferred-plugin',
					'type'          => 'plugin',
					'version'       => '1.0.0',
				),
			),
			wp_get_translation_update_data()
		);
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_get_deferred_translation_updates_filters_to_available_updates() {
		$available_update = (object) array(
			'type'     => 'plugin',
			'slug'     => 'available-plugin',
			'language' => 'de_DE',
			'version'  => '1.0.0',
		);

		$stale_update = (object) array(
			'type'     => 'plugin',
			'slug'     => 'stale-plugin',
			'language' => 'de_DE',
			'version'  => '1.0.0',
		);

		wp_set_deferred_translation_updates( array( $available_update, $stale_update ) );

		$this->assertSame(
			array(
				wp_get_translation_update_id( $available_update ) => array(
					'type'     => 'plugin',
					'slug'     => 'available-plugin',
					'language' => 'de_DE',
					'version'  => '1.0.0',
				),
			),
			wp_get_deferred_translation_updates( array( $available_update ) )
		);

		$this->assertTrue( wp_is_translation_update_deferred( $available_update ) );
		$this->assertFalse(
			wp_is_translation_update_deferred(
				(object) array(
					'type'     => 'plugin',
					'slug'     => 'different-plugin',
					'language' => 'de_DE',
					'version'  => '1.0.0',
				)
			)
		);
	}
}
