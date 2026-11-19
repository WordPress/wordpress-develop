<?php
/**
 * Tests for the WP_Plugin_Dependencies admin notices.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::display_admin_notice_for_unmet_dependencies
 * @covers WP_Plugin_Dependencies::display_admin_notice_for_circular_dependencies
 */
class Tests_Admin_WPPluginDependencies_DisplayAdminNotices extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original current screen.
	 *
	 * @var WP_Screen|null
	 */
	private $original_screen;

	/**
	 * Sets up the user.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Saves the current screen.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_screen = $GLOBALS['current_screen'] ?? null;
		set_current_screen( 'dashboard' );
	}

	/**
	 * Restores the current screen.
	 */
	public function tear_down() {
		$GLOBALS['current_screen'] = $this->original_screen;

		parent::tear_down();
	}

	/**
	 * Tests that no notice is shown when every dependency is installed.
	 *
	 * @ticket 65819
	 */
	public function test_unmet_notice_should_not_be_shown_when_dependencies_are_installed() {
		$this->set_property_value( 'dependency_filepaths', array( 'dependency' => 'dependency/dependency.php' ) );

		$this->assertSame( '', get_echo( array( self::$instance, 'display_admin_notice_for_unmet_dependencies' ) ) );
	}

	/**
	 * Tests the unmet dependencies notice on a single site.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_screens
	 *
	 * @param string $screen The current screen.
	 * @param string $link   The expected link after the message.
	 */
	public function test_unmet_notice_should_link_to_plugins_screen( $screen, $link ) {
		set_current_screen( $screen );
		$this->set_property_value( 'dependency_filepaths', array( 'missing' => false ) );

		$this->assertSame(
			'<div class="notice notice-warning"><p>Some required plugins are missing or inactive.' . $link . '</p></div>',
			get_echo( array( self::$instance, 'display_admin_notice_for_unmet_dependencies' ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_screens() {
		return array(
			'other screen'   => array( 'dashboard', ' <a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php">Manage plugins</a>.' ),
			'Plugins screen' => array( 'plugins', '' ),
		);
	}

	/**
	 * Tests the unmet dependencies notice on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_network_users
	 *
	 * @param bool   $super_admin Whether the user is a super admin.
	 * @param string $suffix      The expected text after the message.
	 */
	public function test_unmet_notice_on_multisite( $super_admin, $suffix ) {
		if ( $super_admin ) {
			grant_super_admin( self::$admin_id );
		}
		wp_set_current_user( self::$admin_id );

		$this->set_property_value( 'dependency_filepaths', array( 'missing' => false ) );

		try {
			$output = get_echo( array( self::$instance, 'display_admin_notice_for_unmet_dependencies' ) );
		} finally {
			revoke_super_admin( self::$admin_id );
		}

		$this->assertSame( '<div class="notice notice-warning"><p>Some required plugins are missing or inactive. ' . $suffix . '</p></div>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_network_users() {
		return array(
			'super admin'        => array( true, '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/network/plugins.php">Manage plugins</a>.' ),
			'site administrator' => array( false, 'Please contact your network administrator.' ),
		);
	}

	/**
	 * Tests that no circular dependencies notice is shown without circular dependencies.
	 *
	 * @ticket 65819
	 */
	public function test_circular_notice_should_not_be_shown_without_circular_dependencies() {
		$this->set_property_value( 'circular_dependencies_pairs', array() );

		$this->assertSame( '', get_echo( array( self::$instance, 'display_admin_notice_for_circular_dependencies' ) ) );
	}

	/**
	 * Tests the circular dependencies notice, with escaped names and repeated pairs listed once.
	 *
	 * @ticket 65819
	 */
	public function test_circular_notice_should_list_each_pair_once() {
		$this->set_property_value(
			'plugins',
			array(
				'a/a.php' => array( 'Name' => 'Plugin <b>A</b> & Co' ),
				'b/b.php' => array( 'Name' => 'Plugin B' ),
			)
		);
		$this->set_property_value( 'circular_dependencies_pairs', array( array( 'a', 'b' ), array( 'b', 'a' ), array( 'a', 'b' ) ) );

		$this->assertSame(
			'<div class="notice notice-warning">'
			. '<p>These plugins cannot be activated because their requirements are invalid.</p>'
			. '<ul><li><strong>Plugin &lt;b&gt;A&lt;/b&gt; &amp; Co</strong> requires <strong>Plugin B</strong></li>'
			. '<li><strong>Plugin B</strong> requires <strong>Plugin &lt;b&gt;A&lt;/b&gt; &amp; Co</strong></li></ul>'
			. '<p>Please contact the plugin authors for more information.</p>'
			. '</div>',
			get_echo( array( self::$instance, 'display_admin_notice_for_circular_dependencies' ) )
		);
	}
}
