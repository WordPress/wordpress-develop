<?php

/**
 * Tests for validate_plugin_requirements().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::validate_plugin_requirements
 */
class Tests_Admin_Includes_Plugin_ValidatePluginRequirements extends WP_UnitTestCase {

	/**
	 * List of created test plugin files to delete on tear_down.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	/**
	 * List of created test plugin directories to delete on tear_down.
	 *
	 * @var string[]
	 */
	private $created_dirs = array();

	/**
	 * Sets up the environment before each test.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Cleans up the environment after each test.
	 */
	public function tear_down() {
		foreach ( $this->created_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		foreach ( array_reverse( $this->created_dirs ) as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}

		$this->created_files = array();
		$this->created_dirs  = array();

		wp_cache_delete( 'plugins', 'plugins' );
		$this->reset_plugin_dependencies();

		parent::tear_down();
	}

	/**
	 * Resets WP_Plugin_Dependencies internal static properties.
	 */
	private function reset_plugin_dependencies() {
		if ( ! class_exists( 'WP_Plugin_Dependencies' ) ) {
			return;
		}

		$ref   = new ReflectionClass( 'WP_Plugin_Dependencies' );
		$props = array(
			'plugins'                     => null,
			'plugin_dirnames'             => null,
			'dependencies'                => null,
			'dependency_slugs'            => null,
			'dependent_slugs'             => null,
			'dependency_api_data'         => null,
			'dependency_filepaths'        => null,
			'circular_dependencies_pairs' => null,
			'circular_dependencies_slugs' => null,
			'initialized'                 => false,
		);

		foreach ( $props as $prop => $val ) {
			if ( $ref->hasProperty( $prop ) ) {
				$property = $ref->getProperty( $prop );
				if ( PHP_VERSION_ID < 80100 ) {
					$property->setAccessible( true );
				}
				$property->setValue( null, $val );
			}
		}
	}

	/**
	 * Helper to create a temporary test plugin file with specified headers.
	 *
	 * @param array  $headers         Key-value pairs of header name to value.
	 * @param string $plugin_filename Relative plugin file path within WP_PLUGIN_DIR.
	 * @return string Relative plugin filename.
	 */
	private function create_test_plugin( array $headers, $plugin_filename ) {
		$full_path = WP_PLUGIN_DIR . '/' . $plugin_filename;
		$dir       = dirname( $full_path );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$this->created_dirs[] = $dir;
		}

		$header_content = "<?php\n/**\n";
		foreach ( $headers as $header => $value ) {
			$header_content .= " * {$header}: {$value}\n";
		}
		$header_content .= " */\n";

		file_put_contents( $full_path, $header_content );
		$this->created_files[] = $full_path;

		wp_cache_delete( 'plugins', 'plugins' );

		return $plugin_filename;
	}

