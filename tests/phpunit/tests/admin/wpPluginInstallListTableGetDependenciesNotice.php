<?php

/**
 * Tests for WP_Plugin_Install_List_Table::get_dependencies_notice().
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Install_List_Table::get_dependencies_notice
 * @covers WP_Plugin_Install_List_Table::get_more_details_link
 */
class Tests_Admin_wpPluginInstallListTableGetDependenciesNotice extends WP_UnitTestCase {

	/**
	 * The notice before the list of dependencies.
	 *
	 * @var string
	 */
	const NOTICE_START = '<div class="plugin-dependencies notice notice-alt notice-info inline"><p class="plugin-dependencies-explainer-text"><strong>Additional plugins are required</strong></p> ';

	/**
	 * The list table.
	 *
	 * @var WP_Plugin_Install_List_Table
	 */
	private $table;

	/**
	 * The original values of the globals and properties the tests change.
	 *
	 * @var array
	 */
	private $original = array();

	/**
	 * Slugs requested from the plugins API.
	 *
	 * @var string[]
	 */
	private $api_requests = array();

	/**
	 * Responses of the mocked plugins API, keyed by slug.
	 *
	 * @var array
	 */
	private $api_responses = array();

	/**
	 * Loads plugins_api(), which the Add Plugins screen loads.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	/**
	 * Sets up the list table, the Add Plugins screen and the plugins API mock.
	 */
	public function set_up() {
		parent::set_up();

		$this->original = array(
			'pagenow'             => $GLOBALS['pagenow'] ?? null,
			'current_screen'      => $GLOBALS['current_screen'] ?? null,
			'dependency_api_data' => $this->get_dependency_api_data(),
		);

		$this->table = _get_list_table( 'WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install' ) );

		$GLOBALS['pagenow'] = 'plugin-install.php';
		set_current_screen( 'plugin-install' );
		$this->set_dependency_api_data( array() );

