<?php

/**
 * Tests for WP_Plugins_List_Table::get_bulk_actions().
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::get_bulk_actions
 */
class Tests_Admin_wpPluginsListTableGetBulkActions extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * ID of a user who can only activate plugins.
	 *
	 * @var int
	 */
	private static $activator_id;

	/**
	 * The original values of the globals the list table sets.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * Sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}

		self::$activator_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$activator_id )->add_cap( 'activate_plugins' );
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
	 * Saves the globals the list table sets and enables plugin auto-updates.
	 */
	public function set_up() {
		parent::set_up();

		// Auto-updates are disabled in the test environment.
		add_filter( 'plugins_auto_update_enabled', '__return_true' );

		$this->original_globals = array(
			'status' => $GLOBALS['status'] ?? null,
			'page'   => $GLOBALS['page'] ?? null,
		);
	}

	/**
	 * Restores the globals the list table sets.
	 */
	public function tear_down() {
		foreach ( $this->original_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		unset( $_REQUEST['plugin_status'] );

		parent::tear_down();
	}

	/**
	 * Tests the bulk actions for each plugin status on the Plugins screen of a single site.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_statuses
	 *
	 * @param string   $status   The plugin status.
	 * @param string[] $expected The expected bulk action names.
	 */
	public function test_should_return_bulk_actions_for_status( $status, $expected ) {
		wp_set_current_user( self::$admin_id );

		$this->assertSame( $expected, array_keys( $this->get_bulk_actions( 'plugins', $status ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_statuses() {
		$all = array(
			'activate-selected',
			'deactivate-selected',
			'update-selected',
			'delete-selected',
			'enable-auto-update-selected',
			'disable-auto-update-selected',
		);

		return array(
			'upgrade'              => array( 'upgrade', $all ),
			'active'               => array(
				'active',
				array(
					'deactivate-selected',
					'update-selected',
					'enable-auto-update-selected',
					'disable-auto-update-selected',
				),
			),
			'inactive'             => array(
				'inactive',
				array(
					'activate-selected',
					'update-selected',
					'delete-selected',
					'enable-auto-update-selected',
					'disable-auto-update-selected',
				),
			),
			// The Recently Active view; get_bulk_actions() checks for 'recent', so Deactivate is offered.
			'recently_activated'   => array( 'recently_activated', $all ),
			'recent'               => array(
				'recent',
				array(
					'activate-selected',
					'update-selected',
					'delete-selected',
					'enable-auto-update-selected',
					'disable-auto-update-selected',
				),
			),
			'auto-update-enabled'  => array(
				'auto-update-enabled',
				array(
					'activate-selected',
					'deactivate-selected',
					'update-selected',
					'delete-selected',
					'disable-auto-update-selected',
				),
			),
			'auto-update-disabled' => array(
				'auto-update-disabled',
				array(
					'activate-selected',
					'deactivate-selected',
					'update-selected',
					'delete-selected',
					'enable-auto-update-selected',
				),
			),
		);
	}

	/**
	 * Tests the labels of the bulk actions on the Plugins screen of a single site.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_return_bulk_action_labels() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array(
				'activate-selected'            => 'Activate',
				'deactivate-selected'          => 'Deactivate',
				'update-selected'              => 'Update',
				'delete-selected'              => 'Delete',
				'enable-auto-update-selected'  => 'Enable Auto-updates',
				'disable-auto-update-selected' => 'Disable Auto-updates',
			),
			$this->get_bulk_actions( 'plugins', 'all' )
		);
	}

	/**
	 * Tests that the auto-update actions are left out when plugin auto-updates are disabled.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_not_return_auto_update_actions_when_auto_updates_are_disabled() {
		wp_set_current_user( self::$admin_id );

		add_filter( 'plugins_auto_update_enabled', '__return_false', 11 );

		$this->assertSame(
			array(
				'activate-selected',
				'deactivate-selected',
				'update-selected',
				'delete-selected',
			),
			array_keys( $this->get_bulk_actions( 'plugins', 'all' ) )
		);
	}

	/**
	 * Tests that a user who can only activate plugins gets only the activation actions.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_only_activation_actions_for_user_who_cannot_update_or_delete() {
		wp_set_current_user( self::$activator_id );

		$this->assertSame(
			array(
				'activate-selected'   => 'Activate',
				'deactivate-selected' => 'Deactivate',
			),
			$this->get_bulk_actions( 'plugins', 'all' )
		);
	}

	/**
	 * Tests that the Plugins screen of a site in a network only offers the activation actions.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_return_only_activation_actions_on_site_screen_in_network() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array(
				'activate-selected'   => 'Activate',
				'deactivate-selected' => 'Deactivate',
			),
			$this->get_bulk_actions( 'plugins', 'all' )
		);
	}

	/**
	 * Tests the bulk actions on the Plugins screen of the network admin.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_return_network_bulk_actions_on_network_screen() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array(
				'activate-selected'            => 'Network Activate',
				'deactivate-selected'          => 'Network Deactivate',
				'update-selected'              => 'Update',
				'delete-selected'              => 'Delete',
				'enable-auto-update-selected'  => 'Enable Auto-updates',
				'disable-auto-update-selected' => 'Disable Auto-updates',
			),
			$this->get_bulk_actions( 'plugins-network', 'all' )
		);
	}

	/**
	 * Returns the bulk actions of a Plugins list table.
	 *
	 * @param string $screen The screen ID.
	 * @param string $status The plugin status, as requested.
	 * @return string[] The bulk actions.
	 */
	private function get_bulk_actions( $screen, $status ) {
		$_REQUEST['plugin_status'] = $status;

		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => $screen ) );

		$method = new ReflectionMethod( $table, 'get_bulk_actions' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $table );
	}
}
