<?php
/**
 * Unit tests covering the failures of WP_REST_Plugins_Controller.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_REST_Plugins_Controller_Failures_Test extends WP_Test_REST_TestCase {

	const BASE = '/wp/v2/plugins';

	/**
	 * Administrator user ID, a super admin on multisite.
	 *
	 * @var int
	 */
	private static $admin;

	/**
	 * Creates the administrator.
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin );
		}
	}

	/**
	 * Cleans up the fixtures.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin );
	}

	/**
	 * Removes the plugin that the tests install.
	 */
	public function tear_down() {
		$dir = WP_PLUGIN_DIR . '/rest-failures-plugin';

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}

		parent::tear_down();
	}

	/**
	 * Makes the Plugin Installation API return a plugin with a package.
	 */
	private function mock_plugin_information() {
		add_filter(
			'plugins_api',
			static function ( $result, $action ) {
				if ( 'plugin_information' !== $action ) {
					return $result;
				}

				return (object) array(
					'name'           => 'Failing Plugin',
					'slug'           => 'rest-failures-plugin',
					'download_link'  => 'https://downloads.example.org/rest-failures-plugin.zip',
					'language_packs' => array(),
				);
			},
			10,
			2
		);
	}

	/**
	 * Makes the filesystem unavailable.
	 */
	private function make_the_filesystem_unavailable() {
		add_filter(
			'filesystem_method',
			static function () {
				return 'ftpext';
			}
		);
		add_filter( 'request_filesystem_credentials', '__return_false' );
	}

	/**
	 * Creates the plugin that the tests delete.
	 *
	 * @return string The plugin, as the route has it.
	 */
	private function create_plugin() {
		$dir = WP_PLUGIN_DIR . '/rest-failures-plugin';

		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse(
			file_put_contents( $dir . '/rest-failures-plugin.php', "<?php\n/**\n * Plugin Name: Failing Plugin\n */\n" ),
			'The test plugin should be written.'
		);

		wp_cache_delete( 'plugins', 'plugins' );

		return 'rest-failures-plugin/rest-failures-plugin';
	}

	/**
	 * Tests that the errors of an installation that failed are returned.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::create_item
	 */
	public function test_create_item_should_return_the_errors_of_an_installation_that_failed() {
		wp_set_current_user( self::$admin );
		$this->mock_plugin_information();

		add_filter(
			'upgrader_pre_download',
			static function () {
				return new WP_Error( 'download_failed', 'The download failed.' );
			}
		);

		$request = new WP_REST_Request( 'POST', self::BASE );
		$request->set_body_params( array( 'slug' => 'rest-failures-plugin' ) );

		$level    = ob_get_level();
		$response = rest_do_request( $request );

		// The upgrader leaves an output buffer open when the installation fails.
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}

		$this->assertErrorResponse( 'download_failed', $response, 500 );
		$this->assertFileDoesNotExist( WP_PLUGIN_DIR . '/rest-failures-plugin', 'Nothing should be installed.' );
	}

	/**
	 * Tests that an error of the Plugin Installation API that is not about a plugin that is not found is a server error.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::create_item
	 */
	public function test_create_item_should_return_the_error_of_the_api_as_a_server_error() {
		wp_set_current_user( self::$admin );

		add_filter(
			'plugins_api',
			static function () {
				return new WP_Error( 'plugins_api_failed', 'The directory could not be reached.' );
			}
		);

		$request = new WP_REST_Request( 'POST', self::BASE );
		$request->set_body_params( array( 'slug' => 'rest-failures-plugin' ) );

		$this->assertErrorResponse( 'plugins_api_failed', rest_do_request( $request ), 500 );
	}

	/**
	 * Tests that a plugin cannot be installed when the filesystem is not available.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::create_item
	 * @covers WP_REST_Plugins_Controller::is_filesystem_available
	 */
	public function test_create_item_should_fail_when_the_filesystem_is_not_available() {
		wp_set_current_user( self::$admin );
		$this->mock_plugin_information();
		$this->make_the_filesystem_unavailable();

		$request = new WP_REST_Request( 'POST', self::BASE );
		$request->set_body_params( array( 'slug' => 'rest-failures-plugin' ) );

		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'fs_unavailable', $response, 500 );
		$this->assertSame( 'The filesystem is currently unavailable for managing plugins.', $response->as_error()->get_error_message() );
	}

	/**
	 * Tests that a plugin cannot be deleted when the filesystem is not available.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::delete_item
	 * @covers WP_REST_Plugins_Controller::is_filesystem_available
	 */
	public function test_delete_item_should_fail_when_the_filesystem_is_not_available() {
		wp_set_current_user( self::$admin );

		$plugin = $this->create_plugin();
		$this->make_the_filesystem_unavailable();

		$response = rest_do_request( new WP_REST_Request( 'DELETE', self::BASE . '/' . $plugin ) );

		$this->assertErrorResponse( 'fs_unavailable', $response, 500 );
		$this->assertFileExists( WP_PLUGIN_DIR . '/rest-failures-plugin/rest-failures-plugin.php', 'The plugin should not be deleted.' );
	}

	/**
	 * Tests that the filesystem is available when its credentials are stored.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::is_filesystem_available
	 */
	public function test_is_filesystem_available_should_be_true_when_the_credentials_are_stored() {
		add_filter(
			'filesystem_method',
			static function () {
				return 'ftpext';
			}
		);
		add_filter( 'request_filesystem_credentials', '__return_true' );

		$controller = new WP_REST_Plugins_Controller();
		$method     = new ReflectionMethod( $controller, 'is_filesystem_available' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->assertTrue( $method->invoke( $controller ) );
	}

	/**
	 * Tests the plugin parameter that is not valid.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::validate_plugin_param
	 *
	 * @dataProvider data_invalid_plugin_params
	 *
	 * @param mixed $plugin The value of the parameter.
	 */
	public function test_validate_plugin_param_should_reject_a_value_that_is_not_a_plugin_path( $plugin ) {
		$this->assertFalse( ( new WP_REST_Plugins_Controller() )->validate_plugin_param( $plugin ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_plugin_params() {
		return array(
			'null'                => array( null ),
			'an integer'          => array( 5 ),
			'an array'            => array( array( 'plugin/plugin' ) ),
			'an empty string'     => array( '' ),
			'a path that goes up' => array( '../plugin' ),
		);
	}

	/**
	 * Tests the plugin parameter that is valid.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::validate_plugin_param
	 *
	 * @dataProvider data_valid_plugin_params
	 *
	 * @param string $plugin The value of the parameter.
	 */
	public function test_validate_plugin_param_should_accept_a_plugin_path( $plugin ) {
		$this->assertTrue( ( new WP_REST_Plugins_Controller() )->validate_plugin_param( $plugin ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_valid_plugin_params() {
		return array(
			'a plugin in a folder' => array( 'my-plugin/my-plugin' ),
			'a single file plugin' => array( 'hello' ),
			'a dash and numbers'   => array( 'my-plugin-2/main-1' ),
		);
	}

	/**
	 * Tests that the plugin parameter gets the extension of a PHP file.
	 *
	 * @ticket 66562
	 *
	 * @covers WP_REST_Plugins_Controller::sanitize_plugin_param
	 */
	public function test_sanitize_plugin_param_should_add_the_php_extension() {
		$this->assertSame( 'my-plugin/my-plugin.php', ( new WP_REST_Plugins_Controller() )->sanitize_plugin_param( 'my-plugin/my-plugin' ) );
	}
}
