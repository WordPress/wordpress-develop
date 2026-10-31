<?php

/**
 * Tests for the version, author and details links WP_Plugins_List_Table::single_row() shows under a plugin.
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::single_row
 */
class Tests_Admin_wpPluginsListTableRowMeta extends WP_UnitTestCase {

	/**
	 * Administrator user ID, a super admin on multisite.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * ID of a user who can activate but not install plugins.
	 *
	 * @var int
	 */
	private static $activator_id;

	/**
	 * Number used to give each rendered plugin a unique file.
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
	 * The arguments passed to the plugin_row_meta filter.
	 *
	 * @var array|null
	 */
	private $filter_args;

	/**
	 * The slug of the plugin rendered by the current test.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id     = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$activator_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$activator_id )->add_cap( 'activate_plugins' );

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
	 * Sets up the Plugins screen and the list table globals.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'status', 's', 'totals' ) as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->original_globals[ $name ] = $GLOBALS[ $name ];
			}
		}

		set_current_screen( 'plugins' );

		$GLOBALS['status'] = 'all';
		$GLOBALS['page']   = 1;
		$GLOBALS['s']      = '';
		$GLOBALS['totals'] = array();

		$this->filter_args = null;
		$this->slug        = 'row-meta-plugin-' . ++self::$counter . '-' . wp_generate_password( 6, false );

		add_filter( 'plugin_row_meta', array( $this, 'capture_meta' ), PHP_INT_MAX, 4 );

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Restores the globals and removes the test plugin.
	 */
	public function tear_down() {
		foreach ( array( 'status', 's', 'totals' ) as $name ) {
			if ( array_key_exists( $name, $this->original_globals ) ) {
				$GLOBALS[ $name ] = $this->original_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		$this->remove_plugin();

		parent::tear_down();
	}

	/**
	 * Tests the version, linked author and View details link of a plugin from the directory.
	 *
	 * @ticket 65819
	 */
	public function test_should_list_version_author_and_details_link() {
		$expected_details_url = esc_url(
			network_admin_url(
				'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550'
			)
		);

		$this->assertSame(
			array(
				'Version 1.2.3',
				'By <a href="https://example.org/jane/">Jane</a>',
				'<a href="' . $expected_details_url . '" class="thickbox open-plugin-details-modal" aria-label="More information about My &quot;Plugin&quot;" data-title="My &quot;Plugin&quot;">View details</a>',
			),
			$this->get_meta(
				array(
					'Name'      => 'My "Plugin"',
					'Version'   => '1.2.3',
					'Author'    => 'Jane',
					'AuthorURI' => 'https://example.org/jane/',
					'PluginURI' => 'https://example.org/plugin/',
					'slug'      => $this->slug,
				)
			)
		);
	}

	/**
	 * Tests the author without a link and the plugin site link of a plugin that is not from the directory.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_plugin_site_without_directory_slug() {
		$this->assertSame(
			array(
				'Version 1.2.3',
				'By Jane',
				'<a href="https://example.org/plugin/?a=1&#038;b=2" aria-label="Visit plugin site for My Plugin">Visit plugin site</a>',
			),
			$this->get_meta(
				array(
					'Version'   => '1.2.3',
					'Author'    => 'Jane',
					'PluginURI' => 'https://example.org/plugin/?a=1&b=2',
				)
			)
		);
	}

	/**
	 * Tests that a user who cannot install plugins gets the plugin site link instead of View details.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_plugin_site_for_user_without_install_capability() {
		wp_set_current_user( self::$activator_id );

		$this->assertSame(
			array( '<a href="https://example.org/plugin/" aria-label="Visit plugin site for My Plugin">Visit plugin site</a>' ),
			$this->get_meta(
				array(
					'slug'      => $this->slug,
					'PluginURI' => 'https://example.org/plugin/',
				)
			)
		);
	}

	/**
	 * Tests that empty fields are left out.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_values
	 *
	 * @param mixed $value The empty value of the version, author, author URI and plugin URI.
	 */
	public function test_should_leave_out_empty_fields( $value ) {
		$this->assertSame(
			array(),
			$this->get_meta(
				array(
					'Version'   => $value,
					'Author'    => $value,
					'AuthorURI' => 'https://example.org/jane/',
					'PluginURI' => $value,
				)
			)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_values() {
		return array(
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
		);
	}

	/**
	 * Tests that an empty or '0' author URI does not link the author.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_values
	 *
	 * @param string $empty_uri Empty or '0' author URI.
	 */
	public function test_should_not_link_author_when_author_uri_is_empty( $empty_uri ) {
		$this->assertSame(
			array( 'By Jane' ),
			$this->get_meta(
				array(
					'Author'    => 'Jane',
					'AuthorURI' => $empty_uri,
				)
			)
		);
	}

	/**
	 * Tests that the filter receives the plugin file, data and status, and its links are joined.
	 *
	 * @ticket 65819
	 */
	public function test_should_filter_and_join_meta() {
		add_filter(
			'plugin_row_meta',
			static function ( $meta ) {
				$meta[] = '<a href="#">Docs</a>';

				return $meta;
			}
		);

		$output = $this->render( array( 'Version' => '1.2.3' ) );

		$this->assertIsArray( $this->filter_args, 'The plugin_row_meta filter should run.' );
		$this->assertSame( $this->get_file(), $this->filter_args[1], 'The filter should receive the plugin file.' );
		$this->assertSame( 'My Plugin', $this->filter_args[2]['Name'], 'The filter should receive the plugin data.' );
		$this->assertSame( 'all', $this->filter_args[3], 'The filter should receive the status.' );
		$this->assertStringContainsString( 'plugin-version-author-uri\'>Version 1.2.3 | <a href="#">Docs</a></div>', $output, 'The links should be joined.' );
	}

	/**
	 * Captures the arguments of the plugin_row_meta filter.
	 *
	 * @param string[] $meta        The row meta.
	 * @param string   $plugin_file The plugin file.
	 * @param array    $plugin_data The plugin data.
	 * @param string   $status      The plugin status.
	 * @return string[] The unchanged row meta.
	 */
	public function capture_meta( $meta, $plugin_file, $plugin_data, $status ) {
		$this->filter_args = array( $meta, $plugin_file, $plugin_data, $status );

		return $meta;
	}

	/**
	 * Renders the row of the test plugin and returns its row meta.
	 *
	 * @param array $plugin_data The plugin data to add.
	 * @return string[] The row meta, before the plugin_row_meta filter.
	 */
	private function get_meta( $plugin_data ) {
		$this->render( $plugin_data );

		$this->assertIsArray( $this->filter_args, 'The plugin_row_meta filter should run.' );

		return $this->filter_args[0];
	}

	/**
	 * Renders the row of the test plugin.
	 *
	 * @param array $plugin_data The plugin data to add.
	 * @return string The row markup.
	 */
	private function render( $plugin_data ) {
		// is_network_only_plugin() reads the plugin headers only on multisite.
		if ( is_multisite() ) {
			if ( ! is_writable( WP_PLUGIN_DIR ) ) {
				$this->markTestSkipped( 'WP_PLUGIN_DIR is not writable.' );
			}

			$dir = WP_PLUGIN_DIR . '/' . $this->slug;
			$this->remove_plugin();
			$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
			$this->assertNotFalse( file_put_contents( $dir . '/' . $this->slug . '.php', "<?php\n/**\n * Plugin Name: My Plugin\n */\n" ), 'The test plugin should be written.' );
		}

		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );

		return get_echo(
			array( $table, 'single_row' ),
			array(
				array(
					$this->get_file(),
					array_merge(
						array(
							'Name'        => 'My Plugin',
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
	 * Removes the test plugin.
	 */
	private function remove_plugin() {
		if ( is_multisite() && $this->slug ) {
			$dir = WP_PLUGIN_DIR . '/' . $this->slug;

			if ( is_dir( $dir ) ) {
				$this->rmdir( $dir );
				$this->delete_folders( $dir );
			}
		}
	}
}
