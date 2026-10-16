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
 * @covers ::wp_process_translation_update_selection
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
	public function test_wp_get_translation_update_data_reads_plugin_updates_independent_of_row_count() {
		$count_reads = function ( $row_count ) {
			$translations = array();
			for ( $i = 1; $i <= $row_count; $i++ ) {
				$translations[] = array(
					'type'     => 'plugin',
					'slug'     => "plugin-$i",
					'language' => 'de_DE',
					'version'  => '1.0.0',
				);
			}

			set_site_transient( 'update_plugins', (object) array( 'translations' => $translations ) );

			$reads  = 0;
			$filter = static function ( $value ) use ( &$reads ) {
				++$reads;
				return $value;
			};

			add_filter( 'pre_site_transient_update_plugins', $filter );
			$data = wp_get_translation_update_data();
			remove_filter( 'pre_site_transient_update_plugins', $filter );

			$this->assertCount( $row_count, $data );

			return $reads;
		};

		$this->assertSame(
			$count_reads( 1 ),
			$count_reads( 10 ),
			'Plugin update reads must not grow with the number of translation rows.'
		);
	}

	/**
	 * @ticket 42281
	 * @dataProvider data_non_plugin_translation_updates
	 *
	 * @param string|null $type Translation type, or null for no updates.
	 */
	public function test_wp_get_translation_update_data_does_not_load_plugin_names_without_plugin_translations( $type ) {
		$translations = array();
		if ( null !== $type ) {
			$translations[] = array(
				'type'     => $type,
				'slug'     => 'core' === $type ? 'default' : 'custom-internationalized-theme',
				'language' => 'de_DE',
				'version'  => '1.0.0',
			);
		}

		set_site_transient(
			'theme' === $type ? 'update_themes' : 'update_core',
			(object) array(
				'translations' => $translations,
			)
		);
		set_site_transient( 'update_plugins', (object) array( 'translations' => array() ) );

		$reads = 0;
		add_filter(
			'pre_site_transient_update_plugins',
			static function ( $value ) use ( &$reads ) {
				++$reads;
				return $value;
			}
		);

		wp_get_translation_updates();
		$baseline_reads = $reads;
		$reads          = 0;
		$data           = wp_get_translation_update_data();

		$this->assertCount( count( $translations ), $data );
		$this->assertSame( $baseline_reads, $reads, 'Display data must not perform a plugin-name lookup without plugin translations.' );
	}

	/**
	 * @return array[]
	 */
	public function data_non_plugin_translation_updates() {
		return array(
			'no updates' => array( null ),
			'core only'  => array( 'core' ),
			'theme only' => array( 'theme' ),
		);
	}

	/**
	 * @ticket 42281
	 */
	public function test_wp_process_translation_update_selection_uses_current_package_for_unchanged_id() {
		$update = (object) array(
			'type'     => 'plugin',
			'slug'     => 'example',
			'language' => 'de_DE',
			'version'  => '1.0.0',
			'package'  => 'https://example.org/old.zip',
		);
		$id     = wp_get_translation_update_id( $update );

		$update->package = 'https://example.org/current.zip';
		set_site_transient( 'update_plugins', (object) array( 'translations' => array( (array) $update ) ) );

		$this->assertEquals(
			array( $id => $update ),
			wp_process_translation_update_selection( array( $id ), array( $id ) )
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

	/**
	 * @ticket 42281
	 * @dataProvider data_translation_update_selections
	 *
	 * @param string[]       $form     Updates included in the form.
	 * @param string[]       $checked  Selected updates.
	 * @param string[]       $stored   Previously deferred updates.
	 * @param string[]|false $selected Expected selected updates, or false for an invalid form.
	 * @param string[]       $deferred Expected stored deferred updates.
	 */
	public function test_wp_process_translation_update_selection( $form, $checked, $stored, $selected, $deferred ) {
		$updates = array();
		foreach ( array( 'core', 'plugin', 'theme', 'new', 'stale' ) as $key ) {
			$updates[ $key ] = (object) array(
				'type'     => in_array( $key, array( 'core', 'theme' ), true ) ? $key : 'plugin',
				'slug'     => 'stale' === $key ? 'plugin' : $key,
				'language' => 'de_DE',
				'version'  => 'stale' === $key ? '0.9.0' : '1.0.0',
				'package'  => "https://example.org/$key.zip",
			);
		}

		foreach ( array( 'core', 'plugin', 'theme' ) as $type ) {
			$translations = array( (array) $updates[ $type ] );
			if ( 'plugin' === $type ) {
				$translations[] = (array) $updates['new'];
			}
			$transient = 'core' === $type ? 'update_core' : "update_{$type}s";
			set_site_transient( $transient, (object) array( 'translations' => $translations ) );
		}

		$get_updates = static function ( $keys ) use ( $updates ) {
			return array_intersect_key( $updates, array_flip( $keys ) );
		};
		$get_ids     = static function ( $keys ) use ( $updates ) {
			return array_map(
				static function ( $key ) use ( $updates ) {
					return isset( $updates[ $key ] ) ? wp_get_translation_update_id( $updates[ $key ] ) : $key;
				},
				$keys
			);
		};

		wp_set_deferred_translation_updates( $get_updates( $stored ) );
		$actual = wp_process_translation_update_selection( $get_ids( $form ), $get_ids( $checked ) );

		if ( false === $selected ) {
			$this->assertFalse( $actual );
		} else {
			$this->assertEquals( wp_get_translation_updates_by_id( $get_updates( $selected ) ), $actual );
		}

		$expected_deferred = array();
		foreach ( $get_updates( $deferred ) as $update ) {
			$expected_deferred[ wp_get_translation_update_id( $update ) ] = array(
				'type'     => $update->type,
				'slug'     => $update->slug,
				'language' => $update->language,
				'version'  => $update->version,
			);
		}

		$this->assertSame( $expected_deferred, get_site_option( 'deferred_translation_updates', array() ) );
		if ( empty( $expected_deferred ) ) {
			$this->assertFalse( get_site_option( 'deferred_translation_updates' ), 'The empty deferral option should be deleted.' );
		}
	}

	/**
	 * Provides selections, including stale forms and updates arriving after rendering.
	 *
	 * @return array[]
	 */
	public function data_translation_update_selections() {
		return array(
			'select all form updates'                    => array(
				array( 'core', 'plugin', 'theme' ),
				array( 'core', 'plugin', 'theme' ),
				array(),
				array( 'core', 'plugin', 'theme' ),
				array(),
			),
			'defer unchecked updates'                    => array(
				array( 'core', 'plugin', 'theme' ),
				array( 'plugin' ),
				array(),
				array( 'plugin' ),
				array( 'core', 'theme' ),
			),
			'all unchecked'                              => array(
				array( 'core', 'plugin', 'theme' ),
				array(),
				array(),
				array(),
				array( 'core', 'plugin', 'theme' ),
			),
			'reselect previously deferred update'        => array(
				array( 'plugin' ),
				array( 'plugin' ),
				array( 'plugin' ),
				array( 'plugin' ),
				array(),
			),
			'preserve deferral outside form'             => array(
				array( 'plugin' ),
				array( 'plugin' ),
				array( 'theme' ),
				array( 'plugin' ),
				array( 'theme' ),
			),
			'checked update outside form is ignored'     => array(
				array( 'plugin' ),
				array( 'plugin', 'theme', 'new' ),
				array( 'theme' ),
				array( 'plugin' ),
				array( 'theme' ),
			),
			'new updates outside form are not deferred'  => array(
				array( 'plugin' ),
				array(),
				array(),
				array(),
				array( 'plugin' ),
			),
			'stale selection does not select newer version' => array(
				array( 'core', 'stale' ),
				array( 'stale' ),
				array( 'stale' ),
				array(),
				array( 'core' ),
			),
			'prune stale deferrals on valid submission'  => array(
				array( 'core' ),
				array( 'core' ),
				array( 'stale', 'theme' ),
				array( 'core' ),
				array( 'theme' ),
			),
			'unknown identifiers are ignored'            => array(
				array( 'plugin', 'unknown' ),
				array( 'unknown' ),
				array(),
				array(),
				array( 'plugin' ),
			),
			'duplicate identifiers'                      => array(
				array( 'plugin', 'plugin' ),
				array( 'plugin', 'plugin' ),
				array(),
				array( 'plugin' ),
				array(),
			),
			'empty form preserves stored state'          => array(
				array(),
				array( 'plugin' ),
				array( 'stale', 'theme' ),
				false,
				array( 'theme', 'stale' ),
			),
			'entirely stale form preserves stored state' => array(
				array( 'stale' ),
				array( 'stale' ),
				array( 'stale', 'theme' ),
				false,
				array( 'theme', 'stale' ),
			),
			'unknown form preserves stored state'        => array(
				array( 'unknown' ),
				array( 'unknown' ),
				array( 'theme' ),
				false,
				array( 'theme' ),
			),
		);
	}
}
