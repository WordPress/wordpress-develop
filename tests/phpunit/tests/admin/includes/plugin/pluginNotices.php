<?php

/**
 * Tests for paused_plugins_notice() and deactivated_plugins_notice().
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_Includes_Plugin_PluginNotices extends WP_UnitTestCase {

	/**
	 * Names of the globals changed by the tests.
	 *
	 * @var string[]
	 */
	const GLOBALS_USED = array( 'pagenow', '_paused_plugins', 'wp_version' );

	/**
	 * ID of a user who can manage plugins.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * ID of a user who cannot manage plugins.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Original values of the globals that were set before the test, keyed by name.
	 *
	 * @var array
	 */
	private $orig_globals = array();

	/**
	 * Records the admin notices displayed during the test.
	 *
	 * @var MockAction
	 */
	private $notices;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->orig_globals = array();

		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->orig_globals[ $name ] = $GLOBALS[ $name ];
			}
		}

		$GLOBALS['pagenow']    = 'index.php';
		$GLOBALS['wp_version'] = '9.9';

		unset( $GLOBALS['_paused_plugins'] );

		$this->notices = new MockAction();
		add_action( 'wp_admin_notice', array( $this->notices, 'action' ), 10, 2 );

		wp_set_current_user( self::$admin_id );
	}

	public function tear_down() {
		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $this->orig_globals ) ) {
				$GLOBALS[ $name ] = $this->orig_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that paused_plugins_notice() displays an error notice linking to the paused plugins.
	 *
	 * @ticket 65819
	 *
	 * @covers ::paused_plugins_notice
	 */
	public function test_paused_plugins_notice_displays_error_notice() {
		$GLOBALS['_paused_plugins'] = array( 'my-plugin' => array( 'message' => 'Fatal error' ) );

		$output = get_echo( 'paused_plugins_notice' );

		$this->assertSame(
			array(
				array(
					'<strong>One or more plugins failed to load properly.</strong><br>You can find more details and make changes on the Plugins screen.</p><p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php?plugin_status=paused">Go to the Plugins screen</a>',
					array( 'type' => 'error' ),
				),
			),
			$this->notices->get_args(),
			'One error notice should be displayed.'
		);
		$this->assertStringContainsString( 'One or more plugins failed to load properly.', $output, 'The notice should be printed.' );
	}

	/**
	 * Tests that paused_plugins_notice() displays nothing when the notice does not apply.
	 *
	 * @ticket 65819
	 *
	 * @covers ::paused_plugins_notice
	 *
	 * @dataProvider data_paused_plugins_notice_is_not_displayed
	 *
	 * @param string     $pagenow        Current admin page.
	 * @param bool       $can_resume     Whether the current user can resume plugins.
	 * @param array|null $paused_plugins Value of the $_paused_plugins global, or null to leave it unset.
	 */
	public function test_paused_plugins_notice_is_not_displayed( $pagenow, $can_resume, $paused_plugins ) {
		$GLOBALS['pagenow'] = $pagenow;

		if ( null !== $paused_plugins ) {
			$GLOBALS['_paused_plugins'] = $paused_plugins;
		}

		wp_set_current_user( $can_resume ? self::$admin_id : self::$subscriber_id );

		$this->assertSame( '', get_echo( 'paused_plugins_notice' ), 'Nothing should be printed.' );
		$this->assertSame( 0, $this->notices->get_call_count(), 'No notice should be displayed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_paused_plugins_notice_is_not_displayed() {
		$paused_plugins = array( 'my-plugin' => array( 'message' => 'Fatal error' ) );

		return array(
			'on the Plugins screen'         => array( 'plugins.php', true, $paused_plugins ),
			'user cannot resume plugins'    => array( 'index.php', false, $paused_plugins ),
			'paused plugins global not set' => array( 'index.php', true, null ),
			'no paused plugins'             => array( 'index.php', true, array() ),
		);
	}

	/**
	 * Tests the notice displayed for each plugin deactivated during an upgrade.
	 *
	 * @ticket 65819
	 *
	 * @covers ::deactivated_plugins_notice
	 */
	public function test_deactivated_plugins_notice_displays_warning_for_each_plugin() {
		update_option(
			'wp_force_deactivated_plugins',
			array(
				'my-plugin/my-plugin.php'       => array(
					'plugin_name'         => 'My Plugin',
					'version_deactivated' => '1.0',
					'version_compatible'  => '2.0',
				),
				'other-plugin/other-plugin.php' => array(
					'plugin_name'         => 'Other Plugin',
					'version_deactivated' => '3.1',
					'version_compatible'  => '',
				),
				'third-plugin/third-plugin.php' => array(
					'plugin_name'         => 'Third Plugin',
					'version_deactivated' => '',
					'version_compatible'  => '4.0',
				),
			),
			false
		);

		if ( is_multisite() ) {
			update_site_option( 'wp_force_deactivated_plugins', array() );
		}

		$link = '</p><p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php?plugin_status=inactive">Go to the Plugins screen</a>';

		get_echo( 'deactivated_plugins_notice' );

		$this->assertSame(
			array(
				array(
					'<strong>My Plugin plugin deactivated during WordPress upgrade.</strong><br>My Plugin 1.0 was deactivated due to incompatibility with WordPress 9.9, please upgrade to My Plugin 2.0 or later.' . $link,
					array( 'type' => 'warning' ),
				),
				array(
					'<strong>Other Plugin plugin deactivated during WordPress upgrade.</strong><br>Other Plugin 3.1 was deactivated due to incompatibility with WordPress 9.9.' . $link,
					array( 'type' => 'warning' ),
				),
				array(
					'<strong>Third Plugin plugin deactivated during WordPress upgrade.</strong><br>Third Plugin  was deactivated due to incompatibility with WordPress 9.9.' . $link,
					array( 'type' => 'warning' ),
				),
			),
			$this->notices->get_args(),
			'One warning notice should be displayed for each plugin.'
		);
		$this->assertSame( array(), get_option( 'wp_force_deactivated_plugins' ), 'The list of deactivated plugins should be emptied.' );
	}

	/**
	 * Tests that plugins deactivated across the network are included on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 *
	 * @covers ::deactivated_plugins_notice
	 */
	public function test_deactivated_plugins_notice_includes_network_plugins() {
		update_option( 'wp_force_deactivated_plugins', array(), false );
		update_site_option(
			'wp_force_deactivated_plugins',
			array(
				'network-plugin/network-plugin.php' => array(
					'plugin_name'         => 'Network Plugin',
					'version_deactivated' => '1.0',
					'version_compatible'  => '2.0',
				),
			)
		);

		get_echo( 'deactivated_plugins_notice' );

		$this->assertSame(
			array(
				array(
					'<strong>Network Plugin plugin deactivated during WordPress upgrade.</strong><br>Network Plugin 1.0 was deactivated due to incompatibility with WordPress 9.9, please upgrade to Network Plugin 2.0 or later.</p><p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php?plugin_status=inactive">Go to the Plugins screen</a>',
					array( 'type' => 'warning' ),
				),
			),
			$this->notices->get_args(),
			'A warning notice should be displayed for the network plugin.'
		);
		$this->assertSame( array(), get_site_option( 'wp_force_deactivated_plugins' ), 'The network list of deactivated plugins should be emptied.' );
	}

	/**
	 * Tests that deactivated_plugins_notice() creates the option when it does not exist.
	 *
	 * @ticket 65819
	 *
	 * @covers ::deactivated_plugins_notice
	 */
	public function test_deactivated_plugins_notice_creates_missing_option() {
		delete_option( 'wp_force_deactivated_plugins' );
		delete_site_option( 'wp_force_deactivated_plugins' );

		$this->assertSame( '', get_echo( 'deactivated_plugins_notice' ), 'Nothing should be printed.' );
		$this->assertSame( 0, $this->notices->get_call_count(), 'No notice should be displayed.' );
		$this->assertSame( array(), get_option( 'wp_force_deactivated_plugins' ), 'The option should be created as an empty array.' );
		$this->assertSame( array(), get_site_option( 'wp_force_deactivated_plugins' ), 'The network option should be an empty array.' );
	}

	/**
	 * Tests that deactivated_plugins_notice() displays nothing and keeps the list when the notice does not apply.
	 *
	 * @ticket 65819
	 *
	 * @covers ::deactivated_plugins_notice
	 *
	 * @dataProvider data_deactivated_plugins_notice_is_not_displayed
	 *
	 * @param string $pagenow             Current admin page.
	 * @param bool   $can_activate        Whether the current user can activate plugins.
	 * @param array  $deactivated_plugins Value of the wp_force_deactivated_plugins option.
	 */
	public function test_deactivated_plugins_notice_is_not_displayed( $pagenow, $can_activate, $deactivated_plugins ) {
		$GLOBALS['pagenow'] = $pagenow;

		update_option( 'wp_force_deactivated_plugins', $deactivated_plugins, false );
		wp_set_current_user( $can_activate ? self::$admin_id : self::$subscriber_id );

		$this->assertSame( '', get_echo( 'deactivated_plugins_notice' ), 'Nothing should be printed.' );
		$this->assertSame( 0, $this->notices->get_call_count(), 'No notice should be displayed.' );
		$this->assertSame( $deactivated_plugins, get_option( 'wp_force_deactivated_plugins' ), 'The list of deactivated plugins should be kept.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_deactivated_plugins_notice_is_not_displayed() {
		$deactivated_plugins = array(
			'my-plugin/my-plugin.php' => array(
				'plugin_name'         => 'My Plugin',
				'version_deactivated' => '1.0',
				'version_compatible'  => '2.0',
			),
		);

		return array(
			'on the Plugins screen'        => array( 'plugins.php', true, $deactivated_plugins ),
			'user cannot activate plugins' => array( 'index.php', false, $deactivated_plugins ),
			'no deactivated plugins'       => array( 'index.php', true, array() ),
		);
	}
}
