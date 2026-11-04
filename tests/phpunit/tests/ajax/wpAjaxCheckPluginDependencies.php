<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing the Ajax handler for checking a plugin's dependencies.
 *
 * @group ajax
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::check_plugin_dependencies_during_ajax
 */
class Tests_Ajax_wpAjaxCheckPluginDependencies extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * The static properties of WP_Plugin_Dependencies that the tests change.
	 *
	 * @var string[]
	 */
	private static $static_properties = array(
		'plugins',
		'plugin_dirnames',
		'dependencies',
		'dependency_slugs',
		'dependent_slugs',
		'dependency_api_data',
		'initialized',
	);

	/**
	 * The original values of the static properties.
	 *
	 * @var array
	 */
	private $original_properties = array();

	/**
	 * Sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin grant before the fixture users are deleted.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Saves the static properties and sets up the installed plugins.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( self::$static_properties as $property ) {
			$this->original_properties[ $property ] = $this->get_property( $property );
		}

		$this->set_property( 'plugins', null );
		$this->set_property( 'plugin_dirnames', null );
		$this->set_property( 'dependencies', null );
		$this->set_property( 'dependency_slugs', null );
		$this->set_property( 'dependent_slugs', null );
		$this->set_property( 'dependency_api_data', null );
		$this->set_property( 'initialized', false );

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );

		// Registered in admin-ajax.php, which the tests do not load.
		add_action( 'wp_ajax_check_plugin_dependencies', array( 'WP_Plugin_Dependencies', 'check_plugin_dependencies_during_ajax' ) );
	}

	/**
	 * Restores the static properties.
	 */
	public function tear_down() {
		foreach ( $this->original_properties as $property => $value ) {
			$this->set_property( $property, $value );
		}

		parent::tear_down();
	}

	/**
	 * Tests that a request without a valid nonce is rejected.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_request_without_nonce() {
		wp_set_current_user( self::$admin_id );

		$_POST['slug'] = 'dependent';

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'check_plugin_dependencies' );
	}

	/**
	 * Tests that a request without a plugin slug returns an error.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_slugs
	 *
	 * @param string|null $slug The slug, or null to leave it out.
	 */
	public function test_should_return_error_without_slug( $slug ) {
		wp_set_current_user( self::$admin_id );

		if ( null !== $slug ) {
			$_POST['slug'] = $slug;
		}

		$this->assertSame(
			array(
				'success' => false,
				'data'    => array(
					'slug'         => '',
					'pluginName'   => '',
					'errorCode'    => 'no_plugin_specified',
					'errorMessage' => 'No plugin specified.',
				),
			),
			$this->make_request()
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_slugs() {
		return array(
			'missing slug' => array( null ),
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
		);
	}

	/**
	 * Tests that a slug that is not installed returns an error with the sanitized slug.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_error_when_plugin_is_not_installed() {
		wp_set_current_user( self::$admin_id );

		$this->set_installed_plugins( array( 'dependent/dependent.php' => '' ) );

		$_POST['slug'] = 'Not_Installed<b>';

		$this->assertSame(
			array(
				'success' => false,
				'data'    => array(
					'slug'         => 'not_installedb',
					'errorCode'    => 'plugin_not_installed',
					'errorMessage' => 'The plugin is not installed.',
				),
			),
			$this->make_request()
		);
	}

	/**
	 * Tests that a plugin without dependencies returns success with an activation URL.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_return_success_with_activate_url_when_plugin_has_no_dependencies() {
		wp_set_current_user( self::$admin_id );

		$this->set_installed_plugins( array( 'dependent/dependent.php' => '' ) );

		$_POST['slug'] = 'dependent';

		$this->assertSame(
			array(
				'success' => true,
				'data'    => array(
					'slug'        => 'dependent',
					'pluginName'  => 'Dependent',
					'plugin'      => 'dependent/dependent.php',
					'activateUrl' => 'http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php?_wpnonce=' . wp_create_nonce( 'activate-plugin_dependent/dependent.php' ) . '&action=activate&plugin=dependent/dependent.php',
					'message'     => 'The plugin has no required plugins.',
				),
			),
			$this->make_request()
		);
	}

	/**
	 * Tests that the activation URL points to the network admin on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_return_network_activate_url_on_multisite() {
		wp_set_current_user( self::$admin_id );

		$this->set_installed_plugins( array( 'dependent/dependent.php' => '' ) );

		$_POST['slug'] = 'dependent';

		$response = $this->make_request();

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/wp-admin/network/plugins.php?_wpnonce=' . wp_create_nonce( 'activate-plugin_dependent/dependent.php' ) . '&action=activate&plugin=dependent%2Fdependent.php&networkwide=1',
			$response['data']['activateUrl']
		);
	}

	/**
	 * Tests that no activation URL is returned to a user who cannot activate the plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_return_activate_url_for_user_without_capability() {
		wp_set_current_user( self::$subscriber_id );

		$this->set_installed_plugins( array( 'dependent/dependent.php' => '' ) );

		$_POST['slug'] = 'dependent';

		$this->assertSame(
			array(
				'success' => true,
				'data'    => array(
					'slug'       => 'dependent',
					'pluginName' => 'Dependent',
					'plugin'     => 'dependent/dependent.php',
					'message'    => 'The plugin has no required plugins.',
				),
			),
			$this->make_request()
		);
	}

	/**
	 * Tests that no activation URL is returned for a plugin that is already active.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_not_return_activate_url_for_active_plugin() {
		wp_set_current_user( self::$admin_id );

		$this->set_installed_plugins( array( 'dependent/dependent.php' => '' ) );
		update_option( 'active_plugins', array( 'dependent/dependent.php' ) );

		$_POST['slug'] = 'dependent';

		$response = $this->make_request();

		$this->assertTrue( $response['success'], 'The request should succeed.' );
		$this->assertArrayNotHasKey( 'activateUrl', $response['data'], 'No activation URL should be returned.' );
	}

	/**
	 * Tests that a plugin whose dependencies are all active returns success.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_success_when_all_dependencies_are_active() {
		wp_set_current_user( self::$subscriber_id );

		$this->set_installed_plugins(
			array(
				'dependent/dependent.php'       => 'dependency-a, dependency-b',
				'dependency-a/dependency-a.php' => '',
				'dependency-b/dependency-b.php' => '',
			)
		);
		update_option( 'active_plugins', array( 'dependency-a/dependency-a.php', 'dependency-b/dependency-b.php' ) );

		$_POST['slug'] = 'dependent';

		$this->assertSame(
			array(
				'success' => true,
				'data'    => array(
					'slug'       => 'dependent',
					'pluginName' => 'Dependent',
					'plugin'     => 'dependent/dependent.php',
					'message'    => 'All required plugins are installed and activated.',
				),
			),
			$this->make_request()
		);
	}

	/**
	 * Tests that inactive dependencies are listed in the error.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_error_with_inactive_dependencies() {
		wp_set_current_user( self::$subscriber_id );

		$this->set_installed_plugins(
			array(
				'dependent/dependent.php'       => 'dependency-a, dependency-b, dependency-c',
				'dependency-a/dependency-a.php' => '',
				'dependency-b/dependency-b.php' => '',
				'dependency-c/dependency-c.php' => '',
			)
		);
		update_option( 'active_plugins', array( 'dependency-b/dependency-b.php' ) );

		$_POST['slug'] = 'dependent';

		$this->assertSame(
			array(
				'success' => false,
				'data'    => array(
					'slug'         => 'dependent',
					'pluginName'   => 'Dependent',
					'plugin'       => 'dependent/dependent.php',
					'errorCode'    => 'inactive_dependencies',
					'errorMessage' => 'The following plugins must be activated first: dependency-a, dependency-c.',
					'errorData'    => array(
						'dependency-a' => 'dependency-a',
						'dependency-c' => 'dependency-c',
					),
				),
			),
			$this->make_request()
		);
	}

	/**
	 * Tests that inactive dependencies are named from the plugin API data when available.
	 *
	 * @ticket 65819
	 */
	public function test_should_name_inactive_dependencies_from_api_data() {
		wp_set_current_user( self::$subscriber_id );

		$this->set_installed_plugins(
			array(
				'dependent/dependent.php'       => 'dependency-a, dependency-b',
				'dependency-a/dependency-a.php' => '',
				'dependency-b/dependency-b.php' => '',
			)
		);
		$this->set_property(
			'dependency_api_data',
			array(
				'dependency-a' => array( 'Name' => 'Dependency A' ),
			)
		);

		$_POST['slug'] = 'dependent';

		$response = $this->make_request();

		$this->assertSame(
			'The following plugins must be activated first: Dependency A, dependency-b.',
			$response['data']['errorMessage'],
			'The error message should name the dependencies.'
		);
		$this->assertSame(
			array(
				'dependency-a' => 'Dependency A',
				'dependency-b' => 'dependency-b',
			),
			$response['data']['errorData'],
			'The error data should map slugs to names.'
		);
	}

	/**
	 * Sets the installed plugins, as returned by get_plugins().
	 *
	 * @param string[] $plugins The 'Requires Plugins' header of each plugin, keyed by plugin file.
	 */
	private function set_installed_plugins( $plugins ) {
		$installed = array();

		foreach ( $plugins as $plugin_file => $requires_plugins ) {
			$installed[ $plugin_file ] = array(
				'Name'            => ucwords( str_replace( '-', ' ', dirname( $plugin_file ) ) ),
				'RequiresPlugins' => $requires_plugins,
			);
		}

		$this->set_property( 'plugins', $installed );
	}

	/**
	 * Makes the Ajax request and returns the decoded response.
	 *
	 * @return array The decoded response.
	 */
	private function make_request() {
		if ( ! isset( $_POST['_ajax_nonce'] ) ) {
			$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		}

		try {
			$this->_handleAjax( 'check_plugin_dependencies' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * Gets a static property of WP_Plugin_Dependencies.
	 *
	 * @param string $property The property name.
	 * @return mixed The property value.
	 */
	private function get_property( $property ) {
		$reflection = new ReflectionProperty( 'WP_Plugin_Dependencies', $property );

		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->getValue();
	}

	/**
	 * Sets a static property of WP_Plugin_Dependencies.
	 *
	 * @param string $property The property name.
	 * @param mixed  $value    The new value.
	 */
	private function set_property( $property, $value ) {
		$reflection = new ReflectionProperty( 'WP_Plugin_Dependencies', $property );

		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$reflection->setValue( null, $value );
	}
}
