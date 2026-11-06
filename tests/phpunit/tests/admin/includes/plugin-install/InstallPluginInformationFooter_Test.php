<?php

/**
 * Tests for the notices and the footer of install_plugin_information().
 *
 * @group plugins
 * @group admin
 *
 * @covers ::install_plugin_information
 */
class Admin_Includes_Plugin_Install_InstallPluginInformationFooter_Test extends WP_UnitTestCase {

	/**
	 * The message of the exception that stops the screen before it ends the request.
	 */
	const STOP = 'Stop the plugin information screen.';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original values of the globals and the request that the tests change.
	 *
	 * @var array
	 */
	private $original = array();

	/**
	 * The slugs that the Plugin Installation API was asked for.
	 *
	 * @var string[]
	 */
	private $api_requests = array();

	/**
	 * Creates the administrator, and loads the tested files.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin fixture.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Saves the globals and the request.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'tab', 'hook_suffix', 'current_screen' ) as $name ) {
			$this->original['globals'][ $name ] = $GLOBALS[ $name ] ?? null;
		}

		$this->original['request'] = $_REQUEST;
		$this->api_requests        = array();

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Restores the globals and the request.
	 */
	public function tear_down() {
		foreach ( $this->original['globals'] as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		$_REQUEST = $this->original['request'];

		parent::tear_down();
	}

	/**
	 * Returns the data of a plugin, as the API returns it.
	 *
	 * @param array $overrides Optional. Values that replace the defaults. Default none.
	 * @return object The data.
	 */
	private function get_api( array $overrides = array() ) {
		return (object) array_merge(
			array(
				'name'     => 'My Plugin',
				'slug'     => 'my-plugin',
				'version'  => '1.0',
				'external' => true,
				'sections' => array(
					'description'  => '<p>The description.</p>',
					'installation' => '<p>The installation.</p>',
					'faq'          => '<p>The questions.</p>',
				),
			),
			$overrides
		);
	}

	/**
	 * Outputs the screen of a plugin, and returns the output.
	 *
	 * The screen ends the request, so an action of the footer stops it before.
	 *
	 * @param object|WP_Error $api     The response of the API.
	 * @param array           $request Optional. The request data besides the plugin. Default none.
	 * @return string The output.
	 */
	private function render( $api, array $request = array() ) {
		$GLOBALS['tab']         = 'plugin-information';
		$GLOBALS['hook_suffix'] = 'plugin-install.php';

		set_current_screen( 'plugin-install' );

		$_REQUEST = array_merge( array( 'plugin' => $api->slug ?? 'my-plugin' ), $request );

		add_filter(
			'plugins_api',
			function ( $result, $action, $args ) use ( $api ) {
				$this->api_requests[] = $args->slug;

				return $api;
			},
			10,
			3
		);
		add_action(
			'admin_footer',
			static function () {
				throw new RuntimeException( self::STOP );
			}
		);

		// The headers have been sent by the output of the tests. Other errors are left to the handler of the tests.
		$previous_handler = set_error_handler(
			static function ( $errno, $errstr, $errfile, $errline ) use ( &$previous_handler ) {
				if ( str_contains( $errstr, 'headers already sent' ) ) {
					return true;
				}

				return $previous_handler ? $previous_handler( $errno, $errstr, $errfile, $errline ) : false;
			},
			E_WARNING
		);

		ob_start();

		try {
			install_plugin_information();
			$this->fail( 'The screen should stop before it ends the request.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( self::STOP, $e->getMessage() );
		} finally {
			restore_error_handler();
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * Limits what the user can do with a filter, because updating PHP needs the capability to update WordPress.
	 *
	 * @param bool $can_update_core Whether the user can update WordPress.
	 * @param bool $can_update_php  Whether the user can learn about updating PHP.
	 */
	private function limit_updates( $can_update_core, $can_update_php ) {
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) use ( $can_update_core, $can_update_php ) {
				if ( 'update_core' === $cap ) {
					return $can_update_core ? array( 'exist' ) : array( 'do_not_allow' );
				}

				if ( 'update_php' === $cap ) {
					return $can_update_php ? array( 'exist' ) : array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			2
		);
	}

	/**
	 * Tests that no notice is output for a plugin that is compatible.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_output_a_notice_for_a_plugin_that_is_compatible() {
		$output = $this->render( $this->get_api( array( 'tested' => '99.0' ) ) );

		$this->assertStringNotContainsString( 'notice-alt', $output );
	}

	/**
	 * Tests the notice for a plugin that needs a newer version of PHP, by what the user can do.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_php_notice
	 *
	 * @param bool   $can_update_php Whether the user can learn about updating PHP.
	 * @param string $expected       What the notice should contain.
	 * @param string $unexpected     What the notice should not contain.
	 */
	public function test_should_output_a_notice_for_a_plugin_that_needs_a_newer_version_of_php( $can_update_php, $expected, $unexpected ) {
		$this->limit_updates( $can_update_php, $can_update_php );

		$output = $this->render( $this->get_api( array( 'requires_php' => '99.0' ) ) );

		$this->assertStringContainsString( 'notice notice-error notice-alt', $output );
		$this->assertStringContainsString( '<strong>Error:</strong> This plugin <strong>requires a newer version of PHP</strong>.', $output );
		$this->assertStringContainsString( $expected, $output );
		$this->assertStringNotContainsString( $unexpected, $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_php_notice() {
		return array(
			'a user who can update PHP' => array( true, 'Click here to learn more about updating PHP</a>.', 'Warning:' ),
			'a user who cannot'         => array( false, 'requires a newer version of PHP</strong>.</p>', 'Click here to learn more' ),
		);
	}

	/**
	 * Tests the warning for a plugin that has not been tested with the version of WordPress.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_a_warning_for_a_plugin_that_has_not_been_tested() {
		$output = $this->render( $this->get_api( array( 'tested' => '1.0' ) ) );

		$this->assertStringContainsString( 'notice notice-warning notice-alt', $output );
		$this->assertStringContainsString( '<strong>Warning:</strong> This plugin <strong>has not been tested</strong> with your current version of WordPress.', $output );
	}

	/**
	 * Tests the notice for a plugin that needs a newer version of WordPress, by what the user can do.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_wordpress_notice
	 *
	 * @param bool $can_update_core Whether the user can update WordPress.
	 */
	public function test_should_output_a_notice_for_a_plugin_that_needs_a_newer_version_of_wordpress( $can_update_core ) {
		$this->limit_updates( $can_update_core, false );

		$output = $this->render( $this->get_api( array( 'requires' => '99.0' ) ) );

		$this->assertStringContainsString( 'notice notice-error notice-alt', $output );
		$this->assertStringContainsString( '<strong>Error:</strong> This plugin <strong>requires a newer version of WordPress</strong>.', $output );
		$this->assertSame( $can_update_core, str_contains( $output, '<a href="' . esc_url( self_admin_url( 'update-core.php' ) ) . '" target="_parent">Click here to update WordPress</a>.' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wordpress_notice() {
		return array(
			'a user who can update WordPress' => array( true ),
			'a user who cannot'               => array( false ),
		);
	}

	/**
	 * Tests that the warning about a plugin that has not been tested is output instead of the notice about the version of WordPress.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_only_the_warning_for_a_plugin_that_has_not_been_tested_and_needs_a_newer_wordpress() {
		$output = $this->render(
			$this->get_api(
				array(
					'tested'   => '1.0',
					'requires' => '99.0',
				)
			)
		);

		$this->assertStringContainsString( 'has not been tested', $output );
		$this->assertStringNotContainsString( 'requires a newer version of WordPress', $output );
	}

	/**
	 * Tests the footer of the screen.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_the_footer_with_the_templates_of_the_updates() {
		$output = $this->render( $this->get_api() );

		$this->assertStringContainsString( "<div id='plugin-information-footer'>", $output );
		$this->assertStringContainsString( 'id="tmpl-wp-bulk-updates-admin-notice"', $output, 'The templates of the notices should be output.' );
	}

	/**
	 * Tests the button to install a plugin.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_the_button_to_install_the_plugin() {
		$output = $this->render( $this->get_api( array( 'download_link' => 'https://downloads.example.org/my-plugin.zip' ) ) );
		$footer = substr( $output, strpos( $output, "id='plugin-information-footer'" ) );

		$this->assertStringContainsString( 'class="right install-now button button-compact"', $footer, 'The button should be on the right.' );
		$this->assertStringContainsString( 'id="plugin_install_from_iframe"', $footer, 'The button should have the identifier of the button of the screen.' );
		$this->assertStringContainsString( 'data-slug="my-plugin"', $footer );
		$this->assertStringContainsString( '>Install Now</a>', $footer );
	}

	/**
	 * Tests that no button is output without a package.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_output_a_button_without_a_package() {
		$output = $this->render( $this->get_api() );
		$footer = substr( $output, strpos( $output, "id='plugin-information-footer'" ) );

		$this->assertStringStartsWith( "id='plugin-information-footer'>\n</div>", $footer, 'The footer should be empty.' );
		$this->assertStringNotContainsString( 'plugin_install_from_iframe', $output );
	}

	/**
	 * Tests that no button is output for a user who cannot install or update plugins.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_output_a_button_for_a_user_who_cannot_install_or_update_plugins() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$output = $this->render( $this->get_api( array( 'download_link' => 'https://downloads.example.org/my-plugin.zip' ) ) );

		$this->assertStringNotContainsString( 'plugin_install_from_iframe', $output );
	}

	/**
	 * Tests that the button to activate a plugin does not get the identifier of the button to install.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_give_the_identifier_of_the_install_button_to_the_activate_button() {
		$plugin  = 'custom-internationalized-plugin';
		$version = get_plugins( '/' . $plugin )[ $plugin . '.php' ]['Version'];

		$output = $this->render(
			$this->get_api(
				array(
					'slug'          => $plugin,
					'version'       => $version,
					'download_link' => 'https://downloads.example.org/' . $plugin . '.zip',
				)
			)
		);

		$footer = substr( $output, strpos( $output, "id='plugin-information-footer'" ) );

		$this->assertStringContainsString( 'class="right button button-compact button-primary activate-now"', $footer, 'The button should be to activate the plugin, on the right.' );
		$this->assertStringContainsString( '>Activate</a>', $footer );
		$this->assertStringNotContainsString( 'plugin_install_from_iframe', $output );
	}

	/**
	 * Tests the button to update a plugin for a user who can update plugins but not install them.
	 *
	 * The 'ms-excluded' group is added, because only a super admin can update plugins on a network.
	 *
	 * @ticket 66566
	 *
	 * @group ms-excluded
	 */
	public function test_should_output_the_button_to_update_for_a_user_who_can_only_update_plugins() {
		$plugin = 'custom-internationalized-plugin';

		set_site_transient(
			'update_plugins',
			(object) array(
				'response' => array(
					$plugin . '/' . $plugin . '.php' => (object) array(
						'slug'        => $plugin,
						'plugin'      => $plugin . '/' . $plugin . '.php',
						'new_version' => '2.0.0',
					),
				),
			)
		);

		$user = wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$user->add_cap( 'update_plugins' );

		$output = $this->render(
			$this->get_api(
				array(
					'slug'          => $plugin,
					'version'       => '2.0.0',
					'download_link' => 'https://downloads.example.org/' . $plugin . '.zip',
				)
			)
		);
		$footer = substr( $output, strpos( $output, "id='plugin-information-footer'" ) );

		$this->assertStringContainsString( 'class="right update-now button button-compact aria-button-if-js"', $footer );
		$this->assertStringContainsString( '>Update Now</a>', $footer );

		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Tests the dialog for the credentials of the filesystem, when the filesystem needs them.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_the_dialog_for_the_credentials_of_the_filesystem_when_it_needs_them() {
		add_filter(
			'filesystem_method',
			static function () {
				return 'ftpext';
			}
		);

		$this->assertStringContainsString( 'id="request-filesystem-credentials-dialog"', $this->render( $this->get_api() ) );
	}
}
