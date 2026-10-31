<?php

/**
 * Tests for WP_Site_Health::get_test_plugin_version().
 *
 * @group admin
 * @group site-health
 * @group plugins
 *
 * @covers WP_Site_Health::get_test_plugin_version
 */
class Tests_Admin_wpSiteHealthGetTestPluginVersion extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Loads the Site Health class and sets up the user.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

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
	 * Resets the plugins cache, the active plugins and the update transient.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Clears the plugins cache and the update transient.
	 */
	public function tear_down() {
		wp_cache_delete( 'plugins', 'plugins' );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Tests the result when every installed plugin is active and up to date.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_active_plugins
	 *
	 * @param int    $count    The number of installed, active plugins.
	 * @param string $sentence The expected sentence.
	 */
	public function test_should_report_active_plugins_up_to_date( $count, $sentence ) {
		$this->set_plugins( $count, $count );

		$this->assertSame(
			array(
				'label'       => 'Your plugins are all up to date',
				'status'      => 'good',
				'badge'       => array(
					'label' => 'Security',
					'color' => 'blue',
				),
				'description' => '<p>Plugins extend your site&#8217;s functionality with things like contact forms, ecommerce and much more. That means they have deep access to your site, so it&#8217;s vital to keep them up to date.</p><p>' . $sentence . '</p>',
				'actions'     => '<p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php">Manage your plugins</a></p>',
				'test'        => 'plugin_version',
			),
			$this->get_result()
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_active_plugins() {
		return array(
			'one plugin'  => array( 1, 'Your site has 1 active plugin, and it is up to date.' ),
			'two plugins' => array( 2, 'Your site has 2 active plugins, and they are all up to date.' ),
		);
	}

	/**
	 * Tests the result when active plugins have updates waiting.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_outdated_plugins
	 *
	 * @param int    $outdated The number of plugins with an update.
	 * @param string $sentence The expected sentence.
	 */
	public function test_should_report_outdated_plugins( $outdated, $sentence ) {
		$this->set_plugins( 2, 2, $outdated );

		$result = $this->get_result();

		$this->assertSame( 'critical', $result['status'], 'The status should be critical.' );
		$this->assertSame( 'You have plugins waiting to be updated', $result['label'], 'The label should match.' );
		$this->assertStringEndsWith( '</p><p>' . $sentence . '</p>', $result['description'], 'The description should count the outdated plugins.' );
		$this->assertSame(
			'<p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php">Manage your plugins</a></p>'
			. '<p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/' . ( is_multisite() ? 'network/' : '' ) . 'plugins.php?plugin_status=upgrade">Update your plugins</a></p>',
			$result['actions'],
			'The actions should link to the plugin updates.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_outdated_plugins() {
		return array(
			'one outdated plugin'  => array( 1, 'Your site has 1 plugin waiting to be updated.' ),
			'two outdated plugins' => array( 2, 'Your site has 2 plugins waiting to be updated.' ),
		);
	}

	/**
	 * Tests that an update for a plugin that is not installed is ignored.
	 *
	 * @ticket 65819
	 */
	public function test_should_ignore_updates_for_plugins_not_installed() {
		$this->set_plugins( 1, 1 );

		$update_plugins                                  = get_site_transient( 'update_plugins' );
		$update_plugins->response['missing/missing.php'] = (object) array(
			'slug'        => 'missing',
			'new_version' => '2.0',
		);
		set_site_transient( 'update_plugins', $update_plugins );

		$this->assertSame( 'good', $this->get_result()['status'] );
	}

	/**
	 * Tests the result when some installed plugins are inactive.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_inactive_plugins
	 *
	 * @param int    $total    The number of installed plugins.
	 * @param int    $active   The number of active plugins.
	 * @param string $sentence The expected sentence about inactive plugins.
	 */
	public function test_should_recommend_removing_inactive_plugins( $total, $active, $sentence ) {
		$this->set_plugins( $total, $active );

		$result = $this->get_result();

		$this->assertSame( 'recommended', $result['status'], 'The status should be recommended.' );
		$this->assertSame( 'You should remove inactive plugins', $result['label'], 'The label should match.' );
		$this->assertStringEndsWith(
			'<p>' . $sentence . ' Inactive plugins are tempting targets for attackers. If you are not going to use a plugin, you should consider removing it.</p>',
			$result['description'],
			'The description should count the inactive plugins.'
		);
		$this->assertStringEndsWith(
			'<p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php?plugin_status=inactive">Manage inactive plugins</a></p>',
			$result['actions'],
			'The actions should link to the inactive plugins.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_inactive_plugins() {
		return array(
			'one inactive plugin'  => array( 2, 1, 'Your site has 1 inactive plugin.' ),
			'two inactive plugins' => array( 3, 1, 'Your site has 2 inactive plugins.' ),
		);
	}

	/**
	 * Tests that inactive plugins are not reported on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_not_report_inactive_plugins_on_multisite() {
		$this->set_plugins( 2, 1 );

		$result = $this->get_result();

		$this->assertSame( 'good', $result['status'], 'The status should stay good.' );
		$this->assertStringEndsWith( '</p><p>Your site has 1 active plugin, and it is up to date.</p>', $result['description'], 'Only the active plugin should be counted, and inactive plugins should not be mentioned.' );
	}

	/**
	 * Primes the plugins cache with test plugins, activates some and adds updates.
	 *
	 * @param int $total    The number of installed plugins.
	 * @param int $active   The number of active plugins, from the first.
	 * @param int $outdated The number of plugins with an update, from the first.
	 */
	private function set_plugins( $total, $active, $outdated = 0 ) {
		$plugins  = array();
		$response = array();

		for ( $i = 1; $i <= $total; $i++ ) {
			$file = "plugin-{$i}/plugin-{$i}.php";

			$plugins[ $file ] = array(
				'Name'    => "Plugin {$i}",
				'Version' => '1.0',
			);

			if ( $i <= $outdated ) {
				$response[ $file ] = (object) array(
					'slug'        => "plugin-{$i}",
					'new_version' => '2.0',
				);
			}
		}

		wp_cache_set( 'plugins', array( '' => $plugins ), 'plugins' );

		$active_plugins = array_slice( array_keys( $plugins ), 0, $active );
		if ( is_multisite() ) {
			update_site_option( 'active_sitewide_plugins', array_fill_keys( $active_plugins, time() ) );
		} else {
			update_option( 'active_plugins', $active_plugins );
		}

		set_site_transient( 'update_plugins', (object) array( 'response' => $response ) );
	}

	/**
	 * Runs the test.
	 *
	 * @return array The test result.
	 */
	private function get_result() {
		$site_health = new WP_Site_Health();

		return $site_health->get_test_plugin_version();
	}
}
