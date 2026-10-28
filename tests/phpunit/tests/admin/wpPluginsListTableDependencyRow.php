<?php

require_once __DIR__ . '/plugin-dependencies/base.php';

/**
 * Tests for the "Requires" row of WP_Plugins_List_Table.
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::single_row
 * @covers WP_Plugins_List_Table::add_dependencies_to_dependent_plugin_row
 * @covers WP_Plugins_List_Table::get_dependency_view_details_link
 * @covers WP_Plugins_List_Table::get_view_details_link
 */
class Tests_Admin_wpPluginsListTableDependencyRow extends WP_PluginDependencies_UnitTestCase {

	/**
	 * The plugin file of the dependent plugin.
	 *
	 * @var string
	 */
	const DEPENDENT = 'dependent/dependent.php';

	/**
	 * The original globals the tests change.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * Whether each saved global existed before setup.
	 *
	 * @var bool[]
	 */
	private $had_globals = array();

	/**
	 * Switches to the Plugins screen and resets the active plugins.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'pagenow', 'current_screen', 'status', 'page', 's', 'totals' ) as $name ) {
			$this->had_globals[ $name ]      = array_key_exists( $name, $GLOBALS );
			$this->original_globals[ $name ] = $this->had_globals[ $name ] ? $GLOBALS[ $name ] : null;
		}

		$GLOBALS['pagenow'] = 'plugins.php';
		$GLOBALS['status']  = 'all';
		$GLOBALS['page']    = 1;
		$GLOBALS['s']       = '';
		$GLOBALS['totals']  = array();
		set_current_screen( is_multisite() ? 'plugins-network' : 'plugins' );

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );
	}

	/**
	 * Restores the globals.
	 */
	public function tear_down() {
		foreach ( $this->original_globals as $name => $value ) {
			if ( $this->had_globals[ $name ] ) {
				$GLOBALS[ $name ] = $value;
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that nothing is output for a plugin without dependencies.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_nothing_without_dependencies() {
		$this->set_state( array(), array(), array() );

		$this->assertStringNotContainsString( '<div class="requires">', $this->get_row() );
	}

	/**
	 * Tests the row for active dependencies, linked when the plugin directory knows them.
	 *
	 * @ticket 65819
	 */
	public function test_should_list_dependencies_with_details_links() {
		$this->set_state(
			array( 'dep-one', 'dep-two' ),
			array(
				'dep-one' => array(
					'name'    => 'Dep <b>One</b> & "Co"',
					'version' => '1.0',
				),
			),
			array(
				'dep-one' => 'dep-one/dep-one.php',
				'dep-two' => 'dep-two/dep-two.php',
			)
		);
		$this->activate( array( 'dep-one/dep-one.php', 'dep-two/dep-two.php' ) );

		$url = 'http://' . WP_TESTS_DOMAIN . '/wp-admin/network/plugin-install.php?tab=plugin-information&#038;plugin=dep-one&#038;TB_iframe=true&#038;width=600&#038;height=550';
		if ( ! is_multisite() ) {
			$url = str_replace( '/network/', '/', $url );
		}

		$this->assertStringContainsString(
			'<div class="requires"><p><strong>Requires:</strong> '
			. "<a href='{$url}' class='thickbox open-plugin-details-modal' aria-label='More information about Dep &lt;b&gt;One&lt;/b&gt; &amp; &quot;Co&quot;' data-title='Dep &lt;b&gt;One&lt;/b&gt; &amp; &quot;Co&quot;'>Dep &lt;b&gt;One&lt;/b&gt; &amp; &quot;Co&quot;</a>"
			. ', Dep Two Installed</p></div>',
			$this->get_row()
		);
	}

	/**
	 * Tests that a dependency is not linked without a version or when its name is the slug.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unlinked_dependencies
	 *
	 * @param array  $api_data The plugin directory data of the dependency.
	 * @param string $expected The expected dependency markup.
	 */
	public function test_should_not_link_dependency( $api_data, $expected ) {
		$this->set_state( array( 'dep-one' ), array( 'dep-one' => $api_data ), array( 'dep-one' => 'dep-one/dep-one.php' ) );
		$this->activate( array( 'dep-one/dep-one.php' ) );

		$this->assertStringContainsString( '<div class="requires"><p><strong>Requires:</strong> ' . $expected . '</p></div>', $this->get_row() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unlinked_dependencies() {
		return array(
			'no version'       => array(
				array(
					'name'    => 'Dep One',
					'version' => '',
				),
				'Dep One',
			),
			'name is the slug' => array(
				array(
					'name'    => 'dep-one',
					'version' => '1.0',
				),
				'dep-one',
			),
		);
	}

	/**
	 * Tests the notice for unmet dependencies, depending on whether the dependent is active.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unmet_dependencies
	 *
	 * @param bool   $dependent_active Whether the dependent plugin is active.
	 * @param string $message          The expected notice message.
	 */
	public function test_should_add_notice_for_unmet_dependencies( $dependent_active, $message ) {
		$this->set_state( array( 'missing' ), array(), array( 'missing' => false ) );
		$this->activate( $dependent_active ? array( self::DEPENDENT ) : array() );

		$this->assertStringContainsString(
			'<div class="requires"><p><strong>Requires:</strong> missing</p>'
			. '<div class="notice notice-error inline notice-alt"><p>' . $message . '</p></div>'
			. '</div>',
			$this->get_row()
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unmet_dependencies() {
		return array(
			'inactive dependent' => array( false, 'This plugin cannot be activated because required plugins are missing or inactive.' ),
			'active dependent'   => array( true, 'This plugin is active but may not function correctly because required plugins are missing or inactive.' ),
		);
	}

	/**
	 * Tests that a plugin active on the site only is not network active on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_check_network_activation_on_multisite() {
		$this->set_state( array( 'missing' ), array(), array( 'missing' => false ) );
		update_option( 'active_plugins', array( self::DEPENDENT ) );

		$this->assertStringContainsString( 'This plugin cannot be activated because required plugins are missing or inactive.', $this->get_row() );
	}

	/**
	 * Tests that an inactive dependency is unmet.
	 *
	 * @ticket 65819
	 */
	public function test_should_add_notice_for_inactive_dependency() {
		$this->set_state( array( 'dep-one' ), array(), array( 'dep-one' => 'dep-one/dep-one.php' ) );

		$this->assertStringContainsString( 'This plugin cannot be activated because required plugins are missing or inactive.', $this->get_row() );
	}

	/**
	 * Sets the dependency state of the dependent plugin.
	 *
	 * @param string[] $dependencies The dependency slugs.
	 * @param array    $api_data     The plugin directory data, keyed by slug.
	 * @param array    $filepaths    The installed dependency files, or false, keyed by slug.
	 */
	private function set_state( $dependencies, $api_data, $filepaths ) {
		$plugins = array(
			self::DEPENDENT => array( 'Name' => 'Dependent' ),
		);
		foreach ( $filepaths as $slug => $file ) {
			if ( false !== $file ) {
				$plugins[ $file ] = array( 'Name' => ucwords( str_replace( '-', ' ', $slug ) ) . ' Installed' );
			}
		}

		$this->set_property_value( 'plugins', $plugins );
		$this->set_property_value( 'dependencies', $dependencies ? array( self::DEPENDENT => $dependencies ) : array() );
		$this->set_property_value( 'dependency_slugs', $dependencies );
		$this->set_property_value(
			'dependent_slugs',
			$dependencies ? array( self::DEPENDENT => 'dependent' ) : array()
		);
		$this->set_property_value( 'dependency_api_data', $api_data );
		$this->set_property_value( 'dependency_filepaths', $filepaths );
		$this->set_property_value( 'initialized', true );
	}

	/**
	 * Activates plugins, network-wide on multisite.
	 *
	 * @param string[] $plugins The plugin files.
	 */
	private function activate( $plugins ) {
		if ( is_multisite() ) {
			update_site_option( 'active_sitewide_plugins', array_fill_keys( $plugins, time() ) );
		} else {
			update_option( 'active_plugins', $plugins );
		}
	}

	/**
	 * Returns the "Requires" row of the dependent plugin.
	 *
	 * @return string The output.
	 */
	private function get_row() {
		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => get_current_screen() ) );

		$row = get_echo(
			array( $table, 'single_row' ),
			array(
				array(
					self::DEPENDENT,
					array(
						'Name'        => 'Dependent',
						'slug'        => 'dependent',
						'Description' => '',
						'Version'     => '',
						'Author'      => '',
						'AuthorURI'   => '',
						'PluginURI'   => '',
					),
				),
			)
		);

		$this->assertLessThanOrEqual(
			1,
			substr_count( $row, '<div class="requires">' ),
			'The plugin row should contain at most one complete dependency fragment.'
		);

		return $row;
	}
}
