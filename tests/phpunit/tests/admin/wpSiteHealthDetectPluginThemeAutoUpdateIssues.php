<?php

/**
 * Tests for WP_Site_Health::detect_plugin_theme_auto_update_issues().
 *
 * @group admin
 * @group site-health
 * @group plugins
 *
 * @covers WP_Site_Health::detect_plugin_theme_auto_update_issues
 */
class Tests_Admin_wpSiteHealthDetectPluginThemeAutoUpdateIssues extends WP_UnitTestCase {

	/**
	 * The filters the tests control.
	 *
	 * @var string[]
	 */
	private static $hooks = array( 'auto_update_plugin', 'auto_update_theme', 'plugins_auto_update_enabled', 'themes_auto_update_enabled' );

	/**
	 * Loads the Site Health class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	/**
	 * Removes any callbacks on the filters the tests control. The hooks are restored after each test.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( self::$hooks as $hook ) {
			remove_all_filters( $hook );
		}
	}

	/**
	 * Tests the reported status for each combination of auto-update filters and settings UI.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_auto_update_states
	 *
	 * @param bool|null $plugin_updates Value of the auto_update_plugin filter, or null for no filter.
	 * @param bool|null $theme_updates  Value of the auto_update_theme filter, or null for no filter.
	 * @param bool      $plugins_ui     Whether the plugins auto-update UI is enabled.
	 * @param bool      $themes_ui      Whether the themes auto-update UI is enabled.
	 * @param string    $status         The expected status.
	 * @param string    $message        The expected message.
	 */
	public function test_should_report_auto_update_issues( $plugin_updates, $theme_updates, $plugins_ui, $themes_ui, $status, $message ) {
		$this->add_value_filter( 'auto_update_plugin', $plugin_updates );
		$this->add_value_filter( 'auto_update_theme', $theme_updates );
		$this->add_value_filter( 'plugins_auto_update_enabled', $plugins_ui );
		$this->add_value_filter( 'themes_auto_update_enabled', $themes_ui );

		$site_health = new WP_Site_Health();

		$result = $site_health->detect_plugin_theme_auto_update_issues();

		$this->assertIsObject( $result, 'The result should be an object.' );
		$this->assertSame(
			array(
				'status'  => $status,
				'message' => $message,
			),
			(array) $result,
			'The status and message should match.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_auto_update_states() {
		$critical = 'Auto-updates for plugins and/or themes appear to be disabled, but settings are still set to be displayed. This could cause auto-updates to not work as expected.';
		$both     = 'Auto-updates for plugins and themes appear to be disabled. This will prevent your site from receiving new versions automatically when available.';
		$plugins  = 'Auto-updates for plugins appear to be disabled. This will prevent your site from receiving new versions automatically when available.';
		$themes   = 'Auto-updates for themes appear to be disabled. This will prevent your site from receiving new versions automatically when available.';
		$good     = 'There appear to be no issues with plugin and theme auto-updates.';

		return array(
			'plugin updates off, plugins UI on'     => array( false, null, true, false, 'critical', $critical ),
			'theme updates off, themes UI on'       => array( null, false, false, true, 'critical', $critical ),
			'both off, both UIs on'                 => array( false, false, true, true, 'critical', $critical ),
			'both off, UIs off'                     => array( false, false, false, false, 'recommended', $both ),
			'plugin updates off, UIs off'           => array( false, null, false, false, 'recommended', $plugins ),
			'plugin updates off, theme updates on'  => array( false, true, false, false, 'recommended', $plugins ),
			'theme updates off, UIs off'            => array( null, false, false, false, 'recommended', $themes ),
			'theme updates off, plugin updates on'  => array( true, false, false, false, 'recommended', $themes ),
			'no filters, UIs off'                   => array( null, null, false, false, 'good', $good ),
			'no filters, UIs on'                    => array( null, null, true, true, 'good', $good ),
			'both forced on, UIs on'                => array( true, true, true, true, 'good', $good ),
			'plugin updates off, UIs on for themes' => array( false, null, false, true, 'recommended', $plugins ),
		);
	}

	/**
	 * Tests that the auto-update filters receive an available update for a fake plugin and theme.
	 *
	 * @ticket 65819
	 */
	public function test_should_pass_mock_items_to_auto_update_filters() {
		$plugin_filter = new MockAction();
		$theme_filter  = new MockAction();
		add_filter( 'auto_update_plugin', array( $plugin_filter, 'filter' ), 10, 2 );
		add_filter( 'auto_update_theme', array( $theme_filter, 'filter' ), 10, 2 );

		$site_health = new WP_Site_Health();
		$site_health->detect_plugin_theme_auto_update_issues();

		$plugin_args = $plugin_filter->get_args();
		$theme_args  = $theme_filter->get_args();

		$this->assertTrue( $plugin_args[0][0], 'The plugin filter should receive true.' );
		$this->assertSame( 'a-fake-plugin/a-fake-plugin.php', $plugin_args[0][1]->plugin, 'The plugin filter should receive the fake plugin.' );
		$this->assertTrue( $theme_args[0][0], 'The theme filter should receive true.' );
		$this->assertSame( 'a-fake-theme', $theme_args[0][1]->theme, 'The theme filter should receive the fake theme.' );
	}

	/**
	 * Adds a filter that returns the given value.
	 *
	 * @param string    $hook  The filter name.
	 * @param bool|null $value The value to return, or null for no filter.
	 */
	private function add_value_filter( $hook, $value ) {
		if ( null === $value ) {
			return;
		}

		add_filter( $hook, $value ? '__return_true' : '__return_false' );
	}
}
