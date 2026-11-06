<?php

/**
 * Tests for is_plugin_active(), is_plugin_inactive(), is_plugin_active_for_network() and is_network_only_plugin().
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_Includes_Plugin_IsPluginActive extends WP_UnitTestCase {

	/**
	 * The slug of the test plugin.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Resets the active plugins and prepares a unique test plugin.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'active_plugins', array( 'active/active.php' ) );
		update_site_option( 'active_sitewide_plugins', array() );

		$this->slug = 'is-active-test-plugin-' . wp_generate_password( 8, false );
	}

	/**
	 * Removes the test plugin.
	 */
	public function tear_down() {
		$this->remove_plugin();
		wp_cache_delete( 'plugins', 'plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that a plugin listed in the active plugins is active.
	 *
	 * @ticket 66555
	 *
	 * @covers ::is_plugin_active
	 */
	public function test_is_plugin_active_should_be_true_for_a_listed_plugin() {
		$this->assertTrue( is_plugin_active( 'active/active.php' ) );
	}

	/**
	 * Tests the plugins that are not active.
	 *
	 * @ticket 66555
	 *
	 * @covers ::is_plugin_active
	 * @covers ::is_plugin_inactive
	 *
	 * @dataProvider data_plugins_that_are_not_active
	 *
	 * @param string|bool $plugin The plugin file.
	 */
	public function test_should_report_a_plugin_that_is_not_listed_as_inactive( $plugin ) {
		$this->assertFalse( is_plugin_active( $plugin ), 'The plugin should not be active.' );
		$this->assertTrue( is_plugin_inactive( $plugin ), 'The plugin should be inactive.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugins_that_are_not_active() {
		return array(
			'another plugin'             => array( 'other/other.php' ),
			'a plugin that is not there' => array( 'missing/missing.php' ),
			'the folder of a plugin'     => array( 'active' ),
			'a different case'           => array( 'ACTIVE/active.php' ),
			'an empty string'            => array( '' ),
			'true'                       => array( true ),
		);
	}

	/**
	 * Tests that is_plugin_inactive() is the opposite of is_plugin_active() for a listed plugin.
	 *
	 * @ticket 66555
	 *
	 * @covers ::is_plugin_inactive
	 */
	public function test_is_plugin_inactive_should_be_false_for_a_listed_plugin() {
		$this->assertFalse( is_plugin_inactive( 'active/active.php' ) );
	}

	/**
	 * Tests that a plugin that is active for the network is active.
	 *
	 * @ticket 66555
	 *
	 * @group ms-required
	 *
	 * @covers ::is_plugin_active
	 * @covers ::is_plugin_inactive
	 */
	public function test_a_network_active_plugin_should_be_active() {
		update_site_option( 'active_sitewide_plugins', array( 'network/network.php' => time() ) );

		$this->assertTrue( is_plugin_active( 'network/network.php' ), 'The plugin should be active.' );
		$this->assertFalse( is_plugin_inactive( 'network/network.php' ), 'The plugin should not be inactive.' );
	}

	/**
	 * Tests that no plugin is active for the network on a single site.
	 *
	 * @ticket 66555
	 *
	 * @group ms-excluded
	 *
	 * @covers ::is_plugin_active_for_network
	 */
	public function test_is_plugin_active_for_network_should_be_false_on_a_single_site() {
		update_site_option( 'active_sitewide_plugins', array( 'network/network.php' => time() ) );

		$this->assertFalse( is_plugin_active_for_network( 'network/network.php' ) );
	}

	/**
	 * Tests that a plugin that is listed as active for the network is active for the network.
	 *
	 * @ticket 66555
	 *
	 * @group ms-required
	 *
	 * @covers ::is_plugin_active_for_network
	 */
	public function test_is_plugin_active_for_network_should_be_true_for_a_listed_plugin() {
		update_site_option( 'active_sitewide_plugins', array( 'network/network.php' => time() ) );

		$this->assertTrue( is_plugin_active_for_network( 'network/network.php' ) );
	}

	/**
	 * Tests that a plugin that is not listed as active for the network is not active for it.
	 *
	 * @ticket 66555
	 *
	 * @group ms-required
	 *
	 * @covers ::is_plugin_active_for_network
	 *
	 * @dataProvider data_plugins_that_are_not_network_active
	 *
	 * @param string $plugin The plugin file.
	 */
	public function test_is_plugin_active_for_network_should_be_false_for_a_plugin_that_is_not_listed( $plugin ) {
		update_site_option( 'active_sitewide_plugins', array( 'network/network.php' => time() ) );

		$this->assertFalse( is_plugin_active_for_network( $plugin ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugins_that_are_not_network_active() {
		return array(
			'another plugin'                    => array( 'other/other.php' ),
			'a plugin that is active on a site' => array( 'active/active.php' ),
			'an empty string'                   => array( '' ),
		);
	}

	/**
	 * Tests that a plugin with the Network header set to true is a network only plugin.
	 *
	 * @ticket 66555
	 *
	 * @covers ::is_network_only_plugin
	 *
	 * @dataProvider data_network_header
	 *
	 * @param string $header   The Network header of the plugin, or an empty string.
	 * @param bool   $expected Whether the plugin is for the network only.
	 */
	public function test_is_network_only_plugin_should_read_the_network_header( $header, $expected ) {
		$this->create_plugin( $header );

		$this->assertSame( $expected, is_network_only_plugin( $this->get_file() ) );
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
	 * Tests that the plugin file is normalized before it is read.
	 *
	 * @ticket 66555
	 *
	 * @covers ::is_network_only_plugin
	 */
	public function test_is_network_only_plugin_should_accept_a_full_path_with_whitespace() {
		$this->create_plugin( " * Network: true\n" );

		$this->assertTrue( is_network_only_plugin( '  ' . WP_PLUGIN_DIR . '/' . $this->get_file() . ' ' ) );
	}

	/**
	 * Returns the plugin file of the test plugin.
	 *
	 * @return string The plugin file.
	 */
	private function get_file() {
		return "{$this->slug}/{$this->slug}.php";
	}

	/**
	 * Creates the test plugin.
	 *
	 * @param string $headers Additional plugin headers.
	 */
	private function create_plugin( $headers = '' ) {
		$dir = WP_PLUGIN_DIR . '/' . $this->slug;

		$this->remove_plugin();
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse(
			file_put_contents( "{$dir}/{$this->slug}.php", "<?php\n/**\n * Plugin Name: Is Active Test Plugin\n{$headers} */\n" ),
			'The test plugin should be written.'
		);

		wp_cache_delete( 'plugins', 'plugins' );
	}

	/**
	 * Removes the test plugin, including one left over by an interrupted run.
	 */
	private function remove_plugin() {
		$dir = WP_PLUGIN_DIR . '/' . $this->slug;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}
	}
}
