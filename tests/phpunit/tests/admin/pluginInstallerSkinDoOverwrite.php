<?php

/**
 * Tests for Plugin_Installer_Skin::do_overwrite().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Installer_Skin::do_overwrite
 */
class Tests_Admin_PluginInstallerSkinDoOverwrite extends WP_UnitTestCase {

	/**
	 * The slug of the installed test plugin.
	 *
	 * @var string
	 */
	const SLUG = 'overwrite-test-plugin';

	/**
	 * The upload URL passed to the skin.
	 *
	 * @var string
	 */
	const URL = 'update.php?action=upload-plugin&package=123';

	/**
	 * Loads the upgrader classes.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Removes a leftover test plugin and clears the plugins cache.
	 */
	public function set_up() {
		parent::set_up();

		$this->remove_plugin();
	}

	/**
	 * Removes the test plugin and clears the plugins cache.
	 */
	public function tear_down() {
		$this->remove_plugin();

		parent::tear_down();
	}

	/**
	 * Tests that nothing is output unless an upload hit an existing plugin folder.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_not_an_overwrite
	 *
	 * @param string $type       The skin type.
	 * @param string $error_code The result error code, or an empty string for a successful result.
	 */
	public function test_should_return_false_when_not_overwriting( $type, $error_code ) {
		$this->create_plugin( array( 'Version' => '1.0.0' ) );

		$result = '' === $error_code ? true : new WP_Error( $error_code, 'Error.', WP_PLUGIN_DIR . '/' . self::SLUG . '/' );
		$skin   = $this->get_skin( $result, array( 'Name' => 'Overwrite Test Plugin' ), $type );

		$this->assertSame( array( false, '' ), $this->do_overwrite( $skin ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_not_an_overwrite() {
		return array(
			'web install'        => array( 'web', 'folder_exists' ),
			'successful upload'  => array( 'upload', '' ),
			'other upload error' => array( 'upload', 'incompatible_archive' ),
		);
	}

	/**
	 * Tests that nothing is output when the existing folder holds no plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_when_existing_folder_has_no_plugin() {
		$this->create_plugin( array( 'Version' => '1.0.0' ) );

		$skin = $this->get_skin(
			new WP_Error( 'folder_exists', 'Error.', WP_PLUGIN_DIR . '/other-plugin/' ),
			array( 'Name' => 'Overwrite Test Plugin' )
		);

		$this->assertSame( array( false, '' ), $this->do_overwrite( $skin ) );
	}

	/**
	 * Tests that nothing is output when the uploaded plugin data is missing.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_without_uploaded_plugin_data() {
		$this->create_plugin( array( 'Version' => '1.0.0' ) );

		$skin = $this->get_skin( $this->get_folder_exists_error(), array() );

		$this->assertSame( array( false, '' ), $this->do_overwrite( $skin ) );
	}

	/**
	 * Tests the comparison table, notice and actions when updating a plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_update_comparison_and_actions() {
		$this->create_plugin(
			array(
				'Version'     => '1.0.0',
				'Author'      => 'Jane <b>Doe</b>',
				'RequiresPHP' => '7.4',
			)
		);

		$skin = $this->get_skin(
			$this->get_folder_exists_error(),
			array(
				'Name'        => 'Overwrite Test Plugin',
				'Version'     => '2.0.0',
				'Author'      => 'Jane <em>&</em> Co',
				'RequiresWP'  => '5.0',
				'RequiresPHP' => '',
			)
		);

		$this->assertSame(
			array(
				true,
				'<h2 class="update-from-upload-heading">This plugin is already installed.</h2>'
				. '<table class="update-from-upload-comparison"><tbody>'
				. '<tr><th></th><th>Current</th><th>Uploaded</th></tr>'
				. '<tr><td class="name-label">Plugin name</td><td>Overwrite Test Plugin</td><td>Overwrite Test Plugin</td></tr>'
				. '<tr><td class="name-label">Version</td><td>1.0.0</td><td>2.0.0</td></tr>'
				. '<tr><td class="name-label">Author</td><td>Jane Doe</td><td class="warning">Jane & Co</td></tr>'
				. '<tr><td class="name-label">Required WordPress version</td><td>-</td><td class="warning">5.0</td></tr>'
				. '<tr><td class="name-label">Required PHP version</td><td>7.4</td><td class="warning">-</td></tr>'
				. '</tbody></table>'
				. '<p class="update-from-upload-notice">You are updating a plugin. Be sure to <a href="https://developer.wordpress.org/advanced-administration/security/backup/">back up your database and files</a> first.</p>'
				. $this->get_expected_actions( 'update-plugin' ),
			),
			$this->do_overwrite( $skin )
		);
	}

	/**
	 * Tests the version warning, notice and overwrite URL when downgrading a plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_downgrade_warning() {
		$this->create_plugin( array( 'Version' => '2.0.0' ) );

		$skin = $this->get_skin(
			$this->get_folder_exists_error(),
			array(
				'Name'    => 'Overwrite Test Plugin',
				'Version' => '1.9.9',
			)
		);

		list( $overwrites, $output ) = $this->do_overwrite( $skin );

		$this->assertTrue( $overwrites, 'The overwrite screen should be shown.' );
		$this->assertStringContainsString(
			'<tr><td class="name-label">Version</td><td>2.0.0</td><td class="warning">1.9.9</td></tr>',
			$output,
			'The uploaded version should be marked as a warning.'
		);
		$this->assertStringContainsString(
			'<p class="update-from-upload-notice">You are uploading an older version of a current plugin. You can continue to install the older version, but be sure to <a href="https://developer.wordpress.org/advanced-administration/security/backup/">back up your database and files</a> first.</p>',
			$output,
			'The downgrade notice should be shown.'
		);
		$this->assertStringEndsWith( $this->get_expected_actions( 'downgrade-plugin' ), $output, 'The overwrite URL should downgrade the plugin.' );
	}

	/**
	 * Tests that the plugin cannot be replaced when the uploaded plugin's requirements are not met.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unmet_requirements
	 *
	 * @param array    $requirements The uploaded plugin's requirements.
	 * @param string[] $reasons      The expected reasons, as format strings for PHP_VERSION and the WordPress version.
	 */
	public function test_should_block_update_when_requirements_are_not_met( $requirements, $reasons ) {
		$this->create_plugin( array( 'Version' => '1.0.0' ) );

		$skin = $this->get_skin(
			$this->get_folder_exists_error(),
			array_merge(
				array(
					'Name'    => 'Overwrite Test Plugin',
					'Version' => '2.0.0',
				),
				$requirements
			)
		);

		list( $overwrites, $output ) = $this->do_overwrite( $skin );

		$items = '';
		foreach ( $reasons as $reason ) {
			$items .= '<li>' . sprintf( $reason, PHP_VERSION, wp_get_wp_version() ) . '</li>';
		}

		$this->assertTrue( $overwrites, 'The overwrite screen should be shown.' );
		$this->assertStringContainsString(
			'</table><p>The plugin cannot be updated due to the following:</p><ul class="ul-disc">' . $items . '</ul>',
			$output,
			'The reasons should be listed after the table.'
		);
		$this->assertStringNotContainsString( 'update-from-upload-notice', $output, 'No backup notice should be shown.' );
		$this->assertStringEndsWith( $this->get_expected_actions( '' ), $output, 'Only the cancel action should be shown.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unmet_requirements() {
		$php = 'The PHP version on your server is %1$s, however the uploaded plugin requires 99.0.';
		$wp  = 'Your WordPress version is %2$s, however the uploaded plugin requires 99.0.';

		return array(
			'PHP'               => array( array( 'RequiresPHP' => '99.0' ), array( $php ) ),
			'WordPress'         => array( array( 'RequiresWP' => '99.0' ), array( $wp ) ),
			'PHP and WordPress' => array(
				array(
					'RequiresPHP' => '99.0',
					'RequiresWP'  => '99.0',
				),
				array( $php, $wp ),
			),
		);
	}

	/**
	 * Tests that the comparison table can be filtered.
	 *
	 * @ticket 65819
	 */
	public function test_should_filter_comparison_table() {
		$this->create_plugin( array( 'Version' => '1.0.0' ) );

		$new_plugin_data = array(
			'Name'    => 'Overwrite Test Plugin',
			'Version' => '2.0.0',
		);

		$filter = new MockAction();
		add_filter( 'install_plugin_overwrite_comparison', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'install_plugin_overwrite_comparison',
			static function () {
				return '<table class="custom"></table>';
			},
			11
		);

		list( , $output ) = $this->do_overwrite( $this->get_skin( $this->get_folder_exists_error(), $new_plugin_data ) );

		$args = $filter->get_args();

		$this->assertStringStartsWith( '<table class="update-from-upload-comparison">', $args[0][0], 'The filter should receive the table.' );
		$this->assertSame( 'Overwrite Test Plugin', $args[0][1]['Name'], 'The filter should receive the current plugin data.' );
		$this->assertSame( $new_plugin_data, $args[0][2], 'The filter should receive the uploaded plugin data.' );
		$this->assertStringContainsString( '</h2><table class="custom"></table><p class="update-from-upload-notice">', $output, 'The filtered table should be output.' );
	}

	/**
	 * Tests that the actions can be filtered, and none are output when they are removed.
	 *
	 * @ticket 65819
	 */
	public function test_should_filter_actions() {
		$this->create_plugin( array( 'Version' => '1.0.0' ) );

		$new_plugin_data = array(
			'Name'    => 'Overwrite Test Plugin',
			'Version' => '2.0.0',
		);

		$filter = new MockAction();
		add_filter( 'install_plugin_overwrite_actions', array( $filter, 'filter' ), 10, 3 );
		add_filter( 'install_plugin_overwrite_actions', '__return_empty_array', 11 );

		$skin = $this->get_skin( $this->get_folder_exists_error(), $new_plugin_data );

		list( $overwrites, $output ) = $this->do_overwrite( $skin );

		$args = $filter->get_args();

		$this->assertTrue( $overwrites, 'The overwrite screen should be shown.' );
		$this->assertSame( array( 'overwrite_plugin', 'plugins_page' ), array_keys( $args[0][0] ), 'The filter should receive both actions.' );
		$this->assertSame( $skin->api, $args[0][1], 'The filter should receive the API data.' );
		$this->assertSame( $new_plugin_data, $args[0][2], 'The filter should receive the uploaded plugin data.' );
		$this->assertStringEndsWith( 'first.</p>', $output, 'No actions should be output.' );
	}

	/**
	 * Tests that after() stops after the overwrite screen.
	 *
	 * @ticket 65819
	 *
	 * @covers Plugin_Installer_Skin::after
	 */
	public function test_after_should_only_output_overwrite_screen() {
		$this->create_plugin( array( 'Version' => '1.0.0' ) );

		$skin = $this->get_skin(
			$this->get_folder_exists_error(),
			array(
				'Name'    => 'Overwrite Test Plugin',
				'Version' => '2.0.0',
			)
		);

		$output = get_echo( array( $skin, 'after' ) );

		$this->assertStringStartsWith( '<h2 class="update-from-upload-heading">', $output, 'The overwrite screen should be output.' );
		$this->assertStringNotContainsString( 'Activate Plugin', $output, 'The install actions should not be output.' );
	}

	/**
	 * Returns the expected actions markup.
	 *
	 * @param string $overwrite The overwrite query argument, or an empty string when the plugin cannot be replaced.
	 * @return string The markup.
	 */
	private function get_expected_actions( $overwrite ) {
		$actions = array();

		if ( '' !== $overwrite ) {
			$actions[] = '<a class="button button-primary update-from-upload-overwrite" href="update.php?action=upload-plugin&amp;package=123&amp;overwrite=' . $overwrite . '&amp;_wpnonce=' . wp_create_nonce( 'plugin-upload' ) . '" target="_parent">Replace current with uploaded</a>';
		}

		$actions[] = '<a class="button" href="update.php?action=upload-plugin-cancel-overwrite&amp;package=123&amp;_wpnonce=' . wp_create_nonce( 'plugin-upload-cancel-overwrite' ) . '">Cancel and go back</a>';

		return '<p class="update-from-upload-expired hidden">The uploaded file has expired. Please go back and upload it again.</p>'
			. '<p class="update-from-upload-actions">' . implode( ' ', $actions ) . '</p>';
	}

	/**
	 * Returns the error the upgrader returns when the plugin folder already exists.
	 *
	 * @return WP_Error The error.
	 */
	private function get_folder_exists_error() {
		return new WP_Error( 'folder_exists', 'Destination folder already exists.', WP_PLUGIN_DIR . '/' . self::SLUG . '/' );
	}

	/**
	 * Returns a skin with the given result and uploaded plugin data.
	 *
	 * @param true|WP_Error $result          The install result.
	 * @param array         $new_plugin_data The uploaded plugin data.
	 * @param string        $type            The skin type.
	 * @return Plugin_Installer_Skin The skin.
	 */
	private function get_skin( $result, $new_plugin_data, $type = 'upload' ) {
		$skin = new Plugin_Installer_Skin(
			array(
				'type' => $type,
				'url'  => self::URL,
			)
		);

		$upgrader                  = new Plugin_Upgrader( $skin );
		$upgrader->new_plugin_data = $new_plugin_data;

		$skin->upgrader = $upgrader;
		$skin->result   = $result;

		return $skin;
	}

	/**
	 * Calls the private do_overwrite() method.
	 *
	 * @param Plugin_Installer_Skin $skin The skin.
	 * @return array The return value and the output.
	 */
	private function do_overwrite( $skin ) {
		$method = new ReflectionMethod( $skin, 'do_overwrite' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$result = $method->invoke( $skin );
		$output = ob_get_clean();

		return array( $result, $output );
	}

	/**
	 * Creates the installed test plugin.
	 *
	 * @param array $headers The Version, Author, RequiresWP and RequiresPHP headers.
	 */
	private function create_plugin( $headers ) {
		$names = array(
			'Version'     => 'Version',
			'Author'      => 'Author',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
		);

		$contents = "<?php\n/**\n * Plugin Name: Overwrite Test Plugin\n";
		foreach ( $headers as $field => $value ) {
			$contents .= " * {$names[ $field ]}: {$value}\n";
		}
		$contents .= " */\n";

		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;

		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse( file_put_contents( $dir . '/' . self::SLUG . '.php', $contents ), 'The test plugin file should be written.' );

		wp_cache_delete( 'plugins', 'plugins' );
	}

	/**
	 * Removes the test plugin and clears the plugins cache.
	 */
	private function remove_plugin() {
		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			rmdir( $dir );
		}

		wp_cache_delete( 'plugins', 'plugins' );
	}
}
