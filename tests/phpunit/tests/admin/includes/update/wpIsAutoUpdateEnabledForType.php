<?php

/**
 * Tests for wp_is_auto_update_enabled_for_type().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers ::wp_is_auto_update_enabled_for_type
 */
class Tests_Admin_Includes_Update_WpIsAutoUpdateEnabledForType extends WP_UnitTestCase {

	/**
	 * Loads the tested file.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	/**
	 * Allows file changes, so that only the filters decide whether the updater is disabled.
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'file_mod_allowed', '__return_true' );
		remove_all_filters( 'automatic_updater_disabled' );
		remove_all_filters( 'plugins_auto_update_enabled' );
		remove_all_filters( 'themes_auto_update_enabled' );
	}

	/**
	 * Tests that auto-updates follow the state of the automatic updater.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_types_and_updater_states
	 *
	 * @param string $type     The update type.
	 * @param bool   $disabled Whether the automatic updater is disabled.
	 */
	public function test_should_follow_automatic_updater( $type, $disabled ) {
		add_filter( 'automatic_updater_disabled', $disabled ? '__return_true' : '__return_false' );

		$this->assertSame( ! $disabled, wp_is_auto_update_enabled_for_type( $type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_types_and_updater_states() {
		return array(
			'plugin, updater enabled'  => array( 'plugin', false ),
			'plugin, updater disabled' => array( 'plugin', true ),
			'theme, updater enabled'   => array( 'theme', false ),
			'theme, updater disabled'  => array( 'theme', true ),
		);
	}

	/**
	 * Tests that auto-updates are disabled while file changes are not allowed.
	 *
	 * @ticket 65819
	 */
	public function test_should_be_disabled_without_file_changes() {
		add_filter( 'file_mod_allowed', '__return_false', 11 );

		$this->assertFalse( wp_is_auto_update_enabled_for_type( 'plugin' ) );
	}

	/**
	 * Tests that the type filter receives the updater state and decides.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_type_filters
	 *
	 * @param string $type   The update type.
	 * @param string $filter The filter for the type.
	 */
	public function test_should_apply_type_filter( $type, $filter ) {
		add_filter( 'automatic_updater_disabled', '__return_true' );

		$mock = new MockAction();
		add_filter( $filter, array( $mock, 'filter' ) );
		add_filter( $filter, '__return_true', 11 );

		$this->assertTrue( wp_is_auto_update_enabled_for_type( $type ), 'The filtered value should be returned.' );
		$this->assertSame( array( array( false ) ), $mock->get_args(), 'The filter should receive whether the updater is enabled.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_type_filters() {
		return array(
			'plugin' => array( 'plugin', 'plugins_auto_update_enabled' ),
			'theme'  => array( 'theme', 'themes_auto_update_enabled' ),
		);
	}

	/**
	 * Tests that other types are never auto-updated.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_other_types
	 *
	 * @param mixed $type The update type.
	 */
	public function test_should_return_false_for_other_types( $type ) {
		add_filter( 'automatic_updater_disabled', '__return_false' );
		add_filter( 'plugins_auto_update_enabled', '__return_true' );
		add_filter( 'themes_auto_update_enabled', '__return_true' );

		$this->assertFalse( wp_is_auto_update_enabled_for_type( $type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_other_types() {
		return array(
			'core'        => array( 'core' ),
			'plural type' => array( 'plugins' ),
		);
	}
}
