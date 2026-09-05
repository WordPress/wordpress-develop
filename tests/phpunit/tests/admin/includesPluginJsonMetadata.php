<?php

/**
 * Tests for reading plugin metadata from plugin.json.
 *
 * @package WordPress
 * @subpackage Administration
 *
 * @group plugins
 *
 * @covers ::_get_plugin_json_data
 */
class Tests_Admin_IncludesPluginJsonMetadata extends WP_UnitTestCase {

	/**
	 * Registers an extra plugin header for testing.
	 *
	 * @param string[] $headers Existing extra headers.
	 * @return string[] Filtered extra headers.
	 */
	public function filter_extra_plugin_headers( $headers ) {
		$headers[] = 'Custom Header';
		return $headers;
	}

	/**
	 * Tests that plugin.json metadata is read correctly.
	 *
	 * @ticket 24152
	 */
	public function test_plugin_json_metadata_is_read() {
		$plugin_file = DIR_TESTDATA . '/plugins/json-metadata-plugin/json-metadata-plugin.php';
		$plugin_data = _get_plugin_json_data( $plugin_file );

		$this->assertIsArray( $plugin_data );
		$this->assertSame( 'JSON Metadata Plugin', $plugin_data['Name'] );
		$this->assertSame( 'https://example.com/json-metadata-plugin', $plugin_data['PluginURI'] );
		$this->assertSame( 'A plugin using JSON metadata', $plugin_data['Description'] );
		$this->assertSame( 'Plugin Author', $plugin_data['Author'] );
		$this->assertSame( 'https://example.com/author', $plugin_data['AuthorURI'] );
		$this->assertSame( '1.0.0', $plugin_data['Version'] );
		$this->assertSame( 'json-metadata-plugin', $plugin_data['TextDomain'] );
		$this->assertSame( '6.0', $plugin_data['RequiresWP'] );
		$this->assertSame( '8.0', $plugin_data['RequiresPHP'] );
	}

	/**
	 * Tests that plugin dependencies are parsed from JSON array.
	 *
	 * Creates a temporary plugin inside WP_PLUGIN_DIR to exercise the real
	 * _get_plugin_json_data() code path, then cleans up immediately.
	 *
	 * @ticket 24152
	 */
	public function test_plugin_json_requires_plugins_is_comma_separated() {
		$plugin_dir  = WP_PLUGIN_DIR . '/json-deps-test-' . uniqid();
		$dir_name    = basename( $plugin_dir );
		$plugin_file = $plugin_dir . '/' . $dir_name . '.php';

		mkdir( $plugin_dir );
		file_put_contents(
			$plugin_dir . '/plugin.json',
			wp_json_encode(
				array(
					'name'     => 'Deps Test Plugin',
					'version'  => '1.0.0',
					'requires' => array(
						'plugins' => array( 'woocommerce', 'jetpack' ),
					),
				)
			)
		);
		file_put_contents( $plugin_file, '<?php // test' );

		$plugin_data = _get_plugin_json_data( $plugin_file );

		// Clean up before any other code can discover this fixture.
		unlink( $plugin_dir . '/plugin.json' );
		unlink( $plugin_file );
		rmdir( $plugin_dir );
		wp_clean_plugins_cache();

		$this->assertIsArray( $plugin_data );
		$this->assertSame( 'woocommerce, jetpack', $plugin_data['RequiresPlugins'] );
	}

	/**
	 * Tests that plugin.json takes priority over PHP file headers.
	 *
	 * @ticket 24152
	 */
	public function test_plugin_json_takes_priority_over_file_headers() {
		$plugin_file = DIR_TESTDATA . '/plugins/json-metadata-plugin/json-metadata-plugin.php';
		$plugin_data = get_plugin_data( $plugin_file, false, false );

		$this->assertSame( 'JSON Metadata Plugin', $plugin_data['Name'] );
		$this->assertSame( '1.0.0', $plugin_data['Version'] );
	}

	/**
	 * Tests that single-file plugins in the root directory return false.
	 *
	 * @ticket 24152
	 */
	public function test_single_file_plugin_returns_false() {
		$plugin_file = WP_PLUGIN_DIR . '/hello.php';
		$plugin_data = _get_plugin_json_data( $plugin_file );

		$this->assertFalse( $plugin_data );
	}

	/**
	 * Tests fallback to PHP file headers when no plugin.json exists.
	 *
	 * @ticket 24152
	 */
	public function test_falls_back_to_file_headers_without_plugin_json() {
		$plugin_file = DIR_TESTDATA . '/plugins/hello.php';
		$plugin_data = _get_plugin_json_data( $plugin_file );

		$this->assertFalse( $plugin_data );
	}

	/**
	 * Tests that non-main PHP files in a directory with plugin.json are not affected.
	 *
	 * Only the main plugin file (matching directory name or mainFile property)
	 * should receive JSON metadata.
	 *
	 * @ticket 24152
	 */
	public function test_non_main_php_file_returns_false() {
		$plugin_file = DIR_TESTDATA . '/plugins/json-metadata-plugin/helper.php';
		$plugin_data = _get_plugin_json_data( $plugin_file );

		$this->assertFalse( $plugin_data );
	}

	/**
	 * Tests that malformed plugin.json falls back to PHP file headers.
	 *
	 * @ticket 24152
	 */
	public function test_malformed_plugin_json_falls_back_to_file_headers() {
		$plugin_file = DIR_TESTDATA . '/plugins/json-malformed-plugin/json-malformed-plugin.php';

		$plugin_data = get_plugin_data( $plugin_file, false, false );

		$this->assertSame( 'Malformed JSON Fallback Plugin', $plugin_data['Name'] );
		$this->assertSame( '3.0.0', $plugin_data['Version'] );
	}