	/**
	 * Tests that validate_plugin_requirements() returns true when requirements are met.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_success() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'       => 'Valid Requirements Plugin',
				'Requires at least' => '5.0',
				'Requires PHP'      => '7.0',
			),
			'valid-plugin/valid-plugin.php'
		);

		$result = validate_plugin_requirements( $plugin );

		$this->assertTrue( $result, 'validate_plugin_requirements() should return true when requirements are met.' );
	}

	/**
	 * Tests that validate_plugin_requirements() returns true when no requirements headers are present.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_without_requirements_headers() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name' => 'No Requirements Plugin',
			),
			'no-req-plugin/no-req-plugin.php'
		);

		$result = validate_plugin_requirements( $plugin );

		$this->assertTrue( $result, 'validate_plugin_requirements() should return true when requirements headers are omitted.' );
	}

	/**
	 * Tests that validate_plugin_requirements() returns WP_Error when WordPress version is incompatible.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_incompatible_wp_version() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'       => 'Future WP Plugin',
				'Requires at least' => '999.0.0',
				'Requires PHP'      => '7.0',
			),
			'future-wp/future-wp.php'
		);

		$result = validate_plugin_requirements( $plugin );

		$this->assertWPError( $result, 'Expected a WP_Error for incompatible WordPress version.' );
		$this->assertSame( 'plugin_wp_incompatible', $result->get_error_code() );
		$this->assertStringContainsString( 'Future WP Plugin', $result->get_error_message() );
		$this->assertStringContainsString( '999.0.0', $result->get_error_message() );
	}

	/**
	 * Tests that validate_plugin_requirements() returns WP_Error when PHP version is incompatible.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_incompatible_php_version() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'       => 'Future PHP Plugin',
				'Requires at least' => '5.0',
				'Requires PHP'      => '999.0.0',
			),
			'future-php/future-php.php'
		);

		$result = validate_plugin_requirements( $plugin );

		$this->assertWPError( $result, 'Expected a WP_Error for incompatible PHP version.' );
		$this->assertSame( 'plugin_php_incompatible', $result->get_error_code() );
		$this->assertStringContainsString( 'Future PHP Plugin', $result->get_error_message() );
		$this->assertStringContainsString( '999.0.0', $result->get_error_message() );
		$this->assertStringContainsString( wp_get_update_php_url(), $result->get_error_message() );
	}

	/**
	 * Tests that validate_plugin_requirements() returns WP_Error when both WordPress and PHP versions are incompatible.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_incompatible_wp_and_php_version() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'       => 'Incompatible Both Plugin',
				'Requires at least' => '999.0.0',
				'Requires PHP'      => '999.0.0',
			),
			'incompatible-both/incompatible-both.php'
		);

		$result = validate_plugin_requirements( $plugin );

		$this->assertWPError( $result, 'Expected a WP_Error when both WP and PHP are incompatible.' );
		$this->assertSame( 'plugin_wp_php_incompatible', $result->get_error_code() );
		$this->assertStringContainsString( 'Incompatible Both Plugin', $result->get_error_message() );
		$this->assertStringContainsString( 'WordPress 999.0.0 and PHP 999.0.0', $result->get_error_message() );
	}

	/**
	 * Tests that the update PHP annotation is included when available.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_includes_update_php_annotation() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'  => 'Annotation Test Plugin',
				'Requires PHP' => '999.0.0',
			),
			'annotation-test/annotation-test.php'
		);

		$custom_url = 'https://custom-host.example.com/update-php';
		add_filter(
			'wp_update_php_url',
			static function () use ( $custom_url ) {
				return $custom_url;
			}
		);

		$result = validate_plugin_requirements( $plugin );

		$this->assertWPError( $result );
		$this->assertSame( 'plugin_php_incompatible', $result->get_error_code() );
		$this->assertStringContainsString( $custom_url, $result->get_error_message() );
		$this->assertStringContainsString( '<em>' . wp_get_update_php_annotation() . '</em>', $result->get_error_message() );
	}

	/**
	 * Tests that validate_plugin_requirements() returns WP_Error when a required dependency is missing.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_with_missing_dependency() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'      => 'Dependent Plugin',
				'Requires Plugins' => 'nonexistent-dependency',
			),
			'dependent-plugin/dependent-plugin.php'
		);

		$this->reset_plugin_dependencies();

		$result = validate_plugin_requirements( $plugin );

		$this->assertWPError( $result, 'Expected a WP_Error when dependency is missing.' );
		$this->assertSame( 'plugin_missing_dependencies', $result->get_error_code() );
		$error_data = $result->get_error_data();
		$this->assertIsArray( $error_data );
		$this->assertArrayHasKey( 'not_installed', $error_data );
		$this->assertArrayHasKey( 'nonexistent-dependency', $error_data['not_installed'] );
	}

	/**
	 * Tests that validate_plugin_requirements() returns WP_Error when a required dependency is installed but inactive.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_with_inactive_dependency() {
		$this->create_test_plugin(
			array(
				'Plugin Name' => 'Required Inactive Addon',
			),
			'required-addon/required-addon.php'
		);

		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'      => 'Main Dependent Plugin',
				'Requires Plugins' => 'required-addon',
			),
			'main-plugin/main-plugin.php'
		);

		$this->reset_plugin_dependencies();

		$result = validate_plugin_requirements( $plugin );

		$this->assertWPError( $result, 'Expected a WP_Error when required dependency is inactive.' );
		$this->assertSame( 'plugin_missing_dependencies', $result->get_error_code() );
		$error_data = $result->get_error_data();
		$this->assertIsArray( $error_data );
		$this->assertArrayHasKey( 'inactive', $error_data );
		$this->assertArrayHasKey( 'required-addon', $error_data['inactive'] );
	}

	/**
	 * Tests that validate_plugin_requirements() succeeds when required dependency is installed and active.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_with_active_dependency() {
		$dependency = $this->create_test_plugin(
			array(
				'Plugin Name' => 'Required Active Addon',
			),
			'active-addon/active-addon.php'
		);

		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'      => 'Active Dependent Plugin',
				'Requires Plugins' => 'active-addon',
			),
			'active-dependent/active-dependent.php'
		);

		update_option( 'active_plugins', array( $dependency ) );
		$this->reset_plugin_dependencies();

		$result = validate_plugin_requirements( $plugin );

		$this->assertTrue( $result, 'Expected true when all required dependencies are active.' );
	}

	/**
	 * Tests that the validate_plugin_requirements filter can modify the validation response.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_filter_modifies_response() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name' => 'Filtered Requirements Plugin',
			),
			'filtered-plugin/filtered-plugin.php'
		);

		$filter_callback = static function ( $met_requirements, $plugin_file ) use ( $plugin ) {
			if ( $plugin === $plugin_file ) {
				return new WP_Error( 'custom_validation_failed', 'Custom requirements check failed.' );
			}
			return $met_requirements;
		};

		add_filter( 'validate_plugin_requirements', $filter_callback, 10, 2 );
		$result = validate_plugin_requirements( $plugin );
		remove_filter( 'validate_plugin_requirements', $filter_callback, 10 );

		$this->assertWPError( $result );
		$this->assertSame( 'custom_validation_failed', $result->get_error_code() );
		$this->assertSame( 'Custom requirements check failed.', $result->get_error_message() );
	}

	/**
	 * Tests that the validate_plugin_requirements filter does not run when Core validation fails.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_requirements_filter_not_called_when_core_validation_fails() {
		$plugin = $this->create_test_plugin(
			array(
				'Plugin Name'  => 'Incompatible Filter Test',
				'Requires PHP' => '999.0.0',
			),
			'incompatible-filter/incompatible-filter.php'
		);

		$filter_called   = false;
		$filter_callback = static function ( $met_requirements ) use ( &$filter_called ) {
			$filter_called = true;
			return $met_requirements;
		};

		add_filter( 'validate_plugin_requirements', $filter_callback );
		$result = validate_plugin_requirements( $plugin );
		remove_filter( 'validate_plugin_requirements', $filter_callback );

		$this->assertWPError( $result );
		$this->assertSame( 'plugin_php_incompatible', $result->get_error_code() );
		$this->assertFalse( $filter_called, 'validate_plugin_requirements filter should not fire when Core requirements fail.' );
	}
}
