<?php

/**
 * Tests for deactivate_plugins().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::deactivate_plugins
 */
class Tests_Admin_Includes_Plugin_DeactivatePlugins extends WP_UnitTestCase {

	/**
	 * The deactivation hooks that fired, with their arguments.
	 *
	 * @var array[]
	 */
	private $calls = array();

	/**
	 * Resets the active plugins and records the deactivation hooks.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'active_plugins', array( 'a/a.php', 'b/b.php', 'c.php' ) );
		update_site_option( 'active_sitewide_plugins', array() );

		$this->calls = array();
		foreach ( array( 'deactivate_plugin', 'deactivate_a/a.php', 'deactivate_b/b.php', 'deactivated_plugin' ) as $hook ) {
			add_action(
				$hook,
				function ( ...$args ) use ( $hook ) {
					$this->calls[] = array( $hook, $args );
				},
				10,
				2
			);
		}
	}

	/**
	 * Tests that plugins are deactivated and the deactivation hooks fire for each.
	 *
	 * @ticket 65819
	 */
	public function test_should_deactivate_plugins_and_fire_hooks() {
		deactivate_plugins( array( 'a/a.php', 'b/b.php' ) );

		$this->assertSame( array( 'c.php' ), array_values( get_option( 'active_plugins' ) ), 'The other plugin should stay active.' );
		$this->assertSame(
			array(
				array( 'deactivate_plugin', array( 'a/a.php', false ) ),
				array( 'deactivate_a/a.php', array( false ) ),
				array( 'deactivated_plugin', array( 'a/a.php', false ) ),
				array( 'deactivate_plugin', array( 'b/b.php', false ) ),
				array( 'deactivate_b/b.php', array( false ) ),
				array( 'deactivated_plugin', array( 'b/b.php', false ) ),
			),
			$this->calls,
			'The deactivation hooks should fire in order for each plugin.'
		);
	}

	/**
	 * Tests that a single plugin can be passed as a full path with surrounding whitespace.
	 *
	 * @ticket 65819
	 */
	public function test_should_accept_single_plugin_as_full_path() {
		deactivate_plugins( ' ' . WP_PLUGIN_DIR . '/a/a.php ' );

		$this->assertSame( array( 'b/b.php', 'c.php' ), array_values( get_option( 'active_plugins' ) ) );
	}

	/**
	 * Tests that inactive plugins are skipped without hooks or an option update.
	 *
	 * @ticket 65819
	 */
	public function test_should_skip_inactive_plugins() {
		$updates = new MockAction();
		add_filter( 'pre_update_option_active_plugins', array( $updates, 'filter' ) );

		deactivate_plugins( array( 'inactive/inactive.php' ) );

		$this->assertSame( array( 'a/a.php', 'b/b.php', 'c.php' ), get_option( 'active_plugins' ), 'The active plugins should not change.' );
		$this->assertSame( array(), $this->calls, 'No deactivation hooks should fire.' );
		$this->assertSame( 0, $updates->get_call_count(), 'The option should not be updated.' );
	}

	/**
	 * Tests that no hooks fire in silent mode.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_fire_hooks_in_silent_mode() {
		deactivate_plugins( 'a/a.php', true );

		$this->assertSame( array( 'b/b.php', 'c.php' ), array_values( get_option( 'active_plugins' ) ), 'The plugin should be deactivated.' );
		$this->assertSame( array(), $this->calls, 'No deactivation hooks should fire.' );
	}

	/**
	 * Tests that a network-active plugin is deactivated network-wide by default.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_deactivate_network_active_plugin_network_wide() {
		update_site_option(
			'active_sitewide_plugins',
			array(
				'a/a.php' => 1000,
				'd/d.php' => 1000,
			)
		);

		deactivate_plugins( 'a/a.php' );

		$this->assertSame( array( 'd/d.php' => 1000 ), get_site_option( 'active_sitewide_plugins' ), 'The plugin should be network deactivated.' );
		$this->assertSame( array( 'b/b.php', 'c.php' ), array_values( get_option( 'active_plugins' ) ), 'The plugin should also be deactivated on the site.' );
		$this->assertSame(
			array(
				array( 'deactivate_plugin', array( 'a/a.php', true ) ),
				array( 'deactivate_a/a.php', array( true ) ),
				array( 'deactivated_plugin', array( 'a/a.php', true ) ),
			),
			$this->calls,
			'The hooks should receive true.'
		);
	}

	/**
	 * Tests that only the network activation is removed when deactivating network-wide.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_only_deactivate_network_wide_when_requested() {
		update_site_option( 'active_sitewide_plugins', array( 'a/a.php' => 1000 ) );

		deactivate_plugins( 'a/a.php', false, true );

		$this->assertSame( array(), get_site_option( 'active_sitewide_plugins' ), 'The plugin should be network deactivated.' );
		$this->assertSame( array( 'a/a.php', 'b/b.php', 'c.php' ), get_option( 'active_plugins' ), 'The site activation should be kept.' );
	}

	/**
	 * Tests that a plugin active only on the site is kept when deactivating network-wide.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_keep_site_active_plugin_when_deactivating_network_wide() {
		deactivate_plugins( 'a/a.php', false, true );

		$this->assertSame( array( 'a/a.php', 'b/b.php', 'c.php' ), get_option( 'active_plugins' ), 'The site activation should be kept.' );
		$this->assertSame(
			array( array( 'deactivate_plugin', array( 'a/a.php', false ) ) ),
			$this->calls,
			'Only deactivate_plugin should fire, although the plugin stays active.'
		);
	}

	/**
	 * Tests that a network activation is kept when deactivating on the site only.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_keep_network_activation_when_deactivating_on_site() {
		update_site_option( 'active_sitewide_plugins', array( 'a/a.php' => 1000 ) );

		deactivate_plugins( 'a/a.php', false, false );

		$this->assertSame( array( 'a/a.php' => 1000 ), get_site_option( 'active_sitewide_plugins' ), 'The network activation should be kept.' );
		$this->assertSame( array( 'b/b.php', 'c.php' ), array_values( get_option( 'active_plugins' ) ), 'The site activation should be removed.' );
	}
}
