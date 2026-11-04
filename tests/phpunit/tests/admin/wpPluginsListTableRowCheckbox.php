<?php

require_once __DIR__ . '/plugin-dependencies/base.php';

/**
 * Tests for the row classes and checkbox WP_Plugins_List_Table::single_row() outputs.
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::single_row
 */
class Tests_Admin_wpPluginsListTableRowCheckbox extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Number used to give each rendered plugin a unique slug.
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * The original globals the list table reads.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * The slug of the plugin rendered by the current test.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Sets up the Plugins screen, the list table globals and the dependency state.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'status', 'page', 's', 'totals', '_paused_plugins', 'current_screen' ) as $name ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
		}

		set_current_screen( 'plugins' );

		$GLOBALS['status']          = 'all';
		$GLOBALS['page']            = 1;
		$GLOBALS['s']               = '';
		$GLOBALS['totals']          = array();
		$GLOBALS['_paused_plugins'] = array();

		$this->set_property_value( 'plugins', array() );
		$this->set_property_value( 'dependencies', array() );
		$this->set_property_value( 'dependency_slugs', array() );
		$this->set_property_value( 'dependency_filepaths', array() );
		$this->set_property_value( 'circular_dependencies_slugs', array() );
		$this->set_property_value( 'circular_dependencies_pairs', array() );
		$this->set_property_value( 'initialized', true );

		update_option( 'active_plugins', array() );
		update_option( 'uninstall_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );

		$this->slug = 'row-checkbox-plugin-' . ++self::$counter;
	}

	/**
	 * Restores the globals and removes the test plugin.
	 */
	public function tear_down() {
		foreach ( $this->original_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		unset( $_REQUEST['plugin_status'] );

		$this->remove_plugin();

		parent::tear_down();
	}

	/**
	 * Tests the row and checkbox of an inactive plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_inactive_row_with_checkbox() {
		$this->assertSame(
			array(
				'<tr class="inactive" data-slug="' . $this->slug . '" data-plugin="' . $this->get_file() . '">',
				$this->get_checkbox( '' ),
			),
			$this->render()
		);
	}

	/**
	 * Tests the class of an active plugin's row.
	 *
	 * @ticket 65819
	 */
	public function test_should_mark_active_row() {
		update_option( 'active_plugins', array( $this->get_file() ) );

		$this->assertSame( '<tr class="active" data-slug="' . $this->slug . '" data-plugin="' . $this->get_file() . '">', $this->render()[0] );
	}

	/**
	 * Tests that the checkbox is disabled for a plugin with dependents or unmet dependencies.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_dependency_states
	 *
	 * @param string $state The dependency state, 'dependents' or 'unmet'.
	 */
	public function test_should_disable_checkbox_for_dependency_state( $state ) {
		if ( 'dependents' === $state ) {
			$this->set_property_value( 'plugins', array( 'dependent/dependent.php' => array( 'Name' => 'Dependent' ) ) );
			$this->set_property_value( 'dependencies', array( 'dependent/dependent.php' => array( $this->slug ) ) );
			$this->set_property_value( 'dependency_slugs', array( $this->slug ) );
		} else {
			$this->set_property_value( 'dependencies', array( $this->get_file() => array( 'missing' ) ) );
			$this->set_property_value( 'dependency_filepaths', array( 'missing' => false ) );
		}

		$this->assertSame( $this->get_checkbox( 'disabled' ), $this->render()[1] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_dependency_states() {
		return array(
			'plugin with dependents'         => array( 'dependents' ),
			'plugin with unmet dependencies' => array( 'unmet' ),
		);
	}

	/**
	 * Tests the row of a plugin whose PHP requirement is not met: no checkbox, marked for update.
	 *
	 * @ticket 65819
	 */
	public function test_should_hide_checkbox_for_incompatible_php() {
		$this->assertSame(
			array(
				'<tr class="inactive update" data-slug="' . $this->slug . '" data-plugin="' . $this->get_file() . '">',
				'',
			),
			$this->render( array( 'RequiresPHP' => '99.0' ) )
		);
	}

	/**
	 * Tests the row of a plugin whose WordPress requirement is not met: a checkbox, marked for update.
	 *
	 * @ticket 65819
	 */
	public function test_should_keep_checkbox_for_incompatible_wordpress() {
		$this->assertSame(
			array(
				'<tr class="inactive update" data-slug="' . $this->slug . '" data-plugin="' . $this->get_file() . '">',
				$this->get_checkbox( '' ),
			),
			$this->render( array( 'RequiresWP' => '99.0' ) )
		);
	}

	/**
	 * Tests that a plugin with an update is only marked while the upgrade view has updates.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_update_states
	 *
	 * @param array  $totals   The status totals.
	 * @param bool   $update   Whether the plugin data has an update.
	 * @param string $expected The expected row class.
	 */
	public function test_should_mark_plugin_with_update( $totals, $update, $expected ) {
		$GLOBALS['totals'] = $totals;

		$row = $this->render( $update ? array( 'update' => true ) : array() )[0];

		$this->assertSame( '<tr class="' . $expected . '" data-slug="' . $this->slug . '" data-plugin="' . $this->get_file() . '">', $row );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_update_states() {
		return array(
			'update and upgrade total'     => array( array( 'upgrade' => 1 ), true, 'inactive update' ),
			'update without upgrade total' => array( array(), true, 'inactive' ),
			'upgrade total without update' => array( array( 'upgrade' => 1 ), false, 'inactive' ),
		);
	}

	/**
	 * Tests the classes of a paused plugin and a plugin that can be uninstalled.
	 *
	 * @ticket 65819
	 */
	public function test_should_mark_paused_and_uninstallable_plugin() {
		update_option( 'active_plugins', array( $this->get_file() ) );
		update_option( 'uninstall_plugins', array( $this->get_file() => 'uninstall_callback' ) );
		$GLOBALS['_paused_plugins'] = array(
			$this->slug => array(
				'type'    => E_ERROR,
				'line'    => 1,
				'file'    => WP_PLUGIN_DIR . '/' . $this->get_file(),
				'message' => 'Error.',
			),
		);

		$this->assertSame( '<tr class="active paused is-uninstallable" data-slug="' . $this->slug . '" data-plugin="' . $this->get_file() . '">', $this->render()[0] );
	}

	/**
	 * Tests that must-use plugins have no checkbox and are active.
	 *
	 * @ticket 65819
	 */
	public function test_should_hide_checkbox_for_must_use_plugins() {
		// The list table reads the status from the request.
		$_REQUEST['plugin_status'] = 'mustuse';

		$this->assertSame(
			array(
				'<tr class="active" data-slug="' . $this->slug . '" data-plugin="' . $this->get_file() . '">',
				'',
			),
			$this->render()
		);
	}

	/**
	 * Tests that a plugin that is network active, or network only, has no checkbox on a site.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_network_restrictions
	 *
	 * @param bool   $network_active Whether the plugin is network active.
	 * @param string $headers        Additional plugin headers.
	 */
	public function test_should_hide_checkbox_for_network_restricted_plugin( $network_active, $headers ) {
		if ( $network_active ) {
			update_site_option( 'active_sitewide_plugins', array( $this->get_file() => time() ) );
		}

		$this->assertSame( '', $this->render( array(), $headers )[1] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_network_restrictions() {
		return array(
			'network active' => array( true, '' ),
			'network only'   => array( false, " * Network: true\n" ),
		);
	}

	/**
	 * Returns the expected checkbox markup.
	 *
	 * @param string $disabled The disabled attribute, or an empty string.
	 * @return string The markup.
	 */
	private function get_checkbox( $disabled ) {
		$id = 'checkbox_' . md5( $this->get_file() );

		return '<label class="label-covers-full-cell" for="' . $id . '"><span class="screen-reader-text">Select My Plugin</span></label>'
			. '<input type="checkbox" name="checked[]" value="' . $this->get_file() . '" id="' . $id . '" ' . $disabled . '/>';
	}

	/**
	 * Renders the row of the test plugin.
	 *
	 * @param array  $plugin_data The plugin data to add.
	 * @param string $headers     Additional plugin headers.
	 * @return string[] The opening row tag and the checkbox markup.
	 */
	private function render( $plugin_data = array(), $headers = '' ) {
		// is_network_only_plugin() reads the plugin headers on multisite.
		$dir = WP_PLUGIN_DIR . '/' . $this->slug;
		$this->remove_plugin();
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse( file_put_contents( $dir . '/' . $this->slug . '.php', "<?php\n/**\n * Plugin Name: My Plugin\n{$headers} */\n" ), 'The test plugin should be written.' );

		$table  = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
		$output = get_echo(
			array( $table, 'single_row' ),
			array(
				array(
					$this->get_file(),
					array_merge(
						array(
							'Name'        => 'My Plugin',
							'slug'        => $this->slug,
							'Description' => '',
							'Version'     => '',
							'Author'      => '',
							'AuthorURI'   => '',
							'PluginURI'   => '',
						),
						$plugin_data
					),
				),
			)
		);

		$this->assertSame( 1, preg_match( '/^<tr [^>]*>/', $output, $row ), 'The row should start with a tr tag.' );
		$this->assertSame( 1, preg_match( "#<td class='check-column'>(.*?)</td>#s", $output, $checkbox ), 'The row should have a checkbox column.' );

		return array( $row[0], $checkbox[1] );
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
	 * Removes the test plugin, including one left over by an interrupted run.
	 */
	private function remove_plugin() {
		$dir = WP_PLUGIN_DIR . '/' . $this->slug;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			rmdir( $dir );
		}
	}
}