	/**
	 * Tests that the mainFile property in plugin.json designates the main plugin file.
	 *
	 * @ticket 24152
	 */
	public function test_main_file_property_designates_main_plugin_file() {
		$main_file  = DIR_TESTDATA . '/plugins/json-mainfile-plugin/bootstrap.php';
		$other_file = DIR_TESTDATA . '/plugins/json-mainfile-plugin/json-mainfile-plugin.php';

		$main_data  = _get_plugin_json_data( $main_file );
		$other_data = _get_plugin_json_data( $other_file );

		$this->assertIsArray( $main_data );
		$this->assertSame( 'Main File Plugin', $main_data['Name'] );
		$this->assertFalse( $other_data );
	}

	/**
	 * Tests that the network flag only accepts a strict boolean true.
	 *
	 * @ticket 24152
	 */
	public function test_plugin_json_network_requires_strict_boolean_true() {
		$plugin_dir = WP_PLUGIN_DIR . '/json-network-test-' . uniqid();
		$dir_name   = basename( $plugin_dir );

		mkdir( $plugin_dir );

		file_put_contents(
			$plugin_dir . '/plugin.json',
			wp_json_encode(
				array(
					'name'    => 'Network True Plugin',
					'version' => '1.0.0',
					'network' => true,
				)
			)
		);
		file_put_contents( $plugin_dir . '/' . $dir_name . '.php', '<?php // test' );
		$true_data = _get_plugin_json_data( $plugin_dir . '/' . $dir_name . '.php' );

		file_put_contents(
			$plugin_dir . '/plugin.json',
			wp_json_encode(
				array(
					'name'    => 'Network String False Plugin',
					'version' => '1.0.0',
					'network' => 'false',
				)
			)
		);
		$string_false_data = _get_plugin_json_data( $plugin_dir . '/' . $dir_name . '.php' );

		file_put_contents(
			$plugin_dir . '/plugin.json',
			wp_json_encode(
				array(
					'name'    => 'Network Int One Plugin',
					'version' => '1.0.0',
					'network' => 1,
				)
			)
		);
		$int_one_data = _get_plugin_json_data( $plugin_dir . '/' . $dir_name . '.php' );

		unlink( $plugin_dir . '/plugin.json' );
		unlink( $plugin_dir . '/' . $dir_name . '.php' );
		rmdir( $plugin_dir );
		wp_clean_plugins_cache();

		$this->assertSame( 'true', $true_data['Network'] );
		$this->assertSame( '', $string_false_data['Network'] );
		$this->assertSame( '', $int_one_data['Network'] );
	}

	/**
	 * Tests that custom plugin headers registered via extra_plugin_headers are read from plugin.json.
	 *
	 * @ticket 24152
	 */
	public function test_plugin_json_supports_extra_plugin_headers() {
		$plugin_dir  = WP_PLUGIN_DIR . '/json-custom-header-test-' . uniqid();
		$dir_name    = basename( $plugin_dir );
		$plugin_file = $plugin_dir . '/' . $dir_name . '.php';

		add_filter( 'extra_plugin_headers', array( $this, 'filter_extra_plugin_headers' ) );
		mkdir( $plugin_dir );
		try {
			file_put_contents(
				$plugin_dir . '/plugin.json',
				wp_json_encode(
					array(
						'name'          => 'Custom Header Plugin',
						'version'       => '1.0.0',
						'Custom Header' => 'Custom Header Value',
					)
				)
			);
			file_put_contents( $plugin_file, '<?php // test' );

			$plugin_data = get_plugin_data( $plugin_file, false, false );
			$this->assertSame( 'Custom Header Value', $plugin_data['Custom Header'] );
		} finally {
			remove_filter( 'extra_plugin_headers', array( $this, 'filter_extra_plugin_headers' ) );
			if ( file_exists( $plugin_dir . '/plugin.json' ) ) {
				unlink( $plugin_dir . '/plugin.json' );
			}
			if ( file_exists( $plugin_file ) ) {
				unlink( $plugin_file );
			}
			if ( is_dir( $plugin_dir ) ) {
				rmdir( $plugin_dir );
			}
			wp_clean_plugins_cache();
		}
	}

	/**
	 * Tests that plugin.json does not affect MU-plugins.
	 *
	 * @ticket 24152
	 */
	public function test_plugin_json_does_not_affect_mu_plugins() {
		// A file outside WP_PLUGIN_DIR should not be affected by plugin.json.
		$mu_plugin_file = WPMU_PLUGIN_DIR . '/some-mu-plugin.php';
		$plugin_data    = _get_plugin_json_data( $mu_plugin_file );

		$this->assertFalse( $plugin_data );
	}

	/**
	 * Tests that plugin.json does not affect files in MU-plugin subdirectories.
	 *
	 * @ticket 24152
	 */
	public function test_plugin_json_does_not_affect_mu_plugin_subdirectories() {
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR );
		}

		$mu_subdir = WPMU_PLUGIN_DIR . '/json-mu-subdir-test-' . uniqid();
		$dir_name  = basename( $mu_subdir );
		$mu_file   = $mu_subdir . '/' . $dir_name . '.php';

		mkdir( $mu_subdir );
		file_put_contents(
			$mu_subdir . '/plugin.json',
			wp_json_encode(
				array(
					'name'    => 'MU Subdir JSON Plugin',
					'version' => '1.0.0',
				)
			)
		);
		file_put_contents( $mu_file, '<?php // test' );

		$plugin_data = _get_plugin_json_data( $mu_file );

		unlink( $mu_subdir . '/plugin.json' );
		unlink( $mu_file );
		rmdir( $mu_subdir );
		wp_clean_plugins_cache();

		$this->assertFalse( $plugin_data );
	}
}
