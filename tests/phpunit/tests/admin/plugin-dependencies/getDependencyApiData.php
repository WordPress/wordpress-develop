<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_dependency_api_data() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_dependency_api_data
 * @covers WP_Plugin_Dependencies::get_plugin_dirnames
 */
class Tests_Admin_WPPluginDependencies_GetDependencyApiData extends WP_PluginDependencies_UnitTestCase {

	/**
	 * The original value of the filename of the current screen.
	 *
	 * @var string
	 */
	private $original_pagenow;

	/**
	 * The slugs that the Plugin Installation API was asked for.
	 *
	 * @var string[]
	 */
	private $api_requests = array();

	/**
	 * The responses of the API, by slug. A slug without a response gets an error.
	 *
	 * @var array<string, object>
	 */
	private $api_responses = array();

	/**
	 * Sets the screen of the plugins, and answers the API.
	 */
	public function set_up() {
		global $pagenow;

		parent::set_up();

		$this->original_pagenow = $pagenow;
		$this->api_requests     = array();
		$this->api_responses    = array();

		$pagenow = 'plugins.php';
		set_current_screen( 'plugins.php' );

		delete_site_transient( 'wp_plugin_dependencies_plugin_data' );

		add_filter( 'plugins_api', array( $this, 'answer_api' ), 10, 3 );
	}

	/**
	 * Restores the screen, and deletes the transients.
	 */
	public function tear_down() {
		global $pagenow;

		$pagenow = $this->original_pagenow;

		delete_site_transient( 'wp_plugin_dependencies_plugin_data' );

		foreach ( array( 'one', 'two', 'gone' ) as $slug ) {
			delete_site_transient( "wp_plugin_dependencies_plugin_timeout_{$slug}" );
		}

		parent::tear_down();
	}

	/**
	 * Answers the API, and records the request.
	 *
	 * @param false|object $result The result.
	 * @param string       $action The action.
	 * @param object       $args   The arguments.
	 * @return object|WP_Error The response.
	 */
	public function answer_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$this->api_requests[] = $args->slug;

