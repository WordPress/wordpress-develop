<?php

/**
 * @group admin
 * @group upgrade
 *
 * @covers Language_Pack_Upgrader
 */
class Tests_Admin_LanguagePackUpgrader extends WP_UnitTestCase {
	/**
	 * Loads the class to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	public function tear_down() {
		delete_site_option( 'deferred_translation_updates' );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that deferred translation updates are not passed to the bulk upgrader.
	 *
	 * @ticket 42281
	 *
	 * @covers Language_Pack_Upgrader::async_upgrade
	 */
	public function test_async_upgrade_should_skip_deferred_translation_updates() {
		$deferred_update = (object) array(
			'type'       => 'plugin',
			'slug'       => 'deferred-plugin',
			'language'   => 'de_DE',
			'version'    => '1.0.0',
			'autoupdate' => true,
			'package'    => 'https://example.org/deferred-plugin-de_DE.zip',
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array( $deferred_update ),
			)
		);

		wp_set_deferred_translation_updates( array( $deferred_update ) );

		$async_update_results = array();
		$bulk_upgrade_started = false;

		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );
		add_filter(
			'async_update_translation',
			static function ( $update ) use ( &$async_update_results ) {
				$async_update_results[] = $update;
				return $update;
			}
		);
		add_filter(
			'request_filesystem_credentials',
			static function () use ( &$bulk_upgrade_started ) {
				$bulk_upgrade_started = true;
				return false;
			}
		);

		Language_Pack_Upgrader::async_upgrade();

		$this->assertSame( array( false ), $async_update_results );
		$this->assertFalse( $bulk_upgrade_started );
	}
}