		add_filter( 'plugins_api', array( $this, 'mock_plugins_api' ), 10, 3 );
	}

	/**
	 * Restores the globals and properties.
	 */
	public function tear_down() {
		$GLOBALS['pagenow']        = $this->original['pagenow'];
		$GLOBALS['current_screen'] = $this->original['current_screen'];
		$this->set_dependency_api_data( $this->original['dependency_api_data'] );

		parent::tear_down();
	}

	/**
	 * Tests that no notice is returned for a plugin without dependencies.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_no_dependencies
	 *
	 * @param array $plugin_data The plugin data.
	 */
	public function test_should_return_empty_string_without_dependencies( $plugin_data ) {
		$this->assertSame( '', $this->get_dependencies_notice( $plugin_data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_dependencies() {
		return array(
			'no requires_plugins key' => array( array( 'slug' => 'my-plugin' ) ),
			'empty array'             => array( array( 'requires_plugins' => array() ) ),
		);
	}

	/**
	 * Tests that a dependency is named from the stored dependency data.
	 *
	 * @ticket 65819
	 */
	public function test_should_name_dependency_from_dependency_data() {
		$this->set_dependency_api_data(
			array(
				'dep-one' => array(
					'name'    => 'Dep <b>&</b> "One"',
					'slug'    => 'dep-one',
					'version' => '1.0',
				),
			)
		);

		$this->assertSame(
			self::NOTICE_START . $this->get_named_dependency( 'Dep &lt;b&gt;&amp;&lt;/b&gt; &quot;One&quot;', 'dep-one' ) . '</div>',
			$this->get_dependencies_notice( array( 'requires_plugins' => array( 'dep-one' ) ) ),
			'The dependency should be named from the stored data.'
		);
		$this->assertSame( array(), $this->api_requests, 'The plugins API should not be requested.' );
	}

	/**
	 * Tests that the details link uses the slug of the stored dependency data.
	 *
	 * @ticket 65819
	 */
	public function test_should_use_slug_from_dependency_data() {
		$this->set_dependency_api_data(
			array(
				'dep-one' => array(
					'name'    => 'Dep One',
					'slug'    => 'dep-one-renamed',
					'version' => '1.0',
				),
			)
		);

		$this->assertSame(
			self::NOTICE_START . $this->get_named_dependency( 'Dep One', 'dep-one-renamed' ) . '</div>',
			$this->get_dependencies_notice( array( 'requires_plugins' => array( 'dep-one' ) ) )
		);
	}

	/**
	 * Tests that incomplete dependency data falls back to the plugins API.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_incomplete_dependency_data
	 *
	 * @param array|false $dependency_data The stored dependency data, or false for none.
	 */
	public function test_should_fall_back_to_plugins_api_for_incomplete_dependency_data( $dependency_data ) {
		if ( false !== $dependency_data ) {
			$this->set_dependency_api_data( array( 'dep-one' => $dependency_data ) );
		}

		$this->api_responses['dep-one'] = (object) array(
			'name' => 'Dep One From API',
			'slug' => 'dep-one',
		);

		$this->assertSame(
			self::NOTICE_START . $this->get_named_dependency( 'Dep One From API', 'dep-one' ) . '</div>',
			$this->get_dependencies_notice( array( 'requires_plugins' => array( 'dep-one' ) ) ),
			'The dependency should be named from the plugins API.'
		);
		$this->assertSame( array( 'dep-one' ), $this->api_requests, 'The plugins API should be requested once.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_dependency_data() {
		$complete = array(
			'name'    => 'Dep One',
			'slug'    => 'dep-one',
			'version' => '1.0',
		);

		return array(
			'no dependency data' => array( false ),
			'empty name'         => array( array_merge( $complete, array( 'name' => '' ) ) ),
			'empty slug'         => array( array_merge( $complete, array( 'slug' => '' ) ) ),
			'empty version'      => array( array_merge( $complete, array( 'version' => '' ) ) ),
			'no version'         => array( array_diff_key( $complete, array( 'version' => true ) ) ),
		);
	}

	/**
	 * Tests that the details link uses the slug returned by the plugins API.
	 *
	 * @ticket 65819
	 */
	public function test_should_use_slug_from_plugins_api() {
		$this->api_responses['dep-one'] = (object) array(
			'name' => 'Dep <i>One</i>',
			'slug' => 'renamed-dep',
		);

		$this->assertSame(
			self::NOTICE_START . $this->get_named_dependency( 'Dep &lt;i&gt;One&lt;/i&gt;', 'renamed-dep' ) . '</div>',
			$this->get_dependencies_notice( array( 'requires_plugins' => array( 'dep-one' ) ) )
		);
	}

	/**
	 * Tests that a dependency without a name is listed by its slug.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unnamed_api_responses
	 *
	 * @param string $response The plugins API response type.
	 */
	public function test_should_list_dependency_by_slug_without_name( $response ) {
		$this->api_responses['dep<one>'] = 'error' === $response
			? new WP_Error( 'plugins_api_failed', 'An error.' )
			: (object) array(
				'name' => '',
				'slug' => 'dep-one',
			);

		$this->assertSame(
			self::NOTICE_START . '<div class="plugin-dependency"><span class="plugin-dependency-name">dep&lt;one&gt;</span></div></div>',
			$this->get_dependencies_notice( array( 'requires_plugins' => array( 'dep<one>' ) ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unnamed_api_responses() {
		return array(
			'API error'         => array( 'error' ),
			'API data, no name' => array( 'no name' ),
		);
	}

	/**
	 * Tests that every dependency is listed, in order.
	 *
	 * @ticket 65819
	 */
	public function test_should_list_all_dependencies_in_order() {
		$this->set_dependency_api_data(
			array(
				'dep-one' => array(
					'name'    => 'Dep One',
					'slug'    => 'dep-one',
					'version' => '1.0',
				),
			)
		);

		$this->api_responses['dep-two']   = (object) array(
			'name' => 'Dep Two',
			'slug' => 'dep-two',
		);
		$this->api_responses['dep-three'] = new WP_Error( 'plugins_api_failed', 'An error.' );

		$this->assertSame(
			self::NOTICE_START
				. $this->get_named_dependency( 'Dep One', 'dep-one' )
				. $this->get_named_dependency( 'Dep Two', 'dep-two' )
				. '<div class="plugin-dependency"><span class="plugin-dependency-name">dep-three</span></div>'
				. '</div>',
			$this->get_dependencies_notice( array( 'requires_plugins' => array( 'dep-one', 'dep-two', 'dep-three' ) ) ),
			'Each dependency should be listed in order.'
		);
		$this->assertSame( array( 'dep-two', 'dep-three' ), $this->api_requests, 'Only dependencies without stored data should be requested.' );
	}

	/**
	 * Returns the expected markup of a named dependency.
	 *
	 * @param string $escaped_name The escaped plugin name.
	 * @param string $slug         The plugin slug.
	 * @return string The markup.
	 */
	private function get_named_dependency( $escaped_name, $slug ) {
		$url = 'http://' . WP_TESTS_DOMAIN . '/wp-admin/' . ( is_multisite() ? 'network/' : '' )
			. 'plugin-install.php?tab=plugin-information&#038;plugin=' . $slug . '&#038;TB_iframe=true&#038;width=600&#038;height=550';

		return '<div class="plugin-dependency"><span class="plugin-dependency-name">' . $escaped_name . '</span> '
			. '<a href="' . $url . '" class="more-details-link thickbox open-plugin-details-modal" aria-label="More information about ' . $escaped_name . '" data-title="' . $escaped_name . '">More Details</a>'
			. '</div>';
	}

	/**
	 * Calls the protected get_dependencies_notice() method.
	 *
	 * @param array $plugin_data The plugin data.
	 * @return string The notice.
	 */
	private function get_dependencies_notice( $plugin_data ) {
		$method = new ReflectionMethod( $this->table, 'get_dependencies_notice' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $this->table, $plugin_data );
	}

	/**
	 * Gets the stored dependency data of WP_Plugin_Dependencies.
	 *
	 * @return array|null The dependency data.
	 */
	private function get_dependency_api_data() {
		$property = new ReflectionProperty( 'WP_Plugin_Dependencies', 'dependency_api_data' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property->getValue();
	}

	/**
	 * Sets the stored dependency data of WP_Plugin_Dependencies.
	 *
	 * @param array|null $value The dependency data.
	 */
	private function set_dependency_api_data( $value ) {
		$property = new ReflectionProperty( 'WP_Plugin_Dependencies', 'dependency_api_data' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( null, $value );
	}

	/**
	 * Returns the mocked plugins API response for the requested slug.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return object|WP_Error The mocked response.
	 */
	public function mock_plugins_api( $result, $action, $args ) {
		$this->api_requests[] = $args->slug;

		return $this->api_responses[ $args->slug ] ?? new WP_Error( 'plugins_api_failed', 'No mocked response.' );
	}
}