		return $this->api_responses[ $args->slug ] ?? new WP_Error( 'plugins_api_failed', 'Not found.' );
	}

	/**
	 * Tests that nothing is returned outside of the screens for plugins.
	 *
	 * @ticket 66560
	 *
	 * @dataProvider data_other_screens
	 *
	 * @param string $page The filename of the screen.
	 */
	public function test_should_return_nothing_outside_of_the_plugin_screens( $page ) {
		global $pagenow;

		$pagenow = $page;
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );

		$this->assertNull( $this->call_method( 'get_dependency_api_data' ) );
		$this->assertSame( array(), $this->api_requests, 'The API should not be asked.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_other_screens() {
		return array(
			'the dashboard' => array( 'index.php' ),
			'the editor'    => array( 'post.php' ),
		);
	}

	/**
	 * Tests that the data is returned on the screens for plugins.
	 *
	 * @ticket 66560
	 *
	 * @dataProvider data_plugin_screens
	 *
	 * @param string $page The filename of the screen.
	 */
	public function test_should_return_the_data_on_the_plugin_screens( $page ) {
		global $pagenow;

		$pagenow = $page;
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );
		$this->set_property_value( 'dependency_api_data', array( 'one' => array( 'Name' => 'One' ) ) );

		$this->assertSame( array( 'one' => array( 'Name' => 'One' ) ), $this->call_method( 'get_dependency_api_data' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugin_screens() {
		return array(
			'the installed plugins' => array( 'plugins.php' ),
			'the new plugins'       => array( 'plugin-install.php' ),
		);
	}

	/**
	 * Tests that the data that was already read is returned as it is.
	 *
	 * @ticket 66560
	 */
	public function test_should_return_the_data_that_was_read_before() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );
		$this->set_property_value( 'dependency_api_data', array( 'one' => array( 'Name' => 'One' ) ) );

		$this->assertSame( array( 'one' => array( 'Name' => 'One' ) ), $this->call_method( 'get_dependency_api_data' ) );
		$this->assertSame( array(), $this->api_requests, 'The API should not be asked.' );
	}

	/**
	 * Tests that the API is asked for a dependency that has no data, and that the data is kept.
	 *
	 * @ticket 66560
	 */
	public function test_should_ask_the_api_for_a_dependency_without_data() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );
		$this->api_responses['one'] = (object) array(
			'name'         => 'Plugin One',
			'last_updated' => '2026-01-01 00:00:00',
		);

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array( 'one' ), $this->api_requests );
		$this->assertSame( array( 'one' ), array_keys( $data ) );
		$this->assertSame( 'Plugin One', $data['one']['Name'], 'The name should be the Name key too.' );
		$this->assertSame( '2026-01-01 00:00:00', $data['one']['last_updated'] );
		$this->assertSame( $data, get_site_transient( 'wp_plugin_dependencies_plugin_data' ), 'The data should be kept in a transient.' );
		$this->assertTrue( get_site_transient( 'wp_plugin_dependencies_plugin_timeout_one' ), 'The time to ask again should be kept.' );
	}

	/**
	 * Tests that a dependency that the API cannot find is left out.
	 *
	 * @ticket 66560
	 */
	public function test_should_leave_out_a_dependency_that_the_api_cannot_find() {
		$this->set_property_value( 'dependency_slugs', array( 'one', 'two' ) );
		$this->api_responses['two'] = (object) array( 'name' => 'Plugin Two' );

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array( 'one', 'two' ), $this->api_requests );
		$this->assertSame( array( 'two' ), array_keys( $data ) );
	}

	/**
	 * Tests that the API is not asked for data that has a date of the last update.
	 *
	 * @ticket 66560
	 */
	public function test_should_not_ask_the_api_when_the_data_is_from_the_api() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );

		set_site_transient(
			'wp_plugin_dependencies_plugin_data',
			array(
				'one' => array(
					'Name'         => 'Plugin One',
					'last_updated' => '2026-01-01 00:00:00',
				),
			)
		);
		set_site_transient( 'wp_plugin_dependencies_plugin_timeout_one', true, HOUR_IN_SECONDS );

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array(), $this->api_requests, 'The API should not be asked.' );
		$this->assertSame( 'Plugin One', $data['one']['Name'] );
	}

	/**
	 * Tests that the API is asked again when the data has no date of the last update.
	 *
	 * @ticket 66560
	 */
	public function test_should_ask_the_api_when_the_data_is_not_from_the_api() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );
		$this->api_responses['one'] = (object) array( 'name' => 'Plugin One' );

		set_site_transient( 'wp_plugin_dependencies_plugin_data', array( 'one' => array( 'Name' => 'Only A Name' ) ) );
		set_site_transient( 'wp_plugin_dependencies_plugin_timeout_one', true, HOUR_IN_SECONDS );

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array( 'one' ), $this->api_requests );
		$this->assertSame( 'Plugin One', $data['one']['Name'] );
	}

	/**
	 * Tests that the data is removed and asked for again when the time to ask again has passed.
	 *
	 * @ticket 66560
	 */
	public function test_should_ask_the_api_again_when_the_time_has_passed() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );
		$this->api_responses['one'] = (object) array(
			'name'         => 'New Name',
			'last_updated' => '2026-02-02 00:00:00',
		);

		set_site_transient(
			'wp_plugin_dependencies_plugin_data',
			array(
				'one' => array(
					'Name'         => 'Old Name',
					'last_updated' => '2026-01-01 00:00:00',
				),
			)
		);

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array( 'one' ), $this->api_requests, 'The API should be asked.' );
		$this->assertSame( 'New Name', $data['one']['Name'] );
		$this->assertTrue( get_site_transient( 'wp_plugin_dependencies_plugin_timeout_one' ), 'The time to ask again should be set.' );
	}

	/**
	 * Tests the name of a dependency that is not on WordPress.org and is not installed.
	 *
	 * @ticket 66560
	 */
	public function test_should_use_the_slug_as_the_name_of_a_dependency_that_is_not_installed() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );
		$this->set_property_value( 'dependency_filepaths', array( 'one' => false ) );

		set_site_transient( 'wp_plugin_dependencies_plugin_data', array( 'one' => false ) );
		set_site_transient( 'wp_plugin_dependencies_plugin_timeout_one', true, HOUR_IN_SECONDS );

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array( 'one' => array( 'Name' => 'one' ) ), $data );
		$this->assertSame( array(), $this->api_requests, 'The API should not be asked.' );
	}

	/**
	 * Tests the name of a dependency that is not on WordPress.org and is installed.
	 *
	 * @ticket 66560
	 */
	public function test_should_use_the_name_of_an_installed_dependency_that_is_not_on_wordpress_org() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );
		$this->set_property_value( 'dependency_filepaths', array( 'one' => 'one/one.php' ) );
		$this->set_property_value( 'plugins', array( 'one/one.php' => array( 'Name' => 'The Installed One' ) ) );

		set_site_transient( 'wp_plugin_dependencies_plugin_data', array( 'one' => false ) );
		set_site_transient( 'wp_plugin_dependencies_plugin_timeout_one', true, HOUR_IN_SECONDS );

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array( 'one' => array( 'Name' => 'The Installed One' ) ), $data );
	}

	/**
	 * Tests that the data of a slug that is no longer a dependency is removed.
	 *
	 * @ticket 66560
	 */
	public function test_should_remove_the_data_of_a_slug_that_is_no_longer_a_dependency() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );

		set_site_transient(
			'wp_plugin_dependencies_plugin_data',
			array(
				'one'  => array(
					'Name'         => 'Plugin One',
					'last_updated' => '2026-01-01 00:00:00',
				),
				'gone' => array(
					'Name'         => 'Gone',
					'last_updated' => '2026-01-01 00:00:00',
				),
			)
		);
		set_site_transient( 'wp_plugin_dependencies_plugin_timeout_one', true, HOUR_IN_SECONDS );

		$data = $this->call_method( 'get_dependency_api_data' );

		$this->assertSame( array( 'one' ), array_keys( $data ) );
		$this->assertSame( array( 'one' ), array_keys( get_site_transient( 'wp_plugin_dependencies_plugin_data' ) ), 'The transient should be updated.' );
	}

	/**
	 * Tests that the data is sorted by slug.
	 *
	 * @ticket 66560
	 */
	public function test_should_sort_the_data_by_slug() {
		$this->set_property_value( 'dependency_slugs', array( 'two', 'one' ) );
		$this->api_responses['one'] = (object) array( 'name' => 'Plugin One' );
		$this->api_responses['two'] = (object) array( 'name' => 'Plugin Two' );

		$this->assertSame( array( 'one', 'two' ), array_keys( $this->call_method( 'get_dependency_api_data' ) ) );
	}

	/**
	 * Tests that a dependency without data is not in the data.
	 *
	 * @ticket 66560
	 */
	public function test_should_leave_out_a_dependency_with_empty_data() {
		$this->set_property_value( 'dependency_slugs', array( 'one' ) );

		set_site_transient( 'wp_plugin_dependencies_plugin_data', array( 'one' => array() ) );
		set_site_transient( 'wp_plugin_dependencies_plugin_timeout_one', true, HOUR_IN_SECONDS );

		$this->assertSame( array(), $this->call_method( 'get_dependency_api_data' ) );
	}

	/**
	 * Tests that the names of the folders of the plugins are cached.
	 *
	 * @ticket 66560
	 */
	public function test_get_plugin_dirnames_should_return_the_names_that_were_listed_before() {
		$this->set_property_value( 'plugin_dirnames', array( 'one' => 'one/one.php' ) );

		$this->assertSame( array( 'one' => 'one/one.php' ), $this->call_method( 'get_plugin_dirnames' ) );
	}

	/**
	 * Tests the names of the folders of the plugins.
	 *
	 * @ticket 66560
	 */
	public function test_get_plugin_dirnames_should_list_the_folders_of_the_plugins() {
		$this->set_property_value(
			'plugins',
			array(
				'one/one.php'            => array( 'Name' => 'One' ),
				'single-file-plugin.php' => array( 'Name' => 'Single' ),
			)
		);

		$dirnames = $this->call_method( 'get_plugin_dirnames' );

		$this->assertSame( 'one/one.php', $dirnames['one'] );
		$this->assertSame( 'single-file-plugin.php', $dirnames['single-file-plugin'] );
	}
}
