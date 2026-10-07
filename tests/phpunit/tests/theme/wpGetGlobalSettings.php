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

	public function test_reads_through_to_the_resolver() {
		$contexts = array(
			'wp_get_global_settings_custom' => array(),
			'wp_get_global_settings_theme'  => array( 'origin' => 'base' ),
		);

		foreach ( $contexts as $context ) {
			wp_get_global_settings( array(), $context );
		}

		foreach ( array_keys( $contexts ) as $cache_key ) {
			$this->assertFalse(
				wp_cache_get( $cache_key, 'theme_json' ),
				"The merged settings should not be cached under $cache_key."
			);
		}

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
			'Settings changed after a block registration should be present on the next call.'
		);
	}
}
