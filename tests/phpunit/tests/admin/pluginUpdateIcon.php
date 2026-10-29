<?php
/**
 * Tests for plugin update icon HTML on the Updates screen.
 *
 * @group admin
 * @group plugins
 *
 * @covers ::wp_get_plugin_update_icon_html
 */
class Tests_Admin_PluginUpdateIcon extends WP_UnitTestCase {

	/**
	 * Loads the admin Update API.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	/**
	 * Default Dashicon used when no usable plugin icon is available.
	 *
	 * @var string
	 */
	private static $default_icon = '<span class="dashicons dashicons-admin-plugins"></span>';

	/**
	 * @ticket 56431
	 */
	public function test_returns_default_icon_when_update_is_missing() {
		$this->assertSame( self::$default_icon, wp_get_plugin_update_icon_html( null ) );
	}

	/**
	 * @ticket 56431
	 */
	public function test_returns_default_icon_when_icons_are_not_set() {
		$update = (object) array(
			'slug'        => 'example',
			'new_version' => '1.0.1',
		);

		$this->assertSame( self::$default_icon, wp_get_plugin_update_icon_html( $update ) );
	}

	/**
	 * @ticket 56431
	 */
	public function test_returns_image_for_array_icons() {
		$update = (object) array(
			'icons' => array(
				'svg' => 'https://example.com/icon.svg',
			),
		);

		$html = wp_get_plugin_update_icon_html( $update );

		$this->assertStringContainsString( 'src="https://example.com/icon.svg"', $html );
		$this->assertStringContainsString( 'class="plugin-icon"', $html );
		$this->assertStringNotContainsString( 'dashicons-admin-plugins', $html );
	}

	/**
	 * @ticket 56431
	 *
	 * Object-shaped `icons` values fatal on PHP 8+ when accessed as arrays.
	 */
	public function test_does_not_fatal_when_icons_is_an_object() {
		$update = (object) array(
			'icons' => (object) array(
				'svg' => 'https://example.com/icon.svg',
			),
		);

		$html = wp_get_plugin_update_icon_html( $update );

		$this->assertStringContainsString( 'src="https://example.com/icon.svg"', $html );
		$this->assertStringContainsString( 'class="plugin-icon"', $html );
	}

	/**
	 * @ticket 56431
	 */
	public function test_returns_default_icon_when_icons_is_a_non_array_scalar() {
		$update = (object) array(
			'icons' => 'https://example.com/icon.svg',
		);

		$this->assertSame( self::$default_icon, wp_get_plugin_update_icon_html( $update ) );
	}

	/**
	 * @ticket 56431
	 */
	public function test_prefers_svg_over_lower_resolution_icons() {
		$update = (object) array(
			'icons' => array(
				'default' => 'https://example.com/default.png',
				'1x'      => 'https://example.com/1x.png',
				'2x'      => 'https://example.com/2x.png',
				'svg'     => 'https://example.com/icon.svg',
			),
		);

		$html = wp_get_plugin_update_icon_html( $update );

		$this->assertStringContainsString( 'src="https://example.com/icon.svg"', $html );
		$this->assertStringNotContainsString( 'default.png', $html );
	}

	/**
	 * @ticket 56431
	 */
	public function test_escapes_icon_url() {
		$update = (object) array(
			'icons' => array(
				'svg' => 'https://example.com/icon.svg" onerror="alert(1)',
			),
		);

		$html = wp_get_plugin_update_icon_html( $update );

		$this->assertStringNotContainsString( '" onerror=', $html );
		$this->assertMatchesRegularExpression( '/src="https:\/\/example\.com\/icon\.svg[^"]*"/', $html );
	}
}
