<?php

/**
 * Tests for get_registered_theme_features().
 *
 * @group theme
 *
 * @covers ::get_registered_theme_features
 */
class Tests_Theme_GetRegisteredThemeFeatures extends WP_UnitTestCase {

	/**
	 * Original registered theme features backup.
	 *
	 * @var array
	 */
	private $original_features;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();

		global $_wp_registered_theme_features;
		$this->original_features = $_wp_registered_theme_features;
	}

	/**
	 * Tears down the test fixture.
	 */
	public function tear_down() {
		global $_wp_registered_theme_features;
		$_wp_registered_theme_features = $this->original_features;

		parent::tear_down();
	}

	/**
	 * Tests that get_registered_theme_features() returns an array.
	 *
	 * @ticket 49406
	 */
	public function test_get_registered_theme_features_returns_array() {
		$features = get_registered_theme_features();

		$this->assertIsArray( $features );
	}

	/**
	 * Tests that get_registered_theme_features() includes core registered theme features.
	 *
	 * @ticket 49406
	 */
	public function test_get_registered_theme_features_includes_core_features() {
		$features = get_registered_theme_features();

		$this->assertArrayHasKey( 'post-thumbnails', $features );
		$this->assertArrayHasKey( 'responsive-embeds', $features );
		$this->assertArrayHasKey( 'editor-styles', $features );
	}

	/**
	 * Tests that get_registered_theme_features() includes a newly registered custom feature.
	 *
	 * @ticket 49406
	 */
	public function test_get_registered_theme_features_includes_custom_feature() {
		$feature_name = 'wptests-custom-feature';
		$args         = array(
			'type'         => 'boolean',
			'variadic'     => false,
			'description'  => 'Custom feature description.',
			'show_in_rest' => false,
		);

		register_theme_feature( $feature_name, $args );

		$features = get_registered_theme_features();

		$this->assertArrayHasKey( $feature_name, $features );
		$this->assertSame( $args, $features[ $feature_name ] );
	}

	/**
	 * Tests that get_registered_theme_features() returns an empty array when the global is not an array.
	 *
	 * @ticket 49406
	 */
	public function test_get_registered_theme_features_returns_empty_array_when_global_not_array() {
		global $_wp_registered_theme_features;
		$_wp_registered_theme_features = null;

		$this->assertSame( array(), get_registered_theme_features() );
	}
}
