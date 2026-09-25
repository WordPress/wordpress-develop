<?php

require_once __DIR__ . '/base.php';

/**
 * Test functions in global-styles-and-settings.php.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @group themes
 *
 * @covers ::wp_get_viewport_media_queries
 * @covers ::wp_get_global_settings
 * @covers ::wp_get_global_styles
 */
class Tests_Theme_WpGetGlobalStylesAndSettings extends WP_Theme_UnitTestCase {

	/**
	 * @ticket 82082
	 */
	public function test_wp_get_viewport_media_queries_returns_media_queries() {
		$this->assertSame(
			array(
				'@mobile'  => '@media (width <= 640px)',
				'@tablet'  => '@media (640px < width <= 960px)',
				'@desktop' => '@media (width > 960px)',
			),
			wp_get_viewport_media_queries(
				array(
					'mobile' => '640px',
					'tablet' => '960px',
				),
				array(
					'include_desktop' => true,
				)
			)
		);
	}

	/**
	 * Tests that wp_get_global_settings() returns the value of an existing setting.
	 *
	 * @ticket 66183
	 */
	public function test_wp_get_global_settings_returns_value_for_existing_path() {
		switch_theme( 'block-theme' );

		$this->assertFalse( wp_get_global_settings( array( 'color', 'custom' ) ) );
	}

	/**
	 * Tests that wp_get_global_settings() returns null when the path does not exist.
	 *
	 * @ticket 66183
	 *
	 * @dataProvider data_missing_paths
	 *
	 * @param array $path Path that does not exist.
	 */
	public function test_wp_get_global_settings_returns_null_for_missing_path( $path ) {
		switch_theme( 'block-theme' );

		$this->assertNull( wp_get_global_settings( $path ) );
	}

	/**
	 * Tests that wp_get_global_settings() returns all settings when the path is empty.
	 *
	 * @ticket 66183
	 */
	public function test_wp_get_global_settings_returns_all_settings_for_empty_path() {
		switch_theme( 'block-theme' );

		$settings = wp_get_global_settings( array() );

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'color', $settings );
		$this->assertSame( wp_get_global_settings(), $settings );
	}

	/**
	 * Tests that wp_get_global_settings() returns null when the block has no settings.
	 *
	 * @ticket 66183
	 */
	public function test_wp_get_global_settings_returns_null_for_missing_block() {
		switch_theme( 'block-theme' );

		$this->assertNull( wp_get_global_settings( array(), array( 'block_name' => 'core/does-not-exist' ) ) );
	}

	/**
	 * Tests that wp_get_global_styles() returns the value of an existing style.
	 *
	 * @ticket 66183
	 */
	public function test_wp_get_global_styles_returns_value_for_existing_path() {
		switch_theme( 'block-theme' );

		$this->assertSame(
			'10px 10px 5px 0px rgba(0,0,0,0.66)',
			wp_get_global_styles( array( 'shadow' ), array( 'block_name' => 'core/post-featured-image' ) )
		);
	}

	/**
	 * Tests that wp_get_global_styles() returns null when the path does not exist.
	 *
	 * @ticket 66183
	 *
	 * @dataProvider data_missing_paths
	 *
	 * @param array $path Path that does not exist.
	 */
	public function test_wp_get_global_styles_returns_null_for_missing_path( $path ) {
		switch_theme( 'block-theme' );

		$this->assertNull( wp_get_global_styles( $path ) );
	}

	/**
	 * Tests that wp_get_global_styles() returns all styles when the path is empty.
	 *
	 * @ticket 66183
	 */
	public function test_wp_get_global_styles_returns_all_styles_for_empty_path() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_styles( array() );

		$this->assertIsArray( $styles );
		$this->assertArrayHasKey( 'blocks', $styles );
		$this->assertSame( wp_get_global_styles(), $styles );
	}

	/**
	 * Tests that wp_get_global_styles() returns null when the block has no styles.
	 *
	 * @ticket 66183
	 */
	public function test_wp_get_global_styles_returns_null_for_missing_block() {
		switch_theme( 'block-theme' );

		$this->assertNull( wp_get_global_styles( array(), array( 'block_name' => 'core/does-not-exist' ) ) );
	}

	/**
	 * Data provider for tests that request a path that does not exist.
	 *
	 * @return array[]
	 */
	public function data_missing_paths() {
		return array(
			'missing top-level key' => array( array( 'foo', 'bar' ) ),
			'missing nested key'    => array( array( 'color', 'does-not-exist' ) ),
		);
	}

	/**
	 * Tests that the Image block falls back to the top-level lightbox setting
	 * when no block-level setting exists.
	 *
	 * @ticket 66183
	 *
	 * @covers ::block_core_image_get_lightbox_settings
	 */
	public function test_image_lightbox_falls_back_to_top_level_setting() {
		switch_theme( 'block-theme' );

		// Remove the block-level lightbox setting defined in core's theme.json.
		add_filter(
			'wp_theme_json_data_default',
			static function ( $theme_json ) {
				$data = $theme_json->get_data();
				unset( $data['settings']['blocks']['core/image']['lightbox'] );

				return new WP_Theme_JSON_Data( $data, 'default' );
			}
		);

		add_filter(
			'wp_theme_json_data_theme',
			static function ( $theme_json ) {
				return $theme_json->update_with(
					array(
						'version'  => WP_Theme_JSON::LATEST_SCHEMA,
						'settings' => array(
							'lightbox' => array(
								'enabled'      => true,
								'allowEditing' => false,
							),
						),
					)
				);
			}
		);
		wp_clean_theme_json_cache();

		$this->assertSame(
			array(
				'enabled'      => true,
				'allowEditing' => false,
			),
			block_core_image_get_lightbox_settings( array() )
		);
	}
}
