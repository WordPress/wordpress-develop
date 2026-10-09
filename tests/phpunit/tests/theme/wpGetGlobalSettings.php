<?php

/**
 * Tests wp_get_global_settings().
 *
 * @group themes
 *
 * @covers ::wp_get_global_settings
 */
class Tests_Theme_wpGetGlobalSettings extends WP_UnitTestCase {

	public function tear_down() {
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	public function test_reflects_theme_data_changed_after_a_previous_call() {
		wp_get_global_settings();

		// Block registration adds no settings, so inject one through the theme data
		// filter: registering a block refreshes the theme data, which reapplies the filter.
		$filter = static function ( $theme_json ) {
			return $theme_json->update_with(
				array(
					'version'  => WP_Theme_JSON::LATEST_SCHEMA,
					'settings' => array(
						'custom' => array( 'cacheProbe' => 'fresh' ),
					),
				)
			);
		};
		add_filter( 'wp_theme_json_data_theme', $filter );
		register_block_type( 'test/block-settings' );

		$settings = wp_get_global_settings();

		unregister_block_type( 'test/block-settings' );
		remove_filter( 'wp_theme_json_data_theme', $filter );

		$this->assertSame(
			'fresh',
			$settings['custom']['cacheProbe'] ?? null,
			'Settings from theme data changed after a previous call should be present.'
		);
	}
}
