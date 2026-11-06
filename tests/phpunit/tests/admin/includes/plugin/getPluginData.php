<?php

/**
 * Tests for get_plugin_data().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::get_plugin_data
 */
class Tests_Admin_Includes_Plugin_GetPluginData extends WP_UnitTestCase {

	/**
	 * The slug of the test plugin.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Prepares a unique test plugin.
	 */
	public function set_up() {
		parent::set_up();

		$this->slug = 'get-plugin-data-test-' . wp_generate_password( 8, false );
	}

	/**
	 * Removes the test plugin.
	 */
	public function tear_down() {
		$dir = WP_PLUGIN_DIR . '/' . $this->slug;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}

		parent::tear_down();
	}

	/**
	 * Tests that the headers of a plugin are read.
	 *
	 * @ticket 66556
	 */
	public function test_should_read_the_headers_of_a_plugin() {
		$data = get_plugin_data(
			$this->create_plugin(
				" * Plugin Name: My Plugin\n"
				. " * Plugin URI: https://example.org/my-plugin\n"
				. " * Version: 2.3.4\n"
				. " * Description: Does <b>things</b>.\n"
				. " * Author: Jane Doe\n"
				. " * Author URI: https://example.org/jane\n"
				. " * Text Domain: my-text-domain\n"
				. " * Domain Path: /languages\n"
				. " * Network: true\n"
				. " * Requires at least: 6.0\n"
				. " * Requires PHP: 7.4\n"
				. " * Update URI: https://example.org/updates\n"
				. " * Requires Plugins: akismet, hello-dolly\n"
			),
			false,
			false
		);

		$this->assertSame(
			array(
				'Name'            => 'My Plugin',
				'PluginURI'       => 'https://example.org/my-plugin',
				'Version'         => '2.3.4',
				'Description'     => 'Does <b>things</b>.',
				'Author'          => 'Jane Doe',
				'AuthorURI'       => 'https://example.org/jane',
				'TextDomain'      => 'my-text-domain',
				'DomainPath'      => '/languages',
				'Network'         => true,
				'RequiresWP'      => '6.0',
				'RequiresPHP'     => '7.4',
				'UpdateURI'       => 'https://example.org/updates',
				'RequiresPlugins' => 'akismet, hello-dolly',
				'Title'           => 'My Plugin',
				'AuthorName'      => 'Jane Doe',
			),
			$data
		);
	}

	/**
	 * Tests that a plugin without headers other than the name has empty values.
	 *
	 * @ticket 66556
	 */
	public function test_should_return_empty_values_for_the_headers_that_are_missing() {
		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: Bare Plugin\n" ), false, false );

		$this->assertSame( 'Bare Plugin', $data['Name'] );
		$this->assertFalse( $data['Network'], 'The plugin should not be for the network only.' );

		foreach ( array( 'PluginURI', 'Version', 'Description', 'Author', 'AuthorURI', 'DomainPath', 'RequiresWP', 'RequiresPHP', 'UpdateURI', 'RequiresPlugins' ) as $header ) {
			$this->assertSame( '', $data[ $header ], "The $header header should be empty." );
		}
	}

	/**
	 * Tests that the name of the plugin folder is the text domain when the plugin has none.
	 *
	 * @ticket 66556
	 */
	public function test_should_use_the_folder_as_the_text_domain_when_it_is_missing() {
		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: Bare Plugin\n" ), false, false );

		$this->assertSame( $this->slug, $data['TextDomain'] );
	}

	/**
	 * Tests that the text domain of the plugin is kept.
	 *
	 * @ticket 66556
	 */
	public function test_should_keep_the_text_domain_of_the_plugin() {
		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: Bare Plugin\n * Text Domain: other-domain\n" ), false, false );

		$this->assertSame( 'other-domain', $data['TextDomain'] );
	}

	/**
	 * Tests that a plugin file in the plugins directory has no text domain when it has none.
	 *
	 * @ticket 66556
	 */
	public function test_should_not_use_the_plugins_directory_as_the_text_domain() {
		$file = WP_PLUGIN_DIR . '/' . $this->slug . '.php';

		$this->assertNotFalse( file_put_contents( $file, "<?php\n/**\n * Plugin Name: Single File Plugin\n */\n" ), 'The test plugin should be written.' );

		try {
			$data = get_plugin_data( $file, false, false );
		} finally {
			unlink( $file );
		}

		$this->assertSame( '', $data['TextDomain'] );
	}

	/**
	 * Tests the value of the Network header.
	 *
	 * @ticket 66556
	 *
	 * @dataProvider data_network_header
	 *
	 * @param string $header   The header line.
	 * @param bool   $expected The expected value.
	 */
	public function test_should_read_the_network_header_as_a_boolean( $header, $expected ) {
		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: Network Plugin\n$header" ), false, false );

		$this->assertSame( $expected, $data['Network'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_network_header() {
		return array(
			'true'           => array( " * Network: true\n", true ),
			'TRUE'           => array( " * Network: TRUE\n", true ),
			'false'          => array( " * Network: false\n", false ),
			'something else' => array( " * Network: yes\n", false ),
			'no header'      => array( '', false ),
		);
	}

	/**
	 * Tests that the deprecated header for a plugin for the network is read, and reported.
	 *
	 * @ticket 66556
	 */
	public function test_should_read_the_deprecated_site_wide_only_header() {
		$this->setExpectedDeprecated( 'get_plugin_data' );

		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: Old Plugin\n * Site Wide Only: true\n" ), false, false );

		$this->assertTrue( $data['Network'], 'The plugin should be for the network only.' );
		$this->assertArrayNotHasKey( '_sitewide', $data, 'The deprecated header should be removed.' );
	}

	/**
	 * Tests that the network header is preferred to the deprecated header.
	 *
	 * @ticket 66556
	 */
	public function test_should_prefer_the_network_header_to_the_deprecated_header() {
		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: Old Plugin\n * Network: false\n * Site Wide Only: true\n" ), false, false );

		$this->assertFalse( $data['Network'] );
	}

	/**
	 * Tests that the plugin data is not changed without markup and translation.
	 *
	 * @ticket 66556
	 */
	public function test_should_not_add_markup_without_markup_and_translation() {
		$data = get_plugin_data(
			$this->create_plugin( " * Plugin Name: My Plugin\n * Plugin URI: https://example.org/\n * Author: Jane Doe\n * Author URI: https://example.org/jane\n * Description: Does <b>things</b>.\n" ),
			false,
			false
		);

		$this->assertSame( 'My Plugin', $data['Title'], 'The title should be the name.' );
		$this->assertSame( 'Jane Doe', $data['AuthorName'], 'The author name should be the author.' );
		$this->assertSame( 'Does <b>things</b>.', $data['Description'], 'The description should not be changed.' );
		$this->assertSame( 'Jane Doe', $data['Author'], 'The author should not be linked.' );
	}

	/**
	 * Tests that markup is added by default.
	 *
	 * @ticket 66556
	 */
	public function test_should_add_markup_by_default() {
		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: My Plugin\n * Plugin URI: https://example.org/\n * Author: Jane Doe\n * Author URI: https://example.org/jane\n" ) );

		$this->assertSame( '<a href="https://example.org/">My Plugin</a>', $data['Title'], 'The title should link to the plugin.' );
		$this->assertStringContainsString( '<a href="https://example.org/jane">Jane Doe</a>', $data['Author'], 'The author should be linked.' );
		$this->assertSame( 'Jane Doe', $data['AuthorName'], 'The author name should not be linked.' );
	}

	/**
	 * Tests that markup is added without translation.
	 *
	 * @ticket 66556
	 */
	public function test_should_add_markup_without_translation() {
		$data = get_plugin_data( $this->create_plugin( " * Plugin Name: My Plugin\n * Plugin URI: https://example.org/\n" ), true, false );

		$this->assertSame( '<a href="https://example.org/">My Plugin</a>', $data['Title'] );
	}

	/**
	 * Creates the test plugin.
	 *
	 * @param string $headers The headers of the plugin.
	 * @return string The path of the plugin file.
	 */
	private function create_plugin( $headers ) {
		$dir  = WP_PLUGIN_DIR . '/' . $this->slug;
		$file = "{$dir}/{$this->slug}.php";

		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse( file_put_contents( $file, "<?php\n/**\n{$headers} */\n" ), 'The test plugin should be written.' );

		return $file;
	}
}
